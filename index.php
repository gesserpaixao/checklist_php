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
    <link rel="icon" href="assets/emp.png" type="image/x-icon">
    <link rel="stylesheet" href="assets/stylenew.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.0.2/jquery.min.js"></script>

</head>
<body>
<div class="container" style="display: flex;">
    <div class="login-image">
        <img src="assets/logo.jpg" alt="Logo da Empresa" class="header-logo">
    </div>
    <div class="card center">
        <h2>Entrar</h2>
        <?php if ($err): ?>
            <div class="alert"><?= htmlspecialchars($err) ?></div>
        <?php endif; ?>
        <form method="post">
            <label>Usuario<br><input name="nome" required></label><br>
            <label>Senha<br><input name="senha" style="display: inline-block;" id="senha" type="password" required>
                <img class="in-block" style="display: inline-block;" id="olho" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAABDUlEQVQ4jd2SvW3DMBBGbwQVKlyo4BGC4FKFS4+TATKCNxAggkeoSpHSRQbwAB7AA7hQoUKFLH6E2qQQHfgHdpo0yQHX8T3exyPR/ytlQ8kOhgV7FvSx9+xglA3lM3DBgh0LPn/onbJhcQ0bv2SHlgVgQa/suFHVkCg7bm5gzB2OyvjlDFdDcoa19etZMN8Qp7oUDPEM2KFV1ZAQO2zPMBERO7Ra4JQNpRa4K4FDS0R0IdneCbQLb4/zh/c7QdH4NL40tPXrovFpjHQr6PJ6yr5hQV80PiUiIm1OKxZ0LICS8TWvpyyOf2DBQQtcXk8Zi3+JcKfNafVsjZ0WfGgJlZZQxZjdwzX+ykf6u/UF0Fwo5Apfcq8AAAAASUVORK5CYII=">
            </label>
            <br>
            <button>Entrar</button>
        </form>
        <script>
            var senha = $('#senha');
            var olho= $("#olho");

            olho.mousedown(function() {
            senha.attr("type", "text");
            });

            olho.mouseup(function() {
            senha.attr("type", "password");
            });
            // para evitar o problema de arrastar a imagem e a senha continuar exposta, 
            //citada pelo nosso amigo nos comentários
            $( "#olho" ).mouseout(function() { 
            $("#senha").attr("type", "password");
            });
        </script>
    </div>
</div>
</body>
</html>
