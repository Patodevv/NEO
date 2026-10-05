# NeoMind

Aplicação educacional em PHP 8 com MySQL, geração de conteúdo por OpenAI e fallback para Groq.

## Configuração

1. Copie `.env.example` para `.env` e configure o banco e as chaves das APIs. O `.env` é local e fica fora do GitHub.
2. Crie o banco vazio ou ative `DB_AUTO_CREATE=true` apenas durante a instalação local.
3. Execute as migrações:

```powershell
C:\xampp\php\php.exe scripts\migrate.php
```

4. Mantenha `DB_AUTO_CREATE=false` e `DB_AUTO_MIGRATE=false` durante o uso normal.
5. Sirva a pasta `neo` pelo Apache do XAMPP.

## Aplicativo Android

Abra `instalar.html` pelo endereço de rede do computador para baixar o `NEO-Remoto.apk`. O aplicativo exibe o mesmo NEO servido pelo Apache e usa a mesma conta e o mesmo banco de dados da versão web.

No primeiro acesso, conecte o celular e o computador à mesma rede Wi-Fi e informe no aplicativo o endereço exibido pela página de instalação. O Apache e o MySQL precisam permanecer ligados no computador.

As credenciais de IA ficam somente no `.env`. Nunca coloque chave real no código, no README ou no `.env.example`:

```dotenv
OPENAI_API_KEY=
OPENAI_MODEL=gpt-5-mini
GROQ_API_KEY=
GROQ_MODEL=openai/gpt-oss-20b
```

## Manutenção

Para remover imagens antigas que não são mais utilizadas:

```powershell
C:\xampp\php\php.exe scripts\cleanup_uploads.php
```

Para executar a suíte de integração:

```powershell
C:\xampp\php\php.exe tests\integration.php
```

## Colocar online em uma hospedagem

### 1. Comprar a hospedagem

Contrate uma hospedagem compartilhada ou VPS simples com:

- PHP 8.1 ou superior.
- MySQL ou MariaDB.
- Apache com `.htaccess` habilitado.
- SSL grátis pelo painel da hospedagem.
- Acesso ao gerenciador de arquivos ou FTP/SFTP.

Se a hospedagem permitir escolher a pasta pública, aponte o domínio para a pasta deste projeto. Se ela usar `public_html`, envie os arquivos do projeto para dentro de `public_html`.

### 2. Criar o banco

No painel da hospedagem, crie:

- Um banco MySQL.
- Um usuário do banco.
- Uma senha forte.
- Permissão total desse usuário nesse banco.

Guarde host, porta, nome do banco, usuário e senha.

### 3. Enviar os arquivos

Envie todos os arquivos do projeto, mantendo as pastas `config`, `database`, `includes`, `services`, `static` e `scripts`. Não envie o arquivo `.env` local com senhas antigas.

Depois de enviar, crie um novo `.env` no servidor com base no `.env.example`. Exemplo:

```dotenv
APP_ENV=production
APP_URL=https://seudominio.com
TRUST_PROXY=false
APP_TIMEZONE=America/Fortaleza

DB_HOST=host-do-banco
DB_PORT=3306
DB_NAME=nome_do_banco
DB_USER=usuario_do_banco
DB_PASSWORD=senha_forte
DB_AUTO_CREATE=false
DB_AUTO_MIGRATE=false

OPENAI_API_KEY=sua_chave
OPENAI_MODEL=gpt-5-mini
GROQ_API_KEY=sua_chave_groq
GROQ_MODEL=openai/gpt-oss-20b
NEO_ADMIN_PASSWORD_HASH=hash_da_senha_admin
```

O `.env` já está no `.gitignore`, então ele não vai para o GitHub.

### 4. Gerar a senha do admin

No computador, gere o hash da senha que você quer usar no admin:

```powershell
php -r "echo password_hash('SUA_SENHA_ADMIN', PASSWORD_DEFAULT), PHP_EOL;"
```

Copie o resultado para `NEO_ADMIN_PASSWORD_HASH` no `.env` do servidor.

### 5. Rodar as migrações

Se a hospedagem tiver terminal, rode:

```bash
php scripts/migrate.php
```

Se ela não tiver terminal, ative temporariamente no `.env`:

```dotenv
DB_AUTO_MIGRATE=true
```

Abra o site uma vez no navegador para criar/atualizar as tabelas. Depois volte para:

```dotenv
DB_AUTO_MIGRATE=false
```

### 6. Ativar SSL

No painel da hospedagem, ative SSL para o domínio. Depois acesse o site com `https://`.

Se a hospedagem usa proxy reverso ou Cloudflare e o login ficar perdendo sessão, mude no `.env`:

```dotenv
TRUST_PROXY=true
```

### 7. Entrar no admin

O botão de admin não aparece mais no login. Para abrir o painel, vá para `login.php` e pressione:

```text
Ctrl + Alt + D
```

Isso abre `adm_login.php`, onde você entra com a senha configurada no `.env`.

### 8. Conferência final

Antes de divulgar, confira:

- O site abre em `https://seudominio.com`.
- Cadastro e login funcionam.
- A loja abre e salva compras.
- O painel admin abre apenas pelo atalho.
- Geração por IA funciona com a chave configurada.
- Pastas internas como `/config`, `/includes`, `/services` e `/database` não abrem pelo navegador.
