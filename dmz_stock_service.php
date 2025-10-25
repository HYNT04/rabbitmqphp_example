<?php
// -----------------------------
// LOAD RABBITMQ LIBRARY WITHOUT COMPOSER
// -----------------------------
require_once __DIR__ . '/php-amqplib/PhpAmqpLib/Autoloader.php';
\PhpAmqpLib\Autoloader::register();

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// -----------------------------
// CONFIGURATION
// -----------------------------
$host = '127.0.0.1';
$port = 5672;
$user = 'test';
$pass = 'test';
$vhost = 'testHost';
$exchange = 'stockExchange';
$queueSearch = 'stock.request';
$queueTrade = 'trade.request';
$queuePortfolio = 'portfolio.update';
$queueNotify = 'notifications';
$apiKey = 'WX2TX0UU5IZ1BYUC'; // set your AlphaVantage key

// -----------------------------
// CONNECT TO RABBITMQ
// -----------------------------
$connection = new AMQPStreamConnection($host, $port, $user, $pass, $vhost);
$channel = $connection->channel();

// Declare exchange and queues
$channel->exchange_declare($exchange, 'direct', false, true, false);
$queues = [$queueSearch, $queueTrade, $queuePortfolio, $queueNotify];
foreach($queues as $q){
    $channel->queue_declare($q, false, true, false, false);
    $channel->queue_bind($q, $exchange, $q);
}

echo "✅ Connected to RabbitMQ, waiting for messages...\n";

// -----------------------------
// MOCK DATABASE
// -----------------------------
$portfolio = [];       // username => holdings
$trades = [];          // username => trades
$userThresholds = [];  // username => ['symbol' => ['high'=>..., 'low'=>...]]

// -----------------------------
// HELPER FUNCTIONS
// -----------------------------
function fetchStockData($symbol, $apiKey){
    $url = "https://www.alphavantage.co/query?function=TIME_SERIES_DAILY&symbol=$symbol&apikey=$apiKey";
    $json = file_get_contents($url);
    $data = json_decode($json, true);

    if (!isset($data['Time Series (Daily)'])) {
        return ['error' => "Invalid symbol or API limit reached"];
    }

    $latestDate = array_key_first($data['Time Series (Daily)']);
    $priceInfo = $data['Time Series (Daily)'][$latestDate];

    return [
        'symbol' => $symbol,
        'date' => $latestDate,
        'open' => (float)$priceInfo['1. open'],
        'high' => (float)$priceInfo['2. high'],
        'low' => (float)$priceInfo['3. low'],
        'close' => (float)$priceInfo['4. close'],
        'volume' => (int)$priceInfo['5. volume']
    ];
}

function handleSearch($request, $channel, $exchange, $queueSearch, $apiKey){
    $symbol = $request['symbol'] ?? null;
    $username = $request['username'] ?? 'guest';
    if($symbol){
        $result = fetchStockData($symbol, $apiKey);
        // Check thresholds for notifications
        global $userThresholds, $queueNotify;
        if(isset($userThresholds[$username][$symbol])){
            $th = $userThresholds[$username][$symbol];
            if(isset($th['high']) && $result['close'] >= $th['high']){
                $msg = new AMQPMessage(json_encode(["username"=>$username,"symbol"=>$symbol,"message"=>"Stock reached high: {$th['high']}"]));
                $channel->basic_publish($msg, $exchange, $queueNotify);
            }
            if(isset($th['low']) && $result['close'] <= $th['low']){
                $msg = new AMQPMessage(json_encode(["username"=>$username,"symbol"=>$symbol,"message"=>"Stock reached low: {$th['low']}"]));
                $channel->basic_publish($msg, $exchange, $queueNotify);
            }
        }
    } else {
        $result = ['error'=>"No symbol provided"];
    }
    $msg = new AMQPMessage(json_encode($result));
    $channel->basic_publish($msg, $exchange, $queueSearch);
}

function handleTrade($request, $channel, $exchange){
    global $trades, $portfolio, $queuePortfolio;
    $username = $request['username'] ?? 'guest';
    $symbol = $request['symbol'] ?? null;
    $action = $request['action'] ?? null;
    $shares = $request['shares'] ?? 0;

    if($symbol && $action && $shares > 0){
        $trades[$username][] = $request;
        if(!isset($portfolio[$username])) $portfolio[$username] = [];
        if(!isset($portfolio[$username][$symbol])) $portfolio[$username][$symbol]=0;

        if($action==='buy') $portfolio[$username][$symbol]+=$shares;
        if($action==='sell') $portfolio[$username][$symbol]-=$shares;

        $msg = new AMQPMessage(json_encode([
            'username'=>$username,
            'portfolio'=>$portfolio[$username]
        ]));
        $channel->basic_publish($msg, $exchange, $queuePortfolio);
    }
}

// -----------------------------
// CALLBACK
// -----------------------------
$callback = function($msg) use ($channel, $exchange, $queueSearch, $queueTrade, $apiKey){
    $request = json_decode($msg->body, true);
    $queue = $msg->delivery_info['routing_key'];

    if($queue === $queueSearch){
        handleSearch($request, $channel, $exchange, $queueSearch, $apiKey);
    } elseif($queue === $queueTrade){
        handleTrade($request, $channel, $exchange);
    }
    $msg->ack();
};

// -----------------------------
// START CONSUMING
// -----------------------------
foreach([$queueSearch,$queueTrade] as $q){
    $channel->basic_consume($q,'',false,false,false,false,$callback);
}

while($channel->is_consuming()){
    $channel->wait();
}

