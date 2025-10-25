import pika, json

credentials = pika.PlainCredentials("test", "test")
connection = pika.BlockingConnection(
    pika.ConnectionParameters(host="127.0.0.1", port=5672, virtual_host="testHost", credentials=credentials)
)
channel = connection.channel()
channel.queue_declare(queue="testQueue", durable=True)

msg = {
    "type": "stock_data",
    "username": "steve",
    "password": "password",
    "message": {"symbol": "TEST", "timestamp": "now"}
}

channel.basic_publish(exchange="", routing_key="testQueue", body=json.dumps(msg))
print("Test message sent")
connection.close()

