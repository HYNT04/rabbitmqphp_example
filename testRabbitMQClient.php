#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$client = new rabbitMQClient("testRabbitMQ.ini","testServer");
if (isset($argv[1]))
{
  $msg = $argv[1];
}
else
{
  $msg = "test message";
}

$request = array();
$request['type'] = "login";
$request['username'] = "steve";
$request['password'] = "password";
$request['message'] = $msg;
$response = $client->send_request($request);
//$response = $client->publish($request);

echo "client received response: ".PHP_EOL;
print_r($response);
echo "\n\n";
echo PHP_EOL;

if (!isset($response['session_id'])) {
    echo "Login failed or no session ID returned." . PHP_EOL;
    exit();
}

$session_id = $response['session_id'];
echo "Session ID received: $session_id" . PHP_EOL;

$validate_request = array();
$validate_request['type'] = "validate_session";
$validate_request['session_id'] = $session_id;

$validate_response = $client->send_request($validate_request);

echo "Client received session validation response:" . PHP_EOL;
print_r($validate_response);
echo $argv[0]." END".PHP_EOL;

