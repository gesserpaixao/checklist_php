<?php
require_once __DIR__.'/inc/csv.php';
require_once __DIR__.'/inc/auth.php';

$err = '';
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $nome = trim($_POST['nome'] ?? '');
    $senha = $_POST['senha'] ?? '';
    if(login($nome,$senha)){
        header('Location: dashboard.php'); exit;
    } else {
        $err = 'Usuário ou senha inválidos';
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
            height: 100vh; /* Apenas para o exemplo preencher a tela */
            margin: 0;
            padding: 20px;
            color: white; /* Cor do texto para contraste */
            font-family: Arial, sans-serif;
            
        }
    </style>


<!doctype html>
<html><head>
  <meta charset="utf-8"><title>Login - Checklist</title>
 <link rel="stylesheet" href="assets/stylenew.css">
</head>
<body>
<div class="card center">
  <h2>Entrar</h2>
  <?php if($err): ?><div class="alert"><?=htmlspecialchars($err)?></div><?php endif; ?>
  <form method="post">
    <label>Usuário<br><input name="nome" required></label><br>
    <label>Senha<br><input name="senha" type="password" required></label><br>
    <button>Entrar</button>
  </form>
</div>
</body></html>