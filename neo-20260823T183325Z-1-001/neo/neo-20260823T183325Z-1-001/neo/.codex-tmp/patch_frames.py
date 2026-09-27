from pathlib import Path

loja = Path('static/pages/loja.css')
text = loja.read_text(encoding='utf-8')
old = """.store-preview.anel_ouro .store-avatar-demo {
    border-color: #d7bb5f;
    box-shadow: 0 0 0 5px rgba(215, 187, 95, .12);
}

.store-preview.anel_neon .store-avatar-demo {
    border-color: #40d8ff;
    box-shadow: 0 0 0 5px rgba(64, 216, 255, .12);
}

.store-preview.anel_foco .store-avatar-demo {
    border-color: #38d16a;
    box-shadow: 0 0 0 5px rgba(56, 209, 106, .12);
}

"""
text = text.replace(old, '')
start = text.find('.ring-style-cat .store-avatar-demo,')
end_marker = '.store-item-copy {'
if start != -1:
    end = text.find(end_marker, start)
    if end != -1:
        text = text[:start] + text[end:]
append = """
.store-avatar-demo.has-frame-art > .avatar-photo,
.store-avatar-demo.has-frame-art > span {
    z-index: 1;
}

.store-avatar-demo.has-frame-art > .profile-frame-art {
    position: absolute;
    inset: -18%;
    width: 136%;
    height: 136%;
    object-fit: contain;
    pointer-events: none;
    z-index: 3;
}
"""
if '.store-avatar-demo.has-frame-art > .profile-frame-art {' not in text:
    insert_after = '.store-avatar-demo span {\n    color: #fff;\n    font-size: clamp(26px, 3.4vw, 34px);\n    font-weight: 900;\n}\n'
    text = text.replace(insert_after, insert_after + append + '\n')
loja.write_text(text, encoding='utf-8')

perfil = Path('static/pages/perfil.css')
text = perfil.read_text(encoding='utf-8')
old = """.profile-summary.anel_ouro {
    --profile-accent: #d7bb5f;
}

.profile-summary.anel_neon {
    --profile-accent: #40d8ff;
}

.profile-summary.anel_foco {
    --profile-accent: #38d16a;
}

"""
text = text.replace(old, '')
old = """.inventory-preview.anel_ouro .inventory-avatar-demo {
    border-color: #d7bb5f;
}

.inventory-preview.anel_neon .inventory-avatar-demo {
    border-color: #40d8ff;
}

.inventory-preview.anel_foco .inventory-avatar-demo {
    border-color: #38d16a;
}

"""
text = text.replace(old, '')
start = text.find('.ring-style-cat .profile-avatar-ring,')
end_marker = '.mastery-grid {'
if start != -1:
    end = text.find(end_marker, start)
    if end != -1:
        text = text[:start] + text[end:]
insert_after = """.profile-avatar {
    width: clamp(70px, 9vw, 104px);
    height: clamp(70px, 9vw, 104px);
    display: grid;
    place-items: center;
    overflow: hidden;
    border-radius: clamp(18px, 2.5vw, 24px);
    background: #102247;
}
"""
addition = """
.profile-avatar-ring:has(> .profile-frame-art) {
    position: relative;
    border-color: transparent;
    background: transparent;
    box-shadow: none;
    overflow: visible;
}

.profile-avatar-ring > .profile-frame-art {
    position: absolute;
    inset: -18%;
    width: 136%;
    height: 136%;
    object-fit: contain;
    pointer-events: none;
    z-index: 3;
}

.profile-avatar-ring:has(> .profile-frame-art) .profile-avatar {
    width: 76%;
    height: 76%;
    border-radius: 999px;
    z-index: 1;
}
"""
if '.profile-avatar-ring > .profile-frame-art {' not in text:
    text = text.replace(insert_after, insert_after + addition)
insert_after = """.inventory-avatar-demo {
    width: 66px;
    height: 66px;
    position: relative;
    display: grid;
    place-items: center;
    overflow: visible;
    border: 2px solid var(--profile-accent, var(--neo-blue));
    border-radius: 999px;
    background: #102247;
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--profile-accent, var(--neo-blue)) 12%, transparent);
}
"""
addition = """
.inventory-avatar-demo.has-frame-art {
    width: 78px;
    height: 78px;
    border-color: transparent;
    background: transparent;
    box-shadow: none;
    overflow: visible;
}

.inventory-avatar-demo.has-frame-art .avatar-photo,
.inventory-avatar-demo.has-frame-art > span {
    position: absolute;
    inset: 18%;
    width: 64%;
    height: 64%;
    border-radius: 999px;
    z-index: 1;
}

.inventory-avatar-demo.has-frame-art > span {
    display: grid;
    place-items: center;
    background: #102247;
}

.inventory-avatar-demo.has-frame-art > .profile-frame-art {
    position: absolute;
    inset: -18%;
    width: 136%;
    height: 136%;
    object-fit: contain;
    pointer-events: none;
    z-index: 3;
}
"""
if '.inventory-avatar-demo.has-frame-art {' not in text:
    text = text.replace(insert_after, insert_after + addition)
text = text.replace(".inventory-item.anel_ouro { --store-accent: #d7bb5f; }\n.inventory-item.anel_neon { --store-accent: #40d8ff; }\n.inventory-item.anel_foco { --store-accent: #38d16a; }\n", '')
perfil.write_text(text, encoding='utf-8')

adm = Path('adm.php')
text = adm.read_text(encoding='utf-8')
text = text.replace("'decoracao_perfil' => 'Cria anéis e molduras para a foto do perfil.',", "'decoracao_perfil' => 'Cadastra molduras prontas e transparentes para a foto do perfil.',")
adm.write_text(text, encoding='utf-8')
