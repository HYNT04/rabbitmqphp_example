import pika
import json
import requests
import configparser
import os

# -----------------------------
# CONFIGURATION
# -----------------------------
config = configparser.ConfigParser()
config.read('testRabbitMQ.ini')

BROKER_HOST = config['testServer']['BROKER_HOST']
BROKER_PORT = int(config['testServer']['BROKER_PORT'])
USER = config['testServer']['USER']
PASSWORD = config['testServer']['PASSWORD']
VHOST = config['testServer']['VHOST']
EXCHANGE = config['testServer']['EXCHANGE']
QUEUE = config['testServer']['QUEUE']
EXCHANGE_TYPE = config['testServer']['EXCHANGE_TYPE']
AUTO_DELETE = config['testServer'].getboolean('AUTO_DELETE')

# AlphaVantage API key (set via environment variable or directly)
ALPHAVANTAGE_API_KEY = os.environ.get("ALPHAVANTAGE_API_KEY", "YOUR_API_KEY")
ALPHAVANTAGE_BASE = "https://www.alphavantage.co/query"

# -----------------------------
# RABBITMQ CONNECTION
# -----------------------------
credentials = pika.PlainCredentials(USER, PASSWORD)
parameters = pika.ConnectionParameters(
    host=BROKER_HOST,
    port=BROKER_PORT,
    virtual_host=VHOST,
    credentials=credentials
)

connection = pika.BlockingConnection(parameters)
channel = connection.channel()

# Declare exchange and queue
channel.exchange_declare(exchange=EXCHANGE, exchange_type=EXCHANGE_TYPE, durable=True)
channel.queue_declare(queue=QUEUE, durable=True, auto_delete=AUTO_DELETE)
channel.queue_bind(exchange=EXCHANGE, queue=QUEUE, routing_key=QUEUE)

print(f"✅ Connected to RabbitMQ at {BROKER_HOST}:{BROKER_PORT}, queue '{QUEUE}'")

# -----------------------------
# FETCH STOCK DATA
# -----------------------------
def fetch_stock_data(symbol):
    try:
        params = {
            "function": "TIME_SERIES_DAILY",
            "symbol": symbol,
            "apikey": ALPHAVANTAGE_API_KEY
        }
        response = requests.get(ALPHAVANTAGE_BASE, params=params)
        data = response.json()
        if "Time Series (Daily)" not in data:
            return {"error": f"Invalid symbol or API limit reached: {symbol}"}

        latest_date = next(iter(data["Time Series (Daily)"]))
        price_info = data["Time Series (Daily)"][latest_date]

        return {
            "symbol": symbol,
            "date": latest_date,
            "open": float(price_info["1. open"]),
            "high": float(price_info["2. high"]),
            "low": float(price_info["3. low"]),
            "close": float(price_info["4. close"]),
            "volume": int(price_info["5. volume"])
        }
    except Exception as e:
        return {"error": str(e)}

# -----------------------------
# MESSAGE HANDLER
# -----------------------------
def on_message(ch, method, properties, body):
    try:
        request = json.loads(body)
        print(f"📩 Received request: {request}")

        action = request.get("action")
        symbol = request.get("symbol")

        if action == "search" and symbol:
            result = fetch_stock_data(symbol)
        else:
            result = {"error": "Invalid request format"}

        # Publish response
        response = json.dumps(result)
        channel.basic_publish(
            exchange=EXCHANGE,
            routing_key=QUEUE,
            body=response
        )
        print(f"📤 Sent response for {symbol}: {result}")

    except Exception as e:
        print(f"❌ Error processing message: {e}")

    ch.basic_ack(delivery_tag=method.delivery_tag)

# -----------------------------
# START LISTENING
# -----------------------------
channel.basic_consume(queue=QUEUE, on_message_callback=on_message)
print("🕓 Waiting for messages...")
channel.start_consuming()
