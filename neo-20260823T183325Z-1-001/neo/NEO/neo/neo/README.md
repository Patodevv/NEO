# NeoMind

Aplicação educacional em PHP 8 com MySQL, geração de conteúdo por OpenAI e fallback para Groq.

## Configuração

1. Copie `.env.example` para `.env` e configure o banco e as chaves das APIs.
2. Crie o banco vazio ou ative `DB_AUTO_CREATE=true` apenas durante a instalação local.
3. Execute as migrações:

```powershell
C:\xampp\php\php.exe scripts\migrate.php
```

4. Mantenha `DB_AUTO_CREATE=false` e `DB_AUTO_MIGRATE=false` durante o uso normal.
5. Sirva a pasta `neo` pelo Apache do XAMPP.

As credenciais de IA ficam somente no `.env`:

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
