<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/config/db.php';

function limparPastaUploads(PDO $pdo, string $subpasta, string $consulta): int
{
    $base = realpath(dirname(__DIR__) . '/static/uploads/' . $subpasta);
    if ($base === false || !is_dir($base)) {
        return 0;
    }

    $referencias = [];
    foreach ($pdo->query($consulta)->fetchAll(PDO::FETCH_COLUMN) as $caminho) {
        $referencias[basename((string)$caminho)] = true;
    }

    $removidos = 0;
    foreach (new DirectoryIterator($base) as $arquivo) {
        if (!$arquivo->isFile() || $arquivo->getFilename() === '.gitkeep') {
            continue;
        }

        $caminhoReal = $arquivo->getRealPath();
        if ($caminhoReal === false || !str_starts_with($caminhoReal, $base . DIRECTORY_SEPARATOR)) {
            continue;
        }
        if (!isset($referencias[$arquivo->getFilename()])) {
            if (@unlink($caminhoReal)) {
                $removidos++;
            } else {
                error_log('[NEO][uploads] Não foi possível remover: ' . $caminhoReal);
            }
        }
    }

    return $removidos;
}

$perfis = limparPastaUploads($pdo, 'perfis', "SELECT foto FROM users WHERE foto IS NOT NULL AND foto <> ''");
$produtos = limparPastaUploads($pdo, 'produtos', "SELECT imagem FROM produtos WHERE imagem IS NOT NULL AND imagem <> ''");

echo "Uploads sem referência removidos: perfis={$perfis}, produtos={$produtos}.\n";
