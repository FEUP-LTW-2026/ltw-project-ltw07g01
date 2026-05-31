/*******************************************************************************
   CUBO GYM Database
   Script: schema.sql
   Description: Creates the gym management database.
   DB Server: SQLite
********************************************************************************/

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
    address TEXT,
    photo   TEXT
);

CREATE TABLE class_types
(
    id   INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE NOT NULL
);

CREATE TABLE archetypes
(INSERT INTO archetypes (name) VALUES ('POWERLIFTER');
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
    photo        TEXT,
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
        ON DELETE CASCADE ON UPDATE NO ACTION,
    FOREIGN KEY (trainer_id)
        REFERENCES trainers(user_id)
        ON DELETE CASCADE ON UPDATE NO ACTION
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

/*******************************************************************************
   Populate Tables
********************************************************************************/

INSERT INTO gym_locations (name, city, address) VALUES ('Antas',      'Porto', 'Rua das Antas 123');
INSERT INTO gym_locations (name, city, address) VALUES ('Matosinhos', 'Porto', 'Rua de Matosinhos 456');
INSERT INTO gym_locations (name, city, address) VALUES ('Braga',      'Braga', 'Rua de Braga 789');

INSERT INTO class_types (name) VALUES ('Cycling');
INSERT INTO class_types (name) VALUES ('Pilates');
INSERT INTO class_types (name) VALUES ('Personal Training');

INSERT INTO archetypes (name) VALUES ('SPINNER');
INSERT INTO archetypes (name) VALUES ('POWERLIFTER');
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
VALUES (2, 'Pilates instructor with 10 years experience.', 'ACE, Mat Pilates');

INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (4, 'mia.fernandes', 'mia@cubogym.com',
        '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26',
        'Mia', 'Fernandes');

INSERT INTO trainers (user_id, bio, certifications)
VALUES (4, 'Certified personal trainer specializing in one-on-one coaching.', 'NASM-CPT, CSCS');

-- New Trainer:
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (5, 'rui.santos', 'rui@cubogym.com', '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Rui', 'Santos');

INSERT INTO trainers (user_id, bio, certifications)
VALUES (5, 'Cycling coach focused on endurance, rhythm, and conditioning.', 'Indoor Cycling, NASM-CPT');

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

-- Equipment — Antas (gym_id=1)
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Bench Press',           1, 'Chest',     1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Chest Press',           1, 'Chest',     0);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Smith Machine',         1, 'Chest',     1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Cable Crossover',       1, 'Chest',     0);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Shoulder Press',        1, 'Shoulders', 1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Lateral Raise Machine', 1, 'Shoulders', 1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Tricep Pushdown',       1, 'Triceps',   1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Dip Machine',           1, 'Triceps',   1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Lat Pulldown',          1, 'Back',      1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Cable Row',             1, 'Back',      1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Seated Row',            1, 'Back',      1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Bicep Curl Machine',    1, 'Biceps',    1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Preacher Curl',         1, 'Biceps',    1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Leg Press',             1, 'Legs',      1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Leg Extension',         1, 'Legs',      0);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Leg Curl',              1, 'Legs',      1);

-- Equipment — Matosinhos (gym_id=2)
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Bench Press',              2, 'Chest',     1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Pec Deck',                 2, 'Chest',     1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Smith Machine',            2, 'Chest',     0);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Shoulder Press',           2, 'Shoulders', 1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Cable Lateral Raise',      2, 'Shoulders', 1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Tricep Pushdown',          2, 'Triceps',   1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Overhead Tricep Extension',2, 'Triceps',   1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Dip Machine',              2, 'Triceps',   0);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Lat Pulldown',             2, 'Back',      1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Cable Row',                2, 'Back',      1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Seated Row',               2, 'Back',      1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Bicep Curl Machine',       2, 'Biceps',    0);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Leg Press',                2, 'Legs',      1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Leg Extension',            2, 'Legs',      1);

