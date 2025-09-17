<?php
require_once __DIR__.'/inc/auth.php';
requireLogin();
require_once __DIR__.'/inc/csv.php';

$u = currentUser();

if(!isMaster()){
    header('Location: dashboard.php');
    exit;
}

// carregar usuarios e maquinas
$u_data = csvRead(__DIR__.'/data/usuarios.csv');
$uh = $u_data['header'];
$urows = $u_data['rows'];

$m_data = csvRead(__DIR__.'/data/maquinas.csv');
$mh = $m_data['header'];
$mrows = $m_data['rows'];

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(isset($_POST['add_user'])){
        $nome = $_POST['nome'];
        $senha = $_POST['senha'];
        $perfil = $_POST['perfil'];
        csvAppend(__DIR__.'/data/usuarios.csv', [$nome, $senha, $perfil]);
        header('Location: admin.php');
        exit;
    }
    if(isset($_POST['del_user'])){
        $nome = $_POST['del_user'];
        $urows = array_filter($urows, function($r) use($nome){
            return $r[0] !== $nome;
        });
        csvWrite(__DIR__.'/data/usuarios.csv', $uh, $urows);
        header('Location: admin.php');
        exit;
    }
    if(isset($_POST['add_maq'])){
        csvAppend(__DIR__.'/data/maquinas.csv', [$_POST['maq_id'], $_POST['maq_tipo'], $_POST['maq_nome'], $_POST['maq_status']]);
        header('Location: admin.php');
        exit;
    }
    if(isset($_POST['del_maq'])){
        $id = $_POST['del_maq'];
        $mrows = array_filter($mrows, function($r) use($id){
            return $r[0] !== $id;
        });
        csvWrite(__DIR__.'/data/maquinas.csv', $mh, $mrows);
        header('Location: admin.php');
        exit;
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Admin</title>
    <link rel="stylesheet" href="assets/stylenew.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
   <header class="header-painel">
            <h1>Painel</h1>
            <ul class="main-menu">
                <li><a href="dashboard.php"><i class="fa-solid fa-clipboard-list"></i>Dashboard</a></li>
            </ul>
            <div class="user-info">
                <span><?= htmlspecialchars($u['nome']) ?> (<?= htmlspecialchars($u['perfil']) ?>)</span>
                <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i>Sair</a>
            </div>
        </header>
        <ul class="main-menu">
            <?php if (isOperador() || isMaster()): ?>
                <li><a href="workplace.php"><i class="fa-solid fa-clipboard-list"></i>Workplace</a></li>
            <?php endif; ?>
            <?php if (isSupervisor() || isMaster()): ?>
                <li><a href="aprovacao.php"><i class="fa-solid fa-check-to-slot"></i>Aprovação</a></li>
                <li><a href="manutencao.php"><i class="fa-solid fa-wrench"></i>Manutenção</a></li>
            <?php endif; ?>
               <?php if (isMecanica()): ?>
                <li><a href="mecanica.php"><i class="fa-solid fa-wrench"></i>Mecânica</a></li>
            <?php endif; ?>
          
             <?php if (isSupervisor() || isMaster()): ?>
                <li><a href="imprimir.php"><i class="fa-solid fa-download"></i>Imprimir</a></li>
                <li><a href="download.php"><i class="fa-solid fa-wrench"></i>Download</a></li>
            <?php endif; ?>

             <?php if (isMaster()): ?>
                <li><a href="admin.php"><i class="fa-solid fa-user-gear"></i>Administração</a></li>
            <?php endif; ?>
            <li><a href="dashboard.php"><i class="fa-solid fa-wrench"></i>Voltar</a></li>
        </ul>

    <main class="container">
        <h2>Administração</h2>
        <section>
            <h3>Usuários</h3>
            <form method="post">
                <input name="nome" placeholder="Nome">
                <input name="senha" placeholder="Senha">
                <select name="perfil">
                    <option>operador</option>
                    <option>supervisor</option>
                    <option>master</option>
                    <option>mecanico</option>
                </select>
                <button name="add_user">Adicionar</button>
            </form>
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Perfil</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($urows as $r){ echo '<tr><td>'.htmlspecialchars($r[0]).'</td><td>'.htmlspecialchars($r[2]).'</td><td><form method="post" style="display:inline"><button name="del_user" value="'.htmlspecialchars($r[0]).'">Remover</button></form></td></tr>'; } ?>
                </tbody>
            </table>
        </section>
        <section>
            <h3>Máquinas</h3>
            <form method="post">
                <input name="maq_id" placeholder="ID">
                <input name="maq_tipo" placeholder="Tipo">
                <input name="maq_nome" placeholder="Nome">
                <select name="maq_status">
                    <option value="disponivel">disponivel</option>
                    <option value="Inativo">Inativo</option>
                    <option value="em_manutencao">em_manutencao</option>
                </select>
                <button name="add_maq">Adicionar</button>
            </form>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tipo</th>
                        <th>Nome</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($mrows as $r){ echo '<tr><td>'.htmlspecialchars($r[0]).'</td><td>'.htmlspecialchars($r[1]).'</td><td>'.htmlspecialchars($r[2]).'</td><td>'.htmlspecialchars($r[3]).'</td><td><form method="post" style="display:inline"><button name="del_maq" value="'.htmlspecialchars($r[0]).'">Remover</button></form></td></tr>'; } ?>
                </tbody>
            </table>
        </section>
    </main>
</body>
</html>
