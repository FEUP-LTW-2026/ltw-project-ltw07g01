# CUBO GYM: Fitness Club Web Application

**ltw07g01**: group project for the **Web Languages and Technologies** course (L.EIC019) of the Bachelor in Informatics and Computing Engineering at FEUP (University of Porto), 2025/26.

CUBO GYM is a modern fitness club offering gym, pilates and cycling memberships across multiple locations, with flexible plans ranging from basic to ultra. This web application lets **members** book classes and track their progress, **trainers** manage their schedules and profiles, and **admins** run the whole club.

## Technologies

PHP 8 (server-side rendering, no framework) · SQLite · HTML5 · CSS3 · vanilla JavaScript (AJAX with `fetch`) · JSON REST API

## Architecture

```
pages/        entry points: load data and render a page
templates/    reusable HTML templates (layout, pages, forms)
actions/      form handlers (login, register, edit profile, subscribe…)
api/          JSON REST endpoints (classes, equipment, members, trainers, locations, admins)
database/     data-access classes (User, Trainer, GymClass, Membership, Badge…) and the schema (17 tables)
js/           client-side interactivity: schedule, enrolment, equipment, admin tools
utils/        session management and helpers
```

Pages, templates, actions and data access are kept separate. The front-end updates parts of the page (enrolments, equipment availability, admin search) through the JSON API, without reloading the page.

## Security

- **SQL injection:** every query uses prepared statements.
- **XSS:** all user-provided content is escaped before it is rendered.
- **CSRF:** tokens on every form and AJAX endpoint.
- **Passwords:** stored as hashes and checked with `password_verify`.
- **Access control:** role-based (member / trainer / admin) on pages, actions and API endpoints. The database is kept in `private/`, which isn't served to the browser.

## My contribution (André Baptista, [@andrefb26](https://github.com/andrefb26))

- Member **profile and dashboard** pages, and the **trainer profile** and its editing.
- The **class schedule**, including the trainers' schedules.
- **Admin pages**, with restructuring and member search, and equipment management with JavaScript.
- **Membership** updates and cancellation, and **class reviews**.
- Most of the final bug-fixing and restructuring rounds before submission.

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
- [x] Badge and achievement system based on visits, classes attended, and training minutes.
- [x] Purchase class credit packs (1, 5, or 10 credits) to book group classes.
- [x] Visit history and personal stats on the member dashboard.
- [x] Gym location management for admins (add, edit, upload photos).
- [x] CSRF protection across all forms and AJAX endpoints.
- [x] Real-time equipment availability toggle without page reload.
- [x] Each user has a different type of statistics tracking.
- [x] Warning equipment notifications for admins


## Running

    mkdir -p private/db
    sqlite3 private/db/db.db < database/schema.sql
    php -S localhost:9000


## Credentials

- admin / password123 (admin)
- ana.silva / password123 (trainer)
- joao.costa / password123 (member)
