<?php
declare(strict_types=1);

class Admin
{
    static function getDashboardData(PDO $db): array
    {
        $totalMembers     = (int)$db->query('SELECT COUNT(*) FROM clients')->fetchColumn();
        $totalTrainers    = (int)$db->query('SELECT COUNT(*) FROM trainers')->fetchColumn();
        $totalGyms        = (int)$db->query('SELECT COUNT(*) FROM gym_locations')->fetchColumn();
        $totalEquip       = (int)$db->query('SELECT COUNT(*) FROM equipment')->fetchColumn();
        $unavailableEquip = (int)$db->query('SELECT COUNT(*) FROM equipment WHERE is_available = 0')->fetchColumn();
        $upcomingClassesCount = (int)$db->query("SELECT COUNT(*) FROM classes WHERE schedule > datetime('now')")->fetchColumn();

        $s = $db->prepare("SELECT COUNT(*) FROM gym_visits WHERE checked_in >= datetime('now', '-7 days')");
        $s->execute();
        $visitsThisWeek = (int)$s->fetchColumn();

        $s = $db->prepare("SELECT COUNT(*) FROM memberships WHERE gym_start >= datetime('now', '-30 days')");
        $s->execute();
        $newMemberships = (int)$s->fetchColumn();

        $popularClasses = $db->query(
            'SELECT ct.name, COUNT(cc.client_id) AS enrollments
             FROM client_classes cc
             JOIN classes cl ON cl.id = cc.class_id
             JOIN class_types ct ON ct.id = cl.class_type_id
             GROUP BY ct.name ORDER BY enrollments DESC LIMIT 5'
        )->fetchAll(PDO::FETCH_ASSOC);

        $recentReviews = $db->query(
            'SELECT r.rating, r.comment, r.class_id, u.username, ct.name AS class_name
             FROM reviews r
             JOIN users u ON u.id = r.client_id
             JOIN classes cl ON cl.id = r.class_id
             JOIN class_types ct ON ct.id = cl.class_type_id
             ORDER BY r.created_at DESC LIMIT 8'
        )->fetchAll(PDO::FETCH_ASSOC);

        $gymStats = $db->query(
            'SELECT gl.name, gl.city, COUNT(DISTINCT gv.id) AS visit_count
             FROM gym_locations gl
             LEFT JOIN gym_visits gv ON gv.gym_id = gl.id
             GROUP BY gl.id ORDER BY visit_count DESC'
        )->fetchAll(PDO::FETCH_ASSOC);

        $nextClasses = $db->query(
            "SELECT c.schedule, c.capacity, ct.name AS class_type,
                    gl.name AS gym_name, gl.city AS gym_city,
                    u.first_name AS tr_first, u.last_name AS tr_last,
                    (SELECT COUNT(*) FROM client_classes cc WHERE cc.class_id = c.id) AS enrolled
             FROM classes c
             LEFT JOIN class_types ct ON ct.id = c.class_type_id
             LEFT JOIN gym_locations gl ON gl.id = c.gym_id
             LEFT JOIN users u ON u.id = c.trainer_id
             WHERE c.schedule > datetime('now') ORDER BY c.schedule ASC LIMIT 5"
        )->fetchAll(PDO::FETCH_ASSOC);

        return compact(
            'totalMembers', 'totalTrainers', 'totalGyms', 'totalEquip', 'unavailableEquip',
            'upcomingClassesCount', 'visitsThisWeek', 'newMemberships',
            'popularClasses', 'recentReviews', 'gymStats', 'nextClasses'
        );
    }
}
