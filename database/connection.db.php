<?php
    declare(strict_types=1);

    function getDatabaseConnection() : PDO {
        $db = new PDO('sqlite:' . __DIR__ . '/../private/db/db.db');
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('PRAGMA foreign_keys = ON;');
        ensureClientsSelectedBadgesColumn($db);
        ensureMembershipClassesRemainingColumn($db);
        ensureClassesDescriptionColumn($db);
        ensureClassesPhotoColumn($db);
        dropOrphanBadgeTables($db);
        normalizeClassSchedules($db);
        return $db;
    }

    function ensureClientsSelectedBadgesColumn(PDO $db): void {
        $stmt = $db->query('PRAGMA table_info(clients)');
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $hasSelectedBadges = false;
        foreach ($columns as $column) {
            if (($column['name'] ?? $column[1] ?? '') === 'selected_badges') {
                $hasSelectedBadges = true;
                break;
            }
        }
        if (!$hasSelectedBadges) {
            $db->exec('ALTER TABLE clients ADD COLUMN selected_badges TEXT');
        }
    }

    function ensureClassesDescriptionColumn(PDO $db): void {
        $cols = array_column($db->query('PRAGMA table_info(classes)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (!in_array('description', $cols, true)) {
            $db->exec('ALTER TABLE classes ADD COLUMN description TEXT NOT NULL DEFAULT \'\'');
        }
    }

    function ensureClassesPhotoColumn(PDO $db): void {
        $cols = array_column($db->query('PRAGMA table_info(classes)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (!in_array('photo', $cols, true)) {
            $db->exec('ALTER TABLE classes ADD COLUMN photo TEXT');
        }
    }

    function normalizeClassSchedules(PDO $db): void {
        // Fix schedules stored in ISO format ("2026-05-30T13:22") from datetime-local inputs.
        // SQLite string comparison treats 'T' > ' ', making these classes appear as future forever.
        $rows = $db->query("SELECT id, schedule FROM classes WHERE schedule LIKE '%T%'")->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) return;
        $stmt = $db->prepare("UPDATE classes SET schedule = ? WHERE id = ?");
        foreach ($rows as $row) {
            $normalized = date('Y-m-d H:i:s', strtotime($row['schedule']));
            $stmt->execute([$normalized, $row['id']]);
        }
    }

    function dropOrphanBadgeTables(PDO $db): void {
        $tables = array_column($db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (in_array('badge_display', $tables, true)) {
            $db->exec('PRAGMA foreign_keys = OFF');
            $db->exec('DROP TABLE IF EXISTS badge_display');
            $db->exec('DROP TABLE IF EXISTS client_badges');
            $db->exec('DROP TABLE IF EXISTS badges');
            $db->exec('DROP TABLE IF EXISTS workout_sessions');
            $db->exec('DROP TABLE IF EXISTS workout_plans');
            $db->exec('PRAGMA foreign_keys = ON');
        }
    }

    function ensureMembershipClassesRemainingColumn(PDO $db): void {
        $stmt = $db->query('PRAGMA table_info(memberships)');
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $hasClassesRemaining = false;
        $hasPilatesClasses = false;
        $hasCyclingClasses = false;

        foreach ($columns as $column) {
            $name = $column['name'] ?? $column[1] ?? '';
            if ($name === 'classes_remaining') {
                $hasClassesRemaining = true;
            } elseif ($name === 'pilates_classes') {
                $hasPilatesClasses = true;
            } elseif ($name === 'cycling_classes') {
                $hasCyclingClasses = true;
            }
        }

        if (!$hasClassesRemaining) {
            $db->exec('ALTER TABLE memberships ADD COLUMN classes_remaining INTEGER NOT NULL DEFAULT 0');
            if ($hasPilatesClasses && $hasCyclingClasses) {
                $db->exec('UPDATE memberships SET classes_remaining = COALESCE(pilates_classes, 0) + COALESCE(cycling_classes, 0)');
            }
        }
    }
?>
