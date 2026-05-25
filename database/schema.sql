/*******************************************************************************
   CUBO GYM Database
   Script: schema.sql
   Description: Creates the gym management database.
   DB Server: SQLite
********************************************************************************/

DROP TABLE IF EXISTS workout_sessions;
DROP TABLE IF EXISTS workout_plans;
DROP TABLE IF EXISTS gym_visits;
DROP TABLE IF EXISTS client_classes;
DROP TABLE IF EXISTS client_gyms;
DROP TABLE IF EXISTS memberships;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS equipment;
DROP TABLE IF EXISTS classes;
DROP TABLE IF EXISTS trainer_specializations;
DROP TABLE IF EXISTS trainer_locations;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS trainers;
DROP TABLE IF EXISTS clients;
DROP TABLE IF EXISTS archetypes;
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

CREATE TABLE archetypes
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
    bio           TEXT,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE clients
(
    user_id          INTEGER PRIMARY KEY,
    gym_token        TEXT UNIQUE,
    token_expire_at  TIMESTAMP,
    preferred_gym_id INTEGER,
    archetype_id     INTEGER,
    body_weight      REAL,
    height           REAL,
    selected_badges  TEXT,
    FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE NO ACTION,
    FOREIGN KEY (preferred_gym_id)
        REFERENCES gym_locations(id)
        ON DELETE SET NULL ON UPDATE NO ACTION,
    FOREIGN KEY (archetype_id)
        REFERENCES archetypes(id)
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
    body_part    TEXT NOT NULL,
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
    client_id        INTEGER PRIMARY KEY,
    gym_plan         TEXT CHECK(gym_plan IN ('basic', 'pro', 'ultra')),
    gym_start        TIMESTAMP,
    gym_end          TIMESTAMP,
    classes_remaining INTEGER NOT NULL DEFAULT 0,
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

CREATE TABLE client_gyms
(
    client_id  INTEGER,
    gym_id     INTEGER,
    is_primary INTEGER NOT NULL DEFAULT 0,
    joined_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (client_id, gym_id),
    FOREIGN KEY (client_id)
        REFERENCES clients(user_id)
        ON DELETE CASCADE ON UPDATE NO ACTION,
    FOREIGN KEY (gym_id)
        REFERENCES gym_locations(id)
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

CREATE TABLE workout_plans
(
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id  INTEGER NOT NULL,
    name       TEXT    NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id)
        REFERENCES clients(user_id)
        ON DELETE CASCADE ON UPDATE NO ACTION
);

CREATE TABLE workout_sessions
(
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    plan_id  INTEGER NOT NULL,
    type     TEXT    NOT NULL CHECK(type IN ('pilates', 'cycling', 'running')),
    date     DATE    NOT NULL,
    duration INTEGER,
    notes    TEXT,
    FOREIGN KEY (plan_id)
        REFERENCES workout_plans(id)
        ON DELETE CASCADE ON UPDATE NO ACTION
);

/*******************************************************************************
   Create Indexes
********************************************************************************/

CREATE INDEX IFK_clients_preferred_gym ON clients (preferred_gym_id);
CREATE INDEX IFK_clients_archetype     ON clients (archetype_id);
CREATE INDEX IFK_equipment_gym         ON equipment (gym_id);
CREATE INDEX IFK_classes_class_type    ON classes (class_type_id);
CREATE INDEX IFK_classes_gym           ON classes (gym_id);
CREATE INDEX IFK_classes_trainer       ON classes (trainer_id);
CREATE INDEX IFK_reviews_client        ON reviews (client_id);
CREATE INDEX IFK_reviews_class         ON reviews (class_id);
CREATE INDEX IFK_gym_visits_client     ON gym_visits (client_id);
CREATE INDEX IFK_gym_visits_gym        ON gym_visits (gym_id);
CREATE INDEX IFK_workout_plans_client  ON workout_plans (client_id);
CREATE INDEX IFK_workout_sessions_plan ON workout_sessions (plan_id);

/*******************************************************************************
   Populate Tables
********************************************************************************/

INSERT INTO gym_locations (name, city, address) VALUES ('Antas',      'Porto', 'Rua das Antas 123');
INSERT INTO gym_locations (name, city, address) VALUES ('Matosinhos', 'Porto', 'Rua de Matosinhos 456');
INSERT INTO gym_locations (name, city, address) VALUES ('Braga',      'Braga', 'Rua de Braga 789');

INSERT INTO class_types (name) VALUES ('Yoga');
INSERT INTO class_types (name) VALUES ('Cycling');
INSERT INTO class_types (name) VALUES ('Pilates');
INSERT INTO class_types (name) VALUES ('Personal Training');
INSERT INTO class_types (name) VALUES ('Strength & Conditioning');

INSERT INTO archetypes (name) VALUES ('SPINNER');
INSERT INTO archetypes (name) VALUES ('POWERLIFTER');
INSERT INTO archetypes (name) VALUES ('YOGI');
INSERT INTO archetypes (name) VALUES ('PILATES PRACTITIONER');
INSERT INTO archetypes (name) VALUES ('RUNNER');
INSERT INTO archetypes (name) VALUES ('CROSSFITTER');

-- Password for all seed users: 'password123'
INSERT INTO users (username, email, password_hash, first_name, last_name)
VALUES ('admin', 'admin@cubogym.com',
'$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26',
'Admin', 'CUBO');
INSERT INTO admins (user_id) VALUES (1);

INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (2, 'ana.silva', 'ana@cubogym.com',
        '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26',
        'Ana', 'Silva');

INSERT INTO trainers (user_id, bio, certifications)
VALUES (2, 'Yoga instructor with 10 years experience.', 'ACE, RYT-200');

INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (4, 'maria.fernandes', 'maria@cubogym.com',
        '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26',
        'Maria', 'Fernandes');

INSERT INTO trainers (user_id, bio, certifications)
VALUES (4, 'Certified personal trainer specializing in strength training.', 'NASM-CPT, CSCS');

INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (3, 'joao.costa', 'joao@cubogym.com',
        '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26',
        'João', 'Costa');
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (3, 1, 2, 70, 175);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (3, 1, 1);

-- Membership para joao.costa
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining)
VALUES (3, 'pro', '2024-01-15', '2025-01-15', 8);

