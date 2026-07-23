<?php
session_start();
if (isset($_SESSION['admin_id'])) {
    header('Location: /cobranca/admin/index.php');
    exit;
}
if (isset($_SESSION['user_id'])) {
    header('Location: /cobranca/usuario/index.php');
    exit;
}
header('Location: /cobranca/usuario/login.php');
exit;
