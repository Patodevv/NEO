from pathlib import Path

def remove_between(text, start_marker, selectors):
    # remove all blocks/comments related to the two special frame corrections and blue store override, then append clean versions
    lines = text.splitlines()
    out = []
    skip = False
    brace = 0
    for line in lines:
        starts = any(sel in line for sel in selectors) or any(marker in line for marker in start_marker)
        if not skip and starts:
            skip = True
            brace = line.count('{') - line.count('}')
            # If this is a comment only, continue skipping until next non-comment block starts/ends handled by selectors.
            if brace <= 0 and line.strip().startswith('/*'):
                continue
            if brace <= 0:
                skip = False
            continue
        if skip:
            brace += line.count('{') - line.count('}')
            if brace <= 0 and '}' in line:
                skip = False
            continue
        out.append(line)
    return '\n'.join(out).rstrip() + '\n'

loja = Path('static/pages/loja.css')
text = loja.read_text(encoding='utf-8')
text = remove_between(text, ['Ajuste específico:', 'Correção final das duas molduras', 'Loja: todos os cards'], ['moldura_asas_celestes', 'moldura_raposa_fogo', '--button-accent: var(--neo-blue) !important'])
text += '''

/* Asas Celestes e Raposa de Fogo têm aro interno menor: a foto fica só dentro do anel. */
.store-preview.moldura_asas_celestes .store-avatar-demo.has-frame-art .avatar-photo,
.store-preview.moldura_asas_celestes .store-avatar-demo.has-frame-art > span,
.store-preview.moldura_raposa_fogo .store-avatar-demo.has-frame-art .avatar-photo,
.store-preview.moldura_raposa_fogo .store-avatar-demo.has-frame-art > span {
    width: 58%;
    height: 58%;
}

.store-preview.moldura_asas_celestes .store-avatar-demo.has-frame-art > .profile-frame-art,
.store-preview.moldura_raposa_fogo .store-avatar-demo.has-frame-art > .profile-frame-art {
    width: 128%;
    height: 128%;
}

/* Loja: todos os cards, botões e estrelas usam o azul padrão no hover. */
.store-item.neo-star-hover,
.store-item-footer button.neo-star-hover {
    --button-accent: var(--neo-blue) !important;
    --hover-star-core: var(--neo-blue) !important;
    --hover-star-shadow: color-mix(in srgb, var(--neo-blue) 44%, #030824) !important;
    --hover-star-center-stroke: color-mix(in srgb, var(--neo-blue) 62%, #ffffff) !important;
    --hover-star-glow: var(--neo-blue-glow) !important;
}

.store-item.neo-star-hover:hover,
.store-item.neo-star-hover:focus-within,
.store-item-footer button.neo-star-hover:hover,
.store-item-footer button.neo-star-hover:focus-visible {
    color: var(--neo-blue) !important;
    border-color: var(--neo-blue) !important;
}

.store-item.neo-star-hover:hover .store-item-copy :where(span, h2),
.store-item.neo-star-hover:focus-within .store-item-copy :where(span, h2),
.store-item-footer button.neo-star-hover:hover svg:not(.icon-hover-star),
.store-item-footer button.neo-star-hover:focus-visible svg:not(.icon-hover-star) {
    color: var(--neo-blue) !important;
}
'''
loja.write_text(text, encoding='utf-8')

perfil = Path('static/pages/perfil.css')
text = perfil.read_text(encoding='utf-8')
text = remove_between(text, ['Ajuste específico:', 'Correção final das duas molduras'], ['moldura_asas_celestes', 'moldura_raposa_fogo'])
text += '''

/* Asas Celestes e Raposa de Fogo têm aro interno menor: a foto fica só dentro do anel. */
.profile-summary.moldura_asas_celestes .profile-avatar-ring:has(> .profile-frame-art) .profile-avatar,
.profile-summary.moldura_raposa_fogo .profile-avatar-ring:has(> .profile-frame-art) .profile-avatar,
.inventory-preview.moldura_asas_celestes .inventory-avatar-demo.has-frame-art .avatar-photo,
.inventory-preview.moldura_asas_celestes .inventory-avatar-demo.has-frame-art > span,
.inventory-preview.moldura_raposa_fogo .inventory-avatar-demo.has-frame-art .avatar-photo,
.inventory-preview.moldura_raposa_fogo .inventory-avatar-demo.has-frame-art > span {
    width: 58%;
    height: 58%;
}

.profile-summary.moldura_asas_celestes .profile-avatar-ring > .profile-frame-art,
.profile-summary.moldura_raposa_fogo .profile-avatar-ring > .profile-frame-art,
.inventory-preview.moldura_asas_celestes .inventory-avatar-demo.has-frame-art > .profile-frame-art,
.inventory-preview.moldura_raposa_fogo .inventory-avatar-demo.has-frame-art > .profile-frame-art {
    width: 128%;
    height: 128%;
}
'''
perfil.write_text(text, encoding='utf-8')

topbar = Path('static/topbar.css')
text = topbar.read_text(encoding='utf-8')
text = remove_between(text, ['Ajuste específico para molduras', 'Correção final das duas molduras'], ['frame-moldura_asas_celestes', 'frame-moldura_raposa_fogo'])
text += '''

/* Asas Celestes e Raposa de Fogo têm aro interno menor no avatar do topo. */
.topbar .profile.frame-moldura_asas_celestes.has-frame-art .topbar-profile-photo,
.topbar .profile.frame-moldura_raposa_fogo.has-frame-art .topbar-profile-photo {
    width: 58%;
    height: 58%;
}

.topbar .profile.frame-moldura_asas_celestes .profile-frame-art,
.topbar .profile.frame-moldura_raposa_fogo .profile-frame-art {
    width: 146%;
    height: 146%;
}
'''
topbar.write_text(text, encoding='utf-8')
