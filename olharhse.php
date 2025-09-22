<?php
// olharhse.php
declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/header.php';
requireLogin();

// Verifica se o usuário tem permissão para acessar esta página (HSE ou Master)
if (!isHse() && !isMaster()) {
    header('Location: dashboard.php');
    exit;
}

$u = currentUser();

require_once __DIR__ . '/inc/csv.php';
require_once __DIR__ . '/inc/utils.php';



// Leitura de dados de todos os arquivos CSV necessários
$manutencaoCsv = csvRead(__DIR__ . '/data/manutencoes.csv');
$manutencoesH = $manutencaoCsv['header'] ?? [];
$manutencoesRows = $manutencaoCsv['rows'] ?? [];

$maquinasCsv = csvRead(__DIR__ . '/data/maquinas.csv');
$maquinasH = $maquinasCsv['header'] ?? [];
$maquinasRows = $maquinasCsv['rows'] ?? [];

$usersCsv = csvRead(__DIR__ . '/data/users.csv');
$usersH = $usersCsv['header'] ?? [];
$usersRows = $usersCsv['rows'] ?? [];

$checklistsCsv = csvRead(__DIR__ . '/data/checklists.csv');
$checklistsH = $checklistsCsv['header'] ?? [];
$checklistsRows = $checklistsCsv['rows'] ?? [];

$investHseCsv = csvRead(__DIR__ . '/data/invest_hse.csv');
$investHseH = $investHseCsv['header'] ?? [];
$investHseRows = $investHseCsv['rows'] ?? [];

// Mapeamento de dados para buscas eficientes
$maquinasMap = [];
foreach ($maquinasRows as $r) {
    if (count($r) === count($maquinasH)) {
        $maq = array_combine($maquinasH, $r);
        $maquinasMap[$maq['id']] = $maq;
    }
}

// Mapeamento de usuários por nome de usuário (ex: "master")
$usersMap = [];
foreach ($usersRows as $r) {
    if (count($r) === count($usersH)) {
        $user_data = array_combine($usersH, $r);
        $usersMap[$user_data['nome']] = $user_data; // Usa 'nome' para o mapeamento
    }
}

// Mapeamento de checklists por ID
$checklistsMap = [];
foreach ($checklistsRows as $r) {
    if (count($r) === count($checklistsH)) {
        $checklist_data = array_combine($checklistsH, $r);
        $checklistsMap[$checklist_data['id']] = $checklist_data;
    }
}

// Lógica para o primeiro gráfico (Manutenções por Status)
$manutencoes_por_status = array_reduce($manutencoesRows, function ($carry, $item) use ($manutencoesH) {
    if (count($item) === count($manutencoesH)) {
        $data = array_combine($manutencoesH, $item);
        $status = $data['status'] ?? 'N/D';
        $carry[$status] = ($carry[$status] ?? 0) + 1;
    }
    return $carry;
}, []);

$labelsStatus = json_encode(array_keys($manutencoes_por_status));
$dataStatus = json_encode(array_values($manutencoes_por_status));

// Lógica para o segundo gráfico (Manutenções por Tipo de Ação)
$manutencoes_por_acao = array_reduce($manutencoesRows, function ($carry, $item) use ($manutencoesH) {
    if (count($item) === count($manutencoesH)) {
        $data = array_combine($manutencoesH, $item);
        $acao = $data['acoes'] ?? 'N/D';
        $carry[$acao] = ($carry[$acao] ?? 0) + 1;
    }
    return $carry;
}, []);

$labelsAcao = json_encode(array_keys($manutencoes_por_acao));
$dataAcao = json_encode(array_values($manutencoes_por_acao));

// Lógica para o terceiro gráfico (Causas Raiz de Incidentes)
$causasRaiz = array_reduce($investHseRows, function ($carry, $item) use ($investHseH) {
    if (count($item) === count($investHseH)) {
        $data = array_combine($investHseH, $item);
        $causa = $data['causa_raiz'] ?? 'N/D';
        if ($causa !== 'N/D' && $causa !== '') {
            $carry[$causa] = ($carry[$causa] ?? 0) + 1;
        }
    }
    return $carry;
}, []);

$labelsCausasRaiz = json_encode(array_keys($causasRaiz));
$dataCausasRaiz = json_encode(array_values($causasRaiz));

// Lógica para lidar com a submissão do formulário de observação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['obs_hse'])) {
    $manutencaoId = $_POST['id'];
    $obsHse = $_POST['obs_hse'];

    $manutencoesRows = array_map(function ($row) use ($manutencoesH, $manutencaoId, $obsHse) {
        if (count($row) === count($manutencoesH)) {
            $data = array_combine($manutencoesH, $row);
            if ($data['id'] === $manutencaoId) {
                $data['obs_hse'] = $obsHse;
            }
            return array_values($data);
        }
        return $row;
    }, $manutencoesRows);

    csvWrite(__DIR__ . '/data/manutencoes.csv', $manutencoesH, $manutencoesRows);
    header('Location: olharhse.php');
    exit;
}

