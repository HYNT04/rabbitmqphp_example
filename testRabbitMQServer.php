#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

function doLogin($username,$password)
{
    // lookup username in databas
    // check password
    return true;
    //return false if not valid
}

function requestProcessor($request)
{
  echo "received request".PHP_EOL;
  var_dump($request);
  if(!isset($request['type']))
  {
    return "ERROR: unsupported message type";
  }
  switch ($request['type'])
  {
    case "login":
     // return doLogin($request['username'],$request['password']);
    	$session_id = uniqid("sess_", true);
    	file_put_contents("/tmp/session_$session_id.txt", $request['username']);
    	return array(
        	'status' => 'success',
        	'message' => 'Login successful',
        	'session_id' => $session_id
	);

    case "validate_session":
      //return doValidate($request['sessionId']);
  	$session_id = $request['session_id'];
    	$session_file = "/tmp/session_$session_id.txt";
    	if (file_exists($session_file)) {
        	$user = file_get_contents($session_file);
        	return array('status' => 'success', 'message' => "Session valid for user $user");
    	}
	return array('status' => 'fail', 'message' => 'Invalid or expired session');
    case "stock_data":
            $symbol = $request['message']['symbol'] ?? 'unknown';
            $timestamp = $request['message']['timestamp'] ?? '';
            $log_file = "/tmp/stock_$symbol.log";
            file_put_contents($log_file, json_encode($request['message']).PHP_EOL, FILE_APPEND);
            return array('status' => 'success', 'message' => "Stock data received for $symbol at $timestamp");

        default:
            return "ERROR: unsupported message type";

  }
  //return array("returnCode" => '0', 'message'=>"Server received request and processed");
}

$server = new rabbitMQServer("testRabbitMQ.ini","testServer");

echo "testRabbitMQServer BEGIN".PHP_EOL;
$server->process_requests('requestProcessor');
echo "testRabbitMQServer END".PHP_EOL;
exit();
?>

