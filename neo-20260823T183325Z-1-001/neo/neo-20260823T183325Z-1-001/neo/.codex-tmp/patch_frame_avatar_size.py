from pathlib import Path
replacements = {
    'width: 68%;\n    height: 68%;': 'width: 82%;\n    height: 82%;',
    'width: 112%;\n    height: 112%;': 'width: 120%;\n    height: 120%;',
}
for file in ['static/pages/loja.css', 'static/pages/perfil.css']:
    p = Path(file)
    text = p.read_text(encoding='utf-8')
    for old, new in replacements.items():
        text = text.replace(old, new)
    p.write_text(text, encoding='utf-8')

p = Path('static/topbar.css')
text = p.read_text(encoding='utf-8')
text = text.replace('width: 68%;\n    height: 68%;', 'width: 82%;\n    height: 82%;')
text = text.replace('width: 128%;\n    height: 128%;', 'width: 138%;\n    height: 138%;')
p.write_text(text, encoding='utf-8')
