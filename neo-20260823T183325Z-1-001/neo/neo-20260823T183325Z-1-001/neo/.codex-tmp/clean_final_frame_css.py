from pathlib import Path

files = [Path('static/pages/loja.css'), Path('static/pages/perfil.css'), Path('static/topbar.css')]
markers = [
    '/* Asas Celestes e Raposa de Fogo têm aro interno menor',
    '/* Encaixe seguro das molduras de perfil',
    '/* Encaixe final inspirado na referência',
]

def trim_old_frame_blocks(text):
    earliest = len(text)
    for marker in markers:
        pos = text.find(marker)
        if pos != -1:
            earliest = min(earliest, pos)
    if earliest != len(text):
        text = text[:earliest].rstrip() + '\n'
    return text

final_css = r'''

/* Molduras de perfil normalizadas individualmente: foto atrás, anel por cima, centro único. */
.store-avatar-demo.has-frame-art,
.inventory-avatar-demo.has-frame-art,
.profile-avatar-ring:has(> .profile-frame-art),
.topbar .profile.has-frame-art {
    --frame-photo-size: 72%;
    --frame-art-size: 132%;
    position: relative;
    overflow: visible;
    contain: layout;
    isolation: isolate;
}

.store-avatar-demo.has-frame-art .avatar-photo,
.store-avatar-demo.has-frame-art > span,
.inventory-avatar-demo.has-frame-art .avatar-photo,
.inventory-avatar-demo.has-frame-art > span,
.profile-avatar-ring:has(> .profile-frame-art) .profile-avatar,
.topbar .profile.has-frame-art .topbar-profile-photo {
    position: absolute;
    left: 50% !important;
    top: 50% !important;
    width: var(--frame-photo-size) !important;
    height: var(--frame-photo-size) !important;
    aspect-ratio: 1 / 1;
    overflow: hidden !important;
    border-radius: 999px !important;
    clip-path: circle(50% at 50% 50%);
    transform: translate(-50%, -50%) !important;
    z-index: 1;
}

.store-avatar-demo.has-frame-art .avatar-photo,
.inventory-avatar-demo.has-frame-art .avatar-photo,
.profile-avatar-ring:has(> .profile-frame-art) .profile-avatar img,
.topbar .profile.has-frame-art .topbar-profile-photo img {
    width: 100% !important;
    height: 100% !important;
    display: block;
    object-fit: cover;
    object-position: center;
}

.store-avatar-demo.has-frame-art > .profile-frame-art,
.inventory-avatar-demo.has-frame-art > .profile-frame-art,
.profile-avatar-ring > .profile-frame-art,
.topbar .profile.has-frame-art > .profile-frame-art {
    position: absolute;
    left: 50% !important;
    top: 50% !important;
    width: var(--frame-art-size) !important;
    height: var(--frame-art-size) !important;
    max-width: none !important;
    max-height: none !important;
    object-fit: contain;
    object-position: center;
    pointer-events: none;
    transform: translate(-50%, -50%) !important;
    z-index: 3;
}

.store-preview.moldura_sapo_pop .store-avatar-demo.has-frame-art,
.inventory-preview.moldura_sapo_pop .inventory-avatar-demo.has-frame-art,
.profile-summary.moldura_sapo_pop .profile-avatar-ring,
.topbar .profile.frame-moldura_sapo_pop,
.store-preview.moldura_musica_neon .store-avatar-demo.has-frame-art,
.inventory-preview.moldura_musica_neon .inventory-avatar-demo.has-frame-art,
.profile-summary.moldura_musica_neon .profile-avatar-ring,
.topbar .profile.frame-moldura_musica_neon {
    --frame-photo-size: 76%;
}

.store-preview.moldura_coroa_real .store-avatar-demo.has-frame-art,
.inventory-preview.moldura_coroa_real .inventory-avatar-demo.has-frame-art,
.profile-summary.moldura_coroa_real .profile-avatar-ring,
.topbar .profile.frame-moldura_coroa_real {
    --frame-photo-size: 70%;
    --frame-art-size: 136%;
}

.store-preview.moldura_asas_celestes .store-avatar-demo.has-frame-art,
.inventory-preview.moldura_asas_celestes .inventory-avatar-demo.has-frame-art,
.profile-summary.moldura_asas_celestes .profile-avatar-ring,
.topbar .profile.frame-moldura_asas_celestes {
    --frame-photo-size: 68%;
}
'''

for path in files:
    text = trim_old_frame_blocks(path.read_text(encoding='utf-8'))
    text += final_css
    path.write_text(text, encoding='utf-8')
