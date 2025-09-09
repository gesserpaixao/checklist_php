<?php
require_once __DIR__.'/inc/auth.php'; requireLogin();
require_once __DIR__.'/inc/csv.php';
if(!isSupervisor() && !isMaster()){ header('Location: dashboard.php'); exit; }

$csv = csvRead(__DIR__.'/data/checklists.csv'); $h=$csv['header']; $rows=$csv['rows'];
if($_SERVER['REQUEST_METHOD']==='POST'){
    $id = $_POST['id']; $action = $_POST['action'];
    // atualizar em memória
    foreach($rows as &$r){ if($r[0]===$id){
        if($action==='aprovar'){ $r[6]='aprovado'; $r[8]=currentUser()['nome']; }
        if($action==='reprovar'){ $r[6]='reprovado'; $r[8]=currentUser()['nome'];
            // se reprovação com falha crítica, adicionar manutenção
            if(!empty($_POST['falha_critica'])){
                // marca máquina em maintenance
                $idmaq = $r[2];
                // atualizar maquinas.csv
                $mq = csvRead(__DIR__.'/data/maquinas.csv'); $mh=$mq['header']; $mrows=$mq['rows'];
                foreach($mrows as &$mr){ if($mr[0]===$idmaq){ $mr[3]='em_manutencao'; } }
                csvWrite(__DIR__.'/data/maquinas.csv',$mh,$mrows);
                // append manutencoes
                csvAppend(__DIR__.'/data/manutencoes.csv', [
                    uniqid('m_'), // ID
                    $idmaq, // ID da máquina
                    date('c'), // entrada
                    '', // saida
                    currentUser()['nome'], // responsavel
                    '', // obs
                    date('c'), // data_inicio
                    '', // data_fim
                    '', // mecanico
                    'em_reparo', // status
                    'Reprovado no checklist, falha crítica detectada', // descricao_problema
                    '' // descricao_manutencao
                ]);
            }
        }
    } }
    csvWrite(__DIR__.'/data/checklists.csv',$h,$rows);
    header('Location: aprovacao.php'); exit;
}
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Aprovação</title>
 <link rel="stylesheet" href="assets/stylenew.css">
</head>
<body>
    <header class="header-painel">
        <h1>Painel</h1>
        <div class="user-info">
            <span><?=htmlspecialchars(currentUser()['nome'])?> (<?=htmlspecialchars(currentUser()['perfil'])?>)</span>
            <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i>Sair</a>
        </div>
    </header>
    <ul class="main-menu">
        <?php if(isOperador() || isMaster()): ?>
            <li><a href="workplace.php"><i class="fa-solid fa-clipboard-list"></i>Workplace</a></li>
        <?php endif; ?>
        <?php if(isSupervisor() || isMaster()): ?>
            <li><a href="aprovacao.php"><i class="fa-solid fa-check-to-slot"></i>Aprovação</a></li>
            <li><a href="manutencao.php"><i class="fa-solid fa-wrench"></i>Manutenção</a></li>
        <?php endif; ?>
        <?php if(isMaster()): ?>
            <li><a href="admin.php"><i class="fa-solid fa-user-gear"></i>Administração</a></li>
        <?php endif; ?>
        <?php if(isMecanica()): ?>
            <li><a href="mecanica.php"><i class="fa-solid fa-wrench"></i>Mecânica</a></li>
        <?php endif; ?>
         <li><a href="imprimir.php"><i class="fa-solid fa-download"></i>Imprimir</a></li>
         <li><a href="download.php"><i class="fa-solid fa-wrench"></i>Dowload</a></li>
         <li><a href="dashboard.php"><i class="fa-solid fa-wrench"></i>Voltar</a></li>
    </ul>


<main class="container">
  <h2>Checklists Pendentes</h2>
  <table>
    <thead><tr><th>ID</th><th>Data</th><th>Máquina</th><th>Operador</th><th>Falhas</th><th>Ações</th></tr></thead>
    <tbody>
    <?php foreach($rows as $r){ if($r[6]!=='pendente') continue; echo '<tr>';
        echo '<td>'.htmlspecialchars($r[0]).'</td>';
        echo '<td>'.htmlspecialchars($r[3]).'</td>';
        echo '<td>'.htmlspecialchars($r[2]).'</td>';
        echo '<td>'.htmlspecialchars($r[1]).'</td>';
        echo '<td>'.htmlspecialchars($r[9] ?? 0).'</td>';
        echo '<td><form method="post" style="display:inline"><input type="hidden" name="id" value="'.htmlspecialchars($r[0]).'"><button name="action" value="aprovar">Aprovar</button></form>';
        echo ' <form method="post" style="display:inline"><input type="hidden" name="id" value="'.htmlspecialchars($r[0]).'"><input type="checkbox" name="falha_critica" value="1">Falha crítica<button name="action" value="reprovar">Reprovar</button></form></td>';
    echo '</tr>'; } ?>
    </tbody>
  </table>
</main>
</body>
</html>
