from pathlib import Path

loja = Path('static/pages/loja.css')
text = loja.read_text(encoding='utf-8')
repls = {
""".store-avatar-demo.has-frame-art {
    width: clamp(82px, 10vw, 110px);
    height: clamp(82px, 10vw, 110px);
    border-color: transparent;
    background: #102247;
    box-shadow: none;
    overflow: visible;
}

.store-avatar-demo.has-frame-art .avatar-photo {
    position: absolute;
    inset: 18%;
    width: 64%;
    height: 64%;
    border-radius: 999px;
    object-fit: cover;
}

.store-avatar-demo.has-frame-art > span {
    position: absolute;
    inset: 18%;
    display: grid;
    place-items: center;
    border-radius: 999px;
    background: #102247;
}

.store-avatar-demo .profile-frame-art {
    position: absolute;
    inset: -18%;
    width: 136%;
    height: 136%;
    object-fit: contain;
    pointer-events: none;
    z-index: 2;
}
""": """.store-avatar-demo.has-frame-art {
    width: clamp(92px, 11vw, 122px);
    height: clamp(92px, 11vw, 122px);
    border-color: transparent;
    background: transparent;
    box-shadow: none;
    overflow: visible;
}

.store-avatar-demo.has-frame-art .avatar-photo {
    position: absolute;
    left: 50%;
    top: 50%;
    width: 68%;
    height: 68%;
    border-radius: 999px;
    object-fit: cover;
    transform: translate(-50%, -50%);
}

.store-avatar-demo.has-frame-art > span {
    position: absolute;
    left: 50%;
    top: 50%;
    width: 68%;
    height: 68%;
    display: grid;
    place-items: center;
    border-radius: 999px;
    background: #102247;
    transform: translate(-50%, -50%);
}

.store-avatar-demo .profile-frame-art {
    position: absolute;
    left: 50%;
    top: 50%;
    width: 112%;
    height: 112%;
    object-fit: contain;
    pointer-events: none;
    transform: translate(-50%, -50%);
    z-index: 2;
}
""",
""".store-avatar-demo.has-frame-art > .profile-frame-art {
    position: absolute;
    inset: -18%;
    width: 136%;
    height: 136%;
    object-fit: contain;
    pointer-events: none;
    z-index: 3;
}
""": """.store-avatar-demo.has-frame-art > .profile-frame-art {
    position: absolute;
    left: 50%;
    top: 50%;
    width: 112%;
    height: 112%;
    object-fit: contain;
    pointer-events: none;
    transform: translate(-50%, -50%);
    z-index: 3;
}
"""
}
for old, new in repls.items():
    text = text.replace(old, new)
loja.write_text(text, encoding='utf-8')

perfil = Path('static/pages/perfil.css')
text = perfil.read_text(encoding='utf-8')
repls = {
""".profile-avatar-ring > .profile-frame-art {
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
""": """.profile-avatar-ring > .profile-frame-art {
    position: absolute;
    left: 50%;
    top: 50%;
    width: 112%;
    height: 112%;
    object-fit: contain;
    pointer-events: none;
    transform: translate(-50%, -50%);
    z-index: 3;
}

.profile-avatar-ring:has(> .profile-frame-art) .profile-avatar {
    width: 68%;
    height: 68%;
    border-radius: 999px;
    z-index: 1;
}
""",
""".inventory-avatar-demo.has-frame-art {
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
""": """.inventory-avatar-demo.has-frame-art {
    width: 82px;
    height: 82px;
    border-color: transparent;
    background: transparent;
    box-shadow: none;
    overflow: visible;
}

.inventory-avatar-demo.has-frame-art .avatar-photo,
.inventory-avatar-demo.has-frame-art > span {
    position: absolute;
    left: 50%;
    top: 50%;
    width: 68%;
    height: 68%;
    border-radius: 999px;
    transform: translate(-50%, -50%);
    z-index: 1;
}

.inventory-avatar-demo.has-frame-art > span {
    display: grid;
    place-items: center;
    background: #102247;
}

.inventory-avatar-demo.has-frame-art > .profile-frame-art {
    position: absolute;
    left: 50%;
    top: 50%;
    width: 112%;
    height: 112%;
    object-fit: contain;
    pointer-events: none;
    transform: translate(-50%, -50%);
    z-index: 3;
}
"""
}
for old, new in repls.items():
    text = text.replace(old, new)
perfil.write_text(text, encoding='utf-8')

# topbar css
p = Path('static/topbar.css')
text = p.read_text(encoding='utf-8')
old = """.topbar .profile {
    width: clamp(28px, 7.6vw, 34px);
    height: clamp(28px, 7.6vw, 34px);
    flex: 0 0 clamp(28px, 7.6vw, 34px);
    font-size: clamp(12px, 2.5vw, 14px);
    border-radius: clamp(11px, 3vw, 14px);
    background: var(--btn-bg);
    border: 1px solid var(--neo-blue);
    color: var(--btn-icon);
    transition: color .2s ease, background .2s ease, box-shadow .2s ease, transform .2s ease;
}

.topbar .profile:hover {
    color: var(--btn-icon-hover);
    background: color-mix(in srgb, var(--neo-blue) 22%, transparent);
    box-shadow: 0 0 0 4px var(--neo-blue-glow);
    transform: translateY(-1px) scale(1.03);
}

.topbar .profile img {
    border-radius: 12px;
}
"""
new = """.topbar .profile {
    width: clamp(28px, 7.6vw, 34px);
    height: clamp(28px, 7.6vw, 34px);
    position: relative;
    flex: 0 0 clamp(28px, 7.6vw, 34px);
    display: grid;
    place-items: center;
    overflow: visible;
    font-size: clamp(12px, 2.5vw, 14px);
    border-radius: clamp(11px, 3vw, 14px);
    background: var(--btn-bg);
    border: 1px solid var(--neo-blue);
    color: var(--btn-icon);
    transition: color .2s ease, background .2s ease, box-shadow .2s ease, transform .2s ease;
}

.topbar .profile:hover {
    color: var(--btn-icon-hover);
    background: color-mix(in srgb, var(--neo-blue) 22%, transparent);
    box-shadow: 0 0 0 4px var(--neo-blue-glow);
    transform: translateY(-1px) scale(1.03);
}

.topbar .profile.has-frame-art {
    border-color: transparent;
    background: transparent;
    box-shadow: none;
}

.topbar .profile.has-frame-art:hover {
    background: transparent;
    box-shadow: none;
}

.topbar-profile-photo {
    width: 100%;
    height: 100%;
    display: grid;
    place-items: center;
    overflow: hidden;
    border-radius: clamp(11px, 3vw, 14px);
    background: var(--btn-bg);
    border: 1px solid var(--neo-blue);
}

.topbar .profile.has-frame-art .topbar-profile-photo {
    width: 68%;
    height: 68%;
    border-radius: 999px;
    border-color: transparent;
    z-index: 1;
}

.topbar .profile img:not(.profile-frame-art) {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
}

.topbar .profile .profile-frame-art {
    position: absolute;
    left: 50%;
    top: 50%;
    width: 128%;
    height: 128%;
    object-fit: contain;
    pointer-events: none;
    transform: translate(-50%, -50%);
    z-index: 2;
}
"""
if old in text:
    text = text.replace(old, new)
elif '.topbar .profile .profile-frame-art' not in text:
    text += '\n' + new
p.write_text(text, encoding='utf-8')
