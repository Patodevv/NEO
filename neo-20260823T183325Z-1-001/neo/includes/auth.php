<?php
// Controle simples de sessão / login

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function logado() {
    return isset($_SESSION['user_id']);
}

function exigirLogin() {
    if (!logado()) {
        header('Location: login.php');
        exit;
    }
}

function usuarioAtual($pdo) {
    if (!logado()) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
