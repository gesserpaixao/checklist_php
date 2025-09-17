<?php
// aprovação.php
declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__.'/inc/auth.php'; requireLogin();
require_once __DIR__.'/inc/csv.php';

requireLogin();
$u = currentUser();

if (isset($u) && is_array($u)) {
    $safe_value = htmlspecialchars($u['some_key'] ?? '');
} else {
    $safe_value = '';
}

if(!isSupervisor() && !isMaster()){ header('Location: dashboard.php'); exit; }

$csv = csvRead(__DIR__.'/data/checklists.csv'); $h=$csv['header']; $rows=$csv['rows'];

if($_SERVER['REQUEST_METHOD']==='POST'){
    $id = $_POST['id'];
    $action = $_POST['action'];

    $nova_lista_de_linhas = [];
    foreach($rows as $r){ 
        if(count($r) !== count($h)) {
            $nova_lista_de_linhas[] = $r; // Mantém linhas inválidas
            continue;
        }
        
        $checklist_data = array_combine($h, $r);

        if($checklist_data['id'] === $id){
            if($action === 'aprovar'){ 
                $checklist_data['status_checklist'] = 'aprovado'; 
                $checklist_data['aprovado_por'] = currentUser()['nome'];
            } else if($action === 'reprovar'){ 
                $checklist_data['status_checklist'] = 'reprovado'; 
                $checklist_data['aprovado_por'] = currentUser()['nome']; 
            } else if($action === 'enviar_para_manutencao'){
                $checklist_data['status_checklist'] = 'em_reparo'; 
                
                // Pega o ID da máquina
                $idmaq = $checklist_data['id_maquina'];
                
                // Marca a máquina com status 'em_manutencao'
                $mq = csvRead(__DIR__.'/data/maquinas.csv'); $mh=$mq['header']; $mrows=$mq['rows'];
                foreach($mrows as &$mr){ 
                    if(count($mr)===count($mh) && $mr[0]===$idmaq){ 
                        $mr[3] = 'em_manutencao'; 
                    } 
                }
                csvWrite(__DIR__.'/data/maquinas.csv',$mh,$mrows);
                
                // Adiciona um novo registro em manutencoes.csv
                csvAppend(__DIR__.'/data/manutencoes.csv', [
                    uniqid('m_'), 
                    $idmaq, 
                    date('c'), 
                    '', 
                    currentUser()['nome'], 
                    '', 
                    date('c'), 
                    '', 
                    '', 
                    'em_reparo', 
                    'Falhas encontradas no checklist pré-operacional.', 
                    '', 
                    '', 
                    $checklist_data['id'], // ID do checklist na manutenção
                    '', 
                    '', 
                    ''
                ]);
            }
        }
        // Reconstrói a linha na ordem correta do cabeçalho
        $nova_linha = [];
        foreach($h as $campo){
            $nova_linha[] = $checklist_data[$campo] ?? '';
        }
        $nova_lista_de_linhas[] = $nova_linha;
    }
    csvWrite(__DIR__.'/data/checklists.csv',$h,$nova_lista_de_linhas);
    header('Location: aprovacao.php'); 
    exit;
}

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Aprovação</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/stylenew.css">
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
        <h2>Checklists Pendentes </h2>
        <table>
            <thead><tr><th>ID</th><th>Data</th><th>Máquina</th><th>Operador</th><th>Falhas (NC)</th><th>Ações</th></tr></thead>
            <tbody>
            <?php
            foreach ($rows as $r) {
                if (count($r) !== count($h)) continue;
                $checklist_data = array_combine($h, $r);

                // Mostra o checklist se o status for pendente
                if (($checklist_data['status_checklist'] ?? '') === 'pendente') {
                    
                    // Conta quantas falhas (NC) existem no checklist
                    $respostas_json = $checklist_data['respostas_json'] ?? '[]';
                    $respostas = json_decode($respostas_json, true);
                    $falhas_nc = 0;
                    if (is_array($respostas)) {
                        foreach ($respostas as $resposta) {
                            if ($resposta === 'NC') {
                                $falhas_nc++;
                            }
                        }
                    }

                    echo '<tr>';
                    echo '<td>'.htmlspecialchars($checklist_data['id']).'</td>';
                    echo '<td>'.htmlspecialchars($checklist_data['data_abertura']).'</td>';
                    echo '<td>'.htmlspecialchars($checklist_data['id_maquina']).'</td>';
                    echo '<td>'.htmlspecialchars($checklist_data['operador']).'</td>';
                    echo '<td>'.htmlspecialchars((string)$falhas_nc).'</td>';
                    echo '<td>';
                    echo '<form method="post" style="display:inline"><input type="hidden" name="id" value="'.htmlspecialchars($checklist_data['id']).'"><button name="action" value="aprovar">Aprovar</button></form>';
                    echo ' <form method="post" style="display:inline"><input type="hidden" name="id" value="'.htmlspecialchars($checklist_data['id']).'"><button name="action" value="reprovar">Reprovar</button></form>';
                    
                    // Adiciona o botão "Enviar para Manutenção" apenas se houver falhas (NC)
                    if ($falhas_nc > 0) {
                        echo ' <form method="post" style="display:inline"><input type="hidden" name="id" value="'.htmlspecialchars($checklist_data['id']).'"><button name="action" value="enviar_para_manutencao">Enviar para Manutenção</button></form>';
                    }

                    echo '</td>';
                    echo '</tr>';
                }
            }
            ?>
            </tbody>
        </table>
    </main>
</body>
</html>