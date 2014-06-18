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
			font-family: Actor, Geneva, sans-serif;
			font-size: 13px;
			line-height: 1.3;
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
			width: 25em;
			margin-right: 0.769em;
			background: rgba(255, 255, 255, 0.08);
			vertical-align: top;
			overflow-y: scroll;
		}

		.column * {
			max-width: 100%;
		}

		.column.double-width {
			width: 50em;
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
			padding: 0.384em;
			background: rgba(246, 246, 246, 96);
			margin-top: 0.384em;
		}

		.item a, .item a:visited { color: #005885; text-decoration: none; border-bottom: 1px #777 solid; }
		.item a:hover, .item a:active { color: #0094DE; }

		.item .item-author {
			font-size: 1.231em;
			margin: 0.384em;
		}

		.item .item-author a {
			color: #4A4A4A;
			border-bottom: none;
		}

		.item .item-author-photo {
			vertical-align: middle;
			height: 2em;
			margin-right: 0.384em;
		}

		.item.named-item .item-author-photo {
			height: 2.91em;
			vertical-align: top;
		}

		.item.named-item .item-author a {
			font-size: 0.75em;
		}

		.item.named-item .item-name-container {
			display: inline-block;
			width: 21.7em;
			vertical-align: top;
		}

		.item.named-item .item-name {
			font-size: 1.2307em;
		}

		.item .item-content {
			padding: 0.384em;
		}

		.item-content p:not(:last-child) {
			margin-bottom: 0.925em;
		}

		.item-content ins,
		.item-content ins p {
			text-decoration: none;
			background: lightyellow;
		}

		.item-content blockquote {
			margin: 0;
			padding-left: 0.384em;
			border-left: #ddd solid 0.384em;
		}

		.item-content pre {
			overflow-x: auto;
		}

		.item-content small {
			font-size: 0.846em;
			line-height: 1em;
		}

		.item-foot {
			padding: 0.384em;
			font-size: 0.923em;
		}

		.item-url {
			border-bottom: none !important;
		}

		.item .item-published {
			color: #555;
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

		.properties ul {
			padding: 0.5em;
			list-style: none;
		}
	</style>
	<script src="/js/require.js" data-main="/js/app"></script>
</head>
<body>