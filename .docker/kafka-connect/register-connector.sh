#!/bin/bash

set -e

echo "Waiting for Kafka Connect to be ready..."
until curl -s http://kafka-connect:8083/connectors > /dev/null; do
    sleep 5
done

echo "Registering Debezium connector..."
curl -X POST http://kafka-connect:8083/connectors \
  -H "Content-Type: application/json" \
  -d @/etc/kafka-connect/debezium-connector.json

echo "Connector registered successfully"