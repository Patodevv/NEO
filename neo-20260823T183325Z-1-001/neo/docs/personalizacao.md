# Cadastro e aprendizado com o Manel

`register.php` conduz a personalização em perguntas individuais. Visitantes criam o acesso com e-mail e senha após revisar as respostas. Contas existentes completam a personalização ao entrar; podem editá-la em `register.php?editar=1`. Nenhum histórico anterior é apagado.

As migrações `011_personalization`, `012_adaptive_learning`, `013_onboarding_guest_drafts` e `014_learning_history` adicionam perfil, rascunhos, observações de estudo, simulados e importação idempotente do histórico antigo. Para atualizar uma instalação:

```powershell
C:\xampp\php\php.exe scripts\migrate.php
```

O servidor valida cada resposta, a ordem e as dependências entre as etapas. Alterar matérias ou faixa de ensino reabre as perguntas que dependem dessas escolhas. O diagnóstico usa um banco local de questões, corrige no servidor e mostra uma estimativa limitada aos conteúdos avaliados. O cadastro não depende de chamadas de IA.

Respostas confirmadas de visitantes podem ser retomadas por 30 dias no mesmo navegador, por um identificador aleatório em cookie protegido. Senhas e e-mails de acesso não entram no rascunho. O rascunho de visitante é removido ao confirmar a conta; respostas de usuários autenticados ficam ligadas à própria conta.

Ao confirmar, o perfil monta a sequência inicial de conteúdos. Cada livro pode ser preparado pelo botão **Preparar meu conteúdo**, usando a geração já existente. A sequência é um ponto de partida ajustável; não é apresentada como currículo oficial completo.

`aprendizado.php` reúne rotina semanal, mapa de conhecimento, caderno de erros, simulados e preferências corrigíveis. Os simulados usam questões salvas na conta e informam quando não há itens suficientes para um filtro. Novas questões geradas incluem habilidade, tipo e estilo de prova; questões antigas permanecem classificadas como gerais.

As respostas de atividades e simulados alimentam o mapa. Dificuldade muda gradualmente; estimativas com poucas respostas não representam domínio comprovado. Tempo é aproximado, medido durante a interação com questões visíveis. Preferências declaradas são identificadas separadamente das hipóteses baseadas no desempenho. Corrigir uma hipótese altera a rotina e o contexto entregue à IA.

Lembretes aparecem **dentro do NEO**, de acordo com dias, horário e frequência escolhidos. Não enviam e-mail nem notificações com o site fechado. A interface explica isso durante a configuração.

## Verificação

```powershell
C:\xampp\php\php.exe tests\onboarding-personalization.php
C:\xampp\php\php.exe tests\integration.php
node tests\personalization-ui.cjs
```

O teste de navegador requer Playwright e Microsoft Edge. Ele abre um servidor local na porta 8791, cria um banco com prefixo de teste, percorre o cadastro e os estudos e remove o banco ao terminar. As imagens de verificação ficam em `.codex-tmp/personalization`, fora da pasta pública da aplicação.
