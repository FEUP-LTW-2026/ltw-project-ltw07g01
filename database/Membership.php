<?php
declare(strict_types=1);

class Membership
{
    static function getActive(PDO $db, int $clientId): array
    {
        $s = $db->prepare(
            'SELECT gym_plan, gym_start, gym_end, COALESCE(SUM(classes_remaining), 0) AS total_credits
             FROM memberships
             WHERE client_id = ? AND (gym_end IS NULL OR gym_end > CURRENT_TIMESTAMP)
             ORDER BY gym_start DESC LIMIT 1'
        );
        $s->execute([$clientId]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    static function getTotalCredits(PDO $db, int $clientId): int
    {
        $s = $db->prepare('SELECT COALESCE(SUM(classes_remaining), 0) FROM memberships WHERE client_id = ?');
        $s->execute([$clientId]);
        return (int)$s->fetchColumn();
    }

    static function getPlanLabel(?string $plan): string
    {
        switch ($plan) {
            case 'ultra': return 'ULTRA MEMBER';
            case 'pro':   return 'PRO MEMBER';
            case 'basic': return 'BASIC MEMBER';
            default:      return 'NO MEMBERSHIP';
        }
    }
}
