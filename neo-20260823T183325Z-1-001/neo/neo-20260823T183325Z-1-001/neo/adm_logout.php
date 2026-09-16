<?php
require __DIR__ . '/includes/auth.php';
unset($_SESSION['admin_logado'], $_SESSION['admin_autenticado_em']);
header('Location: adm_login.php');
exit;
