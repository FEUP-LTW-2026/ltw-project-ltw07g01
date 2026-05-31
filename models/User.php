<?php
declare(strict_types=1);

class User
{
    static function saveProfilePhoto(PDO $db, int $userId, string $uploadDir): bool
    {
        $pf = $_FILES['photo'] ?? null;
        if (!$pf || $pf['error'] !== UPLOAD_ERR_OK || $pf['size'] > 5 * 1024 * 1024) return false;
        $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($pf['tmp_name']);
        if (!isset($ext[$mime])) return false;
        @mkdir($uploadDir, 0755, true);
        $fn = 'user_' . $userId . '_' . time() . '.' . $ext[$mime];
        if (!move_uploaded_file($pf['tmp_name'], $uploadDir . $fn)) return false;
        $db->prepare('UPDATE users SET profile_photo=? WHERE id=?')
           ->execute(['../images/profile_photos/' . $fn, $userId]);
        return true;
    }

    static function handleProfilePhotoUpload(int $userId, string $uploadDir, string $inputName = 'profile_photo'): array
    {
        $file = $_FILES[$inputName] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) return ['path' => null, 'error' => null];
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset($allowed[$mime])) return ['path' => null, 'error' => 'Profile photo must be a JPEG, PNG, WebP, or GIF image.'];
        if ($file['size'] > 5 * 1024 * 1024) return ['path' => null, 'error' => 'Profile photo must be smaller than 5 MB.'];
        $filename = 'user_' . $userId . '_' . time() . '.' . $allowed[$mime];
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) return ['path' => null, 'error' => 'Failed to save the profile photo. Please try again.'];
        return ['path' => '../images/profile_photos/' . $filename, 'error' => null];
    }

    static function getFullClientProfile(PDO $db, int $userId): ?array
    {
        $s = $db->prepare(
            'SELECT u.username, u.email, u.first_name, u.last_name, u.profile_photo, u.bio, u.created_at,
                    c.preferred_gym_id, c.archetype_id, c.selected_badges, c.body_weight, c.height,
                    gl.name AS gym_name, gl.city AS gym_city,
                    a.name AS archetype
             FROM users u
             JOIN clients c ON c.user_id = u.id
             LEFT JOIN gym_locations gl ON gl.id = c.preferred_gym_id
             LEFT JOIN archetypes a ON a.id = c.archetype_id
             WHERE u.id = ?'
        );
        $s->execute([$userId]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    static function getClientData(PDO $db, int $userId): array
    {
        $s = $db->prepare(
            'SELECT c.body_weight, c.height, c.archetype_id, c.selected_badges,
                    c.preferred_gym_id, gl.name AS gym_name, gl.city AS gym_city,
                    a.name AS archetype
             FROM clients c
             LEFT JOIN gym_locations gl ON gl.id = c.preferred_gym_id
             LEFT JOIN archetypes a ON a.id = c.archetype_id
             WHERE c.user_id = ?'
        );
        $s->execute([$userId]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    static function getById(PDO $db, int $userId): ?array
    {
        $s = $db->prepare('SELECT username, first_name, last_name, email, profile_photo FROM users WHERE id = ?');
        $s->execute([$userId]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
