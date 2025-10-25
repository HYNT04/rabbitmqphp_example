import requests
import pika
import json
import time
import logging

# ---------------------------
# Configuration
# ---------------------------
ALPHA_VANTAGE_API_KEY = "WX2TX0UU5IZ1BYUC"
STOCK_SYMBOLS = ["AAPL", "MSFT", "GOOGL"]  # Add symbols you want to track
INTERVAL = 5  # seconds between updates (5 minutes)

# RabbitMQ Configuration
RABBITMQ_HOST = "127.0.0.1"
RABBITMQ_PORT = 5672
RABBITMQ_USER = "test"
RABBITMQ_PASSWORD = "test"
RABBITMQ_VHOST = "testHost"
QUEUE_NAME = "testQueue"

# Logging setup
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    handlers=[logging.FileHandler("dmz_stock.log"), logging.StreamHandler()]
)

# ---------------------------
# Functions
# ---------------------------
def get_stock_data(symbol):
    """Fetch latest stock data from Alpha Vantage"""
    url = "https://www.alphavantage.co/query"
    params = {
        "function": "TIME_SERIES_INTRADAY",
        "symbol": symbol,
        "interval": "5min",
        "apikey": ALPHA_VANTAGE_API_KEY
    }
    try:
        response = requests.get(url, params=params, timeout=10)
        response.raise_for_status()
        data = response.json()
        # Simplify to latest data point
        ts = list(data.get("Time Series (5min)", {}).keys())
        if not ts:
            logging.warning(f"No data returned for {symbol}")
            return None
        latest = data["Time Series (5min)"][ts[0]]
        simplified = {
            "symbol": symbol,
            "timestamp": ts[0],
            "open": latest["1. open"],
            "high": latest["2. high"],
            "low": latest["3. low"],
            "close": latest["4. close"],
            "volume": latest["5. volume"]
        }
        return simplified
    except Exception as e:
        logging.error(f"Error fetching stock data for {symbol}: {e}")
        return None

def send_to_rabbitmq(message):
    """Send a message to RabbitMQ"""
    try:
        credentials = pika.PlainCredentials(RABBITMQ_USER, RABBITMQ_PASSWORD)
        connection = pika.BlockingConnection(
            pika.ConnectionParameters(
                host=RABBITMQ_HOST,
                port=RABBITMQ_PORT,
                virtual_host=RABBITMQ_VHOST,
                credentials=credentials
            )
        )
        channel = connection.channel()
        channel.queue_declare(queue=QUEUE_NAME, durable=True)
        channel.basic_publish(
            exchange='',
            routing_key=QUEUE_NAME,
            body=json.dumps(message)
        )
        logging.info(f"Sent data to RabbitMQ for {message['message']['symbol']}")
        connection.close()
    except Exception as e:
        logging.error(f"Error sending to RabbitMQ: {e}")

# ---------------------------
# Main Loop
# ---------------------------
if __name__ == "__main__":
    logging.info("DMZ Stock Service started")
    while True:
        for symbol in STOCK_SYMBOLS:
            data = get_stock_data(symbol)
            if data:
                request = {
                    "type": "stock_data",
                    "username": "steve",
                    "password": "password",
                    "message": data
                }
                send_to_rabbitmq(request)
        logging.info(f"Sleeping for {INTERVAL} seconds...")
        time.sleep(INTERVAL)

