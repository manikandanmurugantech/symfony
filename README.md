# Dashboard — DDD + CQRS + Event-Driven Architecture

A high-performance dashboard API built with **Symfony 7**, **PHP 8.2+**, following
**Domain-Driven Design**, **CQRS**, **Event-Driven Architecture**, and an
**optimized read model** capable of serving 100,000+ records with sub-millisecond
cached responses.

---

## Architecture Decisions

### 1. Domain-Driven Design (DDD)

The codebase is organized into three strict layers:

```
src/Dashboard/
├── Domain/          # Pure PHP — zero framework dependency
│   ├── Model/       # Aggregate roots, value objects
│   ├── Event/       # Domain events (raised by aggregates)
│   └── Repository/  # Port interfaces (no implementation here)
├── Application/     # Use cases — orchestrates domain + infrastructure
│   ├── Command/     # Write side (CQRS)
│   ├── Query/       # Read side (CQRS)
│   └── EventHandler/# Async projection handlers
└── Infrastructure/  # Adapters — Doctrine, Redis, Monolog
    ├── Persistence/ # Write repo + read model repo (raw DBAL)
    ├── Cache/       # Redis decorator
    └── Logging/     # Performance measurement
```

**Key rule:** The `Domain` layer has no `use` statements from Symfony or Doctrine.
It is pure PHP and fully unit-testable in isolation.

---

### 2. CQRS (Command Query Responsibility Segregation)

| Side    | Class                      | Transport | Description                            |
|---------|----------------------------|-----------|----------------------------------------|
| Write   | `CreateSiteRecordCommand`  | Sync      | Saves aggregate to `site_records` table |
| Read    | `GetDashboardQuery`        | Sync      | Reads from optimized `dashboard_read_model` |
| Event   | `SiteRecordCreated`        | **Async** | Projects new record to read model      |

Commands and queries travel on **separate Symfony Messenger buses**:
- `messenger.bus.commands` — synchronous, handles commands and queries
- `messenger.bus.events` — consumed by async workers via Redis Streams

This means reads are never blocked by writes and each side can scale independently.

---

### 3. Event-Driven Architecture + Async Processing

```
HTTP Request
    │
    ▼
CreateSiteRecordCommand
    │
    ▼
CreateSiteRecordHandler
    ├─── saves SiteRecord → site_records (write model)
    └─── dispatches SiteRecordCreated → Redis Streams (async)
                                              │
                                    [Worker Process]
                                              │
                                              ▼
                               UpdateDashboardProjectionHandler
                                    ├─── upserts → dashboard_read_model
                                    └─── invalidates Redis cache
```

The worker runs independently:
```bash
php bin/console messenger:consume async --time-limit=3600
```

**Why async?** The HTTP response is returned immediately after saving to the write
model. The expensive read-model projection and cache invalidation happen in the
background, keeping API response times under 50ms even under load.

---

### 4. Optimized Read Model

`dashboard_read_model` is a **denormalized, index-optimized table** separate from
the write model. Design decisions:

- Raw **DBAL queries** (no ORM overhead) for reads
- **No JOINs** — all data pre-projected into a single wide row
- **7 targeted indexes** covering every filter/sort dimension:
  - `occurred_at DESC` (primary sort)
  - `url` with `varchar_pattern_ops` (ILIKE search)
  - `status_code` (exact filter)
  - `country` (exact filter)
  - `(country, occurred_at DESC)` composite (common combined filter)
  - `(status_code, occurred_at DESC)` composite
  - Partial index for 2xx responses only
- Pagination via `LIMIT/OFFSET` with a single `COUNT(*)` query
- Dynamic `WHERE` clause builder with safe parameterized queries

**Benchmark (100k records, no cache):** ~15–40ms  
**Benchmark (100k records, Redis cache hit):** < 1ms

---

### 5. Caching Layer

**Pattern:** Cache-Aside (Lazy Loading) with the **Decorator pattern**

```
GetDashboardQuery
    │
    ▼
CachedDashboardReadModelRepository   ← injected where interface is required
    ├── cache HIT  → return cached DashboardResult (< 1ms)
    └── cache MISS → DoctrineDashboardReadModelRepository → cache → return
```

Cache key encodes every query dimension (`page`, `limit`, `sort`, filters) so
different queries never collide. TTL is configurable via `DASHBOARD_CACHE_TTL` env
variable (default: 300 seconds).

Cache is **invalidated automatically** by `UpdateDashboardProjectionHandler` every
time a new `SiteRecordCreated` event is processed.

---

### 6. Logging & Performance Measurement

`PerformanceLogger` emits **structured JSON logs** to a dedicated `performance` log
channel (separate file/stream from application logs):

```json
{"message":"[QUERY] GetDashboard completed in 12.34ms","context":{"query":"GetDashboard","duration_ms":12.34,"page":1,"total":100000,"cache_hit":false}}
{"message":"[SLOW QUERY] GetDashboard took 245.10ms","context":{"slow":true,...}}
```

Slow queries (> 200ms) are logged as `WARNING` level for monitoring alerting.
All other queries log at `INFO` level.

---

## Setup Instructions

