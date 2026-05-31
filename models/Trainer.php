<?php
declare(strict_types=1);

class Trainer
{
    static function getClassRoster(PDO $db, int $classId, int $trainerId): ?array
    {
        $s = $db->prepare(
            'SELECT ct.name AS class_name, cl.schedule, cl.capacity,
                    (SELECT COUNT(*) FROM client_classes WHERE class_id = cl.id) AS enrolled
             FROM classes cl
             JOIN class_types ct ON ct.id = cl.class_type_id
             WHERE cl.id = ? AND cl.trainer_id = ?'
        );
        $s->execute([$classId, $trainerId]);
        $classInfo = $s->fetch();
        if (!$classInfo) return null;

        $s = $db->prepare(
            'SELECT u.id, u.first_name, u.last_name, u.username, u.profile_photo
             FROM client_classes cc
             JOIN users u ON u.id = cc.client_id
             WHERE cc.class_id = ?
             ORDER BY u.first_name, u.last_name'
        );
        $s->execute([$classId]);
        return ['class' => $classInfo, 'students' => $s->fetchAll(PDO::FETCH_ASSOC)];
    }

    static function getSpecializations(PDO $db, int $trainerId): array
    {
        $s = $db->prepare(
            "SELECT ct.id, ct.name
             FROM trainer_specializations ts
             JOIN class_types ct ON ct.id = ts.class_type_id
             WHERE ts.trainer_id = ?
               AND ct.name IN ('Pilates', 'Cycling', 'Personal Training')
             ORDER BY ct.name"
        );
        $s->execute([$trainerId]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    static function getSpecializationIds(PDO $db, int $trainerId): array
    {
        $s = $db->prepare('SELECT class_type_id FROM trainer_specializations WHERE trainer_id = ?');
        $s->execute([$trainerId]);
        return $s->fetchAll(PDO::FETCH_COLUMN);
    }

    static function getDashboardData(PDO $db, int $trainerId): array
    {
        $s = $db->prepare(
            'SELECT COUNT(DISTINCT cc.client_id)
             FROM client_classes cc
             JOIN classes cl ON cl.id = cc.class_id
             WHERE cl.trainer_id = ?'
        );
        $s->execute([$trainerId]);
        $totalStudents = (int)$s->fetchColumn();

        $s = $db->prepare("SELECT COUNT(*) FROM classes WHERE trainer_id = ? AND schedule < datetime('now')");
        $s->execute([$trainerId]);
        $classesTaught = (int)$s->fetchColumn();

        $s = $db->prepare(
            'SELECT ROUND(AVG(r.rating), 1)
             FROM reviews r
             JOIN classes cl ON cl.id = r.class_id
             WHERE cl.trainer_id = ?'
        );
        $s->execute([$trainerId]);
        $avgRating = $s->fetchColumn() ?? '—';

        $s = $db->prepare(
            'SELECT DISTINCT u.id AS user_id, u.first_name, u.last_name, u.username, u.profile_photo,
                    cc.enrolled_at
             FROM client_classes cc
             JOIN clients c ON c.user_id = cc.client_id
             JOIN users u ON u.id = c.user_id
             JOIN classes cl ON cl.id = cc.class_id
             WHERE cl.trainer_id = ?
             ORDER BY cc.enrolled_at DESC
             LIMIT 6'
        );
        $s->execute([$trainerId]);
        $recentStudents = $s->fetchAll(PDO::FETCH_ASSOC);

        $s = $db->prepare(
            'SELECT ct.name FROM trainer_specializations ts
             JOIN class_types ct ON ct.id = ts.class_type_id
             WHERE ts.trainer_id = ?'
        );
        $s->execute([$trainerId]);
        $specializations = $s->fetchAll(PDO::FETCH_COLUMN);

        $s = $db->prepare(
            'SELECT r.rating, r.comment, r.class_id, u.username, ct.name AS class_name
             FROM reviews r
             JOIN users u ON u.id = r.client_id
             JOIN classes cl ON cl.id = r.class_id
             JOIN class_types ct ON ct.id = cl.class_type_id
             WHERE cl.trainer_id = ?
             ORDER BY r.created_at DESC LIMIT 8'
        );
        $s->execute([$trainerId]);
        $recentReviews = $s->fetchAll(PDO::FETCH_ASSOC);

        return compact('totalStudents', 'classesTaught', 'avgRating', 'recentStudents', 'specializations', 'recentReviews');
    }
}
