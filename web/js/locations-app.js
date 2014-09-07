'use strict';

requirejs.config({
	baseUrl: '/js',
	paths: {
		leaflet: '//cdn.leafletjs.com/leaflet-0.7.3/leaflet',
	},
	shim: {
		'leaflet': {exports: 'L'}
	}
});

define(['bean', 'leaflet'], function (bean, L) {
	console.log('Location application started successfully');
	// DOM convenience functions.
	var first = function (selector, context) { return (context || document).querySelector(selector); };
	var all = function (selector, context) { return (context || document).querySelectorAll(selector); };
	var each = function (els, callback) { return Array.prototype.forEach.call(els, callback); };

	var tileUrl = 'http://{s}.tiles.mapbox.com/v3/barnabywalters.i9img0ac/{z}/{x}/{y}.png';
	var mapAttribution = 'Map data <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://opendatacommons.org/licenses/odbl/1.0/">ODBL</a>, tiles by <a href="https://mapbox.com">Mapbox</a>';


	var mapEl = document.getElementById('map');

	var map = L.map(mapEl, {scrollWheelZoom: false});
	var viewPoints = [];
	var authorLocations = JSON.parse(first('#locations-data').textContent);

	L.tileLayer(tileUrl, {
		attribution: mapAttribution,
		maxZoom: 18
	}).addTo(map);

	console.log(authorLocations);

	Object.keys(authorLocations).forEach(function (k) {
		var authorIcon = L.icon({
			iconUrl: authorLocations[k][0].author.photo,
			iconSize: [30, 30]
		});

		authorLocations[k].forEach(function (hEntry) {
			var coords = [hEntry.location_point.lat, hEntry.location_point.lon];
			viewPoints.push(coords);

			var marker = L.marker(coords, {
				icon: authorIcon
			});

			var content = '<p>' + hEntry.text + '</p>';
			if (hEntry.photo) {
				content = content + '<img style="max-width: 100% !important;" src="' + hEntry.photo + '" />';
			}

			marker.bindPopup(content);
			marker.addTo(map);
		});
	});

	var bounds = L.latLngBounds(viewPoints);
	map.fitBounds(bounds, {
		padding: [10, 10]
	});
});
