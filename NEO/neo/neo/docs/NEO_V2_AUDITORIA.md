# NEO V2.0 — auditoria, gap analysis e implementacao

Data da revisao: 2 de setembro de 2026.

## 1. Escopo auditado

A aplicacao ativa fica em `neo/neo/` e usa PHP procedural, PDO e MariaDB/MySQL. Foram analisados os pontos de entrada, autenticacao, usuarios, materias, conteudos, livros, questoes, historico, perfil, loja, painel administrativo, configuracao da IA, banco, uploads, estilos, scripts e o prototipo legado.

Existe tambem um prototipo Flask/SQLite em `Neomind-main/`. Ele nao compartilha autenticacao nem dados com a aplicacao PHP, continha contas de teste antigas e nao deve ser exposto. A V2.0 foi integrada somente ao sistema PHP ativo; o diretorio legado foi bloqueado no Apache para evitar dois sistemas paralelos publicos.

Nenhum componente visual foi redesenhado. As unicas regras de estilo adicionadas limitam imagens de produtos enviadas pelo administrador ao espaco que ja existia.

## 2. Arquitetura encontrada

| Area | Situacao antes da V2.0 |
|---|---|
| Backend | PHP procedural com paginas como controladores e acesso PDO direto |
| Banco | MariaDB `neo`, schema criado no carregamento, sem controle de versoes |
| Frontend | HTML renderizado no servidor, CSS por pagina e JavaScript pequeno |
| Autenticacao | Aluno com `password_hash`; admin com senha fixa no codigo |
| IA | Groq com resposta JSON, sem pipeline de revisao e com chave no codigo |
| Questoes | Geracao e correcao existentes; explicacao individual incompleta |
| Gamificacao | Saldo, XP e nivel globais; recompensa repetivel |
| Loja | Tres itens fixos no PHP e compra sem transacao atomica |
| Admin | Painel existente para usuarios; sem cadastro de produtos |
| Legado | Flask/SQLite separado e nao integrado |

O banco de producao local foi auditado antes das migrations: 1 usuario, 8 materias, 18 conteudos, 17 questoes, 8 registros de historico e 1 compra, sem relacionamentos orfaos. A atualizacao preservou essas contagens e fez backfill onde necessario.

## 3. Gap analysis final

| Requisito | Estado anterior | Estado final | Implementacao |
|---|---|---|---|
| IA explicativa | Parcial | IMPLEMENTADO | Explicacao correta e feedback por alternativa; respostas antigas recebem geracao complementar |
| IA contextual e didatica | Parcial | IMPLEMENTADO | Prompts com materia, texto-base, perfil, nivel, limites e regras pedagogicas |
| Pipeline gerar/revisar/corrigir | Nao implementado | IMPLEMENTADO | Validacao local, revisor por IA, correcao e limite de duas passagens |
| Filtros de qualidade | Nao implementado | IMPLEMENTADO | Duplicidade, campos vazios, gabarito, ambiguidade via revisor, contexto, vazamento de instrucao e dicas que revelam resposta |
| Facilitador progressivo | Nao implementado | IMPLEMENTADO | Tres niveis; primeiro gratuito, segundo 25 Coças, terceiro 40; ajuda persistida e idempotente |
| Nivel/EXP por materia | Nao implementado | IMPLEMENTADO | `progresso_materias` e `transacoes_exp`, extensivel a novas materias |
| Dificuldade adaptativa | Nao implementado | IMPLEMENTADO | Combina nivel da materia com as cinco tentativas recentes |
| Protecao contra farm | Nao implementado | IMPLEMENTADO | Uma recompensa por lista e no maximo tres listas recompensadas por conteudo/dia |
| Ofensiva semanal | Nao implementado | IMPLEMENTADO | Semana concluida com atividade em tres dias distintos; recompensa unica e crescente em Coças |
| Coças rastreaveis | Parcial | IMPLEMENTADO | Livro-razao imutavel, chave de idempotencia, saldo bloqueado e transacoes atomicas |
| Loja modular | Parcial | IMPLEMENTADO | Produtos no banco, estoque, periodo, ativacao, limite por usuario, permanencia e expiracao |
| Gestao de produtos | Nao implementado | IMPLEMENTADO | Criar, editar, publicar, desativar/remover logicamente e enviar imagem pelo painel existente |
| Inventario e historico | Parcial | IMPLEMENTADO | Compra vinculada ao produto/transacao, preco pago, status, validade e inventario ativo |
| Autenticacao e autorizacao | Parcial | IMPLEMENTADO | Senha admin em hash no ambiente, sessao renovada, expiracao, cookies protegidos e CSRF |
| Migrations seguras | Nao implementado | IMPLEMENTADO | Tres migrations incrementais, trava de execucao e registro em `schema_migrations` |
| Auditoria de IA | Nao implementado | IMPLEMENTADO | Tipo, contexto anonimizado por hash, status, tentativas e problemas |

