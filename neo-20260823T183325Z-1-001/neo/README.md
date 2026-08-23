# NeoMind

Sistema simples em PHP puro (sem frameworks) com banco SQLite embutido (não precisa instalar MySQL).

## Como rodar

1. Tenha o PHP instalado (8.x) com a extensão `pdo_sqlite` (vem habilitada por padrão na maioria das instalações).
2. Dentro da pasta do projeto, rode:

```
php -S localhost:8000
```

3. Acesse http://localhost:8000 no navegador.

O banco (`storage/database.sqlite`) é criado automaticamente na primeira execução, já com algumas matérias, conteúdos e uma questão de exemplo.

## Estrutura

- `config/db.php` — conexão e criação automática do banco (SQLite)
- `includes/auth.php` — sessão e proteção de páginas (exigirLogin)
- `includes/sidebar.php` — menu lateral, incluído em todas as páginas internas
- `includes/head.php` — `<head>` + cor do site (aplicada via CSS var --primary)
- `login.php` / `register.php` / `logout.php` — autenticação
- `index.php` — Dashboard
- `materias.php` — Página 1: lista de matérias
- `conteudos.php` — Página 2: lista de conteúdos de uma matéria
- `livro.php` — Página 3: leitura do conteúdo
- `questoes.php` — Página 4: questões do conteúdo (corrige e salva no histórico)
- `historico.php` — histórico de tentativas do usuário
- `config.php` — configurações (trocar a cor geral do site)
