<?php
require_once __DIR__.'/inc/csv.php';
require_once __DIR__.'/inc/auth.php';

// Inicia a sess\u00e3o para verificar se o usu\u00e1rio j\u00e1 est\u00e1 logado
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verifica se o usu\u00e1rio j\u00e1 est\u00e1 autenticado
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $senha = $_POST['senha'] ?? '';
    if (login($nome, $senha)) {
        header('Location: dashboard.php');
        exit;
    } else {
        $err = 'Usu\u00e1rio ou senha inv\u00e1lidos';
    }
}
?>

<style>
    body {
        background-image: url('assets/emp.png');
        background-repeat: no-repeat;
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        height: 100vh;
        margin: 0;
        padding: 20px;
        color: white;
        font-family: Arial, sans-serif;
    }
</style>

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Login - Checklist</title>
    <link rel="stylesheet" href="assets/stylenew.css">
</head>
<body>
<div class="card center">
    <h2>Entrar</h2>
    <?php if ($err): ?>
        <div class="alert"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>
    <form method="post">
        <label>Usu\u00e1rio<br><input name="nome" required></label><br>
        <label>Senha<br><input name="senha" type="password" required></label><br>
        <button>Entrar</button>
    </form>
</div>
</body>
</html>
