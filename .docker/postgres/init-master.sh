#!/bin/bash
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    -- Создаем пользователя для репликации
    DO \$\$
    BEGIN
        IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'replicator') THEN
            CREATE ROLE replicator WITH REPLICATION LOGIN PASSWORD '${POSTGRES_REPLICATION_PASSWORD}';
        END IF;
    END
    \$\$;

    -- Права на схему (таблицы создадутся позже через миграции)
    GRANT CONNECT ON DATABASE ${POSTGRES_DB} TO replicator;
    GRANT USAGE ON SCHEMA public TO replicator;
    ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT ON TABLES TO replicator;

    -- Создаем публикацию (таблица появится позже)
    DROP PUBLICATION IF EXISTS dbz_publication;
    CREATE PUBLICATION dbz_publication FOR ALL TABLES WITH (publish = 'insert');

EOSQL

echo "Master initialization completed"