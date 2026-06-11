#!/bin/bash

set -e

echo "🚀 Deploying Notification Service..."

# Проверка .env
if [ ! -f .env ]; then
    echo "❌ .env file not found!"
    cp .env.example .env
#    echo "⚠️  Please edit .env file"
#    exit 1
fi

# Генерация ключа
if grep -q "change_this" .env; then
    echo "🔑 Generating application key..."
    APP_KEY=$(openssl rand -base64 32)
    sed -i "s|APP_KEY=.*|APP_KEY=base64:${APP_KEY}|g" .env
fi

# Скопируй .env из корня в src/
cp .env src/.env

# Cluster ID
if [ ! -f .kafka_cluster_id ]; then
    echo "🎲 Generating Kafka Cluster ID..."
    docker run --rm confluentinc/cp-kafka:7.6.0 kafka-storage random-uuid > .kafka_cluster_id
fi
CLUSTER_ID=$(cat .kafka_cluster_id)
sed -i "s|CLUSTER_ID:.*|CLUSTER_ID: '${CLUSTER_ID}'|g" docker-compose.yml

# Запуск всего стека
echo "📦 Starting all services..."
docker compose up -d

# Ждем готовности
echo "⏳ Waiting for services..."
until curl -s http://localhost:8083/ &>/dev/null; do sleep 5; done
echo "✅ Kafka Connect ready"

until curl -s -f http://localhost:8080/health &>/dev/null; do sleep 2; done
echo "✅ App ready"

# Создание топиков
echo "🔧 Creating topics..."

create_topic() {
    docker compose exec -T kafka1 kafka-topics --create \
        --topic "$1" \
        --partitions "$2" \
        --replication-factor "$3" \
        --config "$4" \
        --config "$5" \
        --config compression.type=lz4 \
        --bootstrap-server kafka1:9092 2>/dev/null && echo "  ✅ $1" || echo "  ⏭️  $1 (exists)"
}

create_topic "notifications.transactional" 6 3 "min.insync.replicas=2" "retention.ms=2592000000"
create_topic "notifications.high" 6 3 "min.insync.replicas=2" "retention.ms=1209600000"
create_topic "notifications.normal" 12 3 "min.insync.replicas=1" "retention.ms=604800000"
create_topic "notifications.low" 6 3 "min.insync.replicas=1" "retention.ms=259200000"
create_topic "notifications.dlq" 3 3 "min.insync.replicas=2" "retention.ms=7776000000"
create_topic "notification_service.public.notification_batches" 3 3 "min.insync.replicas=1" "retention.ms=604800000"
create_topic "connect-configs" 1 3 "cleanup.policy=compact" "retention.ms=-1"
create_topic "connect-offsets" 25 3 "cleanup.policy=compact" "retention.ms=-1"
create_topic "connect-status" 5 3 "cleanup.policy=compact" "retention.ms=-1"

# Debezium коннектор
echo "🔗 Registering Debezium connector..."
CONNECTOR_STATUS=$(curl -s -o /dev/null -w '%{http_code}' http://localhost:8083/connectors/notification-outbox-connector)

if [ "$CONNECTOR_STATUS" != "200" ]; then
    curl -s -X POST http://localhost:8083/connectors \
        -H "Content-Type: application/json" \
        -d @./.docker/kafka-connect/debezium-connector.json
    sleep 5
    echo "  ✅ Connector registered"
else
    echo "  ⏭️  Connector exists"
fi

# Миграции
echo "🗄️  Running migrations..."
docker compose exec -T app php artisan migrate --force
docker compose exec app chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Кэш
echo "⚡ Optimizing cache..."
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache
docker compose exec -T app php artisan event:cache

docker compose exec app chmod -R 777 /var/www/storage/logs
docker compose exec app touch /var/www/storage/logs/laravel.log
docker compose exec app chmod 666 /var/www/storage/logs/laravel.log
docker compose exec app chmod -R 775 /var/www/storage/framework/views
docker compose exec app chmod -R 775 /var/www/storage/framework/cache
docker compose exec app chmod -R 755 /var/www/public/docs
docker compose exec app chmod 644 /var/www/public/docs/index.html

docker compose exec postgres psql -U notify_user -d notifications -c "CREATE DATABASE notifications_test;"

echo ""
echo "✅ Deployment complete!"
echo ""
echo "Services:"
echo "  API:      http://localhost:8080"
echo "  Kafka UI: http://localhost:8081"
echo "  Connect:  http://localhost:8083"