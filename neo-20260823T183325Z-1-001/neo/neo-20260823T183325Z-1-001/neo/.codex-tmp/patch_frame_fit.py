from pathlib import Path

# services/store.php: decoration must not export global visual vars
p = Path('services/store.php')
text = p.read_text(encoding='utf-8')
old = """
    $decoracao = $cosmeticos['decoracao_perfil']['metadados'] ?? [];
    foreach (['accent' => '--profile-accent', 'accent2' => '--profile-accent-2'] as $origem => $destino) {
        $valor = valorCssSeguro($decoracao[$origem] ?? '');
        if ($valor !== '') {
            $vars[$destino] = $valor;
        }
    }

"""
text = text.replace(old, '')
p.write_text(text, encoding='utf-8')

# perfil.php: no inline color vars for equipped decoration, and no store/button accent for decoration items
p = Path('perfil.php')
text = p.read_text(encoding='utf-8')
old = """$decoracaoDadosAtual = is_array($decoracaoProdutoAtual) ? ($decoracaoProdutoAtual['metadados'] ?? []) : [];
$decoracaoAccent = valorCssSeguro($decoracaoDadosAtual['accent'] ?? '');
$decoracaoAccent2 = valorCssSeguro($decoracaoDadosAtual['accent2'] ?? '');
$decoracaoImagemAtual = is_array($decoracaoProdutoAtual) ? trim((string)($decoracaoProdutoAtual['imagem'] ?? '')) : '';
$decoracaoInline = trim(
    ($decoracaoAccent !== '' ? '--profile-accent: ' . $decoracaoAccent . '; ' : '')
    . ($decoracaoAccent2 !== '' ? '--profile-accent-2: ' . $decoracaoAccent2 . '; ' : '')
);
"""
new = """$decoracaoImagemAtual = is_array($decoracaoProdutoAtual) ? trim((string)($decoracaoProdutoAtual['imagem'] ?? '')) : '';
$decoracaoInline = '';
"""
text = text.replace(old, new)
old = """                        $accentItem = valorCssSeguro($itemDados['accent'] ?? ($itemDados['manel_color'] ?? ($itemDados['name_color'] ?? '')));
                        $accent2Item = valorCssSeguro($itemDados['accent2'] ?? '');
                        $styleItem = trim(($accentItem !== '' ? '--store-accent: ' . $accentItem . '; --profile-accent: ' . $accentItem . '; ' : '') . ($accent2Item !== '' ? '--profile-accent-2: ' . $accent2Item . '; ' : ''));
"""
new = """                        $accentItem = $item['categoria'] === 'decoracao_perfil' ? '' : valorCssSeguro($itemDados['accent'] ?? ($itemDados['manel_color'] ?? ($itemDados['name_color'] ?? '')));
                        $accent2Item = $item['categoria'] === 'decoracao_perfil' ? '' : valorCssSeguro($itemDados['accent2'] ?? '');
                        $styleItem = trim(($accentItem !== '' ? '--store-accent: ' . $accentItem . '; --profile-accent: ' . $accentItem . '; ' : '') . ($accent2Item !== '' ? '--profile-accent-2: ' . $accent2Item . '; ' : ''));
"""
text = text.replace(old, new)
p.write_text(text, encoding='utf-8')

# loja.php: decoration items should not color card/button through metadata
p = Path('loja.php')
text = p.read_text(encoding='utf-8')
old = """                $accentPreview = valorCssSeguro($metadadosProdutoLoja['accent'] ?? ($metadadosProdutoLoja['manel_color'] ?? ($metadadosProdutoLoja['name_color'] ?? '')));
                $accent2Preview = valorCssSeguro($metadadosProdutoLoja['accent2'] ?? '');
                $stylePreview = trim(($accentPreview !== '' ? '--store-accent: ' . $accentPreview . '; --profile-accent: ' . $accentPreview . '; ' : '') . ($accent2Preview !== '' ? '--profile-accent-2: ' . $accent2Preview . '; ' : ''));
"""
new = """                $accentPreview = $produto['categoria'] === 'decoracao_perfil' ? '' : valorCssSeguro($metadadosProdutoLoja['accent'] ?? ($metadadosProdutoLoja['manel_color'] ?? ($metadadosProdutoLoja['name_color'] ?? '')));
                $accent2Preview = $produto['categoria'] === 'decoracao_perfil' ? '' : valorCssSeguro($metadadosProdutoLoja['accent2'] ?? '');
                $stylePreview = trim(($accentPreview !== '' ? '--store-accent: ' . $accentPreview . '; --profile-accent: ' . $accentPreview . '; ' : '') . ($accent2Preview !== '' ? '--profile-accent-2: ' . $accent2Preview . '; ' : ''));
"""
text = text.replace(old, new)
p.write_text(text, encoding='utf-8')

# includes/topbar.php: apply active profile frame to corner avatar
p = Path('includes/topbar.php')
text = p.read_text(encoding='utf-8')
marker = "$chaveImagemTituloAtiva = null;\n"
addition = """$decoracaoTopbarImagem = '';
if (isset($cosmeticosUsuario['items']['decoracao_perfil']) && is_array($cosmeticosUsuario['items']['decoracao_perfil'])) {
    $decoracaoTopbarImagem = trim((string)($cosmeticosUsuario['items']['decoracao_perfil']['imagem'] ?? ''));
}
"""
if '$decoracaoTopbarImagem' not in text[:text.find('$diasOfensivaTopbar')]:
    text = text.replace(marker, marker + addition)
old = """        <a href="perfil.php" class="profile" aria-label="Abrir meu perfil" data-manel-tip="Abre seu perfil, nível e conquistas.">
        <?php if (!empty($usuario['foto'])): ?>
            <img src="<?= htmlspecialchars($usuario['foto']) ?>" alt="" decoding="async">
        <?php else: ?>
            <?= htmlspecialchars(strtoupper(substr($usuario['nome'], 0, 1))) ?>
        <?php endif; ?>
        </a>
"""
new = """        <a href="perfil.php" class="profile<?= $decoracaoTopbarImagem !== '' ? ' has-frame-art' : '' ?>" aria-label="Abrir meu perfil" data-manel-tip="Abre seu perfil, nível e conquistas.">
            <span class="topbar-profile-photo">
                <?php if (!empty($usuario['foto'])): ?>
                    <img src="<?= htmlspecialchars($usuario['foto']) ?>" alt="" decoding="async">
                <?php else: ?>
                    <?= htmlspecialchars(strtoupper(substr($usuario['nome'], 0, 1))) ?>
                <?php endif; ?>
            </span>
            <?php if ($decoracaoTopbarImagem !== ''): ?>
                <img class="profile-frame-art" src="<?= htmlspecialchars($decoracaoTopbarImagem) ?>" alt="" decoding="async">
            <?php endif; ?>
        </a>
"""
text = text.replace(old, new)
p.write_text(text, encoding='utf-8')
