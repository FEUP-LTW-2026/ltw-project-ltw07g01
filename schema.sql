CREATE TABLE IF NOT EXISTS gym_locations (
    id      INTEGER PRIMARY KEY AUTOINCREMENT,
    name    TEXT NOT NULL,
    city    TEXT NOT NULL,
    address TEXT
);

CREATE TABLE IF NOT EXISTS class_types (
    id   INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE NOT NULL
);

CREATE TABLE IF NOT EXISTS trainers (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    first_name TEXT NOT NULL,
    last_name  TEXT NOT NULL,
    email      TEXT UNIQUE NOT NULL,
    gym_id     INTEGER,
    FOREIGN KEY (gym_id) REFERENCES gym_locations(id)
);

CREATE TABLE IF NOT EXISTS trainer_locations (
    trainer_id INTEGER,
    gym_id     INTEGER,
    PRIMARY KEY (trainer_id, gym_id),
    FOREIGN KEY (trainer_id) REFERENCES trainers(id),
    FOREIGN KEY (gym_id) REFERENCES gym_locations(id)
);

CREATE TABLE IF NOT EXISTS trainer_specializations (
    trainer_id    INTEGER,
    class_type_id INTEGER,
    PRIMARY KEY (trainer_id, class_type_id),
    FOREIGN KEY (trainer_id) REFERENCES trainers(id),
    FOREIGN KEY (class_type_id) REFERENCES class_types(id)
);

CREATE TABLE IF NOT EXISTS classes (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    class_type_id INTEGER,
    gym_id        INTEGER,
    trainer_id    INTEGER,
    schedule      TIMESTAMP NOT NULL,
    duration_min  INTEGER NOT NULL,
    capacity      INTEGER NOT NULL,

    FOREIGN KEY (class_type_id) REFERENCES class_types(id),
    FOREIGN KEY (gym_id) REFERENCES gym_locations(id),
    FOREIGN KEY (trainer_id) REFERENCES trainers(id)
);

CREATE TABLE IF NOT EXISTS clients (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    username         TEXT UNIQUE NOT NULL,
    first_name       TEXT NOT NULL,
    last_name        TEXT NOT NULL,
    email            TEXT UNIQUE NOT NULL,
    enroll_date      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    gym_token        TEXT UNIQUE,
    token_expire_at  TIMESTAMP,
    preferred_gym_id INTEGER,

    FOREIGN KEY (preferred_gym_id)
        REFERENCES gym_locations(id)
);

CREATE TABLE IF NOT EXISTS memberships (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id           INTEGER NOT NULL,
    is_classes_enabled  INTEGER NOT NULL DEFAULT 0,
    start_date          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_date            TIMESTAMP,

    FOREIGN KEY (client_id)
        REFERENCES clients(id)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS client_classes (
    client_id   INTEGER,
    class_id    INTEGER,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (client_id, class_id),

    FOREIGN KEY (client_id)
        REFERENCES clients(id)
        ON DELETE CASCADE,

    FOREIGN KEY (class_id)
        REFERENCES classes(id)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS gym_visits (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id    INTEGER,
    gym_id       INTEGER,
    checked_in   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    checked_out  TIMESTAMP,

    FOREIGN KEY (client_id)
        REFERENCES clients(id),

    FOREIGN KEY (gym_id)
        REFERENCES gym_locations(id)
);