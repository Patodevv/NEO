<?php
require __DIR__ . '/includes/auth.php';
unset($_SESSION['admin_logado']);
header('Location: adm_login.php');
exit;
