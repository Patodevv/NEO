<?php

require_once __DIR__ . '/economy.php';

function produtoDisponivel(array $produto, ?DateTimeImmutable $agora = null): bool
{
    $agora = $agora ?? new DateTimeImmutable();
    if (empty($produto['ativo']) || !empty($produto['removido_em'])) {
        return false;
    }
    if (!empty($produto['disponivel_de']) && $agora < new DateTimeImmutable($produto['disponivel_de'])) {
        return false;
    }
    if (!empty($produto['disponivel_ate']) && $agora > new DateTimeImmutable($produto['disponivel_ate'])) {
        return false;
    }
    return $produto['estoque'] === null || (int)$produto['estoque'] > 0;
}

function listarProdutosDisponiveis(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT * FROM produtos
        WHERE ativo = 1
          AND removido_em IS NULL
          AND (disponivel_de IS NULL OR disponivel_de <= NOW())
          AND (disponivel_ate IS NULL OR disponivel_ate >= NOW())
          AND (estoque IS NULL OR estoque > 0)
        ORDER BY categoria, nome, id
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function metadadosProduto(array $produto): array
{
    $dados = json_decode((string)($produto['metadados_json'] ?? ''), true);
    return is_array($dados) ? $dados : [];
}

function produtoEquipavel(string $categoria): bool
{
    return in_array($categoria, ['decoracao_perfil', 'tema_site', 'skin_manel', 'cor_nome'], true);
}

function campoUsuarioCosmetico(string $categoria): ?string
{
    return match ($categoria) {
        'decoracao_perfil' => 'decoracao_perfil',
        'tema_site' => 'tema_site',
        'skin_manel' => 'skin_manel',
        'cor_nome' => 'cor_nome',
        default => null,
    };
}

function usuarioTemProduto(PDO $pdo, int $userId, int $produtoId): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM compras_loja
        WHERE user_id = ? AND produto_id = ? AND status = 'concluida'
          AND (expira_em IS NULL OR expira_em >= NOW())
    ");
    $stmt->execute([$userId, $produtoId]);
    return (int)$stmt->fetchColumn() > 0;
}

function aplicarItemCosmetico(PDO $pdo, int $userId, int $produtoId): void
{
    $stmt = $pdo->prepare("
        SELECT p.codigo, p.categoria
        FROM produtos p
        JOIN compras_loja cl ON cl.produto_id = p.id
        WHERE p.id = ? AND cl.user_id = ? AND cl.status = 'concluida'
          AND (cl.expira_em IS NULL OR cl.expira_em >= NOW())
        LIMIT 1
    ");
    $stmt->execute([$produtoId, $userId]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$produto) {
        throw new DomainException('Esse item ainda nao esta no seu inventario.');
    }

    $campo = campoUsuarioCosmetico((string)$produto['categoria']);
    if ($campo === null) {
        throw new DomainException('Esse item nao pode ser equipado.');
    }

    $pdo->prepare("UPDATE users SET `{$campo}` = ? WHERE id = ?")->execute([$produto['codigo'], $userId]);
}

function removerItemCosmetico(PDO $pdo, int $userId, string $categoria): void
{
    $campo = campoUsuarioCosmetico($categoria);
    if ($campo === null) {
        throw new DomainException('Esse tipo de item não pode ser removido.');
    }

    $pdo->prepare("UPDATE users SET `{$campo}` = NULL WHERE id = ?")->execute([$userId]);
}

function removerProdutoCompleto(PDO $pdo, int $produtoId): ?array
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ? FOR UPDATE");
        $stmt->execute([$produtoId]);
        $produto = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$produto) {
            $pdo->commit();
            return null;
        }

        $codigo = (string)$produto['codigo'];
        foreach (['decoracao_perfil', 'tema_site', 'skin_manel', 'cor_nome'] as $categoria) {
            $campo = campoUsuarioCosmetico($categoria);
            if ($campo !== null && colunaExiste($pdo, 'users', $campo)) {
                $pdo->prepare("UPDATE users SET `{$campo}` = NULL WHERE `{$campo}` = ?")->execute([$codigo]);
            }
        }

        $pdo->prepare("DELETE FROM compras_loja WHERE produto_id = ? OR item_id = ?")->execute([$produtoId, $codigo]);
        $pdo->prepare("DELETE FROM produtos WHERE id = ?")->execute([$produtoId]);
        $pdo->commit();

        removerImagemUpload((string)($produto['imagem'] ?? ''), 'produtos');
        return $produto;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function produtoEquipadoPorUsuario(array $usuario, array $produto): bool
{
    $campo = campoUsuarioCosmetico((string)($produto['categoria'] ?? ''));
    return $campo !== null && (string)($usuario[$campo] ?? '') === (string)($produto['codigo'] ?? '');
}

