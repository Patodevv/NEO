from pathlib import Path

# Revert global frame proportions to the previous correct defaults.
for file in ['static/pages/loja.css', 'static/pages/perfil.css']:
    p = Path(file)
    text = p.read_text(encoding='utf-8')
    text = text.replace('width: 82%;\n    height: 82%;', 'width: 68%;\n    height: 68%;')
    text = text.replace('width: 120%;\n    height: 120%;', 'width: 112%;\n    height: 112%;')
    p.write_text(text, encoding='utf-8')

p = Path('static/topbar.css')
text = p.read_text(encoding='utf-8')
text = text.replace('width: 82%;\n    height: 82%;', 'width: 68%;\n    height: 68%;')
text = text.replace('width: 138%;\n    height: 138%;', 'width: 128%;\n    height: 128%;')
p.write_text(text, encoding='utf-8')

# Add class with current frame code to the topbar avatar so specific fixes also apply there.
p = Path('includes/topbar.php')
text = p.read_text(encoding='utf-8')
old = """$decoracaoTopbarImagem = '';
if (isset($cosmeticosUsuario['items']['decoracao_perfil']) && is_array($cosmeticosUsuario['items']['decoracao_perfil'])) {
    $decoracaoTopbarImagem = trim((string)($cosmeticosUsuario['items']['decoracao_perfil']['imagem'] ?? ''));
}
"""
new = """$decoracaoTopbarImagem = '';
$decoracaoTopbarCodigo = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($usuario['decoracao_perfil'] ?? ''));
if (isset($cosmeticosUsuario['items']['decoracao_perfil']) && is_array($cosmeticosUsuario['items']['decoracao_perfil'])) {
    $decoracaoTopbarImagem = trim((string)($cosmeticosUsuario['items']['decoracao_perfil']['imagem'] ?? ''));
}
"""
text = text.replace(old, new)
old = """        <a href="perfil.php" class="profile<?= $decoracaoTopbarImagem !== '' ? ' has-frame-art' : '' ?>" aria-label="Abrir meu perfil" data-manel-tip="Abre seu perfil, nível e conquistas.">
"""
new = """        <a href="perfil.php" class="profile<?= $decoracaoTopbarImagem !== '' ? ' has-frame-art frame-' . htmlspecialchars($decoracaoTopbarCodigo, ENT_QUOTES, 'UTF-8') : '' ?>" aria-label="Abrir meu perfil" data-manel-tip="Abre seu perfil, nível e conquistas.">
"""
text = text.replace(old, new)
p.write_text(text, encoding='utf-8')
