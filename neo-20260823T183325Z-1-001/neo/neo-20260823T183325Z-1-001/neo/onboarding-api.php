<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/services/onboarding.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

function neoOnboardingReply(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

$userId = logado() ? (int)$_SESSION['user_id'] : null;
$input = [];
try {
    if ($userId) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE id=?');
        $stmt->execute([$userId]);
        if (!$stmt->fetchColumn()) { unset($_SESSION['user_id']); renovarSessaoAutenticada(); neoOnboardingReply(['ok' => false, 'error' => 'Sua sessão expirou. Entre novamente para continuar.'], 401); }
    }
    $progress = neoOnboardingLoad($pdo, $userId);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET') neoOnboardingReply(neoOnboardingState($progress, $userId !== null) + ['csrf_token' => csrfToken()]);
    if ($method !== 'POST') neoOnboardingReply(['ok' => false, 'error' => 'Método não permitido.'], 405);
    $raw = file_get_contents('php://input', false, null, 0, 65537);
    if (strlen($raw) > 65536) neoOnboardingReply(['ok' => false, 'error' => 'Essa resposta ficou muito grande. Tente uma resposta mais curta.'], 413);
    $input = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($input) || array_is_list($input)) throw new DomainException('Envie uma resposta válida para continuar.');
    $receivedToken = $input['csrf_token'] ?? null;
    if (!is_string($receivedToken) || !hash_equals(csrfToken(), $receivedToken)) neoOnboardingReply(['ok' => false, 'error' => 'Sua sessão do formulário expirou. Recarregue a página: seu progresso continua salvo.'], 419);
    $action = $input['action'] ?? '';
    if ($action === 'reset') {
        if ($userId === null) {
            neoOnboardingResetGuest($pdo);
        } else {
            $completion = $pdo->prepare('SELECT onboarding_concluido_em FROM users WHERE id=?');
            $completion->execute([$userId]);
            if ($completion->fetchColumn() === null) {
                $pdo->prepare('DELETE FROM onboarding_progress WHERE user_id=?')->execute([$userId]);
            }
        }
        $progress = neoOnboardingLoad($pdo, $userId);
        neoOnboardingReply(neoOnboardingState($progress, $userId !== null) + ['csrf_token' => csrfToken()]);
    } elseif ($action === 'save') {
        if (!is_string($input['step'] ?? null) || !array_key_exists('value', $input)) throw new DomainException('Escolha uma resposta antes de continuar.');
        $progress = neoOnboardingSaveAnswer($progress, $input['step'], $input['value']);
    } elseif ($action === 'finish') {
        if (!is_array($input['credentials'] ?? [])) throw new DomainException('Confira os dados da conta.');
        $firstCompletion = $userId === null;
        if ($userId !== null) {
            $completion = $pdo->prepare('SELECT onboarding_concluido_em FROM users WHERE id=?');
            $completion->execute([$userId]);
            $firstCompletion = $completion->fetchColumn() === null;
        }
        $userId = neoOnboardingFinish($pdo, $progress, $input['credentials'] ?? [], $userId);
        renovarSessaoAutenticada();
        $_SESSION['user_id'] = $userId;
        unset($_SESSION['neo_onboarding_guest'], $_SESSION['neo_intro_login']);
        if ($firstCompletion) $_SESSION['neo_dashboard_awaken'] = true;
        else unset($_SESSION['neo_dashboard_awaken']);
        neoOnboardingReply(['ok' => true, 'redirect' => 'index.php']);
    } else {
        throw new DomainException('Essa ação não existe. Recarregue a conversa para continuar.');
    }
    neoOnboardingPersist($pdo, $userId, $progress);
    neoOnboardingReply(neoOnboardingState($progress, $userId !== null) + ['csrf_token' => csrfToken()]);
} catch (JsonException $e) {
    neoOnboardingReply(['ok' => false, 'error' => 'Não consegui ler essa resposta. Tente novamente.'], 400);
} catch (DomainException $e) {
    neoOnboardingReply(['ok' => false, 'error' => $e->getMessage(), 'step' => neoOnboardingNextStep($progress['answers'] ?? [])], 422);
} catch (Throwable $e) {
    error_log('[NEO][onboarding] ' . $e->getMessage());
    neoOnboardingReply(['ok' => false, 'error' => 'Não consegui salvar agora. Seu progresso anterior está guardado; tente novamente em instantes.'], 500);
}
