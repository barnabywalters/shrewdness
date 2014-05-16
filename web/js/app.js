'use strict';

requirejs.config({
	baseUrl: '/js'
});

define(['sortable'], function (Sortable) {
	console.log('Application started successfully');
	// DOM convenience functions.
	var first = document.querySelector.bind(document);
	var all = document.querySelectorAll.bind(document);

	// Ensure that the column container is wide enough to contain all the columns.
	// TODO: this needs to be called whenever there is a new column.
	document.querySelector('.columns').style.width = document.querySelectorAll('.column').length * (document.querySelector('.column').offsetWidth + 10) + 'px';

	function Column(el) {
		this.el = el;
	}

	var columnsEl = first('.columns');

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
});