-- Equipment — Braga (gym_id=3)
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Bench Press',              3, 'Chest',     1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Chest Press',              3, 'Chest',     1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Smith Machine',            3, 'Chest',     1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Pec Deck',                 3, 'Chest',     1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Shoulder Press',           3, 'Shoulders', 1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Lateral Raise Machine',    3, 'Shoulders', 0);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Tricep Pushdown',          3, 'Triceps',   1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Overhead Tricep Extension',3, 'Triceps',   1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Lat Pulldown',             3, 'Back',      1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Rowing Machine',           3, 'Back',      0);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Bicep Curl Machine',       3, 'Biceps',    1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Preacher Curl',            3, 'Biceps',    1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Leg Press',                3, 'Legs',      1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Leg Extension',            3, 'Legs',      1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Leg Curl',                 3, 'Legs',      1);

-- class_type_id: 1=Cycling 2=Pilates 3=Personal Training
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (1, 1, 5, datetime('now', '+1 day', '+10 hours'), 45, 18);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (2, 1, 2, datetime('now', '+2 days', '+18 hours'), 50, 15);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (3, 1, 4, datetime('now', '+3 days', '+7 hours'), 30, 1);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (1, 2, 5, datetime('now', '+4 days', '+19 hours'), 45, 20);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (2, 1, 2, datetime('now', '+5 days', '+6 hours'), 55, 16);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (3, 3, 4, datetime('now', '+6 days', '+17 hours'), 30, 1);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (1, 1, 5, datetime('now', '+7 days', '+8 hours'), 40, 15);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (2, 2, 2, datetime('now', '+8 days', '+18 hours'), 45, 20);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (3, 1, 4, datetime('now', '+9 days', '+7 hours'), 30, 1);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (1, 2, 5, datetime('now', '+10 days', '+9 hours'), 45, 18);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (2, 1, 2, datetime('now', '+11 days', '+19 hours'), 50, 18);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (3, 3, 4, datetime('now', '+12 days', '+6 hours'), 30, 1);


INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (2, 1);
INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (2, 2);

INSERT INTO trainer_specializations (trainer_id, class_type_id) VALUES (2, 2);

INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (4, 1);
INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (4, 3);

INSERT INTO trainer_specializations (trainer_id, class_type_id) VALUES (4, 3);

INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (5, 1);
INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (5, 2);

INSERT INTO trainer_specializations (trainer_id, class_type_id) VALUES (5, 1);

