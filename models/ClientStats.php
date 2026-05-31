<?php
declare(strict_types=1);

class ClientStats
{
    static function getTotals(PDO $db, int $clientId): array
    {
        $s = $db->prepare('SELECT COUNT(*) FROM gym_visits WHERE client_id = ?');
        $s->execute([$clientId]);
        $visits = (int)$s->fetchColumn();

        $s = $db->prepare('SELECT COUNT(*) FROM client_classes WHERE client_id = ?');
        $s->execute([$clientId]);
        $classes = (int)$s->fetchColumn();

        $s = $db->prepare(
            'SELECT COALESCE(SUM((julianday(checked_out) - julianday(checked_in)) * 1440), 0)
             FROM gym_visits WHERE client_id = ? AND checked_out IS NOT NULL'
        );
        $s->execute([$clientId]);
        $minutes = (int)round((float)$s->fetchColumn());

        return ['visits' => $visits, 'classes' => $classes, 'minutes' => $minutes];
    }

    static function getRecentVisits(PDO $db, int $clientId, int $limit = 5): array
    {
        $s = $db->prepare(
            'SELECT gv.checked_in, gv.checked_out, gl.name AS gym_name, gl.city AS gym_city
             FROM gym_visits gv
             LEFT JOIN gym_locations gl ON gl.id = gv.gym_id
             WHERE gv.client_id = ?
             ORDER BY gv.checked_in DESC
             LIMIT ' . $limit
        );
        $s->execute([$clientId]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }
}
