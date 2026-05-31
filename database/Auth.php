<?php
declare(strict_types=1);

class Auth
{
    static function getRole(PDO $db, int $userId): ?string
    {
        foreach (['admins' => 'admin', 'trainers' => 'trainer', 'clients' => 'client'] as $tbl => $r) {
            $s = $db->prepare("SELECT 1 FROM $tbl WHERE user_id = ?");
            $s->execute([$userId]);
            if ($s->fetch()) return $r;
        }
        return null;
    }

    static function isAdmin(PDO $db, int $userId): bool
    {
        $s = $db->prepare('SELECT 1 FROM admins WHERE user_id = ?');
        $s->execute([$userId]);
        return (bool)$s->fetch();
    }

    static function requireAdmin(Session $session, PDO $db): void
    {
        if (!$session->isLoggedIn()) {
            http_response_code(401);
            die(json_encode(['error' => 'Unauthorized']));
        }
        $s = $db->prepare('SELECT 1 FROM admins WHERE user_id = ?');
        $s->execute([$session->getId()]);
        if (!$s->fetch()) {
            http_response_code(403);
            die(json_encode(['error' => 'Forbidden']));
        }
        $token = $_POST['csrf_token']
            ?? (json_decode(file_get_contents('php://input'), true) ?? [])['csrf_token']
            ?? '';
        if (!$session->validateCsrfToken($token)) {
            http_response_code(403);
            die(json_encode(['error' => 'Invalid CSRF token']));
        }
    }

    static function requireLogin(Session $session, string $redirect = '/actions/login.php'): void
    {
        if (!$session->isLoggedIn()) {
            header('Location: ' . $redirect);
            exit;
        }
    }
}
