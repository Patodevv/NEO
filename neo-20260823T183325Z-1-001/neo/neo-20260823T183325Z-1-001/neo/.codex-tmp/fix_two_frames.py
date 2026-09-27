from pathlib import Path
for file in ['static/pages/loja.css', 'static/pages/perfil.css']:
    p = Path(file)
    text = p.read_text(encoding='utf-8')
    text = text.replace('/* Ajuste específico: estas artes têm o aro interno maior que as demais. */', '/* Ajuste específico: estas duas artes têm aro interno menor; a foto fica dentro do anel. */')
    text = text.replace('width: 82%;\n    height: 82%;', 'width: 58%;\n    height: 58%;')
    text = text.replace('width: 120%;\n    height: 120%;', 'width: 128%;\n    height: 128%;')
    p.write_text(text, encoding='utf-8')

p = Path('static/topbar.css')
text = p.read_text(encoding='utf-8')
text = text.replace('/* Ajuste específico para molduras com aro interno maior. */', '/* Ajuste específico para molduras com aro interno menor. */')
text = text.replace('width: 82%;\n    height: 82%;', 'width: 58%;\n    height: 58%;')
text = text.replace('width: 138%;\n    height: 138%;', 'width: 146%;\n    height: 146%;')
p.write_text(text, encoding='utf-8')
