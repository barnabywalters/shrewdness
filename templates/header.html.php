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

		.header {
			background: rgba(255, 255, 255, 0.2);
			position: relative;
			color: rgb(187, 187, 187);
			font-size: 1.25em;
			height: 2.5em;
			margin-bottom: 0.7em;
			padding: 0.5em 0.5em 0 0.5em;
		}

		.header .user {
			display: inline-block;
			right: 0.5em;
			position: absolute;
		}

		.inline-form {
			display: inline-block;
		}

		.micropub-support {
			font-size: 0.6em;
			display: inline-block;
			background: #97DBF6;
			color: #008393;
			border-radius: 1em;
			padding: 0.2em;
			vertical-align: middle;
			text-align: center;
		}

		.inline-edit {
			cursor: pointer;
		}
		.inline-edit .inline-edit-input {
			display: none;
		}
		.inline-edit.editing .inline-edit-input {
			display: inline-block;
		}
		.inline-edit.editing .inline-edit-content {
			display: none;
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

		.space {
			height: -moz-calc(100% - (3.125em + 0.875em));
			height: -webkit-calc(100% - (3.125em + 0.875em));
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
			float: left;
			padding: 0.4em;
			width: 100%;
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
			max-height: 20em;
			overflow-y: scroll;
		}

		.column-settings-name {
			font-size: 0.8125em;
			color: #B3B3B3;
		}

		.column-sources {
			background: rgba(255, 255, 255, 0.78);
			padding: 0.4em;
		}

		.column-source {
			width: 100%;
			height: 1.6em;
		}

		.column-source:hover {
			background: rgba(255, 255, 255, 0.3);
		}

		.column-source .remove-source {
			float: right;
		}

		.new-source-url {
			width: -moz-calc(100% - 3em)
		}

		.add-source {
			width: 3em;
			height: 2.1em;
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

		.delete-column {
			background: rgba(255, 100, 100, 0.3);
			margin-top: 0.5em;
			padding: 0.5em;
		}

		.column-body {
			clear: both;
			overflow-x: hidden;
			overflow-y: scroll;
		}

		.new-column-cta {
			font-size: 1.25em;
			color: #9B9B9B;
		}

		/* Item styles, pasted from intertubes */
		.item {
			margin-top: 0.384em;
		}

		.item .item-author,
		.item .item-content,
		.item .item-foot {
			padding: 0.768em;
			background: rgba(246, 246, 246, 96);
		}

		.item a, .item a:visited { color: #005885; text-decoration: none; border-bottom: 1px #777 solid; }
		.item a:hover, .item a:active { color: #0094DE; }

		.item .item-author {
			font-size: 1.231em;
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
			width: 16em;
			vertical-align: top;
		}

		.item.named-item .item-name {
			font-size: 1.2307em;
		}

		.item .item-photo {
			background: rgba(255, 255, 255, 0.9);
			box-shadow: 0 0 1em #777 inset;
			display: block;
		}

		.item .item-content {
			padding-top: 0;
			padding-bottom: 0;
		}

		.item-content p:not(:last-child) {
			margin-bottom: 0.925em;
		}

		.item-content img {
			height: auto;
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
			font-size: 0.923em;
		}

		.item-logo {
			width: 1.5em;
			vertical-align: middle;
			margin-right: 0.3em;
			max-width: 2.5em;
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

		.item-action-panel {
			background: #A3DDE7;
			padding: 0 0.68em;
		}

		.reply-panel {
			padding-top: 0.68em;
		}

		.reply-content {
			width: 100%;
			height: 4em;
			border: 1px solid #555;
			box-shadow: 0 0 0.1em rgba(0, 0, 0, 0.3) inset;
			padding: 0.35em;
		}

		.reply-post-button {
			margin: 0.5em 0;
		}

		.item-reply-context {
			padding: 0.768em;
			margin-left: 0.768em;
			margin-right: 0.768em;
			font-size: 0.9em;
			background: rgba(246, 246, 246, 0.7);
		}

		.item-reply-context .item-reply-context-author-photo {
			max-height: 1.5em;
			margin-right: 0.1em;
			position: relative;
			bottom: -0.3em;
		}

		.item-reply-context .item-reply-context-content:link {
			border-bottom: none;
			color: #222 !important;
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

		.collapsing-panel {
			transition: all 0.3s;
		}

		.collapsing-panel.collapsed {
			max-height: 0;
			overflow: hidden;
			opacity: 0;
		}
	</style>
	<script src="/js/promise.js"></script>
	<script src="/js/require.js" data-main="/js/app"></script>
</head>
<body>