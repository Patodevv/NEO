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

function loginTemporariamenteBloqueado(string $escopo): bool
{
    $dados = $_SESSION['limites_login'][$escopo] ?? null;
    return is_array($dados)
        && (int)($dados['tentativas'] ?? 0) >= 5
        && (int)($dados['bloqueado_ate'] ?? 0) > time();
}

function registrarFalhaLogin(string $escopo): void
{
    $dados = $_SESSION['limites_login'][$escopo] ?? ['tentativas' => 0, 'bloqueado_ate' => 0];
    $dados['tentativas'] = (int)$dados['tentativas'] + 1;
    if ($dados['tentativas'] >= 5) {
        $dados['bloqueado_ate'] = time() + 300;
    }
    $_SESSION['limites_login'][$escopo] = $dados;
}

function limparFalhasLogin(string $escopo): void
{
    unset($_SESSION['limites_login'][$escopo]);
}

function renovarSessaoAutenticada(): void
{
    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
