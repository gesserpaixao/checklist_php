<?php
// dashboard.php
declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/inc/auth.php';
requireLogin();
$u = currentUser();

// Replace it with this more robust code:
if (isset($u) && is_array($u)) {
    // Variable $u exists and is an array, so it is safe to use.
     $safe_value = htmlspecialchars($u['some_key'] ?? '');
} else {
    // Handle the case where the variable is not defined or is not an array.
    // Set a default value to prevent further errors.
    $safe_value = '';
}

require_once __DIR__ . '/inc/csv.php';
require_once __DIR__ . '/inc/utils.php'; // Adicionado para a função 'getLatestMaintenanceId'

// Leitura dos dados
$maqCsv = csvRead(__DIR__ . '/data/maquinas.csv');
$maquinas = $maqCsv['rows'] ?? [];
$maqH = $maqCsv['header'] ?? [];

$checklistCsv = csvRead(__DIR__ . '/data/checklists.csv');
$checklists = $checklistCsv['rows'] ?? [];
$checkH = $checklistCsv['header'] ?? [];

$manutencaoCsv = csvRead(__DIR__ . '/data/manutencoes.csv');
$manutencoes = $manutencaoCsv['rows'] ?? [];
$manutH = $manutencaoCsv['header'] ?? [];

// Mapear cabeçalhos para facilitar o acesso
$maquinas_map = [];
foreach ($maquinas as $r) {
    if (count($r) === count($maqH)) {
        $maq = array_combine($maqH, $r);
        $maquinas_map[$maq['id']] = $maq;
    }
}

// Mapear cabeçalhos para facilitar o acesso
$checklists_map = [];
foreach ($checklists as $r) {
    if (count($r) === count($checkH)) {
        $checks = array_combine($checkH, $r);
        $checklists_map[$checks['id']] = $checks;
    }
}

// Inicializar contadores
$frota_ativa = 0;
$frota_manutencao = 0;
$frota_nao_conforme = 0;
$frota_em_aprovacao = 0;
$checklists_abertos_hoje = 0;

// Processar dados para os cards
foreach ($checklists_map as $checks) {
    switch ($checks['status_checklist'] ?? 'pendente') {
        case 'pendente':
            $frota_em_aprovacao++;
        break;
    }
}

// Processar dados para os cards
foreach ($checklists_map as $checks) {
    if ($checks['falhas'] >0) {       
                $frota_nao_conforme++;    
    }
}

// Processar dados para os cards
foreach ($maquinas_map as $maq) {
    switch ($maq['status'] ?? 'disponivel') {
        case 'disponivel':
            $frota_ativa++;
            break;
        case 'em_manutencao':
            $frota_manutencao++;
            break;
    }
}



// Contar checklists abertos para o card
$hoje = (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
foreach ($checklists as $r) {
    if (count($r) === count($checkH)) {
        $check_data = array_combine($checkH, $r);
        // Acesso seguro à chave 'inicio' para evitar o erro
        $data_inicio_short = substr($check_data['inicio'] ?? '', 0, 10);
        if (($check_data['status'] ?? '') === 'pendente' && $data_inicio_short === $hoje) {
            $checklists_abertos_hoje++;
        }
    }
}

// Filtra checklists visíveis para a tabela
$checklists_visiveis = array_filter($checklists, function ($c) use ($u, $checkH, $hoje) {
    if (count($c) !== count($checkH)) {
        return false;
    }
    $check_data = array_combine($checkH, $c);
    // Acesso seguro à chave 'inicio' para evitar o erro
    $data_inicio_short = substr($check_data['inicio'] ?? '', 0, 10);

    // Exibe apenas os checklists abertos hoje, pendentes ou reprovados
    return ($data_inicio_short === $hoje && ($check_data['status'] === 'pendente' || $check_data['status'] === 'reprovado'));
});

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
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
        <h2>Dashboard</h2>
        <section class="dashboard-cards">
            <div class="card status-ativo">
                <h3>Frota Ativa</h3>
                <p><?= $frota_ativa ?></p>
            </div>
            <div class="card status-manutencao">
                <h3>Em Manutenção</h3>
                <p><?= $frota_manutencao ?></p>
            </div>
            <div class="card status-nao-conforme">
                <h3>Não Conforme</h3>
                <p><?= $frota_nao_conforme ?></p>
            </div>
            <div class="card em-aprovacao">
                <h3>Em aprovação</h3>
                <p><?= $frota_em_aprovacao ?></p>
            </div>
            <div class="card status-aberto">
                <h3>Checklists Abertos Hoje</h3>
                <p><?= $checklists_abertos_hoje ?></p>
            </div>
        </section>

        <?php if (isSupervisor() || isAdministrador() || isMaster()): ?>
            <h3>Checklists Pendentes</h3>
            <table>
                <thead>
                    <tr>
                        <th>Máquina</th>
                        <th>Turno</th>
                        <th>Aberto por</th>
                        <th>Falhas</th>
                        <th>Tempo em Manutenção</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($checklists_visiveis)): ?>
                        <tr>
                            <td colspan="6" class="text-center">Nenhum checklist pendente hoje.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($checklists_visiveis as $r):
                            if (count($r) !== count($checkH)) continue;
                            $checklist = array_combine($checkH, $r);
                            $maquina_info = $maquinas_map[$checklist['id_maquina'] ?? ''] ?? null;
                            if (!$maquina_info || ($maquina_info['status'] ?? '') === 'concluida') continue;
                            $maquina_nome = $maquina_info['nome'] ?? 'Máquina desconhecida';
                            $maquina_status = $maquina_info['status'] ?? 'desconhecido';
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($maquina_nome) ?> (<?= htmlspecialchars($checklist['id_maquina'] ?? 'N/A') ?>)</td>
                                <td><?= htmlspecialchars($checklist['turno'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($checklist['aberto_por'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($checklist['falhas'] ?? 'N/A') ?></td>
                                <td>
                                    <?php
                                    if ($maquina_status === 'em_manutencao') {
                                        $manutencao_id = getLatestMaintenanceId($checklist['id_maquina'] ?? '', $manutencoes, $manutH);
                                        $manutencao_info = null;
                                        foreach ($manutencoes as $m) {
                                            if (count($m) !== count($manutH)) continue;
                                            $m_data = array_combine($manutH, $m);
                                            if (($m_data['id'] ?? '') === $manutencao_id) {
                                                $manutencao_info = $m_data;
                                                break;
                                            }
                                        }
                                        if ($manutencao_info) {
                                            $inicio = new DateTime($manutencao_info['data_inicio']);
                                            $agora = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
                                            $intervalo = $agora->diff($inicio);
                                            echo $intervalo->format('%d d, %h h, %i m');
                                        }
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <a href="aprovacao.php?id=<?= htmlspecialchars($checklist['id'] ?? '') ?>">Ver Checklist</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
</body>
</html>