$manutencoes_relevantes = [];
foreach ($manutencoesRows as $row) {
    if (count($row) !== count($manutencoesH)) {
        continue;
    }
    $manutencao_data = array_combine($manutencoesH, $row);
    
    // Filtra as manutenções com a ação 'HSE'
    if (($manutencao_data['acoes'] ?? '') === 'HSE') {
        $manutencoes_relevantes[] = $manutencao_data;
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Dashboard HSE</title>
    <link rel="stylesheet" href="assets/stylenew.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .containerMaior { max-width: 1200px; margin: 20px auto; }
        .card { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .chart-container { display: flex; flex-wrap: wrap; justify-content: space-around; gap: 20px; margin-bottom: 20px; }
        .chart-item { width: 100%; max-width: 350px; }
        /* Ajuste para o gráfico de setor ficar menor */
        #manutencaoStatusChart { max-width: 250px; margin: 0 auto; } 

        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 14px; }
        th { background-color: #f2f2f2; }
        .status-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; color: #fff; }
        .status-ok { background-color: #28a745; }
        .status-pendente { background-color: #ffc107; }
        .status-erro { background-color: #dc3545; }
        .btn-acao { background-color: #0d6efd; color: #fff; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; }
        .btn-acao:hover { background-color: #0b5ed7; }
        .btn-investigar { background-color: #6c757d; color: #fff; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; }
        .btn-investigar:hover { background-color: #5a6268; }
    </style>
</head>
<body>
    <main class="containerMaior">
        <h2>Dashboard HSE</h2>

        <div class="chart-container">
            <!-- Gráfico 1: Manutenções por Status (Gráfico de Setor) -->
            <div class="chart-item card">
                <h3>Manutenções por Status</h3>
                <canvas id="manutencaoStatusChart"></canvas>
            </div>
            
            <!-- Gráfico 2: Manutenções por Tipo de Ação (Gráfico de Barras) -->
            <div class="chart-item card">
                <h3>Manutenções por Ação</h3>
                <canvas id="manutencaoAcaoChart"></canvas>
            </div>
            
            <!-- Gráfico 3: Causas Raiz de Incidentes (Gráfico de Barras Horizontais) -->
            <div class="chart-item card">
                <h3>Causas Raiz de Incidentes</h3>
                <canvas id="causasRaizChart"></canvas>
            </div>
        </div>

        <div class="card">
            <h3>Manutenções Relevantes para HSE</h3>
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>ID</th>
                        <th>Máquina</th>
                        <th>Tipo</th>
                        <th>Não Conformidades</th>
                        <th>Operador</th>
                        <th>Supervisor</th>
                        <th>Mecânico</th>
                        <th>Observação HSE</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                foreach ($manutencoes_relevantes as $manutencao_data) {
                    $maquina_data = $maquinasMap[$manutencao_data['id_maquina']] ?? null;
                    $checklist_data = $checklistsMap[$manutencao_data['id_checklist'] ?? ''] ?? null;
                    $obs_hse = $manutencao_data['obs_hse'] ?? '';
                    $data_inicio = date('d/m/Y H:i', strtotime($manutencao_data['data_inicio']));
                    
                    // Busca o nome do Operador
                    $operador_nome = $usersMap[$manutencao_data['aberto_por'] ?? '']['nome'] ?? 'N/D';
    
                    // Busca o nome do Supervisor (aprovador do checklist)
                    $supervisor_nome = 'N/D';
                    if ($checklist_data && isset($checklist_data['aprovado_por'])) {
                        $supervisor_nome = $usersMap[$checklist_data['aprovado_por']]['nome'] ?? 'N/D';
                    }
    
                    // Busca o nome do Mecânico
                    $mecanico_nome = $usersMap[$manutencao_data['mecanico'] ?? '']['nome'] ?? 'N/D';
    
                    // Conta as falhas (NCs) do checklist relacionado
                    $falhas_nc_count = 0;
                    if ($checklist_data && isset($checklist_data['falhas'])) {
                        $falhas_nc = json_decode($checklist_data['falhas'], true);
                        if (is_array($falhas_nc)) {
                            $falhas_nc_count = count($falhas_nc);
                        }
                    }
                ?>
                    <tr>
                        <td><?= htmlspecialchars($data_inicio) ?></td>
                        <td><?= htmlspecialchars($manutencao_data['id'] ?? '') ?></td>
                        <td><?= htmlspecialchars($maquina_data['nome'] ?? '') ?></td>
                        <td><?= htmlspecialchars($maquina_data['tipo'] ?? '') ?></td>
                        <td>
                            <?php if ($falhas_nc_count > 0): ?>
                                <span class="status-badge status-erro"><?= htmlspecialchars((string)$falhas_nc_count) ?></span>
                            <?php else: ?>
                                <span class="status-badge status-ok">OK</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($operador_nome) ?></td>
                        <td><?= htmlspecialchars($supervisor_nome) ?></td>
                        <td><?= htmlspecialchars($mecanico_nome) ?></td>
                        <td><?= htmlspecialchars($obs_hse) ?></td>
                        <td>
                            <form method="post" style="display:inline; margin-right: 5px;">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($manutencao_data['id'] ?? '') ?>">
                                <input type="text" name="obs_hse" placeholder="Adicionar observação" value="<?= htmlspecialchars($obs_hse) ?>">
                                <button type="submit" class="btn-acao">Salvar</button>
                            </form>
                            <?php
                            $investigacao_link = 'investigacao_form.php?' . http_build_query([
                                'id_checklist' => $manutencao_data['id_checklist'] ?? '',
                                'id_maquina' => $manutencao_data['id_maquina'] ?? '',
                                'operador' => $manutencao_data['aberto_por'] ?? '',
                                'supervisor' => $checklist_data['aprovado_por'] ?? '',
                            ]);
                            ?>
                            <a href="<?= htmlspecialchars($investigacao_link) ?>" class="btn-investigar">Investigar</a>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <script>
        // Dados do PHP para o JavaScript
        const labelsStatus = <?php echo $labelsStatus; ?>;
        const dataStatus = <?php echo $dataStatus; ?>;
        const labelsAcao = <?php echo $labelsAcao; ?>;
        const dataAcao = <?php echo $dataAcao; ?>;
        const labelsCausasRaiz = <?php echo $labelsCausasRaiz; ?>;
        const dataCausasRaiz = <?php echo $dataCausasRaiz; ?>;
        const totalStatus = dataStatus.reduce((a, b) => a + b, 0);

        const coresStatus = {
            'concluida': '#28a745', // verde
            'em_andamento': '#ffc107', // amarelo
            'pendente': '#dc3545', // vermelho
            'liberada': '#17a2b8', // ciano
            'rejeitada': '#6c757d' // cinza
        };

        const coresAcao = [
            '#007bff', '#28a745', '#dc3545', '#ffc107', '#6c757d', '#17a2b8', '#fd7e14'
        ];

        // Gráfico de Setor (Manutenções por Status)
        Chart.register(ChartDataLabels);
        new Chart(document.getElementById('manutencaoStatusChart'), {
            type: 'doughnut',
            data: {
                labels: labelsStatus,
                datasets: [{
                    data: dataStatus,
                    backgroundColor: labelsStatus.map(label => coresStatus[label] || '#999'),
                    borderColor: '#fff',
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top' },
                    title: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(tooltipItem) {
                                const dataset = tooltipItem.dataset;
                                const total = dataset.data.reduce((sum, value) => sum + value, 0);
                                const currentValue = dataset.data[tooltipItem.dataIndex];
                                const percentage = parseFloat(((currentValue / total) * 100).toFixed(1));
                                return ` ${tooltipItem.label}: ${currentValue} (${percentage}%)`;
                            }
                        }
                    },
                    datalabels: {
                        color: '#fff',
                        formatter: (value, ctx) => {
                            const percentage = (value / totalStatus * 100).toFixed(0);
                            return `${percentage}%`;
                        },
                        font: {
                            weight: 'bold',
                            size: 16
                        },
                    }
                }
            }
        });

        // Gráfico de Barras (Manutenções por Tipo de Ação)
        new Chart(document.getElementById('manutencaoAcaoChart'), {
            type: 'bar',
            data: {
                labels: labelsAcao,
                datasets: [{
                    label: 'Frequência',
                    data: dataAcao,
                    backgroundColor: coresAcao,
                    borderColor: '#fff',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });

        // Gráfico de Barras Horizontais (Causas Raiz de Incidentes)
        new Chart(document.getElementById('causasRaizChart'), {
            type: 'bar',
            data: {
                labels: labelsCausasRaiz,
                datasets: [{
                    label: 'Número de Incidentes',
                    data: dataCausasRaiz,
                    backgroundColor: 'rgba(255, 193, 7, 0.8)', // Cor amarela
                    borderColor: 'rgba(255, 193, 7, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                scales: {
                    x: { beginAtZero: true }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    </script>
</body>
</html>
