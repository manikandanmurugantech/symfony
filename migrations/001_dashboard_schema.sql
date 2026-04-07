-- =============================================================================
-- Migration: Dashboard Schema
-- Creates write model, read model, and all required indexes.
-- Run: psql -U dashboard -d dashboard -f migrations/001_dashboard_schema.sql
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Write Model: site_records (source of truth / aggregate store)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS site_records (
    id               UUID         NOT NULL PRIMARY KEY,
    url              VARCHAR(500) NOT NULL,
    ip_address       VARCHAR(45)  NOT NULL,
    user_agent       TEXT         NOT NULL DEFAULT '',
    http_method      VARCHAR(10)  NOT NULL,
    status_code      SMALLINT     NOT NULL,
    response_time_ms NUMERIC(8,1) NOT NULL,
    country          CHAR(2)      NOT NULL,
    occurred_at      TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_site_records_occurred_at ON site_records (occurred_at DESC);
CREATE INDEX IF NOT EXISTS idx_site_records_status_code ON site_records (status_code);

-- -----------------------------------------------------------------------------
-- Read Model: dashboard_read_model (denormalized, optimized for reads)
-- This table is populated asynchronously via event projection.
-- Never queried with JOINs — designed for fast paginated SELECT.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dashboard_read_model (
    id               UUID         NOT NULL PRIMARY KEY,
    url              VARCHAR(500) NOT NULL,
    ip_address       VARCHAR(45)  NOT NULL,
    http_method      VARCHAR(10)  NOT NULL,
    status_code      SMALLINT     NOT NULL,
    response_time_ms NUMERIC(8,1) NOT NULL,
    country          CHAR(2)      NOT NULL,
    occurred_at      TIMESTAMP    NOT NULL
);

-- Composite index for the most common dashboard sort pattern
CREATE INDEX IF NOT EXISTS idx_drm_occurred_at     ON dashboard_read_model (occurred_at DESC);

-- Filtered queries: by URL (pattern search)
CREATE INDEX IF NOT EXISTS idx_drm_url             ON dashboard_read_model (url varchar_pattern_ops);

-- Filtered queries: by status code
CREATE INDEX IF NOT EXISTS idx_drm_status_code     ON dashboard_read_model (status_code);

-- Filtered queries: by country
CREATE INDEX IF NOT EXISTS idx_drm_country         ON dashboard_read_model (country);

-- Composite covering index for common filter + sort combination
CREATE INDEX IF NOT EXISTS idx_drm_country_time    ON dashboard_read_model (country, occurred_at DESC);
CREATE INDEX IF NOT EXISTS idx_drm_status_time     ON dashboard_read_model (status_code, occurred_at DESC);

-- Count optimization: partial index for 2xx success responses
CREATE INDEX IF NOT EXISTS idx_drm_success         ON dashboard_read_model (occurred_at DESC)
    WHERE status_code BETWEEN 200 AND 299;

-- -----------------------------------------------------------------------------
-- Analyze tables after initial seed for query planner
-- -----------------------------------------------------------------------------
-- Run after seeding: VACUUM ANALYZE dashboard_read_model;
