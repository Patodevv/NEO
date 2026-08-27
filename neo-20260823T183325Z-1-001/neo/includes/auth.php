<?php

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

function saldoCossasVisual(array $usuario): string {
    $saldo = (int)($usuario['cossas'] ?? 0);
    return number_format($saldo, 0, ',', '.');
}

function xpParaProximoNivel(int $nivel): int {
    return max(100, $nivel * 100);
}

function recompensarUsuario(PDO $pdo, int $userId, int $acertos, int $total): array {
    $stmt = $pdo->prepare("SELECT cossas, xp, nivel FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    $nivel = max(1, (int)($usuario['nivel'] ?? 1));
    $xp = max(0, (int)($usuario['xp'] ?? 0));
    $cossas = max(0, (int)($usuario['cossas'] ?? 0));
    $xpGanho = 20 + ($acertos * 15);
    $cossasGanhas = 25 + ($acertos * 10);

    if ($total > 0 && $acertos === $total) {
        $xpGanho += 25;
        $cossasGanhas += 30;
    }

    $xp += $xpGanho;
    $subiuNivel = false;

    while ($xp >= xpParaProximoNivel($nivel)) {
        $xp -= xpParaProximoNivel($nivel);
        $nivel++;
        $cossasGanhas += 100;
        $subiuNivel = true;
    }

    $cossas += $cossasGanhas;
    $stmt = $pdo->prepare("UPDATE users SET cossas = ?, xp = ?, nivel = ? WHERE id = ?");
    $stmt->execute([$cossas, $xp, $nivel, $userId]);

    return [
        'xp' => $xpGanho,
        'cossas' => $cossasGanhas,
        'nivel' => $nivel,
        'subiu_nivel' => $subiuNivel,
    ];
}

function adminLogado(): bool {
    return !empty($_SESSION['admin_logado']);
}

function exigirAdmin(): void {
    if (!adminLogado()) {
        header('Location: adm_login.php');
        exit;
    }
}
