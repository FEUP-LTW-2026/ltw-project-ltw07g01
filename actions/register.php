<?php
declare(strict_types=1);

require_once(__DIR__ . '/../utils/session.php');
require_once(__DIR__ . '/../database/connection.db.php');
require_once(__DIR__ . '/../templates/common.tpl.php');
require_once(__DIR__ . '/../templates/register.tpl.php');

$session = new Session();

if ($session->isLoggedIn()) {
    header('Location: /pages/dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    if ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $db = getDatabaseConnection();

        $stmt = $db->prepare('
            SELECT id
            FROM users
            WHERE username = ? OR email = ?
        ');
        $stmt->execute([$username, $email]);

        if ($stmt->fetch()) {
            $error = 'Username or email already exists.';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $db->prepare('
                INSERT INTO users (username, email, password_hash, first_name, last_name)
                VALUES (?, ?, ?, ?, ?)
            ');

            $stmt->execute([
                $username,
                $email,
                $passwordHash,
                $firstName,
                $lastName
            ]);

            $userId = (int)$db->lastInsertId();

            $stmt = $db->prepare('
                INSERT INTO clients (user_id)
                VALUES (?)
            ');
            $stmt->execute([$userId]);

            $session->setId($userId);
            $session->setName($firstName);

            header('Location: /pages/dashboard.php');
            exit();
        }
    }
}

drawHeader($session, ['login-register']);
drawRegisterPage($session, $error);
drawFooter();
