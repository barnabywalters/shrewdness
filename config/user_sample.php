<?php

// User-specific Sensitive Configuration

// Database connection credentials
// See also: http://php.net/manual/en/pdo.construct.php
$app['db.dsn'] = '';
$app['db.username'] = '';
$app['db.password'] = '';
$app['db.options'] = array();

$app['superfeedr.username'] = '';
$app['superfeedr.password'] = '';

// A secret for encrypting things with (e.g. cookies).
// Generate one locally by executing: openssl rand -hex 10
$app['encryption.secret'] = '';

// The exact URL of the owner of the site, e.g. “http://waterpigs.co.uk” or “https://example.com”
$app['owner.url'] = '';

// If using Twitter API proxying, credentials here (optional)
$app['twitter.token_key'] = '';
$app['twitter.token_secret'] = '';
