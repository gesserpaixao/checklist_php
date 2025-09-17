<?php
require_once __DIR__.'/inc/auth.php'; requireLogin();
require_once __DIR__.'/inc/csv.php';
$u = currentUser();
if(!isSupervisor()) header('Location: dashboard.php');

$mq = csvRead(__DIR__.'/data/maquinas.csv'); $mh=$mq['header']; $mrows=$mq['rows'];
$mt = csvRead(__DIR__.'/data/manutencoes.csv'); $mth=$mt['header']; $mtrows=$mt['rows'];

// Mapear dados das máquinas para facilitar o acesso
$maquinas_map = [];
foreach ($mrows as $r) {
    if (count($r) === count($mh)) {
        $maq = array_combine($mh, $r);
        $maquinas_map[$maq['id']] = $maq;
    }
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(isset($_POST['entrada'])){
        $idmaq = $_POST['maquina_ent'];
        $obs = $_POST['obs_ent'] ?? '';
        
        // set status
        foreach($mrows as &$mr) if(count($mr)===count($mh) && $mr[0]===$idmaq) $mr[3]='em_manutencao';
        csvWrite(__DIR__.'/data/maquinas.csv',$mh,$mrows);

        // append manutencoes
        $novo_registro = [
            uniqid('m_'), // id (0)
            $idmaq, // id_maquina (1)
            date('c'), // entrada (2)
            '', // saida (3)
            currentUser()['nome'], // responsavel (4)
            $obs, // obs (5)
            date('c'), // data_inicio (6)
            '', // data_fim (7)
            '', // mecanico (8)
            'em_reparo', // status (9)
            $obs, // descricao_problema (10)
            '', // descricao_manutencao (11)
            '', // anexos (12)
            '', // check_id (13)
            '', // aberto_por (14)
            '', // id_checklist (15)
            ''  // prioridade (16)
        ];
        csvAppend(__DIR__.'/data/manutencoes.csv', $novo_registro);
        
        header('Location: manutencao.php'); exit;
    }

    if(isset($_POST['liberar_concluida'])){
        $id_manutencao = $_POST['id_manutencao'];
        $id_maquina = $_POST['id_maquina'];

        // Atualizar o status da máquina para 'disponivel'
        foreach($mrows as &$mr) {
            if(count($mr)===count($mh) && $mr[0]===$id_maquina) {
                $mr[3] = 'disponivel';
                break;
            }
        }
        csvWrite(__DIR__.'/data/maquinas.csv', $mh, $mrows);

        // Atualizar o status da manutenção para 'liberada'
        foreach($mtrows as &$mtr){
            if(count($mtr) !== count($mth)) continue;
            $mt_data = array_combine($mth, $mtr);
            if($mt_data['id'] === $id_manutencao) {
                $mtr = array_values(array_merge($mt_data, [
                    'status' => 'liberada'
                ]));
                break;
            }
        }
        csvWrite(__DIR__.'/data/manutencoes.csv', $mth, $mtrows);
        
        header('Location: manutencao.php'); exit;
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Manutenção</title>
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
    <h2>Manutenção</h2>
    <section>
        <h3>Entrada</h3>
        <form method="post">
            <select name="maquina_ent">
                <?php foreach($mrows as $mr){ if(count($mr) !== count($mh)) continue; $mm=array_combine($mh,$mr); if($mm['status']==='disponivel') echo '<option value="'.htmlspecialchars($mm['id']).'">'.htmlspecialchars($mm['id'].' - '.$mm['nome']).'</option>'; } ?>
            </select>
            <textarea name="obs_ent" placeholder="Observações"></textarea>
            <button name="entrada">Enviar para manutenção</button>
        </form>
    </section>

    <section>
        <h3>Máquinas Prontas para Liberação</h3>
        <table>
            <thead>
                <tr>
                    <th>ID Manutenção</th>
                    <th>ID Máquina</th>
                    <th>Nome Máquina</th>
                    <th>Descrição</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                foreach($mtrows as $mtrow) {
                    if (count($mtrow) !== count($mth)) continue;
                    $manut_data = array_combine($mth, $mtrow);
                    if ($manut_data['status'] === 'concluida') {
                        // Verifica se a máquina existe no mapa antes de exibir
                        if (isset($maquinas_map[$manut_data['id_maquina']])) {
                            $maquina = $maquinas_map[$manut_data['id_maquina']];
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($manut_data['id']) ?></td>
                                <td><?= htmlspecialchars($maquina['id']) ?></td>
                                <td><?= htmlspecialchars($maquina['nome']) ?></td>
                                <td><?= htmlspecialchars($manut_data['descricao_manutencao']) ?></td>
                                <td><?= htmlspecialchars($manut_data['status']) ?></td>
                                <td>
                                    <form method="post">
                                        <input type="hidden" name="id_manutencao" value="<?= htmlspecialchars($manut_data['id']) ?>">
                                        <input type="hidden" name="id_maquina" value="<?= htmlspecialchars($maquina['id']) ?>">
                                        <button name="liberar_concluida">Liberar</button>
                                    </form>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                }
                ?>
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
