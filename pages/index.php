<?php
declare(strict_types=1);

require_once('../utils/session.php');
require_once('../templates/layout/common.tpl.php');
require_once('../templates/pages/index.tpl.php');

$session = new Session();

if ($session->isLoggedIn()) {
    header('Location: /pages/dashboard.php');
    exit();
}

drawHeader($session, ['index']);
drawIndexPage($session);
?>
<script src="../js/index.js"></script>
<?php
drawFooter();
