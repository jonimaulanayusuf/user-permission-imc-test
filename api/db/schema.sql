CREATE EXTENSION IF NOT EXISTS pgcrypto;

DROP TABLE IF EXISTS user_permissions CASCADE;
DROP TABLE IF EXISTS level_permissions CASCADE;
DROP TABLE IF EXISTS users CASCADE;
DROP TABLE IF EXISTS pages CASCADE;
DROP TABLE IF EXISTS levels CASCADE;

CREATE TABLE levels (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description VARCHAR(255),
    is_active SMALLINT NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX ux_levels_name_lower ON levels (lower(name));

CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    level_id BIGINT NOT NULL REFERENCES levels (id) ON DELETE RESTRICT,
    is_active SMALLINT NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX ux_users_username_lower ON users (lower(username));

CREATE UNIQUE INDEX ux_users_email_lower ON users (lower(email));

CREATE INDEX ix_users_level_id ON users (level_id);

CREATE TABLE pages (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    path VARCHAR(150) NOT NULL,
    description VARCHAR(255),
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active SMALLINT NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX ux_pages_path_lower ON pages (lower(path));

CREATE INDEX ix_pages_sort_order ON pages (sort_order);

CREATE TABLE level_permissions (
    level_id BIGINT NOT NULL REFERENCES levels (id) ON DELETE CASCADE,
    page_id BIGINT NOT NULL REFERENCES pages (id) ON DELETE CASCADE,
    PRIMARY KEY (level_id, page_id)
);

CREATE INDEX ix_level_permissions_page_id ON level_permissions (page_id);

CREATE TABLE user_permissions (
    user_id BIGINT NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    page_id BIGINT NOT NULL REFERENCES pages (id) ON DELETE CASCADE,
    effect VARCHAR(5) NOT NULL CHECK (effect IN ('allow', 'deny')),
    PRIMARY KEY (user_id, page_id)
);

CREATE INDEX ix_user_permissions_page_id ON user_permissions (page_id);
