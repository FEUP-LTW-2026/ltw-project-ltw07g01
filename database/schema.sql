/*******************************************************************************
   CUBO GYM Database
   Script: schema.sql
   Description: Creates the gym management database.
   DB Server: SQLite
********************************************************************************/

DROP TABLE IF EXISTS gym_visits;
DROP TABLE IF EXISTS client_classes;
DROP TABLE IF EXISTS memberships;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS equipment;
DROP TABLE IF EXISTS classes;
DROP TABLE IF EXISTS trainer_specializations;
DROP TABLE IF EXISTS trainer_locations;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS trainers;
DROP TABLE IF EXISTS clients;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS class_types;
DROP TABLE IF EXISTS gym_locations;

/*******************************************************************************
   Create Tables
********************************************************************************/

CREATE TABLE gym_locations
(
    id      INTEGER PRIMARY KEY AUTOINCREMENT,
    name    TEXT NOT NULL,
    city    TEXT NOT NULL,
    address TEXT
);

CREATE TABLE class_types
(
    id   INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE NOT NULL
);

CREATE TABLE users
(
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT UNIQUE NOT NULL,
    email         TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    first_name    TEXT NOT NULL,
    last_name     TEXT NOT NULL,
    profile_photo TEXT,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE clients
(
    user_id          INTEGER PRIMARY KEY,
    gym_token        TEXT UNIQUE,
    token_expire_at  TIMESTAMP,
    preferred_gym_id INTEGER,
    archetype TEXT DEFAULT NULL,
    body_weight REAL,
    height REAL,
          CHECK (archetype IS NULL OR archetype IN (
              'SPINNER',
              'POWERLIFTER',
              'YOGI',
              'PILATES PRACTITIONER'
          )),
    FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE NO ACTION,
    FOREIGN KEY (preferred_gym_id)
        REFERENCES gym_locations(id)
        ON DELETE SET NULL ON UPDATE NO ACTION
);

CREATE TABLE trainers
(
    user_id        INTEGER PRIMARY KEY,
    bio            TEXT,
    certifications TEXT,
    FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE NO ACTION
);

CREATE TABLE admins
(
    user_id INTEGER PRIMARY KEY,
    FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE NO ACTION
);

CREATE TABLE equipment
(
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    name         TEXT NOT NULL,
    gym_id       INTEGER NOT NULL,
    is_available INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (gym_id)
        REFERENCES gym_locations(id)
        ON DELETE CASCADE ON UPDATE NO ACTION
);

CREATE TABLE classes
(
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    class_type_id INTEGER,
    gym_id        INTEGER,
    trainer_id    INTEGER,
    schedule      TIMESTAMP NOT NULL,
    duration_min  INTEGER NOT NULL,
    capacity      INTEGER NOT NULL,
    FOREIGN KEY (class_type_id)
        REFERENCES class_types(id)
        ON DELETE SET NULL ON UPDATE NO ACTION,
    FOREIGN KEY (gym_id)
        REFERENCES gym_locations(id)
        ON DELETE SET NULL ON UPDATE NO ACTION,
    FOREIGN KEY (trainer_id)
        REFERENCES trainers(user_id)
        ON DELETE SET NULL ON UPDATE NO ACTION
);

CREATE TABLE trainer_locations
(
    trainer_id INTEGER,
    gym_id     INTEGER,
    PRIMARY KEY (trainer_id, gym_id),
    FOREIGN KEY (trainer_id)
        REFERENCES trainers(user_id)
        ON DELETE CASCADE ON UPDATE NO ACTION,
    FOREIGN KEY (gym_id)
        REFERENCES gym_locations(id)
        ON DELETE CASCADE ON UPDATE NO ACTION
);

CREATE TABLE trainer_specializations
(
    trainer_id    INTEGER,
    class_type_id INTEGER,
    PRIMARY KEY (trainer_id, class_type_id),
    FOREIGN KEY (trainer_id)
        REFERENCES trainers(user_id)
        ON DELETE CASCADE ON UPDATE NO ACTION,
    FOREIGN KEY (class_type_id)
        REFERENCES class_types(id)
        ON DELETE CASCADE ON UPDATE NO ACTION
);

CREATE TABLE memberships
(
    id                 INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id          INTEGER NOT NULL,
    is_classes_enabled INTEGER NOT NULL DEFAULT 0,
    start_date         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_date           TIMESTAMP,
    FOREIGN KEY (client_id)
        REFERENCES clients(user_id)
        ON DELETE CASCADE ON UPDATE NO ACTION
);

CREATE TABLE client_classes
(
    client_id   INTEGER,
    class_id    INTEGER,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (client_id, class_id),
    FOREIGN KEY (client_id)
        REFERENCES clients(user_id)
        ON DELETE CASCADE ON UPDATE NO ACTION,
    FOREIGN KEY (class_id)
        REFERENCES classes(id)
        ON DELETE CASCADE ON UPDATE NO ACTION
);

CREATE TABLE reviews
(
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id  INTEGER NOT NULL,
    class_id   INTEGER NOT NULL,
    rating     INTEGER NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment    TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id)
        REFERENCES clients(user_id)
        ON DELETE CASCADE ON UPDATE NO ACTION,
    FOREIGN KEY (class_id)
        REFERENCES classes(id)
        ON DELETE CASCADE ON UPDATE NO ACTION
);

