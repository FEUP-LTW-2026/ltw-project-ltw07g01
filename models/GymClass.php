<?php
declare(strict_types=1);

class GymClass
{
    const COLORS = [
        'Yoga'                    => '#a78bfa',
        'Cycling'                 => '#60a5fa',
        'Pilates'                 => '#f472b6',
        'HIIT'                    => '#fb923c',
        'Personal Training'       => '#34d399',
        'Spin'                    => '#22d3ee',
        'Strength & Conditioning' => '#fbbf24',
        'Zumba'                   => '#a3e635',
        'Boxing'                  => '#f87171',
    ];

    const DESCRIPTIONS = [
        'Cycling'           => 'High-energy indoor cycling set to motivating music. Builds cardiovascular endurance and lower-body power.',
        'Pilates'           => 'Low-impact exercise focusing on core strength, posture and controlled movement. Suitable for all fitness levels.',
        'Personal Training' => 'One-on-one session tailored to your specific goals with dedicated coach guidance and personalised programming.',
    ];

    const ALLOWED_TYPES = ['Pilates', 'Cycling', 'Personal Training'];

    static function getWeekClasses(PDO $db, DateTime $mon, DateTime $sun,
                                   ?int $trainerFilter, ?int $clientId): array
    {
        $stmt = $db->prepare("
            SELECT cl.id, ct.name AS class_name, cl.schedule, cl.duration_min, cl.capacity,
                   cl.class_type_id, cl.gym_id, cl.trainer_id, cl.description, cl.photo,
                   gl.name AS gym_name, gl.city AS gym_city,
                   u.first_name AS trainer_first, u.last_name AS trainer_last,
                   u.profile_photo AS trainer_photo,
                   (SELECT COUNT(*) FROM client_classes cc WHERE cc.class_id = cl.id) AS enrolled,
                   ROUND(COALESCE((SELECT AVG(r2.rating) FROM reviews r2
                       JOIN classes c2 ON c2.id = r2.class_id
                       WHERE c2.trainer_id = cl.trainer_id AND c2.class_type_id = cl.class_type_id), 0), 1) AS avg_rating,
                   (SELECT COUNT(*) FROM reviews r2
                       JOIN classes c2 ON c2.id = r2.class_id
                       WHERE c2.trainer_id = cl.trainer_id AND c2.class_type_id = cl.class_type_id) AS review_count,
                   my_rev.rating  AS my_rating,
                   my_rev.comment AS my_comment
            FROM classes cl
            JOIN class_types ct ON ct.id = cl.class_type_id
            LEFT JOIN gym_locations gl ON gl.id = cl.gym_id
            LEFT JOIN trainers t ON t.user_id = cl.trainer_id
            LEFT JOIN users u ON u.id = t.user_id
            LEFT JOIN reviews my_rev ON my_rev.class_id = cl.id AND my_rev.client_id = :uid
            WHERE date(cl.schedule) BETWEEN :start AND :end
              AND ct.name IN ('Pilates', 'Cycling', 'Personal Training')
              AND (:trainer_filter IS NULL OR cl.trainer_id = :trainer_filter)
            ORDER BY cl.schedule ASC
        ");
        $stmt->execute([
            ':start'          => $mon->format('Y-m-d'),
            ':end'            => $sun->format('Y-m-d'),
            ':trainer_filter' => $trainerFilter,
            ':uid'            => $clientId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    static function getEnrolledIds(PDO $db, int $clientId): array
    {
        $s = $db->prepare('SELECT class_id FROM client_classes WHERE client_id = ?');
        $s->execute([$clientId]);
        return array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
    }

    static function buildClassesForJS(array $classes, array $enrolledIds, bool $isAdmin): array
    {
        if (empty($classes)) return [];
        if ($isAdmin) {
            return array_combine(
                array_column($classes, 'id'),
                array_map(function ($c) {
                    $c['color']   = self::COLORS[$c['class_name']] ?? '#888';
                    $c['is_full'] = ((int)$c['capacity'] - (int)$c['enrolled']) <= 0;
                    return $c;
                }, $classes)
            );
        }
        return array_combine(
            array_column($classes, 'id'),
            array_map(function ($c) use ($enrolledIds) {
                return [
                    'id'           => (int)$c['id'],
                    'class_name'   => $c['class_name'],
                    'class_type_id'=> (int)($c['class_type_id'] ?? 0),
                    'gym_id'       => (int)($c['gym_id'] ?? 0),
                    'color'        => self::COLORS[$c['class_name']] ?? '#888',
                    'schedule'     => $c['schedule'],
                    'duration_min' => (int)$c['duration_min'],
                    'gym_name'     => $c['gym_name'] ?? '',
                    'gym_city'     => $c['gym_city'] ?? '',
                    'trainer_id'   => (int)$c['trainer_id'],
                    'trainer_first'=> $c['trainer_first'] ?? '',
                    'trainer_last' => $c['trainer_last'] ?? '',
                    'trainer_photo'=> $c['trainer_photo'] ?? '',
                    'capacity'     => (int)$c['capacity'],
                    'enrolled'     => (int)$c['enrolled'],
                    'avg_rating'   => (float)$c['avg_rating'],
                    'review_count' => (int)$c['review_count'],
                    'description'  => !empty($c['description']) ? $c['description'] : (self::DESCRIPTIONS[$c['class_name']] ?? ''),
                    'photo'        => $c['photo'] ?? '',
                    'is_enrolled'  => in_array((int)$c['id'], $enrolledIds, true),
                    'is_full'      => ((int)$c['capacity'] - (int)$c['enrolled']) <= 0,
                    'my_rating'    => isset($c['my_rating']) && $c['my_rating'] !== null ? (int)$c['my_rating'] : null,
                    'my_comment'   => $c['my_comment'] ?? null,
                ];
            }, $classes)
        );
    }

    static function getByIds(PDO $db, array $classIds): array
    {
        if (empty($classIds)) return [];
        $placeholders = implode(',', array_fill(0, count($classIds), '?'));
        $s = $db->prepare("
            SELECT cl.id, ct.name AS class_name, cl.schedule, cl.duration_min, cl.capacity, cl.photo,
                   cl.trainer_id, gl.name AS gym_name, gl.city AS gym_city,
                   u.first_name AS trainer_first, u.last_name AS trainer_last, u.profile_photo AS trainer_photo,
                   (SELECT COUNT(*) FROM client_classes cc WHERE cc.class_id = cl.id) AS enrolled,
                   ROUND(COALESCE((SELECT AVG(r2.rating) FROM reviews r2 JOIN classes c2 ON c2.id=r2.class_id
                       WHERE c2.trainer_id=cl.trainer_id AND c2.class_type_id=cl.class_type_id),0),1) AS avg_rating,
                   (SELECT COUNT(*) FROM reviews r2 JOIN classes c2 ON c2.id=r2.class_id
                       WHERE c2.trainer_id=cl.trainer_id AND c2.class_type_id=cl.class_type_id) AS review_count
            FROM classes cl
            JOIN class_types ct ON ct.id=cl.class_type_id
            LEFT JOIN gym_locations gl ON gl.id=cl.gym_id
            LEFT JOIN users u ON u.id=cl.trainer_id
            WHERE cl.id IN ($placeholders)");
        $s->execute(array_values($classIds));
        return self::buildClassesForJS($s->fetchAll(PDO::FETCH_ASSOC), [], false);
    }

    static function typeColor(string $name): string
    {
        return self::COLORS[$name] ?? '#888';
    }

    static function timeOfDay(string $schedule): string
    {
        $h = (int)(new DateTime($schedule))->format('H');
        if ($h >= 6  && $h <= 11) return 'morning';
        if ($h >= 12 && $h <= 16) return 'afternoon';
        return 'evening';
    }

    static function getUpcomingForClient(PDO $db, int $clientId, int $limit = 5): array
    {
        $stmt = $db->prepare(
            'SELECT cl.id, ct.name AS class_name, cl.schedule, cl.duration_min, cl.capacity,
                    gl.name AS gym_name, gl.city AS gym_city,
                    cl.trainer_id,
                    u.first_name AS trainer_first, u.last_name AS trainer_last,
                    u.profile_photo AS trainer_photo,
                    (SELECT COUNT(*) FROM client_classes cc2 WHERE cc2.class_id = cl.id) AS enrolled,
                    ROUND(COALESCE((SELECT AVG(r.rating) FROM reviews r
                        JOIN classes c2 ON c2.id = r.class_id
                        WHERE c2.trainer_id = cl.trainer_id AND c2.class_type_id = cl.class_type_id), 0), 1) AS avg_rating,
                    (SELECT COUNT(*) FROM reviews r
                        JOIN classes c2 ON c2.id = r.class_id
                        WHERE c2.trainer_id = cl.trainer_id AND c2.class_type_id = cl.class_type_id) AS review_count,
                    my_rev.rating  AS my_rating,
                    my_rev.comment AS my_comment
             FROM client_classes cc
             JOIN classes cl ON cl.id = cc.class_id
             JOIN class_types ct ON ct.id = cl.class_type_id
             LEFT JOIN gym_locations gl ON gl.id = cl.gym_id
             LEFT JOIN users u ON u.id = cl.trainer_id
             LEFT JOIN reviews my_rev ON my_rev.class_id = cl.id AND my_rev.client_id = :uid
             WHERE cc.client_id = :id AND cl.schedule > datetime(\'now\')
             ORDER BY cl.schedule ASC
             LIMIT ' . $limit
        );
        $stmt->execute([':id' => $clientId, ':uid' => $clientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    static function getRecentForClient(PDO $db, int $clientId, int $limit = 5): array
    {
        $stmt = $db->prepare(
            'SELECT cl.id, ct.name AS class_name, cl.schedule, cl.duration_min, cl.capacity,
                    gl.name AS gym_name, gl.city AS gym_city,
                    cl.trainer_id,
                    u.first_name AS trainer_first, u.last_name AS trainer_last,
                    u.profile_photo AS trainer_photo,
                    (SELECT COUNT(*) FROM client_classes cc2 WHERE cc2.class_id = cl.id) AS enrolled,
                    ROUND(COALESCE((SELECT AVG(r.rating) FROM reviews r
                        JOIN classes c2 ON c2.id = r.class_id
                        WHERE c2.trainer_id = cl.trainer_id AND c2.class_type_id = cl.class_type_id), 0), 1) AS avg_rating,
                    (SELECT COUNT(*) FROM reviews r
                        JOIN classes c2 ON c2.id = r.class_id
                        WHERE c2.trainer_id = cl.trainer_id AND c2.class_type_id = cl.class_type_id) AS review_count,
                    my_rev.rating  AS my_rating,
                    my_rev.comment AS my_comment
             FROM client_classes cc
             JOIN classes cl ON cl.id = cc.class_id
             JOIN class_types ct ON ct.id = cl.class_type_id
             LEFT JOIN gym_locations gl ON gl.id = cl.gym_id
             LEFT JOIN users u ON u.id = cl.trainer_id
             LEFT JOIN reviews my_rev ON my_rev.class_id = cl.id AND my_rev.client_id = :uid
             WHERE cc.client_id = :id AND cl.schedule <= datetime(\'now\')
             ORDER BY cl.schedule DESC
             LIMIT ' . $limit
        );
        $stmt->execute([':id' => $clientId, ':uid' => $clientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    static function getUpcomingForTrainer(PDO $db, int $trainerId, int $limit = 6): array
    {
        $stmt = $db->prepare(
            'SELECT cl.id, ct.name AS class_name, cl.schedule, cl.duration_min, cl.capacity,
                    gl.name AS gym_name, gl.city AS gym_city,
                    cl.trainer_id,
                    u.first_name AS trainer_first, u.last_name AS trainer_last,
                    u.profile_photo AS trainer_photo,
                    (SELECT COUNT(*) FROM client_classes cc WHERE cc.class_id = cl.id) AS enrolled,
                    ROUND(COALESCE((SELECT AVG(r.rating) FROM reviews r
                        JOIN classes c2 ON c2.id = r.class_id
                        WHERE c2.trainer_id = cl.trainer_id AND c2.class_type_id = cl.class_type_id), 0), 1) AS avg_rating,
                    (SELECT COUNT(*) FROM reviews r
                        JOIN classes c2 ON c2.id = r.class_id
                        WHERE c2.trainer_id = cl.trainer_id AND c2.class_type_id = cl.class_type_id) AS review_count
             FROM classes cl
             JOIN class_types ct ON ct.id = cl.class_type_id
             LEFT JOIN gym_locations gl ON gl.id = cl.gym_id
             LEFT JOIN users u ON u.id = cl.trainer_id
             WHERE cl.trainer_id = :id AND cl.schedule > datetime(\'now\')
             ORDER BY cl.schedule ASC
             LIMIT ' . $limit
        );
        $stmt->execute([':id' => $trainerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
