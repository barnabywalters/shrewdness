'use strict';

define({
	open: function open(method, url) {
		var xhr = new XMLHttpRequest();
		xhr.open(method.toUpperCase(), url);
		return xhr;
	},

	send: function send(xhr, value) {
		var value = value || null;
		return new Promise(function (resolve, reject) {
			xhr.onload = function () {
				// Success if status in 2XX.
				if (xhr.status - 200 <= 99 && xhr.status - 200 > -1) {
					resolve(xhr);
				} else {
					reject(xhr);
				}
			};

			xhr.onerror = function () {
				reject(xhr);
			};

			xhr.send(value);
		});
	}
});
