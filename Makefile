.PHONY: help install setup seed test test-coverage lint start-worker

help:
	@echo ""
	@echo "  Dashboard Project — Available Commands"
	@echo "  ────────────────────────────────────────────────"
	@echo "  make install       Install PHP dependencies"
	@echo "  make setup         Run DB migrations (requires PostgreSQL + Redis running)"
	@echo "  make seed          Seed 100,000 records into the dashboard"
	@echo "  make seed-more     Seed 500,000 records"
	@echo "  make test          Run all unit tests"
	@echo "  make test-coverage Run tests with HTML coverage report"
	@echo "  make lint          Run PHPStan static analysis"
	@echo "  make start-worker  Start async message worker"
	@echo "  make docker-up     Start Docker services (PostgreSQL + Redis)"
	@echo "  make docker-down   Stop Docker services"
	@echo ""

install:
	composer install --no-interaction --prefer-dist

docker-up:
	docker-compose up -d postgres redis
	@echo "Waiting for PostgreSQL to be ready..."
	@sleep 3

docker-down:
	docker-compose down

setup: docker-up
	php bin/console cache:clear
	psql "$(DATABASE_URL)" -f migrations/001_dashboard_schema.sql
	@echo "Schema created. Run 'make seed' to populate data."

seed:
	php bin/console dashboard:seed --count=100000
	@echo "Run: VACUUM ANALYZE dashboard_read_model; in psql for best performance."

seed-more:
	php bin/console dashboard:seed --count=500000

seed-truncate:
	php bin/console dashboard:seed --count=100000 --truncate

start-worker:
	php bin/console messenger:consume async --time-limit=3600 -vv

test:
	./vendor/bin/phpunit --colors=always

test-coverage:
	XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-html coverage/html --colors=always
	@echo "Coverage report: coverage/html/index.html"

lint:
	./vendor/bin/phpstan analyse src tests --level=8
