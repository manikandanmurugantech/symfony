# 🚀 Dashboard System (Symfony 7 | DDD | CQRS | Event-Driven)

## 📌 Overview

This project implements a high-performance **Dashboard API** using **Symfony 7** and **PHP 8.2+**, following modern architectural principles:

* Domain-Driven Design (DDD)
* CQRS (Command Query Responsibility Segregation)
* Event-Driven Architecture
* Asynchronous Processing (Redis + Messenger)
* Optimized Read Models
* Caching Layer

The system efficiently handles **100,000+ records** with fast query performance.

---

## 🏗️ Architecture

### 🔹 DDD Structure

```
src/
 ├── Domain/           # Business logic, entities
 ├── Application/      # Commands, Queries, Handlers
 ├── Infrastructure/   # DB, Redis, external services
 ├── UI/               # Controllers (API layer)
```

---

### 🔹 CQRS Implementation

* **Write Model** → Handles commands and persists data
* **Read Model** → Optimized queries for dashboard
* Separate tables ensure performance and scalability

---

### 🔹 Event-Driven Flow

1. Command executed
2. Domain event dispatched
3. Event handled asynchronously via **Symfony Messenger**
4. Read model updated
5. Cache invalidated

---

### 🔹 Asynchronous Processing

* Redis used as message broker
* Worker consumes events:

```bash
php bin/console messenger:consume async -vv
```

---

## ⚙️ Setup Instructions

### 🔹 1. Start Services

```bash
docker compose up -d
```

---

### 🔹 2. Install Dependencies

```bash
composer install
```

---

### 🔹 3. Configure Environment

Ensure `.env` contains:

```env
DATABASE_URL="postgresql://dashboard:secret@127.0.0.1:5432/dashboard"
REDIS_URL=redis://localhost:6379
MESSENGER_TRANSPORT_DSN=redis://localhost:6379/messages
```

---

### 🔹 4. Run Database Migration

```bash
docker exec -i dashboard-project-postgres-1 \
psql -U dashboard -d dashboard \
< migrations/001_dashboard_schema.sql
```

---

### 🔹 5. Seed Data (100,000 records)

```bash
php bin/console dashboard:seed
```

---

### 🔹 6. Start Application

```bash
php -S localhost:8000 -t public/  
OR
symfony serve
```

---

### 🔹 7. Start Async Worker

```bash
php bin/console messenger:consume async -vv
```

---

## 🌐 API Endpoints

### 🔹 Get Dashboard Data

```http
GET /api/dashboard
```

---

### 🔹 Pagination

```http
GET /api/dashboard?page=2&limit=100
```

---

### 🔹 Filtering

```http
GET /api/dashboard?status_code=200&country=IN
```

---

### 🔹 Sorting

```http
GET /api/dashboard?sort_by=response_time_ms&sort_dir=ASC
```

---

## ⚡ Performance Optimizations

* Indexed database queries
* Separate read model
* Redis-based caching
* Asynchronous event processing

---

## 🧪 Running Tests

```bash
./vendor/bin/phpunit --colors=always
```

✔ 32 tests passing
✔ 60 assertions

---

## 📊 Caching Strategy

* Redis used as cache layer
* Frequently accessed dashboard queries cached
* Cache invalidated via events

---

## 📈 Additional Features

✔ Logging via Monolog
✔ High-performance read model
✔ Scalable architecture

---

## 🧠 Key Design Decisions

* CQRS for separation of concerns
* Event-driven architecture for scalability
* Redis for async + caching
* Read model optimized for heavy queries

---

## ✅ Conclusion

This system demonstrates a scalable and production-ready backend architecture capable of handling large datasets efficiently using modern PHP and Symfony best practices.
