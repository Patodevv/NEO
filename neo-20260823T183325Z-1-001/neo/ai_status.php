<?php

require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/services/ai_usage.php';

exigirLogin();
$usuario = usuarioAtual($pdo);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$formatarTempo = static function (int $segundos): string {
    $segundos = max(0, $segundos);
    if ($segundos >= 3600) {
        return floor($segundos / 3600) . 'h ' . str_pad((string)floor(($segundos % 3600) / 60), 2, '0', STR_PAD_LEFT) . 'm';
    }
    if ($segundos >= 60) {
        return floor($segundos / 60) . 'm ' . str_pad((string)($segundos % 60), 2, '0', STR_PAD_LEFT) . 's';
    }
    return $segundos . 's';
};

try {
    $status = statusRecargaIA($pdo, (int)$usuario['id']);
    $estado = (string)($status['estado'] ?? 'sem_leitura');
    $segundos = max(0, (int)($status['segundos'] ?? 0));
    $porcentagem = $status['porcentagem'] !== null ? max(0, min(100, (int)$status['porcentagem'])) : null;
    $textoPronto = $porcentagem !== null ? ($porcentagem . '%') : 'IA';
    $textoCarga = $porcentagem !== null ? (' Carga atual: ' . $porcentagem . '%.') : '';
    $detalheTokens = '';
    if ($status['tokens_restantes'] !== null && $status['limite_tokens'] !== null) {
        $detalheTokens = ' Tokens Groq: ' . (int)$status['tokens_restantes'] . '/' . (int)$status['limite_tokens'] . ' por minuto.';
    }
    $detalheRequisicoes = '';
    if ($status['requisicoes_restantes'] !== null && $status['limite_requisicoes'] !== null) {
        $detalheRequisicoes = ' Requisições: ' . (int)$status['requisicoes_restantes'] . '/' . (int)$status['limite_requisicoes'] . ' por dia.';
    }

    $tituloPronto = 'IA liberada.' . ($detalheTokens ?: ' Gere algo para atualizar a leitura real da Groq.') . $detalheRequisicoes;
    $titulo = match ($estado) {
        'pronto' => $tituloPronto,
        'recarga' => 'IA carregando: ' . $formatarTempo($segundos) . ' restantes.' . $textoCarga . $detalheTokens . ' Livros em ' . $formatarTempo((int)($status['livro'] ?? 0)) . '; questões em ' . $formatarTempo((int)($status['questoes'] ?? 0)) . '.',
        'falha' => 'A IA falhou recentemente. Tente novamente em ' . $formatarTempo(max(1, $segundos)) . '.',
        'sem_chave' => 'IA sem chave configurada. Verifique a chave da Groq ou OpenAI.',
        default => 'IA sem leitura recente da Groq. Use uma geração para atualizar a carga real.',
    };

    $rotulo = match ($estado) {
        'pronto' => $textoPronto,
        'recarga' => $porcentagem !== null ? ($porcentagem . '%') : $formatarTempo($segundos),
        'falha' => 'erro',
        'sem_chave' => 'off',
        default => '--',
    };

    echo json_encode([
        'ok' => true,
        'estado' => $estado,
        'segundos' => $segundos,
        'segundos_recarga_total' => max($segundos, (int)($status['segundos_recarga_total'] ?? 0)),
        'porcentagem' => $porcentagem,
        'rotulo' => $rotulo,
        'titulo' => $titulo,
        'titulo_pronto' => $tituloPronto,
        'tokens_restantes' => $status['tokens_restantes'],
        'limite_tokens' => $status['limite_tokens'],
        'requisicoes_restantes' => $status['requisicoes_restantes'],
        'limite_requisicoes' => $status['limite_requisicoes'],
        'livro' => (int)($status['livro'] ?? 0),
        'questoes' => (int)($status['questoes'] ?? 0),
        'atualizado_em' => time(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'estado' => 'sem_leitura',
        'segundos' => 0,
        'porcentagem' => null,
        'rotulo' => '--',
        'titulo' => 'Não consegui ler a bateria da IA agora.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
