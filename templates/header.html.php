<!doctype html>
<html>
<head>
	<title>Shrewdness</title>
	<link rel="stylesheet" href="/js/codemirror/lib/codemirror.css" />
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
			overflow-x: scroll;
		}

		.y-scroll-wrapper {
			width: 100%;
			height: 100%;
			overflow-y: scroll;
		}

		.columns {
			height: 100%;
		}

		.column {
			display: inline-block;
			height: 100%;
			width: 20.3em;
			margin-right: 0.2em;
			background: rgba(255, 255, 255, 0.08);
			vertical-align: top;
			overflow-y: scroll;
		}

		.column * {
			max-width: 100%;
		}

		.column.double-width {
			width: 40em;
		}

		.column.light-column {
			background: rgba(255, 255, 255, 0.7);
		}

		.column.dragged {
			opacity: 0.7;
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
			background: rgba(255, 255, 255, 0.78);
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

		.column-sources progress {
			width: 1.8em;
		}

		.new-column-cta {
			font-size: 1.25em;
			color: #9B9B9B;
		}

		/* Item styles, pasted from intertubes */
		.item {
			padding: 0.5em;
			background: #ccc;
			font-size: 0.9em;
			margin: 1px 0.5em;
		}

		.item a, .item a:visited { color: #005885; text-decoration: none; border-bottom: 1px #777 solid; }
		.item a:hover, .item a:active { color: #0094DE; }

		.item .item-author-photo {
			vertical-align: middle;
			height: 1.2em;
		}

		.item .item-published {
			color: #555;
			font-size: 0.8em;
		}

		.item-foot {
			margin: 0.5em 0;
		}

		.item-foot .item-actions {
			display: inline;
			float: right;
		}

		.item-foot .reply-content {
			width: 100%;
			height: 3em;
		}

		.item-foot .post-reply-button {
			float: right;
		}

		/* Testing UI Styles */
		.test-columns .codemirror,
		.test-columns .CodeMirror {
			height: 50%;
			width: 100%;
			margin-bottom: 0.5em;
		}

		.properties {
			font-size: 0.8em;
		}

		.properties ul {
			padding: 0.5em;
			list-style: none;
		}
	</style>
	<script src="/js/require.js" data-main="/js/app"></script>
</head>
<body>