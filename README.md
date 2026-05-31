# ltw07g01

CUBO GYM is a modern fitness club offering gym, pilates, and cycling memberships across multiple locations, with flexible plans ranging from basic to ultra
---
## Features

**All users:**
- [x] Register a new account.
- [x] Log in and out.
- [x] Edit their profile, including name, username, password, and profile photo.

**Members:**
- [x] Browse the schedule of available fitness classes, filtering by type, trainer, day, or time.
- [x] Enroll in and cancel enrollment from upcoming classes, subject to capacity limits.
- [x] View trainer profiles, including their specializations and the classes they teach.
- [x] Check the current availability of equipment in the main training area.
- [x] Leave ratings and reviews for classes they have attended.

**Trainers:**
- [x] Manage their public profile, including bio, specializations, and certifications.
- [x] View the roster of members enrolled in their classes.
- [x] Track and manage their assigned class schedule.

**Admins:**
- [x] Manage members and trainers (create, update, and deactivate accounts).
- [x] Manage the class catalog (create, edit, and remove classes) and assign trainers to them.
- [x] Manage equipment in the main training area (add, update availability status, and remove items).
- [x] Elevate a user to admin status.
- [x] Oversee and ensure the smooth operation of the entire system.

**Extra:**
- [x] Membership plans with flexible tiers (basic, pro, ultra) and class credits.
- [x] REST API with JSON endpoints for classes, equipment, members, trainers, locations, and admins.
- [x] Personal training booking for members to schedule 1-on-1 sessions with trainers.
- [x] Trainer analytics with attendance stats and ratings summaries per class.
- [x] Admin dashboard with gym-wide metrics and member management.


## Running

    mkdir -p private/db
    sqlite3 private/db/db.db < database/schema.sql
    php -S localhost:9000


## Credentials

- admin / password123 (admin)
- ana.silva / password123 (trainer)
- joao.costa / password123 (member)
