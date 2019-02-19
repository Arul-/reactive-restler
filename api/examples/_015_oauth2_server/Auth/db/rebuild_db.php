<?php

// remove sqlite file if it exists
if (file_exists(OAUTH2_DATABASE)) {
    unlink(OAUTH2_DATABASE);
}

$dir = dirname(OAUTH2_DATABASE);

if (!is_writable($dir)) {
    // try to set permissions.
    if (!@chmod($dir, 0777)) {
        throw new Exception("Unable to write to " . OAUTH2_DATABASE);
    }
}

// rebuild the DB
$db = new PDO(sprintf('sqlite:%s', OAUTH2_DATABASE));
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE oauth_clients (client_id TEXT, client_secret TEXT, redirect_uri TEXT)');
$db->exec('CREATE TABLE oauth_access_tokens (access_token TEXT, client_id TEXT, user_id TEXT, expires TIMESTAMP, scope TEXT)');
$db->exec('CREATE TABLE oauth_authorization_codes (authorization_code TEXT, client_id TEXT, user_id TEXT, redirect_uri TEXT, expires TIMESTAMP, scope TEXT)');
$db->exec('CREATE TABLE oauth_refresh_tokens (refresh_token TEXT, client_id TEXT, user_id TEXT, expires TIMESTAMP, scope TEXT)');
$db->exec('CREATE TABLE oauth_users (username TEXT, password TEXT, first_name TEXT, last_name TEXT)');

// add test data
$db->exec('INSERT INTO oauth_clients (client_id, client_secret) VALUES ("demoapp", "demopass")');
$db->exec(sprintf('INSERT INTO oauth_users (username, password) VALUES ("demouser", "%s")', sha1("testpass")));

chmod(OAUTH2_DATABASE, 0777);
// $db->exec('INSERT INTO oauth_access_tokens (access_token, client_id) VALUES ("testtoken", "Some Client")');
// $db->exec('INSERT INTO oauth_authorization_codes (authorization_code, client_id) VALUES ("testcode", "Some Client")');