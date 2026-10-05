<?php
require __DIR__ . '/includes/auth.php';
exigirPostSeguro();
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
