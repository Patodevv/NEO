<?php
// Only this suite's temporary database can be created or removed.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$name = (string)getenv('DB_NAME');
if (!preg_match('/^neo_personalization_ui_\d+$/', $name)) throw new RuntimeException('Unsafe fixture database name.');
require dirname(__DIR__) . '/config/db.php';
$action = $argv[1] ?? 'setup';
if ($action === 'cleanup') {
    $pdo->exec("DROP DATABASE `{$name}`");
    echo "Temporary test database removed.\n";
    exit;
}
if ($action === 'setup') {
    $pdo->prepare('INSERT INTO users(nome,email,senha) VALUES (?,?,?)')->execute(['Lara', 'legacy@example.test', password_hash('Neo-test-1234', PASSWORD_DEFAULT)]);
    echo "Temporary database and legacy account ready.\n";
    exit;
}
if ($action === 'profile') {
    $stmt = $pdo->prepare('SELECT id,nome,email,gostos,personalizacao_versao,personalizacao_json FROM users WHERE email=?');
    $stmt->execute([$argv[2] ?? '']);
    echo json_encode($stmt->fetch(), JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action === 'questions') {
    $userId = (int)$pdo->query("SELECT id FROM users WHERE email='lara@example.test'")->fetchColumn();
    if (!$userId) throw new RuntimeException('Complete the signup first.');
    $stmt = $pdo->prepare('SELECT id FROM conteudos WHERE user_id=? AND removido_em IS NULL ORDER BY id LIMIT 1');
    $stmt->execute([$userId]);
    $contentId = (int)$stmt->fetchColumn();
    if (!$contentId) throw new RuntimeException('Onboarding must create the study track.');
    $stmt = $pdo->prepare('INSERT INTO questoes(user_id,conteudo_id,enunciado,opcao_a,opcao_b,opcao_c,opcao_d,correta,dificuldade,explicacao_correta,feedback_a,feedback_b,feedback_c,feedback_d) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    for ($i=1; $i<=5; $i++) $stmt->execute([$userId,$contentId,"Questão {$i}: quanto é 20% de 100?",'20','10','50','80','A',1,'20% de 100 é 20, pois 100 × 0,20 = 20.','Correto.','Converta a porcentagem para decimal.','Metade equivale a 50%.','80% é a parcela restante.']);
    echo json_encode(['userId'=>$userId,'contentId'=>$contentId]);
    exit;
}
throw new RuntimeException('Unknown fixture action.');
