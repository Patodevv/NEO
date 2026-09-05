<?php

$bancoTeste = 'neo_v2_test_' . getmypid();
if (!preg_match('/^neo_v2_test_\d+$/', $bancoTeste)) {
    throw new RuntimeException('Nome inseguro para banco de teste.');
}

putenv('DB_NAME=' . $bancoTeste);
putenv('DB_AUTO_CREATE=true');

$pdo = null;
$falhas = [];
$ok = static function (bool $condicao, string $mensagem) use (&$falhas): void {
    if (!$condicao) {
        $falhas[] = $mensagem;
        echo "FALHA: {$mensagem}\n";
    } else {
        echo "OK: {$mensagem}\n";
    }
};

try {
    require dirname(__DIR__) . '/config/db.php';
    require_once dirname(__DIR__) . '/services/gamification.php';
    require_once dirname(__DIR__) . '/services/store.php';
    require_once dirname(__DIR__) . '/services/ai_quality.php';

    $materiaId = (int)$pdo->query("SELECT id FROM materias WHERE nome = 'Matemática'")->fetchColumn();
    $hash = password_hash('senha-segura', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (nome,email,senha,cossas) VALUES (?,?,?,?)");
    $stmt->execute(['Aluno Teste', 'aluno@example.test', $hash, 1000]);
    $userId = (int)$pdo->lastInsertId();
    $stmt->execute(['Aluno Estoque', 'estoque@example.test', $hash, 1000]);
    $userEstoqueId = (int)$pdo->lastInsertId();
    $stmt->execute(['Aluno Sem Saldo', 'semsaldo@example.test', $hash, 0]);
    $userSemSaldoId = (int)$pdo->lastInsertId();

    $preferenciasTeste = ['Tecnologia', 'Ciência'];
    $pdo->prepare("
        UPDATE users
        SET sobrenome = ?, idade = ?, genero = ?, gostos = ?, preferencias_json = ?, onboarding_concluido_em = NOW()
        WHERE id = ?
    ")->execute([
        'Onboarding', 16, 'nao_binario', implode(', ', $preferenciasTeste),
        json_encode($preferenciasTeste, JSON_UNESCAPED_UNICODE), $userId,
    ]);
    $perfilOnboarding = $pdo->query("SELECT sobrenome, idade, genero, gostos, preferencias_json, onboarding_concluido_em FROM users WHERE id = {$userId}")->fetch(PDO::FETCH_ASSOC);
    $ok(
        $perfilOnboarding['sobrenome'] === 'Onboarding'
        && (int)$perfilOnboarding['idade'] === 16
        && $perfilOnboarding['genero'] === 'nao_binario'
        && json_decode((string)$perfilOnboarding['preferencias_json'], true) === $preferenciasTeste
        && str_contains((string)$perfilOnboarding['gostos'], 'Tecnologia')
        && !empty($perfilOnboarding['onboarding_concluido_em']),
        'onboarding persiste perfil e preferências usadas pela IA'
    );

    $pdo->prepare("INSERT INTO conteudos (user_id,materia_id,titulo,corpo,dificuldade) VALUES (?,?,?,?,?)")
        ->execute([$userId, $materiaId, 'Álgebra de teste', str_repeat('Conteúdo didático consistente. ', 20), 3]);
    $conteudoId = (int)$pdo->lastInsertId();

    $stmtQuestao = $pdo->prepare("
        INSERT INTO questoes
            (user_id,conteudo_id,enunciado,opcao_a,opcao_b,opcao_c,opcao_d,correta,dificuldade,explicacao_correta,feedback_a,feedback_b,feedback_c,feedback_d,dica_1,dica_2,dica_3)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $questoes = [];
    for ($i = 1; $i <= 5; $i++) {
        $stmtQuestao->execute([
            $userId, $conteudoId, "Questão {$i}: qual relação algébrica preserva a igualdade?",
            'Somar o mesmo valor nos dois lados.', 'Somar em apenas um lado.', 'Trocar o sinal sem operação.', 'Ignorar uma das parcelas.',
            'A', 3, 'Somar o mesmo valor em ambos os membros preserva a igualdade.',
            'Correto: a mesma operação nos dois membros mantém a equivalência.',
            'A igualdade se perde porque somente um membro foi alterado.',
            'A troca de sinal exige uma operação algébrica correspondente.',
            'Ignorar uma parcela modifica a expressão original.',
            'Pense no princípio de equivalência entre os dois membros.',
            'Elimine operações aplicadas somente a um dos lados.',
            'Procure a operação que mantém os dois membros equilibrados.',
        ]);
        $questoes[] = [
            'id' => (int)$pdo->lastInsertId(), 'enunciado' => "Questão {$i}: qual relação algébrica preserva a igualdade?",
            'opcao_a' => 'Somar o mesmo valor nos dois lados.', 'opcao_b' => 'Somar em apenas um lado.',
            'opcao_c' => 'Trocar o sinal sem operação.', 'opcao_d' => 'Ignorar uma das parcelas.', 'correta' => 'A',
        ];
    }

    $primeira = alterarSaldoCossas($pdo, $userId, 100, 'teste', 'teste', '1', 'teste-idempotente');
    $segunda = alterarSaldoCossas($pdo, $userId, 100, 'teste', 'teste', '1', 'teste-idempotente');
    $saldo = (int)$pdo->query("SELECT cossas FROM users WHERE id = {$userId}")->fetchColumn();
    $ok($saldo === 1100 && !empty($segunda['idempotente']), 'ledger de Coças é idempotente');

    $segundaFeira = inicioSemanaNeo();
    $datasApoio = [];
    for ($dias = 0; $dias < 7 && count($datasApoio) < 2; $dias++) {
        $data = $segundaFeira->modify("+{$dias} days")->format('Y-m-d');
        if ($data !== (new DateTimeImmutable('today'))->format('Y-m-d')) {
            $datasApoio[] = $data;
        }
    }
    foreach ($datasApoio as $data) {
        $pdo->prepare("INSERT IGNORE INTO atividades_estudo_diarias (user_id,data_atividade,materia_id,atividade_tipo) VALUES (?,?,?,'teste')")
            ->execute([$userId, $data, $materiaId]);
    }

    $conteudo = ['id' => $conteudoId, 'materia_id' => $materiaId, 'dificuldade' => 3, 'dificuldade_adaptativa' => 3];
    $respostas = array_fill_keys(array_column($questoes, 'id'), 'A');
    $feedbacks = array_fill_keys(array_column($questoes, 'id'), 'Explicação didática de teste.');
    $resultado1 = registrarResultadoAtividade($pdo, $userId, $conteudo, $questoes, $respostas, $feedbacks);
    $saldoAposPrimeira = (int)$pdo->query("SELECT cossas FROM users WHERE id = {$userId}")->fetchColumn();
    $resultado2 = registrarResultadoAtividade($pdo, $userId, $conteudo, $questoes, $respostas, $feedbacks);
    $saldoAposSegunda = (int)$pdo->query("SELECT cossas FROM users WHERE id = {$userId}")->fetchColumn();
    $ok($resultado1['recompensado'] && !$resultado2['recompensado'] && $saldoAposPrimeira === $saldoAposSegunda, 'a mesma lista de questões recompensa apenas uma vez');
    $ok((int)$resultado1['ofensiva']['recompensa'] === 50 && (int)$resultado2['ofensiva']['recompensa'] === 0, 'ofensiva semanal exige três dias e recompensa uma única vez');
    $ok((int)$pdo->query("SELECT COUNT(*) FROM respostas_historico")->fetchColumn() === 10, 'respostas detalhadas são preservadas em todas as tentativas');
    $ok((int)$pdo->query("SELECT xp_total FROM progresso_materias WHERE user_id={$userId} AND materia_id={$materiaId}")->fetchColumn() > 0, 'EXP é registrado por matéria');
    $ok($pdo->query("SELECT status FROM conteudos WHERE id={$conteudoId}")->fetchColumn() === 'Concluído', 'desempenho suficiente conclui o conteúdo');

    $stmtGate = $pdo->prepare("INSERT INTO recompensas_atividades (user_id,conteudo_id,conjunto_hash) VALUES (?,?,?)");
    $stmtGate->execute([$userId, $conteudoId, hash('sha256', 'lista-extra-1')]);
    $stmtGate->execute([$userId, $conteudoId, hash('sha256', 'lista-extra-2')]);
    $questoesAlternativas = array_map(static function (array $questao): array {
        $questao['correta'] = 'B';
        return $questao;
    }, $questoes);
    $respostasB = array_fill_keys(array_column($questoesAlternativas, 'id'), 'B');
    $saldoAntesLimite = (int)$pdo->query("SELECT cossas FROM users WHERE id = {$userId}")->fetchColumn();
    $resultadoLimite = registrarResultadoAtividade($pdo, $userId, $conteudo, $questoesAlternativas, $respostasB, $feedbacks);
    $saldoDepoisLimite = (int)$pdo->query("SELECT cossas FROM users WHERE id = {$userId}")->fetchColumn();
    $ok(!$resultadoLimite['recompensado'] && $resultadoLimite['motivo_sem_recompensa'] === 'limite_diario' && $saldoAntesLimite === $saldoDepoisLimite, 'limite diário bloqueia farm de novas listas no mesmo conteúdo');

    $questaoId = (int)$questoes[0]['id'];
    $ajuda1 = registrarAjudaQuestao($pdo, $userId, $questaoId, 1, 'Dica inicial sem revelar o gabarito.');
    $saldoAntesAjuda2 = (int)$pdo->query("SELECT cossas FROM users WHERE id = {$userId}")->fetchColumn();
    $ajuda2 = registrarAjudaQuestao($pdo, $userId, $questaoId, 2, 'Dica intermediária sem revelar o gabarito.');
    registrarAjudaQuestao($pdo, $userId, $questaoId, 2, 'Repetição idempotente.');
    $saldoDepoisAjuda2 = (int)$pdo->query("SELECT cossas FROM users WHERE id = {$userId}")->fetchColumn();
    $ok((int)$ajuda1['custo_cossas'] === 0 && $saldoAntesAjuda2 - $saldoDepoisAjuda2 === 25, 'Facilitador é grátis no nível 1 e cobra uma única vez no nível 2');

    $produtoId = (int)$pdo->query("SELECT id FROM produtos WHERE codigo='anel_ouro'")->fetchColumn();
    $token = bin2hex(random_bytes(24));
    $saldoAntesCompra = (int)$pdo->query("SELECT cossas FROM users WHERE id = {$userId}")->fetchColumn();
    comprarProduto($pdo, $userId, $produtoId, $token);
    comprarProduto($pdo, $userId, $produtoId, $token);
    $saldoDepoisCompra = (int)$pdo->query("SELECT cossas FROM users WHERE id = {$userId}")->fetchColumn();
    $ok($saldoAntesCompra - $saldoDepoisCompra === 180, 'reenvio da mesma compra não duplica cobrança');

    $pdo->prepare("INSERT INTO produtos (codigo,nome,preco_cossas,categoria,estoque,limite_por_usuario) VALUES ('limitado_teste','Limitado',10,'item',1,1)")->execute();
    $limitadoId = (int)$pdo->lastInsertId();
    comprarProduto($pdo, $userEstoqueId, $limitadoId, bin2hex(random_bytes(24)));
    $semEstoque = false;
    try {
        comprarProduto($pdo, $userSemSaldoId, $limitadoId, bin2hex(random_bytes(24)));
    } catch (DomainException $e) {
        $semEstoque = true;
    }
    $ok($semEstoque && (int)$pdo->query("SELECT estoque FROM produtos WHERE id={$limitadoId}")->fetchColumn() === 0, 'estoque é decrementado atomicamente e bloqueia venda esgotada');

    $semSaldo = false;
    try {
        comprarProduto($pdo, $userSemSaldoId, $produtoId, bin2hex(random_bytes(24)));
    } catch (DomainException $e) {
        $semSaldo = str_contains($e->getMessage(), 'Saldo');
    }
    $ok($semSaldo, 'compra com saldo insuficiente é rejeitada');

    $duplicada = enriquecerQuestaoFallback([
        'enunciado' => 'Enunciado suficientemente completo para o teste de validação.',
        'opcao_a' => 'Duplicada', 'opcao_b' => 'Duplicada', 'opcao_c' => 'Outra', 'opcao_d' => 'Mais uma', 'correta' => 'A',
    ], 2);
    $listaDuplicada = array_fill(0, 5, $duplicada);
    $ok((bool)problemasQuestoesLocal($listaDuplicada), 'validador rejeita alternativas e enunciados duplicados');
    $ok(dicaRevelaResposta('A alternativa correta é A.', 'A', 'Somar o mesmo valor nos dois lados.'), 'validador impede dica que revela o gabarito');

    $ok(!$falhas, 'todos os cenários de integração passaram');
} finally {
    $hostTeste = (string)($host ?? 'localhost');
    $portaTeste = (int)($porta ?? 3306);
    $usuarioTeste = (string)($user ?? 'root');
    $senhaTeste = (string)($senha ?? '');
    $pdo = null;
    $servidor = new PDO("mysql:host={$hostTeste};port={$portaTeste};charset=utf8mb4", $usuarioTeste, $senhaTeste, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $servidor->exec("DROP DATABASE IF EXISTS `{$bancoTeste}`");
}

exit($falhas ? 1 : 0);
