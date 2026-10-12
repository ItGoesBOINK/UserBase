-- UserBase migration 001
-- Initial account, session, and email-verification tables.
-- Target: MySQL 8.4+, InnoDB, utf8mb4.
--
-- Application code must normalize usernames and email addresses
-- consistently before writing their normalized columns.
--
-- All application timestamps must use UTC. Database connections
-- should also use UTC because created_at uses CURRENT_TIMESTAMP.


-- USERS TABLE --
CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    username VARCHAR(64) NOT NULL,
    username_normalized VARCHAR(64)
        CHARACTER SET utf8mb4
        COLLATE utf8mb4_0900_bin NOT NULL,

    email VARCHAR(254) NOT NULL,
    email_normalized VARCHAR(254)
        CHARACTER SET utf8mb4
        COLLATE utf8mb4_0900_bin NOT NULL,

    password_hash VARCHAR(255) NOT NULL,

    email_verified_at DATETIME(6) NULL,

    created_at DATETIME(6) NOT NULL
        DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),

    updated_at DATETIME(6) NOT NULL
        DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),

    PRIMARY KEY (id),

    UNIQUE KEY uq_users_username_normalized (username_normalized),
    UNIQUE KEY uq_users_email_normalized (email_normalized);

) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utfmb4_0900_ai_ci;



-- SESSIONS TABLE --
CREATE TABLE auth_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    user_id BIGINT UNSIGNED NOT NULL,

    -- SHA-256 hash of the random session token
    token_hash BINARY(32) NOT NULL,

    created_at DATETIME(6) NOT NULL
        DEFAULT CURRENT_TIMESTAMP(6),

    expires_at DATETIME(6) NOT NULL,
    revoked_at DATETIME(6) NULL,
    last_used_at DATETIME(6) NULL,

    PRIMARY KEY (id),

    UNIQUE KEY uq_auth_sessions_token_hash (token_hash),
    KEY idx_auth_sessions_user_expiry (user_id, expires_at),
    CONSTRAINT fk_auth_sessions_user
        FOREIGN KEY (user_id)
        REFERENCES users (id)
        ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci


-- EMAIL VERIFICATION TABLE --
CREATE TABLE email_verification_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    user_id BIGINT UNSIGNED NOT NULL,

    -- SHA-256 hash of the random verification token
    token_hash BINARY(32) NOT NULL,

    created_at DATETIME(6) NOT NULL
        DEFAULT CURRENT_TIMESTAMP(6),

    expires_at DATETIME(6) NOT NULL,
    used_at DATETIME(6) NULL,

    -- Set when a newer token replaces this token
    invalidated_at DATETIME(6) NULL,

    PRIMARY KEY (id),

    UNIQUE KEY uq_email_verification_tokens_token_hash (token_hash),
    KEY idx_email_verification_tokens_user (user_id),
    KEY idx_email_verification_tokens_expiry (expires_at),
    CONSTRAINT fk_email_verification_tokens_user
        FOREIGN KEY (user_id)
        REFERENCES users (id)
        ON DELETE CASCASE

) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci