CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    email TEXT NOT NULL UNIQUE,
    username TEXT NOT NULL UNIQUE,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    is_verified BOOLEAN NOT NULL DEFAULT false,
    created_at TIMESTAMP NOT NULL,
    last_login_at TIMESTAMP NULL,
    last_seen_at TIMESTAMP NULL
);

CREATE TABLE profiles (
    user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    gender TEXT NULL,
    orientation TEXT NOT NULL DEFAULT 'bisexual',
    bio TEXT NULL,
    location_city TEXT NULL,
    location_lat NUMERIC(9,6) NULL,
    location_lng NUMERIC(9,6) NULL,
    birthdate DATE NULL,
    popularity_score INTEGER NOT NULL DEFAULT 0,
    main_photo_id INTEGER NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);

CREATE TABLE email_verification_tokens (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash TEXT NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL
);

CREATE TABLE password_reset_tokens (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash TEXT NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL
);

CREATE TABLE photos (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    path TEXT NOT NULL,
    is_main BOOLEAN NOT NULL DEFAULT false,
    created_at TIMESTAMP NOT NULL
);

ALTER TABLE profiles
    ADD CONSTRAINT profiles_main_photo_fk
    FOREIGN KEY (main_photo_id) REFERENCES photos(id) ON DELETE SET NULL;

CREATE TABLE tags (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL UNIQUE
);

CREATE TABLE user_tags (
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    tag_id INTEGER NOT NULL REFERENCES tags(id) ON DELETE CASCADE,
    PRIMARY KEY (user_id, tag_id)
);

CREATE TABLE likes (
    id SERIAL PRIMARY KEY,
    liker_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    liked_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TIMESTAMP NOT NULL,
    UNIQUE (liker_id, liked_id)
);

CREATE TABLE matches (
    id SERIAL PRIMARY KEY,
    user_one_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    user_two_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TIMESTAMP NOT NULL,
    UNIQUE (user_one_id, user_two_id)
);

CREATE TABLE messages (
    id SERIAL PRIMARY KEY,
    sender_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    receiver_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    body TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL,
    read_at TIMESTAMP NULL
);

CREATE TABLE notifications (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type TEXT NOT NULL,
    actor_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    metadata JSONB NULL,
    created_at TIMESTAMP NOT NULL,
    read_at TIMESTAMP NULL
);

CREATE TABLE profile_views (
    id SERIAL PRIMARY KEY,
    viewer_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    viewed_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TIMESTAMP NOT NULL
);

CREATE TABLE blocks (
    id SERIAL PRIMARY KEY,
    blocker_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    blocked_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TIMESTAMP NOT NULL,
    UNIQUE (blocker_id, blocked_id)
);

CREATE TABLE reports (
    id SERIAL PRIMARY KEY,
    reporter_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    reported_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    reason TEXT NULL,
    created_at TIMESTAMP NOT NULL
);

CREATE INDEX idx_profiles_location ON profiles(location_lat, location_lng);
CREATE INDEX idx_likes_liked ON likes(liked_id);
CREATE INDEX idx_notifications_user ON notifications(user_id, read_at);
CREATE INDEX idx_messages_receiver ON messages(receiver_id, read_at);
