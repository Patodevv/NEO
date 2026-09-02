<?php

/**
 * Carregador mínimo de ambiente para instalações locais sem Composer.
 * Variáveis reais do processo sempre têm prioridade sobre o arquivo .env.
 */
function carregarAmbienteNeo(?string $arquivo = null): void
{
    static $carregado = false;

    if ($carregado) {
        return;
    }

    $carregado = true;
    $arquivo = $arquivo ?? dirname(__DIR__) . '/.env';

    if (!is_file($arquivo) || !is_readable($arquivo)) {
        return;
    }

    foreach (file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $linha) {
        $linha = trim($linha);

        if ($linha === '' || str_starts_with($linha, '#') || !str_contains($linha, '=')) {
            continue;
        }

        [$chave, $valor] = array_map('trim', explode('=', $linha, 2));

        if ($chave === '' || getenv($chave) !== false) {
            continue;
        }

        if (strlen($valor) >= 2) {
            $primeiro = $valor[0];
            $ultimo = $valor[strlen($valor) - 1];

            if (($primeiro === '"' && $ultimo === '"') || ($primeiro === "'" && $ultimo === "'")) {
                $valor = substr($valor, 1, -1);
            }
        }

        putenv($chave . '=' . $valor);
        $_ENV[$chave] = $valor;
    }
}

function ambienteNeo(string $chave, mixed $padrao = null): mixed
{
    carregarAmbienteNeo();
    $valor = getenv($chave);

    return $valor === false || $valor === '' ? $padrao : $valor;
}

function ambienteBooleanoNeo(string $chave, bool $padrao = false): bool
{
    $valor = ambienteNeo($chave);

    if ($valor === null) {
        return $padrao;
    }

    return filter_var($valor, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $padrao;
}
