<?php
// Render the real page templates with isolated sample data, without a database or AI calls.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$page = $argv[1] ?? 'index';
if (!in_array($page, ['index', 'materias', 'conteudos', 'livro', 'questoes', 'estudos'], true)) {
    exit(1);
}
$root = dirname(__DIR__);
$_SESSION = [];
function csrfToken(): string { return 'welcome-ui-fixture'; }
function campoCsrf(): string { return '<input type="hidden" name="csrf_token" value="welcome-ui-fixture">'; }
function saldoCossasVisual(array $usuario): string { return '208'; }
$usuario = ['id' => (int)($argv[3] ?? 999999), 'nome' => 'Visitante', 'nivel' => 1, 'xp' => 0];
$usaSidebar = true;
$mostrarDespertarDashboard = ($argv[2] ?? '') === 'first';
$tituloPagina = $page === 'index' ? 'Início' : 'Matérias';
$paginaAtual = $page === 'index' ? 'inicio' : 'materias';
$cssPaginas = [$page === 'index' ? 'dashboard' : $page];
$bodyClasses = $page === 'estudos' ? ['study-screen'] : [];
$rostoNoPainelEstudo = $page === 'estudos';
$ultimosAcessos = [];
$progressoNivel = 24;
$nivelAtual = 1;
$livrosAcessados = 0;
$somaTotal = 0;
$materias = [];
foreach (['Biologia', 'Física', 'Geografia', 'História', 'Matemática', 'Português', 'Química', 'Redação'] as $i => $nome) {
    $materias[] = ['id' => $i + 1, 'nome' => $nome, 'total' => $i === 1 ? 0 : 3, 'nivel_materia' => 1, 'xp_materia' => 0];
}
$materia = ['id' => 1, 'nome' => 'Biologia'];
$materiaId = 1;
$conteudos = [];
foreach (['Introdução à Biologia Celular', 'Estrutura e função das células', 'Ecologia e relações entre os seres vivos'] as $i => $titulo) {
    $conteudos[] = ['id' => $i + 1, 'titulo' => $titulo, 'materia_id' => 1, 'dificuldade_adaptativa' => 1];
}
$totalConteudos = count($conteudos);
$rotuloConteudos = 'livros';
$erroIA = $erroAcao = $erroSolicitacao = $conteudoSolicitado = $erro = '';
$abrirModalSolicitacao = false;
$conteudo = $conteudos[0];
$questoesGeradasLivro = $livroBloqueado = false;
$rotuloModeloLivro = 'OP';
$nomeProvedorLivro = 'OpenAI';
$corpoLivroExibicao = str_repeat("As células são a unidade básica dos seres vivos. Cada estrutura contribui para seu funcionamento.\n\n", 7);
$questoes = [];
$resultado = null;
require $root . '/includes/head.php';
$source = file_get_contents($root . '/' . $page . '.php');
$anchor = "require __DIR__ . '/includes/head.php';";
$body = substr($source, strpos($source, $anchor) + strlen($anchor));
$body = substr($body, strpos($body, '?>') + 2);
$body = str_replace('__DIR__', var_export($root, true), $body);
eval('?>' . $body);
