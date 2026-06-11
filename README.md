# Notification Service

Микросервис массовой рассылки SMS и Email уведомлений на Laravel с Apache Kafka, PostgreSQL и Redis.


## Быстрый старт

```bash
# 1. Клонировать
git clone https://github.com/apetushok/notification-service.git
cd notification-service

# 4. Запустить
chmod +x run.sh
./run.sh
```

## API

| Метод | URL | Описание |
|-------|-----|----------|
| POST | `/api/v1/notifications/send` | Отправить уведомления |
| GET | `/api/v1/notifications/recipient/{r}` | История получателя |
| GET | `/api/v1/notifications/{id}` | Детали уведомления |

**Документация:** `http://localhost:8080/docs/index.html`

## Тестирование

```bash
# Интеграционные тесты
docker compose exec app php artisan test tests/Feature/ --env=testing
```

## Сервисы

| Сервис | URL |
|--------|-----|
| API | `http://localhost:8080` |
| Swagger | `http://localhost:8080/docs/index.html` |
| Kafka UI | `http://localhost:8081` |
| Debezium UI | `http://localhost:8084` |


Кратко, но все ключевые моменты покрыты.



## Архитектура


### Диаграмма последовательности

```
Клиент          API          PostgreSQL    Debezium     Kafka        Consumer      Provider
│              │               │            │           │             │             │
├─POST /send──▶│               │            │           │             │             │
│              ├──INSERT──────▶│            │           │             │             │
│              │               │ (outbox)   │           │             │             │
│◀──202────────┤               │            │           │             │             │
│              │               ├─CDC───────▶│           │             │             │
│              │               │            ├─publish──▶│             │             │
│              │               │            │           ├─consume────▶│             │
│              │               │            │           │             ├─send──────▶│
│              │               │            │           │             │◀─result────┤
│              │               │            │           │             │             │
│              │               │            │           │             ├─UPDATE────▶│
│              │               │◀────────────────────────────────────┤  (status)   │
│              │               │            │           │             │             │
│              │               │            │           │             │             │
├─GET /recipient/{r}──────────▶│            │           │             │             │
│◀─200────────────────────────┤            │           │             │             │
│              │               │            │           │             │             │
```

### Статусная модель

```
queued ──▶ sending ──▶ sent ──▶ delivered
│                     │
└──▶ failed ──▶ discarded (после N попыток)
```

### Приоритетность (как достигается)

```
┌─────────────────────────────────────────────────────┐
│  Физически разные топики Kafka                      │
│                                                     │
│  transactional │ high │ normal │ low                │
│       ▲            ▲       ▲       ▲                │
│       │            │       │       │                │
│   Consumer     Consumer Consumer Consumer           │
│   (4 шт)       (3 шт)   (8 шт)   (2 шт)            │
│                                                     │
│  Каждый consumer читает ТОЛЬКО свой топик           │
│  Transactional не ждёт normal — очереди разделены   │
└─────────────────────────────────────────────────────┘
```

## Ключевые особенности

**Приоритетность доставки:**
- 4 отдельных топика Kafka: `transactional`, `high`, `normal`, `low`
- Разные consumer groups с разным числом воркеров
- Транзакционные сообщения не ждут маркетинговые — физически разные очереди

**Гарантия доставки (at-least-once):**
- Outbox pattern через Debezium CDC — сообщение не потеряется даже при сбое
- Fallback: если Debezium недоступен, pending записи обрабатываются через Redis Queue
- Circuit Breaker для провайдеров — временные сбои не забивают очередь
- Retry с экспоненциальной задержкой для каждого провайдера

**Идемпотентность (exactly-once):**
- Двухуровневая проверка: Redis (быстрый) → PostgreSQL (надежный)
- Ключ идемпотентности через заголовок `X-Idempotency-Key`
- Повторный запрос возвращает 200 с тем же batch_id

**Статусы уведомлений:**
`queued → sending → sent → delivered` (или `failed → discarded`)
