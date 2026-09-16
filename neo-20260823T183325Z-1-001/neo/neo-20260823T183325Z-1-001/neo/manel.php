<?php

require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/services/manel.php';

exigirLogin();
$usuario = usuarioAtual($pdo);

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['reply' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawPayload = (string)file_get_contents('php://input', false, null, 0, 256001);
if (strlen($rawPayload) > 256000) {
    http_response_code(413);
    echo json_encode(['reply' => 'O pedido ficou grande demais. Envie um trecho menor.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$payload = json_decode($rawPayload, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['reply' => 'Não entendi essa mensagem.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($payload['mode'] ?? '') === 'study' && (int)($payload['userId'] ?? 0) !== (int)$usuario['id']) {
    http_response_code(409);
    echo json_encode(['reply' => 'A conta mudou. Recarregue esta página antes de continuar.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$csrfRecebido = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($payload['csrf_token'] ?? ''));
$csrfEsperado = (string)($_SESSION['csrf_token'] ?? '');

if ($csrfEsperado === '' || !hash_equals($csrfEsperado, $csrfRecebido)) {
    http_response_code(419);
    echo json_encode(['reply' => 'Sua sessão expirou. Recarregue a página e tente de novo.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($payload['mode'] ?? '') === 'study') {
    require_once __DIR__ . '/services/estudos_externos.php';
    session_write_close();
    try {
        $study = executarOperacaoControladaIA($pdo, (int)$usuario['id'], 'estudos', $rawPayload,
            fn() => manelEstudoExterno($usuario, $payload), 'estudos_externos');
        echo json_encode(['study' => $study], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (DomainException $error) {
        http_response_code(429);
        echo json_encode(['reply' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
    } catch (InvalidArgumentException $error) {
        http_response_code(422);
        echo json_encode(['reply' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $error) {
        error_log('[NEO][estudos] ' . $error->getMessage());
        http_response_code(502);
        $message = str_starts_with($error->getMessage(), 'Nenhum provedor') ? 'O Manel está indisponível agora. Tente novamente em instantes.' : $error->getMessage();
        echo json_encode(['reply' => $error instanceof RuntimeException && !$error instanceof PDOException ? $message : 'Não consegui preparar o estudo agora. Tente novamente.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$mensagem = (string)($payload['message'] ?? '');
$historico = is_array($payload['messages'] ?? null) ? $payload['messages'] : [];
$contexto = is_array($payload['context'] ?? null) ? $payload['context'] : [];

echo json_encode(
    manelResponder($pdo, $usuario ?? [], $mensagem, $historico, $contexto),
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
