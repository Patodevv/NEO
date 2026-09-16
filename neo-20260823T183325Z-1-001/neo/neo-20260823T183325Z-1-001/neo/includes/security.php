<?php

require_once dirname(__DIR__) . '/config/env.php';

if (session_status() === PHP_SESSION_NONE) {
    $segura = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $segura,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function campoCsrf(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function validarCsrf(): void
{
    $recebido = $_POST['csrf_token'] ?? '';
    $esperado = $_SESSION['csrf_token'] ?? '';

    if (!is_string($recebido) || !is_string($esperado) || $esperado === '' || !hash_equals($esperado, $recebido)) {
        http_response_code(419);
        exit('A sessao do formulario expirou. Volte para a pagina e tente novamente.');
    }
}

function exigirPostSeguro(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        exit('Metodo nao permitido.');
    }
    validarCsrf();
}

function hashIdentificadorLogin(string $valor): string
{
    return hash('sha256', mb_strtolower(trim($valor)));
}

function hashIpLogin(): string
{
    return hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'desconhecido'));
}

function loginTemporariamenteBloqueado(PDO $pdo, string $escopo, string $identificador): bool
{
    $stmt = $pdo->prepare("
        SELECT
            SUM(identificador_hash = ?) AS tentativas_identificador,
            SUM(ip_hash = ?) AS tentativas_ip
        FROM tentativas_login
        WHERE escopo = ? AND criado_em >= NOW() - INTERVAL 15 MINUTE
    ");
    $stmt->execute([hashIdentificadorLogin($identificador), hashIpLogin(), $escopo]);
    $tentativas = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return (int)($tentativas['tentativas_identificador'] ?? 0) >= 5
        || (int)($tentativas['tentativas_ip'] ?? 0) >= 20;
}

function registrarFalhaLogin(PDO $pdo, string $escopo, string $identificador): void
{
    $pdo->prepare("
        INSERT INTO tentativas_login (escopo, identificador_hash, ip_hash)
        VALUES (?, ?, ?)
    ")->execute([$escopo, hashIdentificadorLogin($identificador), hashIpLogin()]);
    $pdo->prepare('DELETE FROM tentativas_login WHERE criado_em < NOW() - INTERVAL 1 DAY')->execute();
}

function limparFalhasLogin(PDO $pdo, string $escopo, string $identificador): void
{
    $pdo->prepare("
        DELETE FROM tentativas_login
        WHERE escopo = ? AND identificador_hash = ?
    ")->execute([$escopo, hashIdentificadorLogin($identificador)]);
}

function renovarSessaoAutenticada(): void
{
    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