### Prerequisites

- PHP 8.2+
- Composer
- PostgreSQL 14+
- Redis 7+
- (Optional) Docker + Docker Compose

### Option A: Docker (Recommended)

```bash
# 1. Clone and install dependencies
git clone <repo-url> dashboard && cd dashboard
composer install

# 2. Start PostgreSQL and Redis
docker-compose up -d postgres redis

# 3. Create schema
psql "postgresql://dashboard:secret@localhost:5432/dashboard" \
    -f migrations/001_dashboard_schema.sql

# 4. Seed 100,000 records (~30 seconds)
php bin/console dashboard:seed

# 5. Optimize query planner
psql "postgresql://dashboard:secret@localhost:5432/dashboard" \
    -c "VACUUM ANALYZE dashboard_read_model;"

# 6. Start the async event worker
php bin/console messenger:consume async -vv
```

### Option B: Manual

```bash
# Copy and edit environment config
cp .env .env.local
# Edit DATABASE_URL, REDIS_URL, MESSENGER_TRANSPORT_DSN in .env.local

composer install
php bin/console cache:clear

# Create schema
psql "$DATABASE_URL" -f migrations/001_dashboard_schema.sql

# Seed data
php bin/console dashboard:seed --count=100000

# Start worker (separate terminal)
php bin/console messenger:consume async --time-limit=3600 -vv

# Start built-in server
php -S localhost:8000 -t public/
```

### Using Makefile

```bash
make install       # Install dependencies
make docker-up     # Start PostgreSQL + Redis
make setup         # Create schema
make seed          # Seed 100,000 records
make start-worker  # Start async worker
```

---

## How to Run the Project

### Query the Dashboard API

```bash
# Basic paginated list
curl "http://localhost:8000/api/dashboard"
![Sample Output](image-1.png)

# Page 2, 100 records per page
curl "http://localhost:8000/api/dashboard?page=2&limit=100"
![Sample Output](image-2.png)

# Filter by status code and country
curl "http://localhost:8000/api/dashboard?status_code=200&country=IN"
![Sample Output](image-3.png)

# Search by URL pattern
curl "http://localhost:8000/api/dashboard?url=/api"
![Sample Output](image-4.png)

# Sort by response time ascending
curl "http://localhost:8000/api/dashboard?sort_by=response_time_ms&sort_dir=ASC"
![Sample Output](image-5.png)

```

### Sample Response

```json
{
  "data": [
    {
      "id": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
      "url": "/api/users",
      "ip_address": "203.0.113.10",
      "http_method": "GET",
      "status_code": 200,
      "response_time_ms": 85.3,
      "country": "IN",
      "occurred_at": "2024-06-15 14:32:01"
    }
  ],
  "meta": {
    "total": 100000,
    "page": 1,
    "limit": 50,
    "total_pages": 2000,
    "query_ms": 0.34
  }
}
```

### Seed More Data

```bash
php bin/console dashboard:seed --count=500000
php bin/console dashboard:seed --count=100000 --truncate  # wipe and re-seed
```

---

## How to Execute Unit Tests

```bash
# Run all unit tests
./vendor/bin/phpunit

# Run with verbose output
./vendor/bin/phpunit -v

# Run a specific test file
./vendor/bin/phpunit tests/Unit/Dashboard/Domain/Model/SiteRecordTest.php

# Run with code coverage (requires Xdebug or PCOV)
XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-html coverage/html

# Using Makefile
make test
make test-coverage
```

### Test Coverage Summary

| Test Class                              | What It Tests                                      |
|-----------------------------------------|----------------------------------------------------|
| `SiteRecordIdTest`                      | UUID generation, validation, equality, toString    |
| `SiteRecordTest`                        | Aggregate creation, domain event dispatch, guards  |
| `GetDashboardHandlerTest`               | Query handler delegates to repo, passes query intact |
| `CreateSiteRecordHandlerTest`           | Command handler saves record, dispatches event     |
| `CachedDashboardReadModelRepositoryTest`| Cache hit/miss, key isolation, delegation          |

---

## Project Structure

```
dashboard/
├── config/
│   ├── packages/
│   │   ├── cache.yaml       # Redis cache pool config
│   │   ├── doctrine.yaml    # DBAL config
│   │   ├── messenger.yaml   # Command + event buses, async transport
│   │   └── monolog.yaml     # Performance + app log channels
│   ├── routes.yaml
│   └── services.yaml        # DI wiring — interface → implementation bindings
├── migrations/
│   └── 001_dashboard_schema.sql  # Write + read model tables with indexes
├── src/
│   ├── Dashboard/
│   │   ├── Domain/          # Pure PHP domain layer (no framework)
│   │   ├── Application/     # CQRS handlers, DTOs
│   │   └── Infrastructure/  # Doctrine, Redis, logging adapters
│   └── UI/
│       ├── Http/            # JSON API controller
│       └── Console/         # Seed command
├── tests/Unit/              # PHPUnit unit tests
├── .env                     # Environment config
├── docker-compose.yml       # PostgreSQL + Redis + worker
├── Makefile                 # Convenience commands
└── README.md
```
