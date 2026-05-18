<?php
declare(strict_types=1);
require_once('../utils/session.php');
$session = new Session();

//if (!$session->isLoggedIn()) {
//  header('Location: login.php');
//    exit;
//}

$userId = 3; //user de teste

require_once('../database/connection.db.php');
require_once('../templates/common.tmp.php');

$db = getDatabaseConnection();
//$userId = $session->getId();

// --- Buscar dados atuais ---
$stmt = $db->prepare(
    'SELECT u.username, u.email, u.first_name, u.last_name, u.profile_photo, u.created_at, c.preferred_gym_id, c.archetype, c.body_weight, c.height,
            gl.name AS gym_name, gl.city AS gym_city
     FROM users u
     JOIN clients c ON c.user_id = u.id
     LEFT JOIN gym_locations gl ON gl.id = c.preferred_gym_id
     WHERE u.id = :id'
);
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: login.php');
    exit;
}

// --- Buscar todos os gyms ---
$stmt = $db->prepare('SELECT id, name, city FROM gym_locations ORDER BY city, name');
$stmt->execute();
$gyms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Opções de archetype ---
$archetypeOptions = ['SPINNER', 'POWERLIFTER', 'YOGI', 'PILATES PRACTITIONER'];

// --- Processar form ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $archetype = $_POST['archetype'] ?? null;
    $preferredGymId = (int)($_POST['preferred_gym_id'] ?? 0);
    $bodyWeight = (float)($_POST['body_weight'] ?? 0);
    $height = (float)($_POST['height'] ?? 0);

    // Validações básicas
    if (empty($firstName) || empty($lastName) || empty($email)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif ($bodyWeight <= 0 || $height <= 0) {
        $error = 'Body weight and height must be positive numbers.';
    } elseif (!in_array($archetype, $archetypeOptions, true) && $archetype !== null) {
        $error = 'Invalid archetype selected.';
    } else {
        // Atualizar users
        $stmt = $db->prepare('UPDATE users SET first_name = :first, last_name = :last, email = :email WHERE id = :id');
        $stmt->execute([':first' => $firstName, ':last' => $lastName, ':email' => $email, ':id' => $userId]);

        // Atualizar clients
        $stmt = $db->prepare('UPDATE clients SET archetype = :arch, preferred_gym_id = :gym, body_weight = :weight, height = :height WHERE user_id = :id');
        $stmt->execute([
            ':arch' => $archetype ?: null,
            ':gym' => $preferredGymId ?: null,
            ':weight' => $bodyWeight,
            ':height' => $height,
            ':id' => $userId
        ]);

        // Redirecionar de volta ao perfil
        header('Location: profile.php');
        exit;
    }
}

// --- Valores atuais ---
$fullName = $user['first_name'] . ' ' . $user['last_name'];
$memberSince = (new DateTime($user['created_at']))->format('F Y');
$homeGym = $user['gym_name'] ? 'Cubo Gym - ' . $user['gym_city'] . ', ' . $user['gym_name'] : 'No gym selected';
$profilePhoto = $user['profile_photo'] ?? '../images/profile_pic.webp';
$archetype = $user['archetype'] ?? 'NO ARCHETYPE';
$bodyWeight = $user['body_weight'] ?? '';
$height = $user['height'] ?? '';
$memberTag = 'MEMBER'; // Simplificado
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile | Cubo Gym</title>
    <link rel="stylesheet" href="../css/profile.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=League+Gothic&display=swap" rel="stylesheet">
</head>
<body>

<?php drawHeader($session); ?>

<main class="profile-page">
    <aside class="sidebar-container">
        <section class="profile-card">
            <div class="profile-info">
                <figure class="profile-avatar">
                    <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="User Profile Picture">
                </figure>
                <div class="user-meta">
                    <h2 class="user-name"><?= htmlspecialchars($fullName) ?></h2>
                    <p class="user-handle">@<?= htmlspecialchars($user['username']) ?></p>
                    <span class="member-tag"><?= htmlspecialchars($memberTag) ?></span>
                </div>
            </div>

            </div>
        </section>
    </aside>

    <div class="main-content">
        <?php if (isset($error)): ?>
            <div class="error-message" style="color: red; margin-bottom: 20px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" class="edit-form">
            <div class="profile-details">
                <div class="detail-item">
                    <label class="detail-label" for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" class="detail-input" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                </div>
                <div class="detail-item">
                    <label class="detail-label" for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" class="detail-input" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                </div>
                <div class="detail-item">
                    <label class="detail-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="detail-input" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>
                <div class="detail-item">
                    <label class="detail-label" for="preferred_gym_id">Home Gym</label>
                    <select id="preferred_gym_id" name="preferred_gym_id" class="detail-select">
                        <option value="">No gym selected</option>
                        <?php foreach ($gyms as $gym): ?>
                            <option value="<?= $gym['id'] ?>" <?= $user['preferred_gym_id'] == $gym['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($gym['city'] . ' - ' . $gym['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="detail-item">
                    <label class="detail-label" for="archetype">Archetype</label>
                    <select id="archetype" name="archetype" class="detail-select">
                        <option value="">No archetype</option>
                        <?php foreach ($archetypeOptions as $option): ?>
                            <option value="<?= $option ?>" <?= $user['archetype'] === $option ? 'selected' : '' ?>>
                                <?= htmlspecialchars($option) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="metrics-section">
                <h3>METRICS</h3>
                <div class="metrics-grid">
                    <div class="metric-card">
                        <label class="metric-label" for="body_weight">Body Weight (kg)</label>
                        <input type="number" id="body_weight" name="body_weight" class="metric-input" step="0.1" value="<?= htmlspecialchars($bodyWeight) ?>" required>
                    </div>
                    <div class="metric-card">
                        <label class="metric-label" for="height">Height (cm)</label>
                        <input type="number" id="height" name="height" class="metric-input" step="0.1" value="<?= htmlspecialchars($height) ?>" required>
                    </div>
                </div>
            </div>

            <div class="profile-actions">
                <button type="submit" class="btn-save-changes">Save Changes</button>
                <a href="profile.php" class="btn-cancel">Cancel</a>
            </div>
        </form>
    </div>
</main>

<?php drawFooter(); ?>

</body>
</html>