-- ---- TRAINERS (user_ids 8-27) ----
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (8,  'carlos.mendes',    'carlos.mendes@cubogym.com',    '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Carlos',   'Mendes');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (9,  'sofia.rodrigues',  'sofia.rodrigues@cubogym.com',  '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Sofia',    'Rodrigues');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (10, 'pedro.ferreira',   'pedro.ferreira@cubogym.com',   '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Pedro',    'Ferreira');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (11, 'ines.alves',       'ines.alves@cubogym.com',       '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Inês',     'Alves');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (12, 'miguel.sousa',     'miguel.sousa@cubogym.com',     '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Miguel',   'Sousa');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (13, 'beatriz.pinto',    'beatriz.pinto@cubogym.com',    '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Beatriz',  'Pinto');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (14, 'diogo.lopes',      'diogo.lopes@cubogym.com',      '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Diogo',    'Lopes');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (15, 'catarina.nunes',   'catarina.nunes@cubogym.com',   '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Catarina', 'Nunes');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (16, 'tiago.carvalho',   'tiago.carvalho@cubogym.com',   '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Tiago',    'Carvalho');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (17, 'mariana.ribeiro',  'mariana.ribeiro@cubogym.com',  '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Mariana',  'Ribeiro');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (18, 'andre.cunha',      'andre.cunha@cubogym.com',      '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'André',    'Cunha');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (19, 'filipa.martins',   'filipa.martins@cubogym.com',   '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Filipa',   'Martins');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (20, 'goncalo.vieira',   'goncalo.vieira@cubogym.com',   '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Gonçalo',  'Vieira');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (21, 'claudia.santos',   'claudia.santos@cubogym.com',   '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Cláudia',  'Santos');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (22, 'henrique.fonseca', 'henrique.fonseca@cubogym.com', '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Henrique', 'Fonseca');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (23, 'daniela.teixeira', 'daniela.teixeira@cubogym.com', '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Daniela',  'Teixeira');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (24, 'ricardo.melo',     'ricardo.melo@cubogym.com',     '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Ricardo',  'Melo');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (25, 'joana.correia',    'joana.correia@cubogym.com',    '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Joana',    'Correia');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (26, 'francisco.leal',   'francisco.leal@cubogym.com',   '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Francisco','Leal');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (27, 'patricia.barros',  'patricia.barros@cubogym.com',  '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Patrícia', 'Barros');

INSERT INTO trainers (user_id, bio, certifications) VALUES (8,  'Cycling specialist with 8 years of experience.', 'Indoor Cycling, NASM-CPT');
INSERT INTO trainers (user_id, bio, certifications) VALUES (9,  'Personal trainer focused on strength and conditioning.', 'NASM-CPT, CSCS');
INSERT INTO trainers (user_id, bio, certifications) VALUES (10, 'Pilates instructor and movement specialist.', 'STOTT Pilates, ACE');
INSERT INTO trainers (user_id, bio, certifications) VALUES (11, 'High-energy cycling coach and pilates guide.', 'Indoor Cycling, Mat Pilates');
INSERT INTO trainers (user_id, bio, certifications) VALUES (12, 'Certified personal trainer with nutrition background.', 'NASM-CPT, PN1');
INSERT INTO trainers (user_id, bio, certifications) VALUES (13, 'Pilates and postural correction specialist.', 'STOTT Pilates, CEP');
INSERT INTO trainers (user_id, bio, certifications) VALUES (14, 'Cardio cycling coach and fitness enthusiast.', 'Indoor Cycling, NSCA');
INSERT INTO trainers (user_id, bio, certifications) VALUES (15, 'Functional training and one-on-one coaching.', 'NASM-CPT, FMS');
INSERT INTO trainers (user_id, bio, certifications) VALUES (16, 'Pilates and body mobility expert.', 'Classical Pilates, RYT-200');
INSERT INTO trainers (user_id, bio, certifications) VALUES (17, 'Power cycling and personal training coach.', 'Indoor Cycling, NASM-CPT');
INSERT INTO trainers (user_id, bio, certifications) VALUES (18, 'Personal training and athletic performance.', 'CSCS, NASM-CPT');
INSERT INTO trainers (user_id, bio, certifications) VALUES (19, 'Pilates teacher and core strength specialist.', 'ACE, STOTT Pilates');
INSERT INTO trainers (user_id, bio, certifications) VALUES (20, 'Cycling instructor with background in endurance sports.', 'Indoor Cycling, ACSM');
INSERT INTO trainers (user_id, bio, certifications) VALUES (21, 'Functional movement and personal training expert.', 'NASM-CPT, TRX');
INSERT INTO trainers (user_id, bio, certifications) VALUES (22, 'Pilates and cycling dual specialist.', 'Mat Pilates, Indoor Cycling');
INSERT INTO trainers (user_id, bio, certifications) VALUES (23, 'All-around coach: cycling, pilates, PT.', 'NASM-CPT, STOTT Pilates, Indoor Cycling');
INSERT INTO trainers (user_id, bio, certifications) VALUES (24, 'Injury prevention and personal training coach.', 'NASM-CPT, CEP');
INSERT INTO trainers (user_id, bio, certifications) VALUES (25, 'Pilates practitioner and wellness advocate.', 'STOTT Pilates, ACE');
INSERT INTO trainers (user_id, bio, certifications) VALUES (26, 'Endurance cycling coach and team trainer.', 'Indoor Cycling, NSCA');
INSERT INTO trainers (user_id, bio, certifications) VALUES (27, 'Personal trainer specialising in hypertrophy.', 'NASM-CPT, NSCA-CPT');

INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (8,  1);
INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (9,  1);
INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (10, 1);
INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (11, 1);
INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (12, 1);
INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (12, 2);
INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (13, 2);
INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (14, 2);
INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (15, 2);
INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (16, 2);
INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (17, 2);
INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (17, 3);
INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (18, 3);
INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (19, 3);
INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (20, 3);
INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (21, 3);
INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (22, 3);
INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (22, 1);
INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (23, 1);
INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (23, 2);
INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (24, 1);
INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (24, 3);
INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (25, 2);
INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (25, 3);
INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (26, 1);
INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (26, 2);
INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (26, 3);
INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (27, 1);

INSERT INTO trainer_specializations (trainer_id, class_type_id) VALUES (8,  1);
INSERT INTO trainer_specializations (trainer_id, class_type_id) VALUES (9,  3);
INSERT INTO trainer_specializations (trainer_id, class_type_id) VALUES (10, 2);
INSERT INTO trainer_specializations (trainer_id, class_type_id) VALUES (11, 1);
INSERT INTO trainer_specializations (trainer_id, class_type_id) VALUES (11, 2);
INSERT INTO trainer_specializations (trainer_id, class_type_id) VALUES (12, 3);
INSERT INTO trainer_specializations (trainer_id, class_type_id) VALUES (13, 2);
INSERT INTO trainer_specializations (trainer_id, class_type_id) VALUES (14, 1);
INSERT INTO trainer_specializations (trainer_id, class_type_id) VALUES (15, 3);
INSERT INTO trainer_specializations (trainer_id, class_type_id) VALUES (16, 2);
INSERT INTO trainer_specializations (trainer_id, class_type_id) VALUES (17, 1);
INSERT INTO trainer_specializations (trainer_id, class_type_id) VALUES (17, 3);
INSERT INTO trainer_specializations (trainer_id, class_type_id) VALUES (18, 3);
INSERT INTO trainer_specializations (trainer_id, class_type_id) VALUES (19, 2);
INSERT INTO trainer_specializations (trainer_id, class_type_id) VALUES (20, 1);
INSERT INTO trainer_specializations (trainer_id, class_type_id) VALUES (21, 3);
INSERT INTO trainer_specializations (trainer_id, class_type_id) VALUES (22, 2);
INSERT INTO trainer_specializations (trainer_id, class_type_id) VALUES (22, 1);
INSERT INTO trainer_specializations (trainer_id, class_type_id) VALUES (23, 1);
INSERT INTO trainer_specializations (trainer_id, class_type_id) VALUES (23, 2);
INSERT INTO trainer_specializations (trainer_id, class_type_id) VALUES (23, 3);
INSERT INTO trainer_specializations (trainer_id, class_type_id) VALUES (24, 3);
INSERT INTO trainer_specializations (trainer_id, class_type_id) VALUES (25, 2);
INSERT INTO trainer_specializations (trainer_id, class_type_id) VALUES (26, 1);
INSERT INTO trainer_specializations (trainer_id, class_type_id) VALUES (27, 3);
INSERT INTO trainer_specializations (trainer_id, class_type_id) VALUES (27, 2);

-- ---- MEMBERS (user_ids 28-67) ----
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (28, 'antonio.ferreira',  'antonio.ferreira@email.com',  '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'António',  'Ferreira');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (29, 'susana.lima',       'susana.lima@email.com',       '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Susana',   'Lima');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (30, 'bruno.oliveira',    'bruno.oliveira@email.com',    '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Bruno',    'Oliveira');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (31, 'helena.sousa',      'helena.sousa@email.com',      '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Helena',   'Sousa');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (32, 'eduardo.costa',     'eduardo.costa@email.com',     '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Eduardo',  'Costa');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (33, 'laura.neves',       'laura.neves@email.com',       '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Laura',    'Neves');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (34, 'ruben.silva',       'ruben.silva@email.com',       '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Rúben',    'Silva');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (35, 'vera.santos',       'vera.santos@email.com',       '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Vera',     'Santos');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (36, 'sergio.pereira',    'sergio.pereira@email.com',    '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Sérgio',   'Pereira');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (37, 'teresa.rodrigues',  'teresa.rodrigues@email.com',  '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Teresa',   'Rodrigues');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (38, 'luis.carvalho',     'luis.carvalho@email.com',     '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Luís',     'Carvalho');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (39, 'carla.mendes',      'carla.mendes@email.com',      '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Carla',    'Mendes');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (40, 'manuel.alves',      'manuel.alves@email.com',      '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Manuel',   'Alves');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (41, 'paula.fonseca',     'paula.fonseca@email.com',     '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Paula',    'Fonseca');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (42, 'nuno.martins',      'nuno.martins@email.com',      '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Nuno',     'Martins');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (43, 'francisca.lopes',   'francisca.lopes@email.com',   '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Francisca','Lopes');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (44, 'alvaro.teixeira',   'alvaro.teixeira@email.com',   '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Álvaro',   'Teixeira');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (45, 'raquel.vieira',     'raquel.vieira@email.com',     '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Raquel',   'Vieira');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (46, 'rodrigo.pinto',     'rodrigo.pinto@email.com',     '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Rodrigo',  'Pinto');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (47, 'monica.ribeiro',    'monica.ribeiro@email.com',    '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Mónica',   'Ribeiro');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (48, 'hugo.azevedo',      'hugo.azevedo@email.com',      '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Hugo',     'Azevedo');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (49, 'diana.correia',     'diana.correia@email.com',     '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Diana',    'Correia');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (50, 'leandro.barbosa',   'leandro.barbosa@email.com',   '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Leandro',  'Barbosa');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (51, 'sara.gomes',        'sara.gomes@email.com',        '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Sara',     'Gomes');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (52, 'afonso.dias',       'afonso.dias@email.com',       '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Afonso',   'Dias');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (53, 'angela.matos',      'angela.matos@email.com',      '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Ângela',   'Matos');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (54, 'duarte.sampaio',    'duarte.sampaio@email.com',    '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Duarte',   'Sampaio');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (55, 'marta.cardoso',     'marta.cardoso@email.com',     '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Marta',    'Cardoso');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (56, 'vitor.freitas',     'vitor.freitas@email.com',     '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Vítor',    'Freitas');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (57, 'cristina.moreira',  'cristina.moreira@email.com',  '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Cristina', 'Moreira');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (58, 'simao.henriques',   'simao.henriques@email.com',   '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Simão',    'Henriques');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (59, 'vanda.cunha',       'vanda.cunha@email.com',       '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Vanda',    'Cunha');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (60, 'tomas.guerreiro',   'tomas.guerreiro@email.com',   '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Tomás',    'Guerreiro');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (61, 'elsa.baptista',     'elsa.baptista@email.com',     '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Elsa',     'Baptista');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (62, 'alexandre.reis',    'alexandre.reis@email.com',    '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Alexandre','Reis');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (63, 'barbara.neto',      'barbara.neto@email.com',      '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Bárbara',  'Neto');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (64, 'mateus.braga',      'mateus.braga@email.com',      '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Mateus',   'Braga');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (65, 'isabel.monteiro',   'isabel.monteiro@email.com',   '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Isabel',   'Monteiro');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (66, 'gustavo.rocha',     'gustavo.rocha@email.com',     '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Gustavo',  'Rocha');
INSERT INTO users (id, username, email, password_hash, first_name, last_name)
VALUES (67, 'ana.xavier',        'ana.xavier@email.com',        '$2y$12$RLrV1W7DVRUuO64nGrcxKeM9yl8qIE7V86o3zswBXQyLg96ASGA26', 'Ana',      'Xavier');

INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (28, 1, 1, 75.0, 178);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (29, 1, 2, 60.0, 165);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (30, 2, 3, 82.0, 182);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (31, 2, 4, 58.0, 162);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (32, 3, 5, 80.0, 175);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (33, 3, 6, 55.0, 160);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (34, 1, 2, 90.0, 185);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (35, 1, 4, 62.0, 168);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (36, 2, 1, 78.0, 180);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (37, 2, 3, 57.0, 163);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (38, 3, 5, 85.0, 183);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (39, 3, 6, 61.0, 166);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (40, 1, 2, 95.0, 188);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (41, 1, 4, 54.0, 159);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (42, 2, 1, 76.0, 176);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (43, 2, 3, 59.0, 161);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (44, 3, 5, 88.0, 181);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (45, 3, 6, 56.0, 164);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (46, 1, 2, 83.0, 179);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (47, 1, 4, 63.0, 170);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (48, 2, 1, 77.0, 177);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (49, 2, 3, 60.0, 165);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (50, 3, 5, 91.0, 186);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (51, 3, 6, 53.0, 157);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (52, 1, 2, 87.0, 184);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (53, 1, 4, 65.0, 171);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (54, 2, 1, 74.0, 174);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (55, 2, 3, 58.0, 162);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (56, 3, 5, 86.0, 182);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (57, 3, 6, 61.0, 167);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (58, 1, 2, 79.0, 178);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (59, 1, 4, 64.0, 169);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (60, 2, 1, 93.0, 187);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (61, 2, 3, 55.0, 160);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (62, 3, 5, 84.0, 180);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (63, 3, 6, 59.0, 163);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (64, 1, 2, 89.0, 183);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (65, 1, 4, 62.0, 168);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (66, 2, 1, 81.0, 179);
INSERT INTO clients (user_id, preferred_gym_id, archetype_id, body_weight, height) VALUES (67, 2, 3, 57.0, 161);

INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (28, 1, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (29, 1, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (30, 2, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (31, 2, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (32, 3, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (33, 3, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (34, 1, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (35, 1, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (36, 2, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (37, 2, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (38, 3, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (39, 3, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (40, 1, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (41, 1, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (42, 2, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (43, 2, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (44, 3, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (45, 3, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (46, 1, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (47, 1, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (48, 2, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (49, 2, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (50, 3, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (51, 3, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (52, 1, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (53, 1, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (54, 2, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (55, 2, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (56, 3, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (57, 3, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (58, 1, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (59, 1, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (60, 2, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (61, 2, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (62, 3, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (63, 3, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (64, 1, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (65, 1, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (66, 2, 1);
INSERT INTO client_gyms (client_id, gym_id, is_primary) VALUES (67, 2, 1);

INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (28, 'pro',   datetime('now', '-27 days'), datetime('now', '-27 days', '+1 year'), 10);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (29, 'basic', datetime('now', '-26 days'), datetime('now', '-26 days', '+1 year'),  5);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (30, 'ultra', datetime('now', '-25 days'), datetime('now', '-25 days', '+1 year'), 20);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (31, 'pro',   datetime('now', '-24 days'), datetime('now', '-24 days', '+1 year'), 12);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (32, 'basic', datetime('now', '-23 days'), datetime('now', '-23 days', '+1 year'),  3);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (33, 'ultra', datetime('now', '-22 days'), datetime('now', '-22 days', '+1 year'), 18);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (34, 'pro',   datetime('now', '-21 days'), datetime('now', '-21 days', '+1 year'),  8);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (35, 'basic', datetime('now', '-20 days'), datetime('now', '-20 days', '+1 year'),  4);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (36, 'ultra', datetime('now', '-19 days'), datetime('now', '-19 days', '+1 year'), 25);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (37, 'pro',   datetime('now', '-18 days'), datetime('now', '-18 days', '+1 year'),  7);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (38, 'basic', datetime('now', '-17 days'), datetime('now', '-17 days', '+1 year'),  2);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (39, 'ultra', datetime('now', '-16 days'), datetime('now', '-16 days', '+1 year'), 15);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (40, 'pro',   datetime('now', '-15 days'), datetime('now', '-15 days', '+1 year'),  9);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (41, 'basic', datetime('now', '-14 days'), datetime('now', '-14 days', '+1 year'),  6);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (42, 'ultra', datetime('now', '-13 days'), datetime('now', '-13 days', '+1 year'), 22);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (43, 'pro',   datetime('now', '-12 days'), datetime('now', '-12 days', '+1 year'), 11);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (44, 'basic', datetime('now', '-11 days'), datetime('now', '-11 days', '+1 year'),  1);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (45, 'ultra', datetime('now', '-10 days'), datetime('now', '-10 days', '+1 year'), 30);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (46, 'pro',   datetime('now',  '-9 days'), datetime('now',  '-9 days', '+1 year'), 13);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (47, 'basic', datetime('now',  '-8 days'), datetime('now',  '-8 days', '+1 year'),  5);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (48, 'ultra', datetime('now',  '-7 days'), datetime('now',  '-7 days', '+1 year'), 16);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (49, 'pro',   datetime('now',  '-6 days'), datetime('now',  '-6 days', '+1 year'),  8);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (50, 'basic', datetime('now',  '-5 days'), datetime('now',  '-5 days', '+1 year'),  4);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (51, 'ultra', datetime('now',  '-4 days'), datetime('now',  '-4 days', '+1 year'), 19);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (52, 'pro',   datetime('now',  '-3 days'), datetime('now',  '-3 days', '+1 year'), 10);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (53, 'basic', datetime('now',  '-2 days'), datetime('now',  '-2 days', '+1 year'),  7);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (54, 'ultra', datetime('now',  '-1 day'),  datetime('now',  '-1 day',  '+1 year'), 24);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (55, 'pro',   datetime('now'),            datetime('now',             '+1 year'),  6);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (56, 'basic', datetime('now', '-27 days'), datetime('now', '-27 days', '+1 year'),  2);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (57, 'ultra', datetime('now', '-26 days'), datetime('now', '-26 days', '+1 year'), 17);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (58, 'pro',   datetime('now', '-25 days'), datetime('now', '-25 days', '+1 year'), 14);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (59, 'basic', datetime('now', '-24 days'), datetime('now', '-24 days', '+1 year'),  3);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (60, 'ultra', datetime('now', '-23 days'), datetime('now', '-23 days', '+1 year'), 21);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (61, 'pro',   datetime('now', '-22 days'), datetime('now', '-22 days', '+1 year'),  9);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (62, 'basic', datetime('now', '-21 days'), datetime('now', '-21 days', '+1 year'),  5);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (63, 'ultra', datetime('now', '-20 days'), datetime('now', '-20 days', '+1 year'), 28);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (64, 'pro',   datetime('now', '-19 days'), datetime('now', '-19 days', '+1 year'), 11);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (65, 'basic', datetime('now', '-18 days'), datetime('now', '-18 days', '+1 year'),  6);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (66, 'ultra', datetime('now', '-17 days'), datetime('now', '-17 days', '+1 year'), 23);
INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (67, 'pro',   datetime('now', '-16 days'), datetime('now', '-16 days', '+1 year'), 12);

-- ---- EQUIPMENT (30 pieces across 3 gyms) ----
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Cable Crossover',          1, 'Chest',     1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Incline Bench Press',      1, 'Chest',     1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Leg Curl',                 1, 'Legs',      1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Calf Raise Machine',       1, 'Legs',      0);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Dip Machine',              1, 'Triceps',   1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Glute Kickback Machine',   1, 'Glutes',    1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Back Extension',           1, 'Back',      1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Hack Squat',               1, 'Legs',      0);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Pull-up Station',          1, 'Back',      1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Rowing Machine',           1, 'Back',      1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Incline Bench Press',      2, 'Chest',     1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Chest Fly Machine',        2, 'Chest',     1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Leg Curl',                 2, 'Legs',      1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Calf Raise',               2, 'Legs',      1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Glute Kickback Machine',   2, 'Glutes',    0);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Back Extension',           2, 'Back',      1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Hack Squat',               2, 'Legs',      1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Preacher Curl',            2, 'Biceps',    1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Smith Machine',            2, 'Chest',     1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Ab Crunch Machine',        2, 'Abs',       1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Cable Crossover',          3, 'Chest',     1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Dip Machine',              3, 'Triceps',   1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Glute Kickback Machine',   3, 'Glutes',    1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Leg Curl',                 3, 'Legs',      0);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Hack Squat',               3, 'Legs',      1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Pull-up Station',          3, 'Back',      1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Pec Deck',                 3, 'Chest',     1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Cable Row',                3, 'Back',      0);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Bicep Curl Machine',       3, 'Biceps',    1);
INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES ('Ab Crunch Machine',        3, 'Abs',       1);

-- ---- CLASSES (30 across 10 days, 3 per day) ----
-- class_type_id: 1=Cycling  2=Pilates  3=Personal Training
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (1, 1,  8, datetime('now', 'start of day', '+7 hours'),  45, 15);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (2, 2, 13, datetime('now', 'start of day', '+12 hours'), 50, 14);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (3, 3, 18, datetime('now', 'start of day', '+19 hours'), 30,  1);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (3, 1,  9, datetime('now', 'start of day', '+1 day', '+8 hours'),  45,  1);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (1, 2, 14, datetime('now', 'start of day', '+1 day', '+12 hours'), 45, 18);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (2, 3, 19, datetime('now', 'start of day', '+1 day', '+18 hours'), 55, 12);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (2, 1, 10, datetime('now', 'start of day', '+2 days', '+7 hours'),  50, 15);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (3, 2, 15, datetime('now', 'start of day', '+2 days', '+13 hours'), 30,  1);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (1, 3, 20, datetime('now', 'start of day', '+2 days', '+19 hours'), 45, 20);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (1, 1,  8, datetime('now', 'start of day', '+3 days', '+9 hours'),  45, 18);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (2, 2, 13, datetime('now', 'start of day', '+3 days', '+12 hours'), 50, 15);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (3, 3, 18, datetime('now', 'start of day', '+3 days', '+18 hours'), 30,  1);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (3, 1,  9, datetime('now', 'start of day', '+4 days', '+7 hours'),  45,  1);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (1, 2, 14, datetime('now', 'start of day', '+4 days', '+13 hours'), 45, 20);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (2, 3, 19, datetime('now', 'start of day', '+4 days', '+19 hours'), 50, 12);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (2, 1, 10, datetime('now', 'start of day', '+5 days', '+8 hours'),  50, 15);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (3, 2, 15, datetime('now', 'start of day', '+5 days', '+12 hours'), 30,  1);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (1, 3, 20, datetime('now', 'start of day', '+5 days', '+18 hours'), 45, 18);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (1, 1,  8, datetime('now', 'start of day', '+6 days', '+7 hours'),  45, 15);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (2, 2, 13, datetime('now', 'start of day', '+6 days', '+13 hours'), 55, 12);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (3, 3, 18, datetime('now', 'start of day', '+6 days', '+19 hours'), 30,  1);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (3, 1,  9, datetime('now', 'start of day', '+7 days', '+8 hours'),  45,  1);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (1, 2, 14, datetime('now', 'start of day', '+7 days', '+12 hours'), 45, 20);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (2, 3, 19, datetime('now', 'start of day', '+7 days', '+18 hours'), 50, 15);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (2, 1, 10, datetime('now', 'start of day', '+8 days', '+7 hours'),  50, 15);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (3, 2, 15, datetime('now', 'start of day', '+8 days', '+13 hours'), 30,  1);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (1, 3, 20, datetime('now', 'start of day', '+8 days', '+19 hours'), 45, 18);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (1, 1,  8, datetime('now', 'start of day', '+9 days', '+9 hours'),  45, 15);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (2, 2, 13, datetime('now', 'start of day', '+9 days', '+12 hours'), 50, 12);
INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity)
VALUES (3, 3, 18, datetime('now', 'start of day', '+9 days', '+18 hours'), 30,  1);
