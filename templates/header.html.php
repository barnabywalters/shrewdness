<!doctype html>
<html>
<head>
	<title>Shrewdness</title>
	<style>
		* {
			box-sizing: border-box;
		}

		html, body {
			margin: 0;
			padding: 0;
			width: 100%;
			height: 100%;
		}

		p {
			margin: 0;
			padding: 0;
		}

		body {
			background: url(/background.png) #111;
			font-family: Actor-Regular, Geneva, sans-serif;
			font-size: 100%;
		}

		h1, h2, h3, h4, h5, h6 {
			padding: 0;
			margin: 0;
			font-weight: normal;
		}

		.x-scroll-wrapper {
			width: 100%;
			height: 100%;
			padding: 2em;
			padding-bottom: 0;
			overflow-x: scroll;
		}

		.columns {
			height: 100%;
		}

		.column {
			display: inline-block;
			height: 100%;
			width: 20.3em;
			margin-right: 0.2em;
			background: rgba(255,255,255,0.08);
		}

		.column-header {
			padding: 0.4em;
		}

		.column-name {
			float: left;
			font-size: 1.25em;
			color: #9B9B9B;
		}

		.column-settings-button {
			float: right;
			margin: 0.4em;
		}

		.column-settings {
			clear: both;
		}

		.column-settings-name {
			font-size: 0.8125em;
			color: #B3B3B3;
		}

		.column-sources {
			background: rgba(255,255,255,0.78);
			padding: 0.4em;
		}

		.column-source-photo {
			height: 1.25em;
			width: 1.25em;
			vertical-align: middle;
			position: relative;
			top: -0.2em;
		}

		.source-domain {
			font-size: 0.875em;
			color: #727272;
		}
	</style>
	<script src="/js/require.js" data-main="js/app"></script>
</head>
<body>