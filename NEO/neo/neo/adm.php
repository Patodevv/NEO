<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/services/store.php';
exigirAdmin();

$mensagem = '';
$erro = '';
$usuarioEditar = null;
$produtoEditar = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $acao = $_POST['acao'] ?? '';

    try {
        if ($acao === 'salvar_usuario') {
            $id = (int)($_POST['id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            $email = mb_strtolower(trim($_POST['email'] ?? ''));
            $gostos = mb_substr(trim($_POST['gostos'] ?? ''), 0, 2000);
            $cossas = max(0, (int)($_POST['cossas'] ?? 0));
            $xp = max(0, (int)($_POST['xp'] ?? 0));
            $nivel = max(1, (int)($_POST['nivel'] ?? 1));
            $decoracao = trim($_POST['decoracao_perfil'] ?? '');
            if ($id <= 0 || $nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new DomainException('Informe nome e e-mail válidos.');
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT cossas FROM users WHERE id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $saldoAnterior = $stmt->fetchColumn();
            if ($saldoAnterior === false) {
                throw new DomainException('Usuário não encontrado.');
            }

            $pdo->prepare("UPDATE users SET nome = ?, email = ?, gostos = ?, xp = ?, nivel = ?, decoracao_perfil = ? WHERE id = ?")
                ->execute([$nome, $email, $gostos, $xp, $nivel, $decoracao !== '' ? $decoracao : null, $id]);
            $diferenca = $cossas - (int)$saldoAnterior;
            if ($diferenca !== 0) {
                alterarSaldoCossas($pdo, $id, $diferenca, 'ajuste_admin', 'usuario', (string)$id, 'admin:' . bin2hex(random_bytes(16)));
            }
            $pdo->commit();
            $mensagem = 'Conta atualizada com alteração de saldo rastreada.';
        } elseif ($acao === 'salvar_produto') {
            $id = (int)($_POST['produto_id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            $codigo = normalizarCodigoProduto((string)($_POST['codigo'] ?? $nome));
            $descricao = mb_substr(trim($_POST['descricao'] ?? ''), 0, 3000);
            $preco = max(0, (int)($_POST['preco_cossas'] ?? 0));
            $categoria = normalizarCodigoProduto((string)($_POST['categoria'] ?? 'item')) ?: 'item';
            $classe = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['classe_visual'] ?? '')) ?: null;
            $estoqueTexto = trim((string)($_POST['estoque'] ?? ''));
            $estoque = $estoqueTexto === '' ? null : max(0, (int)$estoqueTexto);
            $limiteTexto = trim((string)($_POST['limite_por_usuario'] ?? '1'));
            $limite = $limiteTexto === '' ? null : max(1, (int)$limiteTexto);
            $ativo = !empty($_POST['ativo']) ? 1 : 0;
            $temporario = !empty($_POST['temporario']) ? 1 : 0;
            $permanente = !empty($_POST['permanente']) ? 1 : 0;
            $de = trim((string)($_POST['disponivel_de'] ?? '')) ?: null;
            $ate = trim((string)($_POST['disponivel_ate'] ?? '')) ?: null;
            if ($nome === '' || $codigo === '') {
                throw new DomainException('Nome e código do produto são obrigatórios.');
            }
            if ($de && $ate && strtotime($de) > strtotime($ate)) {
                throw new DomainException('A data inicial deve ser anterior à data final.');
            }
            if (!$permanente && !$ate) {
                throw new DomainException('Produtos não permanentes precisam de uma data final para expiração do item.');
            }

            $imagem = salvarImagemProduto($_FILES['imagem'] ?? []);
            if ($id > 0) {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("SELECT codigo FROM produtos WHERE id = ? FOR UPDATE");
                $stmt->execute([$id]);
                $codigoAnterior = $stmt->fetchColumn();
                if ($codigoAnterior === false) {
                    throw new DomainException('Produto não encontrado.');
                }
                $stmt = $pdo->prepare("
                    UPDATE produtos SET codigo=?, nome=?, descricao=?, preco_cossas=?, categoria=?, classe_visual=?, estoque=?, ativo=?, temporario=?, permanente=?, disponivel_de=?, disponivel_ate=?, limite_por_usuario=?, imagem=COALESCE(?, imagem), removido_em=NULL
                    WHERE id=?
                ");
                $stmt->execute([$codigo, $nome, $descricao ?: null, $preco, $categoria, $classe, $estoque, $ativo, $temporario, $permanente, $de, $ate, $limite, $imagem, $id]);
                if ($codigoAnterior !== $codigo) {
                    $pdo->prepare("UPDATE compras_loja SET item_id = ? WHERE produto_id = ?")->execute([$codigo, $id]);
                    $pdo->prepare("UPDATE users SET decoracao_perfil = ? WHERE decoracao_perfil = ?")->execute([$codigo, $codigoAnterior]);
                }
                $pdo->commit();
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO produtos (codigo,nome,descricao,preco_cossas,categoria,classe_visual,estoque,ativo,temporario,permanente,disponivel_de,disponivel_ate,limite_por_usuario,imagem)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ");
                $stmt->execute([$codigo, $nome, $descricao ?: null, $preco, $categoria, $classe, $estoque, $ativo, $temporario, $permanente, $de, $ate, $limite, $imagem]);
            }
            $mensagem = 'Produto salvo e integrado à loja.';
        } elseif ($acao === 'remover_produto') {
            $id = (int)($_POST['produto_id'] ?? 0);
            $pdo->prepare("UPDATE produtos SET ativo = 0, removido_em = NOW() WHERE id = ?")->execute([$id]);
            $mensagem = 'Produto removido da loja sem apagar o histórico de compras.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e instanceof PDOException && $e->getCode() === '23000') {
            $erro = 'Já existe um registro com esse e-mail ou código.';
        } else {
            $erro = $e->getMessage();
        }
    }
}

$editarId = (int)($_GET['editar'] ?? 0);
if ($editarId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$editarId]);
    $usuarioEditar = $stmt->fetch(PDO::FETCH_ASSOC);
}

$produtoEditarId = (int)($_GET['produto'] ?? 0);
if ($produtoEditarId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ?");
    $stmt->execute([$produtoEditarId]);
    $produtoEditar = $stmt->fetch(PDO::FETCH_ASSOC);
}

$produtos = $pdo->query("SELECT * FROM produtos ORDER BY removido_em IS NOT NULL, ativo DESC, nome")->fetchAll(PDO::FETCH_ASSOC);

$usuarios = $pdo->query("
    SELECT u.*,
        (SELECT COUNT(*) FROM conteudos c WHERE c.user_id = u.id) AS total_conteudos,
        (SELECT COUNT(*) FROM historico h WHERE h.user_id = u.id) AS total_tentativas
    FROM users u
    ORDER BY u.criado_em DESC
")->fetchAll(PDO::FETCH_ASSOC);

$tituloPagina = 'Painel admin';
$cssPaginas = ['admin'];
require __DIR__ . '/includes/head.php';
?>
<main class="admin-main">
    <header class="admin-topbar">
        <div>
            <span class="eyebrow">NEOMIND</span>
            <h1>Painel admin</h1>
        </div>
        <a href="adm_logout.php" class="ghost">Sair</a>
    </header>

    <?php if ($mensagem): ?>
        <div class="msg-ok"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div class="error"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <?php if ($usuarioEditar): ?>
        <section class="admin-panel">
            <h2>Editar <?= htmlspecialchars($usuarioEditar['nome']) ?></h2>
            <form method="post" class="admin-form">
                <?= campoCsrf() ?>
                <input type="hidden" name="acao" value="salvar_usuario">
                <input type="hidden" name="id" value="<?= (int)$usuarioEditar['id'] ?>">
                <label>Nome<input name="nome" value="<?= htmlspecialchars($usuarioEditar['nome']) ?>" required></label>
                <label>Email<input type="email" name="email" value="<?= htmlspecialchars($usuarioEditar['email']) ?>" required></label>
                <label>Gostos<textarea name="gostos" rows="4"><?= htmlspecialchars($usuarioEditar['gostos'] ?? '') ?></textarea></label>
                <label>Coças<input type="number" name="cossas" min="0" value="<?= (int)($usuarioEditar['cossas'] ?? 0) ?>"></label>
                <label>XP<input type="number" name="xp" min="0" value="<?= (int)($usuarioEditar['xp'] ?? 0) ?>"></label>
                <label>Nível<input type="number" name="nivel" min="1" value="<?= (int)($usuarioEditar['nivel'] ?? 1) ?>"></label>
                <label>Decoração
                    <select name="decoracao_perfil">
                        <option value="">Nenhuma</option>
                        <?php foreach ($produtos as $produto): ?>
                            <?php if ($produto['categoria'] === 'decoracao_perfil' && empty($produto['removido_em'])): ?>
                                <option value="<?= htmlspecialchars($produto['codigo']) ?>" <?= ($usuarioEditar['decoracao_perfil'] ?? '') === $produto['codigo'] ? 'selected' : '' ?>><?= htmlspecialchars($produto['nome']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="primary">Salvar alterações</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="admin-panel">
        <h2><?= $produtoEditar ? 'Editar produto' : 'Criar produto' ?></h2>
        <form method="post" enctype="multipart/form-data" class="admin-form">
            <?= campoCsrf() ?>
            <input type="hidden" name="acao" value="salvar_produto">
            <input type="hidden" name="produto_id" value="<?= (int)($produtoEditar['id'] ?? 0) ?>">
            <label>Nome<input name="nome" maxlength="150" value="<?= htmlspecialchars($produtoEditar['nome'] ?? '') ?>" required></label>
            <label>Código<input name="codigo" maxlength="100" value="<?= htmlspecialchars($produtoEditar['codigo'] ?? '') ?>" placeholder="gerado a partir do nome"></label>
            <label>Descrição<textarea name="descricao" rows="3"><?= htmlspecialchars($produtoEditar['descricao'] ?? '') ?></textarea></label>
            <label>Preço em Coças<input type="number" name="preco_cossas" min="0" value="<?= (int)($produtoEditar['preco_cossas'] ?? 0) ?>" required></label>
            <label>Categoria<input name="categoria" maxlength="80" value="<?= htmlspecialchars($produtoEditar['categoria'] ?? 'decoracao_perfil') ?>" required></label>
            <label>Classe visual existente<input name="classe_visual" maxlength="50" value="<?= htmlspecialchars($produtoEditar['classe_visual'] ?? '') ?>" placeholder="gold, neon ou focus"></label>
            <label>Estoque<input type="number" name="estoque" min="0" value="<?= $produtoEditar && $produtoEditar['estoque'] !== null ? (int)$produtoEditar['estoque'] : '' ?>" placeholder="vazio = ilimitado"></label>
            <label>Limite por usuário<input type="number" name="limite_por_usuario" min="1" value="<?= $produtoEditar && $produtoEditar['limite_por_usuario'] !== null ? (int)$produtoEditar['limite_por_usuario'] : 1 ?>" placeholder="vazio = ilimitado"></label>
            <label>Disponível de<input type="datetime-local" name="disponivel_de" value="<?= !empty($produtoEditar['disponivel_de']) ? htmlspecialchars(date('Y-m-d\TH:i', strtotime($produtoEditar['disponivel_de']))) : '' ?>"></label>
            <label>Disponível até<input type="datetime-local" name="disponivel_ate" value="<?= !empty($produtoEditar['disponivel_ate']) ? htmlspecialchars(date('Y-m-d\TH:i', strtotime($produtoEditar['disponivel_ate']))) : '' ?>"></label>
            <label>Imagem/ícone<input type="file" name="imagem" accept="image/png,image/jpeg,image/webp,image/gif"></label>
            <label><input type="checkbox" name="ativo" value="1" <?= !$produtoEditar || !empty($produtoEditar['ativo']) ? 'checked' : '' ?>> Ativo na loja</label>
            <label><input type="checkbox" name="temporario" value="1" <?= !empty($produtoEditar['temporario']) ? 'checked' : '' ?>> Produto temporário</label>
            <label><input type="checkbox" name="permanente" value="1" <?= !$produtoEditar || !empty($produtoEditar['permanente']) ? 'checked' : '' ?>> Item permanente na conta</label>
            <button type="submit" class="primary">Salvar produto</button>
        </form>
    </section>

    <section class="admin-panel">
        <h2>Produtos da loja</h2>
        <div class="admin-table">
            <?php foreach ($produtos as $produto): ?>
                <div class="admin-row">
                    <div class="admin-user"><div><b><?= htmlspecialchars($produto['nome']) ?></b><small><?= htmlspecialchars($produto['codigo']) ?> • <?= htmlspecialchars($produto['categoria']) ?></small></div></div>
                    <span><?= (int)$produto['preco_cossas'] ?> coças</span>
                    <span><?= $produto['estoque'] === null ? 'Estoque ilimitado' : (int)$produto['estoque'] . ' em estoque' ?></span>
                    <span><?= !empty($produto['ativo']) && empty($produto['removido_em']) ? 'Ativo' : 'Inativo' ?></span>
                    <a class="ghost" href="adm.php?produto=<?= (int)$produto['id'] ?>">Editar</a>
                    <?php if (empty($produto['removido_em'])): ?>
                        <form method="post">
                            <?= campoCsrf() ?>
                            <input type="hidden" name="acao" value="remover_produto">
                            <input type="hidden" name="produto_id" value="<?= (int)$produto['id'] ?>">
                            <button type="submit" class="ghost">Remover</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="admin-panel">
        <h2>Usuários cadastrados</h2>
        <div class="admin-table">
            <?php foreach ($usuarios as $u): ?>
                <div class="admin-row">
                    <div class="admin-user">
                        <span class="profile">
                            <?php if (!empty($u['foto'])): ?>
                                <img src="<?= htmlspecialchars($u['foto']) ?>" alt="">
                            <?php else: ?>
                                <?= htmlspecialchars(strtoupper(substr($u['nome'], 0, 1))) ?>
                            <?php endif; ?>
                        </span>
                        <div>
                            <b><?= htmlspecialchars($u['nome']) ?></b>
                            <small><?= htmlspecialchars($u['email']) ?></small>
                        </div>
                    </div>
                    <span><?= saldoCossasVisual($u) ?> coças</span>
                    <span>Level <?= (int)($u['nivel'] ?? 1) ?></span>
                    <span><?= (int)$u['total_conteudos'] ?> conteúdos</span>
                    <span><?= (int)$u['total_tentativas'] ?> tentativas</span>
                    <a class="ghost" href="adm.php?editar=<?= (int)$u['id'] ?>">Editar</a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</main>
</body>
</html>
