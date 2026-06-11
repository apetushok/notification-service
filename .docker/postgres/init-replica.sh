#!/bin/sh
set -e

echo "Waiting for master..."
until PGPASSWORD=${DB_REPLICATION_PASSWORD} pg_isready -h postgres -U replicator -d notifications; do sleep 5; done

echo "Creating base backup..."
rm -rf /var/lib/postgresql/data/*
PGPASSWORD=${DB_REPLICATION_PASSWORD} pg_basebackup \
    -h postgres -D /var/lib/postgresql/data -U replicator \
    -P -R -S replica_1 -C --slot=replica_1 --wal-method=stream

# Добавить параметры для реплики
echo "primary_conninfo = 'host=postgres port=5432 user=replicator password=${DB_REPLICATION_PASSWORD} application_name=replica_1'" >> /var/lib/postgresql/data/postgresql.auto.conf
echo "primary_slot_name = 'replica_1'" >> /var/lib/postgresql/data/postgresql.auto.conf
echo "hot_standby = on" >> /var/lib/postgresql/data/postgresql.auto.conf
echo "max_connections = 200" >> /var/lib/postgresql/data/postgresql.auto.conf
echo "shared_buffers = 256MB" >> /var/lib/postgresql/data/postgresql.auto.conf

echo "Done"