-- Visitas ao ginásio
INSERT INTO gym_visits (client_id, gym_id, checked_in, checked_out)
VALUES (3, 1, datetime('now', '-1 day', '+8 hours'), datetime('now', '-1 day', '+9 hours', '+30 minutes'));
INSERT INTO gym_visits (client_id, gym_id, checked_in, checked_out)
VALUES (3, 1, datetime('now', '-3 days', '+7 hours'), datetime('now', '-3 days', '+8 hours', '+45 minutes'));
INSERT INTO gym_visits (client_id, gym_id, checked_in, checked_out)
VALUES (3, 1, datetime('now', '-5 days', '+9 hours'), datetime('now', '-5 days', '+11 hours'));
INSERT INTO gym_visits (client_id, gym_id, checked_in, checked_out)
VALUES (3, 1, datetime('now', '-10 days', '+9 hours'), datetime('now', '-5 days', '+22 hours')); 
INSERT INTO gym_visits (client_id, gym_id, checked_in, checked_out)
VALUES (3, 1, datetime('now', '-20 days', '+9 hours'), datetime('now', '-19 days', '+22 hours')); 
INSERT INTO gym_visits (client_id, gym_id, checked_in, checked_out)
VALUES (3, 1, datetime('now', '-18 days', '+9 hours'), datetime('now', '-17 days', '+22 hours')); 
INSERT INTO gym_visits (client_id, gym_id, checked_in, checked_out)
VALUES (3, 1, datetime('now', '-16 days', '+9 hours'), datetime('now', '-15 days', '+22 hours')); 
INSERT INTO gym_visits (client_id, gym_id, checked_in, checked_out)
VALUES (3, 1, datetime('now', '-10 days', '+9 hours'), datetime('now', '-5 days', '+22 hours')); 
INSERT INTO gym_visits (client_id, gym_id, checked_in, checked_out)
VALUES (3, 1, datetime('now', '-10 days', '+9 hours'), datetime('now', '-5 days', '+22 hours')); 
INSERT INTO gym_visits (client_id, gym_id, checked_in, checked_out)
VALUES (3, 1, datetime('now', '-10 days', '+9 hours'), datetime('now', '-5 days', '+22 hours')); 
INSERT INTO gym_visits (client_id, gym_id, checked_in, checked_out)
VALUES (3, 1, datetime('now', '-10 days', '+9 hours'), datetime('now', '-5 days', '+22 hours')); 
INSERT INTO gym_visits (client_id, gym_id, checked_in, checked_out)
VALUES (3, 1, datetime('now', '-10 days', '+9 hours'), datetime('now', '-5 days', '+22 hours')); 
INSERT INTO gym_visits (client_id, gym_id, checked_in, checked_out)
VALUES (3, 1, datetime('now', '-18 days', '+9 hours'), datetime('now', '-17 days', '+22 hours')); 
INSERT INTO gym_visits (client_id, gym_id, checked_in, checked_out)
VALUES (3, 1, datetime('now', '-16 days', '+9 hours'), datetime('now', '-15 days', '+22 hours')); 
INSERT INTO gym_visits (client_id, gym_id, checked_in, checked_out)
VALUES (3, 1, datetime('now', '-10 days', '+9 hours'), datetime('now', '-5 days', '+22 hours')); 
INSERT INTO gym_visits (client_id, gym_id, checked_in, checked_out)
VALUES (3, 1, datetime('now', '-10 days', '+9 hours'), datetime('now', '-5 days', '+22 hours')); 
INSERT INTO gym_visits (client_id, gym_id, checked_in, checked_out)
VALUES (3, 1, datetime('now', '-10 days', '+9 hours'), datetime('now', '-5 days', '+22 hours')); 
INSERT INTO gym_visits (client_id, gym_id, checked_in, checked_out)
VALUES (3, 1, datetime('now', '-10 days', '+9 hours'), datetime('now', '-5 days', '+22 hours')); 
INSERT INTO gym_visits (client_id, gym_id, checked_in, checked_out)
VALUES (3, 1, datetime('now', '-10 days', '+9 hours'), datetime('now', '-5 days', '+22 hours')); 

