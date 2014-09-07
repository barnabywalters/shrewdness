<!doctype html>
<html>
<head>
	<title>Shrewdness Locations</title>
	<script src="/js/require.js" data-main="/js/locations-app"></script>

	<link rel="stylesheet" href="//cdn.leafletjs.com/leaflet-0.7.3/leaflet.css" />
	<!--[if lte IE 8]>
	<link rel="stylesheet" href="//cdn.leafletjs.com/leaflet-0.7.3/leaflet.ie.css" />
	<![endif]-->

	<style>
		html, body {
			margin: 0;
			padding: 0;
			width: 100%;
			height: 100%;
		}

		#map {
			width: 100%;
			height: 100%;
		}
	</style>
</head>
<body>
	<script id="locations-data" type="application/json"><?= json_encode($authorLocations) ?></script>
	<div id="map"></div>
</body>
</html>