function comprasUsuarioPorProduto(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("
        SELECT produto_id, COUNT(*) quantidade
        FROM compras_loja
        WHERE user_id = ? AND status = 'concluida' AND produto_id IS NOT NULL
          AND (expira_em IS NULL OR expira_em >= NOW())
        GROUP BY produto_id
    ");
    $stmt->execute([$userId]);
    $resultado = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
        $resultado[(int)$linha['produto_id']] = (int)$linha['quantidade'];
    }
    return $resultado;
}

function comprarProduto(PDO $pdo, int $userId, int $produtoId, string $idempotencyKey): array
{
    if (!preg_match('/^[a-f0-9]{32,64}$/', $idempotencyKey)) {
        throw new DomainException('Identificador de compra invalido. Atualize a pagina e tente novamente.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM compras_loja WHERE user_id = ? AND idempotency_key = ?");
        $stmt->execute([$userId, $idempotencyKey]);
        if ($existente = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->commit();
            return ['compra_id' => (int)$existente['id'], 'ja_processada' => true, 'produto_id' => (int)$existente['produto_id']];
        }

        $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ? FOR UPDATE");
        $stmt->execute([$produtoId]);
        $produto = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$produto || !produtoDisponivel($produto)) {
            throw new DomainException('Este produto nao esta disponivel no momento.');
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM compras_loja WHERE user_id = ? AND produto_id = ? AND status = 'concluida'");
        $stmt->execute([$userId, $produtoId]);
        $comprasAnteriores = (int)$stmt->fetchColumn();
        $limite = $produto['limite_por_usuario'] === null ? null : (int)$produto['limite_por_usuario'];
        if ($limite !== null && $comprasAnteriores >= $limite) {
            throw new DomainException('Este item ja atingiu o limite de compras da sua conta.');
        }

        $preco = (int)$produto['preco_cossas'];
        $transacao = $preco > 0
            ? alterarSaldoCossas($pdo, $userId, -$preco, 'compra_loja', 'produto', (string)$produtoId, 'compra:' . $idempotencyKey, ['codigo' => $produto['codigo']])
            : null;

        if ($produto['estoque'] !== null) {
            $stmt = $pdo->prepare("UPDATE produtos SET estoque = estoque - 1 WHERE id = ? AND estoque > 0");
            $stmt->execute([$produtoId]);
            if ($stmt->rowCount() !== 1) {
                throw new DomainException('O estoque deste produto terminou.');
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO compras_loja
                (user_id, item_id, produto_id, quantidade, preco_pago, status, idempotency_key, transacao_cossas_id, expira_em)
            VALUES (?, ?, ?, 1, ?, 'concluida', ?, ?, ?)
        ");
        $expiraEm = empty($produto['permanente']) ? ($produto['disponivel_ate'] ?: null) : null;
        $stmt->execute([$userId, $produto['codigo'], $produtoId, $preco, $idempotencyKey, $transacao['id'] ?? null, $expiraEm]);
        $compraId = (int)$pdo->lastInsertId();

        $campoCosmetico = campoUsuarioCosmetico((string)$produto['categoria']);
        if ($campoCosmetico !== null) {
            $pdo->prepare("UPDATE users SET `{$campoCosmetico}` = ? WHERE id = ?")->execute([$produto['codigo'], $userId]);
        }

        $pdo->commit();
        return ['compra_id' => $compraId, 'ja_processada' => false, 'produto' => $produto, 'saldo_apos' => $transacao['saldo_apos'] ?? null];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function inventarioUsuario(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("
        SELECT p.*, COUNT(cl.id) quantidade_comprada, MAX(cl.criado_em) comprado_em
        FROM compras_loja cl
        JOIN produtos p ON p.id = cl.produto_id
        WHERE cl.user_id = ? AND cl.status = 'concluida' AND (cl.expira_em IS NULL OR cl.expira_em >= NOW())
        GROUP BY p.id
        ORDER BY comprado_em DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aplicarDecoracaoPerfil(PDO $pdo, int $userId, int $produtoId): void
{
    aplicarItemCosmetico($pdo, $userId, $produtoId);
}

function salvarImagemUploadSeguro(array $arquivo, string $subpasta, string $prefixo = ''): string
{
    if (($arquivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new DomainException('Nao foi possivel receber a imagem.');
    }

    $permitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime = mime_content_type($arquivo['tmp_name']);
    $dimensoes = @getimagesize($arquivo['tmp_name']);
    $largura = is_array($dimensoes) ? (int)($dimensoes[0] ?? 0) : 0;
    $altura = is_array($dimensoes) ? (int)($dimensoes[1] ?? 0) : 0;

    if (!isset($permitidos[$mime]) || (int)$arquivo['size'] <= 0 || (int)$arquivo['size'] > 2 * 1024 * 1024) {
        throw new DomainException('Use uma imagem JPG, PNG, WEBP ou GIF de ate 2 MB.');
    }
    if ($largura <= 0 || $altura <= 0 || $largura > 4096 || $altura > 4096 || $largura * $altura > 16000000) {
        throw new DomainException('A imagem possui dimensoes invalidas ou muito grandes.');
    }

    if (!preg_match('/^[a-z0-9_-]+$/', $subpasta)) {
        throw new InvalidArgumentException('Pasta de upload invalida.');
    }

    $pasta = dirname(__DIR__) . '/static/uploads/' . $subpasta;
    if (!is_dir($pasta) && !mkdir($pasta, 0775, true) && !is_dir($pasta)) {
        throw new RuntimeException('Nao foi possivel criar a pasta de imagens.');
    }
    $prefixo = preg_replace('/[^a-zA-Z0-9_-]/', '', $prefixo) ?? '';
    $nome = $prefixo . bin2hex(random_bytes(16)) . '.' . $permitidos[$mime];
    if (!move_uploaded_file($arquivo['tmp_name'], $pasta . '/' . $nome)) {
        throw new RuntimeException('Nao foi possivel salvar a imagem.');
    }
    return 'static/uploads/' . $subpasta . '/' . $nome;
}

function removerImagemUpload(?string $caminhoPublico, string $subpasta): void
{
    $caminhoPublico = trim((string)$caminhoPublico);
    $prefixo = 'static/uploads/' . $subpasta . '/';
    if ($caminhoPublico === '' || !str_starts_with($caminhoPublico, $prefixo)) {
        return;
    }

    $nome = basename($caminhoPublico);
    if ($caminhoPublico !== $prefixo . $nome) {
        return;
    }

    $arquivo = dirname(__DIR__) . '/' . $prefixo . $nome;
    if (is_file($arquivo)) {
        @unlink($arquivo);
    }
}

function salvarImagemProduto(array $arquivo): ?string
{
    if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    return salvarImagemUploadSeguro($arquivo, 'produtos');
}

function normalizarCodigoProduto(string $codigo): string
{
    $codigo = mb_strtolower(trim($codigo));
    $codigo = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $codigo) ?: $codigo;
    $codigo = preg_replace('/[^a-z0-9]+/', '_', $codigo) ?? '';
    return trim($codigo, '_');
}

function rotuloCategoriaProduto(string $categoria): string
{
    $rotulos = [
        'decoracao_perfil' => 'Decoração de perfil',
        'tema_site' => 'Tema do site',
        'skin_manel' => 'Skin do Manel',
        'cor_nome' => 'Cor do nome',
        'item' => 'Item especial',
        'multiplicador' => 'Multiplicador',
    ];

    return $rotulos[$categoria] ?? ucfirst(str_replace('_', ' ', $categoria));
}

function produtoAtivoPorCodigo(PDO $pdo, string $codigo, string $categoria): ?array
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM produtos
        WHERE codigo = ? AND categoria = ? AND removido_em IS NULL
        LIMIT 1
    ");
    $stmt->execute([$codigo, $categoria]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($produto) ? $produto : null;
}

function cosmeticosAtivosUsuario(PDO $pdo, array $usuario): array
{
    $resultado = [
        'decoracao_perfil' => null,
        'tema_site' => null,
        'skin_manel' => null,
        'cor_nome' => null,
    ];

    foreach ($resultado as $categoria => $_) {
        $campo = campoUsuarioCosmetico($categoria);
        $codigo = $campo !== null ? trim((string)($usuario[$campo] ?? '')) : '';
        if ($codigo === '') {
            continue;
        }
        $produto = produtoAtivoPorCodigo($pdo, $codigo, $categoria);
        if ($produto) {
            $produto['metadados'] = metadadosProduto($produto);
            $resultado[$categoria] = $produto;
        }
    }

    return $resultado;
}

function valorCssSeguro(?string $valor, string $padrao = ''): string
{
    $valor = trim((string)$valor);
    if ($valor === '') {
        return $padrao;
    }
    if (preg_match('/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?$/', $valor)) {
        return $valor;
    }
    if (preg_match('/^rgba?\(\s*[0-9]{1,3}\s*,\s*[0-9]{1,3}\s*,\s*[0-9]{1,3}(?:\s*,\s*(?:0|1|0?\.\d+|\.\d+))?\s*\)$/', $valor)) {
        return $valor;
    }
    return $padrao;
}

function corCssParaRgb(string $valor): ?array
{
    $valor = trim($valor);
    if (preg_match('/^#([0-9a-fA-F]{3})$/', $valor, $m)) {
        return [
            hexdec(str_repeat($m[1][0], 2)),
            hexdec(str_repeat($m[1][1], 2)),
            hexdec(str_repeat($m[1][2], 2)),
        ];
    }
    if (preg_match('/^#([0-9a-fA-F]{6})$/', $valor, $m)) {
        return [
            hexdec(substr($m[1], 0, 2)),
            hexdec(substr($m[1], 2, 2)),
            hexdec(substr($m[1], 4, 2)),
        ];
    }
    if (preg_match('/^rgba?\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})/i', $valor, $m)) {
        return [
            max(0, min(255, (int)$m[1])),
            max(0, min(255, (int)$m[2])),
            max(0, min(255, (int)$m[3])),
        ];
    }
    return null;
}

function matizRgb(array $rgb): float
{
    [$r, $g, $b] = array_map(static fn ($valor) => max(0, min(255, (int)$valor)) / 255, $rgb);
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $delta = $max - $min;
    if ($delta == 0.0) {
        return 0.0;
    }
    if ($max === $r) {
        $hue = 60 * fmod((($g - $b) / $delta), 6);
    } elseif ($max === $g) {
        $hue = 60 * ((($b - $r) / $delta) + 2);
    } else {
        $hue = 60 * ((($r - $g) / $delta) + 4);
    }
    return $hue < 0 ? $hue + 360 : $hue;
}

function saturacaoRgb(array $rgb): float
{
    [$r, $g, $b] = array_map(static fn ($valor) => max(0, min(255, (int)$valor)) / 255, $rgb);
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    return $max <= 0 ? 0.0 : ($max - $min) / $max;
}

function diferencaMatiz(float $destino, float $origem): float
{
    $diferenca = fmod($destino - $origem + 540, 360) - 180;
    return round($diferenca, 1);
}

function estiloCosmeticosUsuario(PDO $pdo, array $usuario): array
{
    $cosmeticos = cosmeticosAtivosUsuario($pdo, $usuario);
    $vars = [];

    $tema = $cosmeticos['tema_site']['metadados'] ?? [];
    if ($tema) {
        foreach ([
            'accent' => '--neo-blue',
            'accent2' => '--neo-blue-hover-strong',
            'page_bg' => '--page-bg',
            'panel_bg' => '--panel-bg',
            'control_bg' => '--control-bg',
            'border' => '--panel-border',
        ] as $origem => $destino) {
            $valor = valorCssSeguro($tema[$origem] ?? '');
            if ($valor !== '') {
                $vars[$destino] = $valor;
                if ($origem === 'accent') {
                    $vars['--btn-icon'] = $valor;
                    $vars['--neo-blue-glow'] = 'color-mix(in srgb, ' . $valor . ' 34%, transparent)';
                    $vars['--neo-blue-hover'] = 'color-mix(in srgb, ' . $valor . ' 22%, transparent)';
                    $rgb = corCssParaRgb($valor);
                    if ($rgb !== null) {
                        if (saturacaoRgb($rgb) < .08) {
                            $vars['--neo-theme-image-filter'] = 'grayscale(1) saturate(.22) brightness(1.06)';
                            $vars['--neo-theme-image-tint-opacity'] = '.46';
                        } else {
                            $delta = diferencaMatiz(matizRgb($rgb), matizRgb([26, 46, 255]));
                            $vars['--neo-theme-image-filter'] = 'hue-rotate(' . $delta . 'deg) saturate(1.28) brightness(1.04)';
                            $vars['--neo-theme-image-tint-opacity'] = '.78';
                        }
                        $vars['--neo-theme-image-tint'] = 'rgba(' . implode(', ', $rgb) . ', .32)';
                    }
                }
            }
        }
    }

    $skin = $cosmeticos['skin_manel']['metadados'] ?? [];
    $corManel = valorCssSeguro($skin['manel_color'] ?? '');
    if ($corManel !== '') {
        $vars['--manel-color'] = $corManel;
    }
    $brilhoManel = valorCssSeguro($skin['manel_glow'] ?? '');
    if ($brilhoManel !== '') {
        $vars['--manel-glow'] = $brilhoManel;
    }

    $nome = $cosmeticos['cor_nome']['metadados'] ?? [];
    $corNome = valorCssSeguro($nome['name_color'] ?? '');
    if ($corNome !== '') {
        $vars['--profile-name-color'] = $corNome;
    }
    return ['vars' => $vars, 'items' => $cosmeticos];
}

function estiloInlineVars(array $vars): string
{
    $partes = [];
    foreach ($vars as $nome => $valor) {
        if (preg_match('/^--[a-z0-9-]+$/', (string)$nome) && trim((string)$valor) !== '') {
            $partes[] = $nome . ': ' . $valor;
        }
    }
    return implode('; ', $partes);
}

function varianteManelClasse(?string $variante): string
{
    $variante = normalizarCodigoProduto((string)$variante);
    return $variante !== '' ? 'manel-variant-' . $variante : '';
}

function decoracoesRostoManelSvg(): string
{
    return '
        <g class="neo-companion-lashes" aria-hidden="true">
            <path d="M 92 73 L 80 58"></path>
            <path d="M 107 66 L 103 49"></path>
            <path d="M 122 73 L 133 58"></path>
            <path d="M 178 73 L 166 58"></path>
            <path d="M 193 66 L 197 49"></path>
            <path d="M 208 73 L 220 58"></path>
        </g>
        <g class="neo-companion-glasses" aria-hidden="true">
            <circle cx="110" cy="100" r="29"></circle>
            <circle cx="190" cy="100" r="29"></circle>
            <path d="M 139 100 H 161"></path>
            <path d="M 81 96 L 62 88"></path>
            <path d="M 219 96 L 238 88"></path>
        </g>
        <g class="neo-companion-freckles" aria-hidden="true">
            <circle cx="88" cy="143" r="4"></circle>
            <circle cx="105" cy="154" r="3.5"></circle>
            <circle cx="118" cy="139" r="3"></circle>
            <circle cx="182" cy="139" r="3"></circle>
            <circle cx="195" cy="154" r="3.5"></circle>
            <circle cx="212" cy="143" r="4"></circle>
        </g>';
}