-- Equipment status
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Bench Press', 1, 'Chest', 1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Chest Press', 1, 'Chest', 0);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Shoulder Press', 1, 'Shoulders', 1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Tricep Pushdown', 1, 'Triceps', 1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Bicep Curl Machine', 2, 'Biceps', 0);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Leg Press', 2, 'Legs', 1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Leg Extension', 2, 'Legs', 1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Lat Pulldown', 3, 'Back', 1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Rowing Machine', 3, 'Back', 0);

-- class_type_id: 1=Yoga 2=Cycling 3=Pilates 4=Personal Training 5=Strength & Conditioning
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (1, 1, 4, datetime('now', '+1 day', '+10 hours'), 60, 20);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (2, 1, 4, datetime('now', '+2 days', '+18 hours'), 45, 15);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (3, 1, 2, datetime('now', '+3 days', '+7 hours'), 30, 10);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (4, 1, 2, datetime('now', '+4 days', '+19 hours'), 50, 25);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (5, 1, 4, datetime('now', '+5 days', '+6 hours'), 40, 20);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (1, 1, 2, datetime('now', '+6 days', '+17 hours'), 55, 30);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (2, 1, 2, datetime('now', '+7 days', '+8 hours'), 35, 15);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (3, 1, 2, datetime('now', '+8 days', '+18 hours'), 45, 20);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (5, 1, 2, datetime('now', '+9 days', '+7 hours'), 60, 25);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (1, 1, 2, datetime('now', '+10 days', '+9 hours'), 30, 10);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (2, 1, 3, datetime('now', '+11 days', '+19 hours'), 50, 20);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (3, 1, 4, datetime('now', '+12 days', '+6 hours'), 40, 15);
