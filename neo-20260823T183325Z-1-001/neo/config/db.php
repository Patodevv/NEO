<?php

//config do bd
$host = 'localhost';
$dbname = 'neo';
$user = 'root';
$senha = '';

try {

    // Conexão do carai
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $senha
    );

    // configurações do pdo
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    die("Erro na conexão com o banco: " . $e->getMessage());

}



$pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        senha VARCHAR(255) NOT NULL,
        gostos TEXT,
        cor VARCHAR(20) DEFAULT '#0878ff',
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

try {
    $colunaGostos = $pdo->query("SHOW COLUMNS FROM users LIKE 'gostos'")->fetch();
    if (!$colunaGostos) {
        $pdo->exec("ALTER TABLE users ADD COLUMN gostos TEXT AFTER senha");
    }
} catch (PDOException $e) {
}


$pdo->exec("
    CREATE TABLE IF NOT EXISTS materias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");


$pdo->exec("
    CREATE TABLE IF NOT EXISTS conteudos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        materia_id INT NOT NULL,
        titulo VARCHAR(200) NOT NULL,
        status VARCHAR(50) DEFAULT 'Não iniciado',
        corpo TEXT,
        dificuldade INT DEFAULT 1,
        ordem INT DEFAULT 1,

        FOREIGN KEY (materia_id)
        REFERENCES materias(id)
        ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

try {
    $colunaDificuldade = $pdo->query("SHOW COLUMNS FROM conteudos LIKE 'dificuldade'")->fetch();
    if (!$colunaDificuldade) {
        $pdo->exec("ALTER TABLE conteudos ADD COLUMN dificuldade INT DEFAULT 1 AFTER corpo");
    }

    $colunaOrdem = $pdo->query("SHOW COLUMNS FROM conteudos LIKE 'ordem'")->fetch();
    if (!$colunaOrdem) {
        $pdo->exec("ALTER TABLE conteudos ADD COLUMN ordem INT DEFAULT 1 AFTER dificuldade");
    }
} catch (PDOException $e) {
}


$pdo->exec("
    CREATE TABLE IF NOT EXISTS questoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conteudo_id INT NOT NULL,
        enunciado TEXT NOT NULL,
        opcao_a VARCHAR(500) NOT NULL,
        opcao_b VARCHAR(500) NOT NULL,
        opcao_c VARCHAR(500) NOT NULL,
        opcao_d VARCHAR(500) NOT NULL,
        correta CHAR(1) NOT NULL,

        FOREIGN KEY (conteudo_id)
        REFERENCES conteudos(id)
        ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");


$pdo->exec("
    CREATE TABLE IF NOT EXISTS historico (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        conteudo_id INT NOT NULL,
        acertos INT NOT NULL,
        total INT NOT NULL,
        data TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

        FOREIGN KEY (conteudo_id)
        REFERENCES conteudos(id)
        ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

//vai ver se já tem livro
$totalMaterias = $pdo->query(
    "SELECT COUNT(*) FROM materias"
)->fetchColumn();


// add as tabela se n tiver
if ($totalMaterias == 0) {

    $materias = [
        'Matemática',
        'Português',
        'Física',
        'Química',
        'Biologia',
        'História',
        'Geografia',
        'Redação'
    ];

    $stmtMateria = $pdo->prepare(
        "INSERT INTO materias (nome) VALUES (?)"
    );

    foreach ($materias as $materia) {
        $stmtMateria->execute([$materia]);
    }
}



// Ve se ja tem conteudos
$totalConteudos = $pdo->query(
    "SELECT COUNT(*) FROM conteudos"
)->fetchColumn();


if ($totalConteudos == 0) {

    $stmtConteudo = $pdo->prepare("
        INSERT INTO conteudos
        (materia_id, titulo, status, corpo)
        VALUES (?, ?, ?, ?)
    ");

    $corpoExemplo =
        "Uma função do 1º grau relaciona duas grandezas por meio de uma expressão linear.\n\n" .
        "Na forma f(x) = ax + b, o coeficiente a determina a inclinação da reta " .
        "e b representa o ponto onde ela cruza o eixo vertical.\n\n" .
        "Exemplo: f(x) = 2x + 3. Quando x = 2, temos f(2) = 7.";


    $stmtConteudo->execute([
        1,
        'Funções do 1º grau',
        'Concluído',
        $corpoExemplo
    ]);

    $idConteudo = $pdo->lastInsertId();


    $stmtConteudo->execute([
        1,
        'Funções do 2º grau',
        'Em andamento',
        'Conteúdo sobre funções quadráticas, gráficos em parábola e suas raízes.'
    ]);


    $stmtConteudo->execute([
        1,
        'Geometria plana',
        'Não iniciado',
        'Conteúdo sobre áreas, perímetros e propriedades das figuras planas.'
    ]);


    $stmtConteudo->execute([
        1,
        'Probabilidade',
        'Não iniciado',
        'Conteúdo sobre cálculo de probabilidades e eventos.'
    ]);

    $stmtConteudo->execute([
        2,
        'Literatura brasileira',
        'Não iniciado',
        'Conteúdo sobre os principais movimentos literários do Brasil.'
    ]);


    $stmtQuestao = $pdo->prepare("
        INSERT INTO questoes
        (
            conteudo_id,
            enunciado,
            opcao_a,
            opcao_b,
            opcao_c,
            opcao_d,
            correta
        )
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmtQuestao->execute([
        $idConteudo,
        'Se f(x) = 2x + 3, qual é o valor de f(4)?',
        '7',
        '9',
        '11',
        '12',
        'C'
    ]);
}