## 4. Modelo de dados adicionado

- `produtos`: catalogo administravel, estoque e janela de disponibilidade;
- `transacoes_cossas`: cada credito/debito com saldo resultante e chave idempotente;
- `progresso_materias`: XP, nivel e desempenho recente independentes;
- `transacoes_exp`: origem de cada concessao de experiencia;
- `recompensas_atividades`: trava contra recompensa duplicada;
- `respostas_historico`: alternativa escolhida, acerto e feedback por questao;
- `ajudas_questoes`: nivel, dica, custo e transacao do Facilitador;
- `atividades_estudo_diarias`: dias validos para a ofensiva;
- `progresso_ofensivas` e `ofensivas_semanais`: sequencia e recompensa semanal;
- `auditoria_ia`: rastreio das validacoes de conteudo gerado.

As tabelas antigas nao foram apagadas. `questoes`, `historico` e `compras_loja` receberam apenas colunas e indices compativeis, com vinculo posterior aos novos registros.

## 5. Regras principais

### Recompensas e progressao

O EXP considera dificuldade e percentual de acertos. A progressao de cada materia usa uma curva crescente; o nivel global permanece como indicador de compatibilidade e e recalculado a partir do EXP educacional. Reenvios e listas repetidas continuam no historico, mas nao voltam a conceder moeda ou EXP.

A dificuldade-base segue as faixas pedidas (basica, intermediaria, avancada e desafio) e sobe ou desce um passo conforme o desempenho recente.

### Ofensiva

Uma semana valida exige estudo em tres datas distintas. Cada semana so pode pagar uma vez. Semanas consecutivas aumentam a sequencia e concedem 50, 100, 150 e depois 200 Coças por semana, com teto seguro.

### Facilitador

Cada questao aceita ate tres ajudas progressivas. A camada de qualidade bloqueia texto muito curto, fora dos limites ou que revele a letra/texto do gabarito. A cobranca e a liberacao ficam na mesma transacao; repetir a requisicao nao duplica o gasto.

### Loja

A compra bloqueia simultaneamente produto e saldo, valida disponibilidade e limite individual, debita a carteira, reduz estoque e cria o historico na mesma transacao. Uma falha reverte todas as etapas. Remover no admin e uma exclusao logica para preservar compras antigas.

## 6. Seguranca revisada

- chave da IA e credenciais retiradas do codigo e movidas para `.env`;
- senha administrativa armazenada somente como hash;
- CSRF em todos os formularios mutaveis;
- consultas parametrizadas e verificacao de propriedade de conteudos/questoes;
- cookies `HttpOnly`, `SameSite=Lax`, `Secure` sob HTTPS e renovacao de sessao;
- limite de tentativas de login por sessao;
- uploads limitados a 2 MB, validados pelo MIME real, renomeados aleatoriamente e sem execucao de scripts;
- arquivos sensiveis, listagem de diretorios, endpoints de teste e prototipo legado bloqueados;
- erros internos de banco/IA registrados no servidor sem expor chave ou SQL ao aluno;
- saldo, estoque e recompensas protegidos por transacoes, bloqueios de linha e idempotencia.

Risco operacional restante: como a antiga chave Groq esteve presente no codigo, ela deve ser revogada no painel do provedor mesmo depois da remocao local. O bloqueio de login em sessao reduz tentativas casuais; um ambiente publico deve ainda aplicar rate limiting por IP no proxy/servidor.

## 7. Validacao executada

- sintaxe PHP verificada em todos os arquivos;
- migrations `001_base_schema`, `002_neo_v2` e `003_integrity_hardening` aplicadas;
- testes de integracao em banco isolado aprovados;
- fluxo HTTP verificado para cadastro, login, dashboard, materias, loja, perfil e historico;
- requisicao de alteracao sem CSRF corretamente recusada com HTTP 419;
- fallback da IA gerou cinco questoes sem problemas locais, seis conteudos e livro valido;
- contagens e relacionamentos do banco existente conferidos antes e depois da migracao.

## 8. Operacao e evolucao

Para producao: configurar HTTPS, criar usuario de banco dedicado, definir `DB_AUTO_CREATE=false` depois do provisionamento, guardar backups fora da pasta publica e programar limpeza/arquivamento da auditoria de IA. O prototipo Flask deve permanecer bloqueado ou ser removido em uma tarefa de descarte explicitamente autorizada.

Novos tipos de premio (item, multiplicador ou cosmetico especial) podem ser adicionados sobre os livros-razao e produtos existentes. Nesta entrega, a ofensiva concede Coças, uma das recompensas previstas na especificacao, sem criar uma segunda economia.
