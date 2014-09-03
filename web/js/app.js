'use strict';

requirejs.config({
	baseUrl: '/js',
	paths: {
		'es6-promise': 'promise'
	}
});

define(['sortable', 'bean', 'http', 'es6-promise'], function (Sortable, bean, http, promise) {
	console.log('Application started successfully', promise);
	// DOM convenience functions.
	var first = function (selector, context) { return (context || document).querySelector(selector); };
	var all = function (selector, context) { return (context || document).querySelectorAll(selector); };
	var each = function (els, callback) { return Array.prototype.forEach.call(els, callback); };
	var map = function (els, callback) { return Array.prototype.map.call(els, callback); };
	var enhanceEach = function (selector, dependencies, callback) {
		var elements = all(selector);
		if (elements.length > 0) {
			require(dependencies, function () {
				var args = Array.prototype.slice.call(arguments);
				each(elements, function (element) {
					var innerArgs = args.slice();
					innerArgs.unshift(element);
					callback.apply(callback, innerArgs);
				});
			});
		}
	};

	// Ensure that the column container is wide enough to contain all the columns.
	// TODO: this needs to be called whenever there is a new column.
	var expandColumnContainer = function () {
		document.querySelector('.columns').style.width = Array.prototype.reduce.call(document.querySelectorAll('.column'), function (total, el) { return el.offsetWidth + total + 23; }, 0) + 'px';
	};
	expandColumnContainer();

	var columnsEl = first('.columns');

	function Item(itemEl) {
		var self = this;
		self.el = itemEl;
		self.url = first('.item-url', self.el).href;

		var actionPanelEl = first('.item-action-panel', self.el);
		var replyButton = first('.reply-button', self.el);
		var replyPostButton = first('.reply-post-button', self.el);
		var replyTextarea = first('.reply-content', self.el);

		actionPanelEl.classList.add('activated');

		bean.on(replyButton, 'click', function (event) {
			actionPanelEl.classList.toggle('collapsed');
			if (!actionPanelEl.classList.contains('collapsed')) {
				// The panel was just opened.
				replyTextarea.focus();
			}
		});

		bean.on(replyTextarea, 'blur', function (event) {
			if (actionPanelEl.classList.contains('collapsed')) {
				return;
			} else if (replyTextarea.value.trim() == '') {
				actionPanelEl.classList.add('collapsed');
			}
		});

		bean.on(replyTextarea, 'keyup', function () {
			replyPostButton.disabled = replyTextarea.value.trim() == '';
		});
		bean.fire(replyTextarea, 'keyup');

		// TODO: also post on command+return.
		bean.on(replyPostButton, 'click', function () {
			if (replyPostButton.disabled) {
				return;
			}

			var req = http.open('POST', '/micropub/');
			var data = new FormData();
			data.append('h', 'entry');
			data.append('content', replyTextarea.value);
			data.append('in-reply-to', self.url);
			replyPostButton.disabled = true;
			replyPostButton.textContent = 'Posting…';
			http.send(req, data).then(function (respXhr) {
				replyTextarea.value = '';
				replyPostButton.textContent = 'Posted!';
				setTimeout(function () {
					replyPostButton.textContent = 'Post';
					bean.fire(replyTextarea, 'blur');
				}, 3000);
			}, function (errXhr) {
				console.log('HTTP Error when sending a micropub reply', data, errXhr);
				replyPostButton.textContent = 'Post Failed';
			});
		});
	}

	function InlineEdit(el) {
		var self = this;
		var contentEl = first('.inline-edit-content', el);
		var inputEl = first('.inline-edit-input', el);
		self.el = el;
		self.editing = false;
		self.value = inputEl.value = contentEl.textContent;

		var setEditing = function (state) {
			if (state) {
				self.editing = true;
				self.el.classList.add('editing');
				inputEl.focus();
			} else {
				self.value = inputEl.value;
				bean.fire(self, 'change');
			}
		};

		bean.on(self, 'change', function () {
			self.editing = false;
			self.el.classList.remove('editing');
			contentEl.textContent = inputEl.value = self.value;
		});

		bean.on(contentEl, 'click', function () {
			if (!self.editing) {
				setEditing(true);
			}
		});

		bean.on(inputEl, 'blur', function () {
			setEditing(false);
		});

		bean.on(inputEl, 'keyup', function (event) {
			// TODO: should Esc blur without changing?
			if (['Enter', 'Esc'].indexOf(event.key) !== -1) {
				bean.fire(inputEl, 'blur');
			}
		});
	}

	// TODO: start moving some of the stuff in this massive init handler to separate methods.
	function Column(columnEl) {
		var self = this;
		self.el = columnEl;
		self.id = columnEl.getAttribute('data-column-id');
		var settingsButton = first('.column-settings-button', self.el);
		var settingsEl = first('.column-settings', self.el);
		var sourcesEl = first('.column-sources', self.el);
		var sourceContainerEl = first('.source-container', self.el);
		var newSourceUrl = first('.new-source-url', self.el);
		var newSourceButton = first('.add-source', self.el);
		var searchTermEl = first('.column-search-term', self.el);
		var searchOrderEl = first('.column-search-order', self.el);
		var columnBodyEl = first('.column-body', self.el);
		var deleteColumnButton = first('.delete-column-button', self.el);
		var columnNameEl = first('.column-name', self.el);
		var columnName = new InlineEdit(columnNameEl);
		var items = [];
		var searchTermTimeout;
		var refreshInterval;
		var itemLoadingInProgress = false;

		settingsEl.classList.add('activated');

		bean.on(columnName, 'change', function (event) {
			var req = http.open('POST', '/columns/' + self.id + '/');
			var data = new FormData();
			data.append('name', columnName.value);
			http.send(req, data).then(function (respXhr) {
				console.log('Name change successful!');
			}, function (errXhr) {
				console.log('HTTP Error whilst changing column name:', errXhr);
			});
		});

		bean.on(settingsButton, 'click', function (event) {
			settingsEl.classList.toggle('collapsed');
		});

		bean.on(deleteColumnButton, 'click', function () {
			if (deleteColumnButton.getAttribute('state') == 'deleting') {
				var req = http.open('DELETE', '/columns/' + self.id + '/');
				http.send(req).then(function (respXhr) {
					// TODO: some sort of fancy animated exit so it’s not too abrupt.
					self.el.parentNode.removeChild(self.el);
				}, function (errXhr) {
					console.log('HTTP Error whilst deleting column', errXhr);
				})
			} else {
				deleteColumnButton.setAttribute('state', 'deleting');
				deleteColumnButton.textContent = 'Click again to confirm deletion';
			}
		});

		bean.on(self.el, 'scroll', function (event) {
			if (self.el.scrollHeight - (self.el.scrollTop + self.el.offsetHeight) > items[items.length-1].el.offsetHeight) {
				return;
			}
			console.log(itemLoadingInProgress);
			if (itemLoadingInProgress) {
				return;
			}

			// The user has scrolled the last item in the column into view. Load earlier items.
			var req = http.open('GET', '/columns/' + self.id + '/?from=' + items.length);
			itemLoadingInProgress = true;
			http.send(req).then(function (respXhr) {
				var incomingDoc = document.implementation.createHTMLDocument();
				incomingDoc.documentElement.innerHTML = respXhr.responseText;
				each(all('.item', incomingDoc), function (itemEl) {
					columnBodyEl.appendChild(document.importNode(itemEl, true));
					items.push(new Item(itemEl));
				});
			}, function (errXhr) {
				console.log('HTTP Error whilst fetching older items', errXhr);
			}).then(function () { itemLoadingInProgress = false; });

		});

		if (newSourceUrl) {
			bean.on(newSourceUrl, 'keyup', function (event) {
				newSourceButton.disabled = newSourceUrl.value.trim() == '';

				if (event.keyCode === 13 && !newSourceButton.disabled) {
					bean.fire(newSourceButton, 'click');
				}
			});
			bean.fire(newSourceUrl, 'keyup');

			bean.on(newSourceButton, 'click', function (event) {
				var req = http.open('POST', '/columns/' + self.id + '/sources/');
				var data = new FormData();
				data.append('url', newSourceUrl.value);

				var progress = document.createElement('progress');
				newSourceButton.parentNode.appendChild(progress);

				http.send(req, data).then(function (xhr) {
					// Success! The response is freshest HTML for the source list.
					sourceContainerEl.innerHTML = xhr.responseText;
					newSourceUrl.value = '';

					refreshFeed();
				}, function (xhrErr) {
					// If the result is an error, report it effectively
					console.log('HTTP Error subscribing to ' + newSourceUrl.value, xhrErr);
				}).then(function () {
					// Turn off progress indicators, re-enable button.
					progress.parentNode.removeChild(progress);
					bean.fire(newSourceUrl, 'keyup');
					console.log('HTTP Done');
				});
			});

			bean.on(sourceContainerEl, 'click', 'button.remove-source', function (event) {
				var buttonEl = event.target;
				var req = http.open('POST', '/columns/' + self.id + '/sources/');
				var data = new FormData();
				data.append('url', buttonEl.getAttribute('data-url'));
				data.append('mode', 'unsubscribe');
				http.send(req, data).then(function (xhr) {
					sourceContainerEl.innerHTML = xhr.responseText;
					refreshFeed();
				}, function (xhrErr) {
					// If the result is an error, report it.
					console.log('HTTP Error unsubscribing from ' + buttonEl.getAttribute('data-url'), xhrErr);
				});
			});
		} else if (searchTermEl) {
			var saveSearchTerm = function () {
				console.log('saveSearchTerm called');
				var req = http.open('POST', '/columns/' + self.id + '/search/');
				var data = new FormData();
				data.append('term', searchTermEl.value);

				if (columnName.value.search('Search: ') === 0) {
					columnName.value = 'Search: ' + searchTermEl.value;
					bean.fire(columnName, 'change');
				}

				http.send(req, data).then(function (respXhr) {
					refreshFeed(true);
				}, function (errXhr) {
					console.log('HTTP Error when saving search term', errXhr);
				});
			};

			bean.on(searchTermEl, 'keyup', function () {
				clearTimeout(searchTermTimeout);
				searchTermTimeout = setTimeout(saveSearchTerm, 500);
			});
			
			bean.on(searchOrderEl, 'change', function () {
				var req = http.open('POST', '/columns/' + self.id + '/search/');
				var data = new FormData();
				data.append('order', searchOrderEl.value);
				http.send(req, data).then(
					function (respXhr) { console.log('Saved order state'); refreshFeed(true); },
					function (errXhr) { console.log('HTTP Error while saving search column order', errXhr); }
				); 
			});
		}

		// Either loads new items in, or completely clears the feed, depending on whether or not clear is set.
		var refreshFeed = function (clear) {
			if (itemLoadingInProgress) {
				return;
			}

			var req = http.open('GET', '/columns/' + self.id + '/');
			itemLoadingInProgress = true;
			http.send(req).then(function (respXhr) {
				var incomingDoc = document.implementation.createHTMLDocument(),
						finishedImporting = false,
						firstExistingItem;
				incomingDoc.documentElement.innerHTML = respXhr.responseText;

				if (clear || items.length == 0) {
					columnBodyEl.innerHTML = first('.column-body', incomingDoc).innerHTML;
				} else {
					firstExistingItem = items[0];
					each(all('.item', incomingDoc), function (itemEl) {
						if (finishedImporting || first('.item-url', itemEl).href == firstExistingItem.url) {
							finishedImporting = true;
							return;
						} else {
							columnBodyEl.insertBefore(document.importNode(itemEl, true), firstExistingItem.el);
							items.unshift(new Item(itemEl));
						}
					});
				}
			}, function (errXhr) {
				console.log('HTTP Error fetching feed items', errXhr);
			}).then(function () { itemLoadingInProgress = false; });

			enhanceItems();
		};

		var enhanceItems = function () {
			items = map(all('.item', self.el), function (itemEl) {
				return new Item(itemEl);
			});
		};

		// TODO: re-implement this as a space-wide status checker which checks all columns for new content at once, or even an eventsource (somehow).
		refreshInterval = window.setInterval(refreshFeed, 15000);
		enhanceItems();
	}

	function NewColumn(columnEl) {
		self.el = columnEl;
		bean.on(self.el, 'click', '.new-column-cta button', function (event) {
			var buttonEl = event.target;
			var req = http.open('POST', '/columns/');
			var data = new FormData();
			data.append('type', buttonEl.value);
			http.send(req, data).then(function (respXhr) {
				var lastColumnEl = first('.column:nth-last-of-type(2)');
				var incomingDoc = document.implementation.createHTMLDocument();
				incomingDoc.documentElement.innerHTML = respXhr.responseText;
				var newColEl = first('.column', incomingDoc);
				lastColumnEl.parentNode.insertBefore(newColEl, lastColumnEl.nextSibling);
				columns.push(new Column(newColEl));
				expandColumnContainer();
			}, function (errXhr) {
				console.log('HTTP Error when creating new ' + buttonEl.value + ' column:', errXhr);
			});
		});
	}

	// Activate all the already-existing columns.
	var columns = map(all('.editable-column', columnsEl), function (columnEl) {
		return new Column(columnEl);
	});

	var newColumn = NewColumn(first('.new-column'));

	// Debug handle for columns object.
	console.log(columns);

	// Make the columns sortable.
	var sortableColumns = new Sortable(columnsEl, {
		group: 'columns',
		handle: '.column-name',
		draggable: '.orderable-column',
		ghostClass: '.dragged',
		onUpdate: function (event) {
			// In here, figure out the new order of the columns on the dashboard and save it.
		}
	});

	// Set up any Codemirror instances which need creating.
	enhanceEach('textarea.codemirror', ['codemirror/lib/codemirror', 'codemirror/mode/htmlmixed/htmlmixed'], function (el, CodeMirror) {
		var config = {
			indentUnit: 1,
			tabSize: 2
		};

		if (el.hasAttribute('data-codemirror-mode')) {
			config['mode'] = el.getAttribute('data-codemirror-mode');
		}
		var codemirror = CodeMirror.fromTextArea(el, config);
		expandColumnContainer();
	});
});
