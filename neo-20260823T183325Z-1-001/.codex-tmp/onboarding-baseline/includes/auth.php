<?php

require_once __DIR__ . '/security.php';
require_once dirname(__DIR__) . '/services/gamification.php';

function logado(): bool
{
    return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
}

function exigirLogin(): void
{
    if (!logado()) {
        header('Location: login.php');
        exit;
    }
}
function usuarioAtual(PDO $pdo): ?array
{
    if (!logado()) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        unset($_SESSION['user_id']);
        renovarSessaoAutenticada();
        header('Location: login.php');
        exit;
    }

    if (!empty($usuario['decoracao_perfil']) && tabelaExiste($pdo, 'produtos')) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM compras_loja cl JOIN produtos p ON p.id = cl.produto_id
            WHERE cl.user_id = ? AND p.codigo = ? AND cl.status = 'concluida'
              AND (cl.expira_em IS NULL OR cl.expira_em >= NOW())
        ");
        $stmt->execute([(int)$usuario['id'], $usuario['decoracao_perfil']]);
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->prepare("UPDATE users SET decoracao_perfil = NULL WHERE id = ?")->execute([(int)$usuario['id']]);
            $usuario['decoracao_perfil'] = null;
        }
    }

    return $usuario;
}

function saldoCossasVisual(array $usuario): string
{
    return number_format((int)($usuario['cossas'] ?? 0), 0, ',', '.');
}

function adminLogado(): bool
{
    $autenticadoEm = (int)($_SESSION['admin_autenticado_em'] ?? 0);

    if (empty($_SESSION['admin_logado']) || $autenticadoEm <= 0 || $autenticadoEm < time() - 14400) {
        unset($_SESSION['admin_logado'], $_SESSION['admin_autenticado_em']);
        return false;
    }

    return true;
}

function exigirAdmin(): void
{
    if (!adminLogado()) {
        header('Location: adm_login.php');
        exit;
    }
}
