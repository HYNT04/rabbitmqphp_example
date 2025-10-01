#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

// Create RabbitMQ client
$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

// Ask user for username and password
echo "Enter username: ";
$username = trim(fgets(STDIN));

echo "Enter password: ";
$password = trim(fgets(STDIN));

// Build login request
$request = array(
    'type' => 'login',
    'username' => $username,
    'password' => $password
);

// Send request to server
$response = $client->send_request($request);

// Print result
if ($response === true) {
    echo "Login successful for $username" . PHP_EOL;
} else {
    echo "Login failed for $username" . PHP_EOL;
}

echo "Client script finished." . PHP_EOL;
?>
