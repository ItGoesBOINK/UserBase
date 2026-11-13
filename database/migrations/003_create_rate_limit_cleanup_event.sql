-- UserBase migration 003
-- Periodic cleanup of expired rate-limit events

CREATE EVENT IF NOT EXISTS cleanup_rate_limit_events
ON SCHEDULE EVERY 1 HOUR
DO
    DELETE FROM rate_limit_events
    WHERE occurred_at < UTC_TIMESTAMP(6) - INTERVAL 2 DAY;