CREATE TABLE gym_visits
(
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id   INTEGER,
    gym_id      INTEGER,
    checked_in  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    checked_out TIMESTAMP,
    FOREIGN KEY (client_id)
        REFERENCES clients(user_id)
        ON DELETE SET NULL ON UPDATE NO ACTION,
    FOREIGN KEY (gym_id)
        REFERENCES gym_locations(id)
        ON DELETE SET NULL ON UPDATE NO ACTION
);

/*******************************************************************************
   Create Indexes
********************************************************************************/

CREATE INDEX IFK_clients_preferred_gym ON clients (preferred_gym_id);
CREATE INDEX IFK_equipment_gym         ON equipment (gym_id);
CREATE INDEX IFK_classes_class_type    ON classes (class_type_id);
CREATE INDEX IFK_classes_gym           ON classes (gym_id);
CREATE INDEX IFK_classes_trainer       ON classes (trainer_id);
CREATE INDEX IFK_memberships_client    ON memberships (client_id);
CREATE INDEX IFK_reviews_client        ON reviews (client_id);
CREATE INDEX IFK_reviews_class         ON reviews (class_id);
CREATE INDEX IFK_gym_visits_client     ON gym_visits (client_id);
CREATE INDEX IFK_gym_visits_gym        ON gym_visits (gym_id);

/*******************************************************************************
   Populate Tables
********************************************************************************/

INSERT INTO gym_locations (name, city, address) VALUES ('Antas',      'Porto', 'Rua das Antas 123');
INSERT INTO gym_locations (name, city, address) VALUES ('Matosinhos', 'Porto', 'Rua de Matosinhos 456');
INSERT INTO gym_locations (name, city, address) VALUES ('Braga',      'Braga', 'Rua de Braga 789');

INSERT INTO class_types (name) VALUES ('Yoga');
INSERT INTO class_types (name) VALUES ('Cycling');
INSERT INTO class_types (name) VALUES ('Pilates');
INSERT INTO class_types (name) VALUES ('HIIT');

-- Password for all seed users: 'password123'
INSERT INTO users (username, email, password_hash, first_name, last_name)
VALUES ('admin', 'admin@cubogym.com',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'Admin', 'CUBO');
INSERT INTO admins (user_id) VALUES (1);

INSERT INTO users (username, email, password_hash, first_name, last_name)
VALUES ('ana.silva', 'ana@cubogym.com',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'Ana', 'Silva');
INSERT INTO trainers (user_id, bio, certifications)
VALUES (2, 'Yoga instructor with 10 years experience.', 'ACE, RYT-200');

INSERT INTO users (username, email, password_hash, first_name, last_name)
VALUES ('joao.costa', 'joao@cubogym.com',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'João', 'Costa');
INSERT INTO clients (user_id, preferred_gym_id, archetype, body_weight, height) VALUES (3, 1, 'POWERLIFTER', 70, 175);

-- Membership para joao.costa
INSERT INTO memberships (client_id, is_classes_enabled, start_date)
VALUES (3, 1, '2024-01-15');

-- Visitas ao ginásio
INSERT INTO gym_visits (client_id, gym_id, checked_in, checked_out)
VALUES (3, 1, datetime('now', '-1 day', '+8 hours'), datetime('now', '-1 day', '+9 hours', '+30 minutes'));
INSERT INTO gym_visits (client_id, gym_id, checked_in, checked_out)
VALUES (3, 1, datetime('now', '-3 days', '+7 hours'), datetime('now', '-3 days', '+8 hours', '+45 minutes'));
INSERT INTO gym_visits (client_id, gym_id, checked_in, checked_out)
VALUES (3, 1, datetime('now', '-5 days', '+9 hours'), datetime('now', '-5 days', '+11 hours'));
INSERT INTO gym_visits (client_id, gym_id, checked_in, checked_out)
VALUES (3, 1, datetime('now', '-10 days', '+9 hours'), datetime('now', '-5 days', '+22 hours')); //para testar os badges

   