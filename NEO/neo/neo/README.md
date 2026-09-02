# NEO V2.0

Plataforma educacional adaptativa em PHP 8, PDO e MariaDB/MySQL. A V2.0 evolui a aplicacao existente sem substituir a interface: adiciona validacao da IA, feedback didatico, Facilitador progressivo, dominio por materia, dificuldade adaptativa, recompensas rastreaveis, ofensiva semanal e loja administravel.

## Requisitos

- PHP 8.1 ou superior com `pdo_mysql`, `mbstring`, `fileinfo` e `curl`;
- MariaDB ou MySQL;
- Apache/XAMPP recomendado, para que as regras `.htaccess` de protecao sejam aplicadas.

## Configuracao

1. Copie `.env.example` para `.env` nesta pasta.
2. Configure o banco e `GROQ_API_KEY` no `.env`.
3. Gere a senha administrativa com:

```powershell
php -r "echo password_hash('uma-senha-forte', PASSWORD_DEFAULT), PHP_EOL;"
```

4. Salve o resultado em `NEO_ADMIN_PASSWORD_HASH`.
5. Acesse `http://localhost/NEO/`.

As migrations sao executadas automaticamente ao abrir a aplicacao. Para executa-las explicitamente:

```powershell
php scripts/migrate.php
```

## Validacao

Os testes usam um banco isolado e descartavel; o banco principal nao e alterado:

```powershell
php tests/integration.php
```

Eles cobrem Coças e idempotencia, recompensas antifraude, EXP por materia, conclusao de conteudo, ofensiva semanal, Facilitador, estoque, compra duplicada, saldo insuficiente e validadores de questoes/dicas.

## Estrutura principal

- `config/`: ambiente, banco e provedor de IA;
- `database/migrations/`: schema evolutivo e preservacao dos dados existentes;
- `services/ai.php`: geracao educacional, explicacoes e dicas;
- `services/ai_quality.php`: filtros, revisao e auditoria do conteudo gerado;
- `services/economy.php`: livro-razao e alteracoes atomicas de Coças;
- `services/gamification.php`: EXP, dominio, dificuldade adaptativa e ofensiva;
- `services/store.php`: catalogo, compras, estoque e inventario;
- `adm.php`: gestao existente de usuarios e gestao modular de produtos;
- `tests/integration.php`: cenarios de integracao da V2.0;
- `docs/NEO_V2_AUDITORIA.md`: auditoria, gap analysis e decisoes tecnicas.

## Seguranca operacional

- Nunca publique o arquivo `.env` ou chaves reais.
- A chave Groq que antes esteve gravada no codigo deve ser revogada e substituida no provedor.
- O prototipo Flask em `Neomind-main/` e legado, nao faz parte da aplicacao ativa e esta bloqueado pelo Apache. Nao o use como raiz publica.
- Em producao, use HTTPS, desative `DB_AUTO_CREATE` depois do provisionamento e utilize um usuario de banco com privilegios limitados.
