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
		document.querySelector('.columns').style.width = Array.prototype.reduce.call(document.querySelectorAll('.column'), function (total, el) { return el.offsetWidth + total + 13; }, 0) + 'px';
	};
	expandColumnContainer();

	var columnsEl = first('.columns');

	function Item(itemEl) {
		var self = this;
		self.el = itemEl;
		var actionPanelEl = first('.item-action-panel', self.el);
		var replyButton = first('.reply-button', self.el);
		var replyPostButton = first('.reply-post-button', self.el);
		var replyTextarea = first('.reply-content', self.el);
		var url = first('.item-url', self.el).href;

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
			data.append('in-reply-to', url);
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
		var columnBodyEl = first('.column-body', self.el);
		var items = [];

		settingsEl.classList.add('activated');

		bean.on(settingsButton, 'click', function (event) {
			settingsEl.classList.toggle('collapsed');
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
		}

		var refreshFeed = function () {
			var req = http.open('GET', '/columns/' + self.id + '/');
			http.send(req).then(function (respXhr) {
				var incomingDoc = document.implementation.createHTMLDocument();
				incomingDoc.documentElement.innerHTML = respXhr.responseText;
				columnBodyEl.innerHTML = first('.column-body', incomingDoc).innerHTML;
			}, function (errXhr) {
				console.log('HTTP Error fetching feed items', errXhr);
			});

			enhanceItems();
		};

		var enhanceItems = function () {
			items = map(all('.item', self.el), function (itemEl) {
				return new Item(itemEl);
			});
		};

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
