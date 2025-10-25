#!/usr/bin/php
<?php
require_once('testRabbitMQClient.php'); // include your existing client

// -----------------------------
// CONFIGURATION
// -----------------------------
$symbols = ['AAPL', 'MSFT', 'GOOGL']; // stocks to fetch
$apiKey = 'WX2TX0UU5IZ1BYUC';   // your AlphaVantage key

// -----------------------------
// HELPER FUNCTION
// -----------------------------
function fetchStockData($symbol, $apiKey) {
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

// -----------------------------
// SEND STOCK DATA TO RABBITMQ
// -----------------------------
$client = new rabbitMQClient("testRabbitMQ.ini","testServer");

foreach ($symbols as $symbol) {
    $stockData = fetchStockData($symbol, $apiKey);

    $request = array();
    $request['type'] = "stock_data";
    $request['symbol'] = $symbol;
    $request['data'] = $stockData;

    $response = $client->send_request($request);

    echo "Sent stock data for $symbol, received response:\n";
    print_r($response);
    echo "\n\n";
}

