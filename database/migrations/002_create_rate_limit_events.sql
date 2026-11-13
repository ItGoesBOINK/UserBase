-- UserBase migration 002
-- Rolling-window rate-limit event storage
-- Each row represents one request counted against one policy and subject
-- Target: MySQL 8.4+, InnoDB, utf8mb4
--
-- Subject identifiers are stored as HMAC-SHA256 hashes rather than
-- raw IP addresses, user-names, or other identifying values.
--
-- All application time-stamps must use UTC.

CREATE TABLE rate_limit_events (

    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- Identifies the rate-limit policy, e.g. login:ip or login:account
    policy_key VARCHAR(100)
        CHARACTER SET ascii
        COLLATE ascii_bin NOT NULL,

    -- HMAC-SHA256 digest identifying the subject being rate-limited
    subject_hash BINARY(32) NOT NULL,

    -- UTC time at which the request was counted
    occurred_at DATETIME(6) NOT NULL,

    PRIMARY KEY (id),

    -- Supports counting events for a specific policy and subject
    -- within a rolling time window
    KEY idx_rate_limit_policy_subject_time
        (policy_key, subject_hash, occurred_at),

    -- Supports deleting old events during periodic cleanup
    KEY idx_rate_limit_occurred_at (occurred_at)

) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;