<?php
    declare(strict_types=1);

    function getDatabaseConnection() : PDO {
        $db = new PDO('sqlite:' . __DIR__ . '/database.db');
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('PRAGMA foreign_keys = ON;');
        ensureClientsSelectedBadgesColumn($db);
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
?>