<?php
// workplace.php
declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');
// ... 
require_once __DIR__.'/inc/auth.php';
require_once __DIR__ . '/inc/header.php';
requireLogin();
// Usuário logado
$u = currentUser();
// Permissão para acessar a página
if(!isMecanica() && !isMaster()) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__.'/inc/csv.php';
require_once __DIR__ . '/inc/utils.php';
// Leitura dos dados das máquinas
$maqCsv = csvRead(__DIR__.'/data/maquinas.csv');
$maquinas = $maqCsv['rows'] ?? [];
$maqH = $maqCsv['header'] ?? [];

// Mapear dados das máquinas para facilitar o acesso
$maquinas_map = [];
foreach ($maquinas as $r) {
    if (count($r) === count($maqH)) {
        $maq = array_combine($maqH, $r);
        $maquinas_map[$maq['id']] = $maq;
    }
}

// Leitura dos dados de manutenções
$manutCsv = csvRead(__DIR__.'/data/manutencoes.csv');
$manutencoes = $manutCsv['rows'] ?? [];
$manutH = $manutCsv['header'] ?? [];

// ** CORREÇÃO: Lê os dados dos usuários e checklists para evitar erros.
$usersCsv = csvRead(__DIR__.'/data/users.csv');
$usersRows = $usersCsv['rows'] ?? [];
$usersH = $usersCsv['header'] ?? [];

$checklistsCsv = csvRead(__DIR__.'/data/checklists.csv');
$checklistsRows = $checklistsCsv['rows'] ?? [];
$checklistsH = $checklistsCsv['header'] ?? [];

// Lógica para processar as ações do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
        $id_manutencao = $_POST['id_manutencao'];
        $novo_status = $_POST['status'];
        $mecanico = $u['nome'];

        foreach ($manutencoes as &$manut) {
            if (count($manut) !== count($manutH)) {
                continue;
            }
            $manut_data = array_combine($manutH, $manut);
            if ($manut_data['id'] === $id_manutencao) {
                $manut_data['status'] = $novo_status;
                $manut_data['mecanico'] = $mecanico;
                $manut = array_values($manut_data);
                break;
            }
        }
        csvWrite(__DIR__.'/data/manutencoes.csv', $manutH, $manutencoes);
        header('Location: mecanica.php');
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'concluir_manutencao') {
        // Lógica de upload de arquivos
        $upload_dir = __DIR__.'/store/mecanica/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $anexo_paths = [];
        if (isset($_FILES['anexos']) && is_array($_FILES['anexos']['name'])) {
            foreach ($_FILES['anexos']['name'] as $key => $name) {
                if ($_FILES['anexos']['error'][$key] == UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['anexos']['tmp_name'][$key];
                    $file_name = uniqid('anexos_') . '_' . basename($name);
                    $target_file = $upload_dir . $file_name;

                    if (move_uploaded_file($tmp_name, $target_file)) {
                        $anexo_paths[] = 'store/mecanica/' . $file_name;
                    }
                }
            }
        }
        $anexos_string = implode(',', $anexo_paths);

        $id_manutencao = $_POST['id_manutencao'];
        $descricao_manutencao = $_POST['descricao_manutencao'] ?? '';
        $conclusao = $_POST['conclusaoNC'] ?? '';
        
        // ** CORREÇÃO **: Ações é um campo de rádio, que envia uma string, não um array.
        $acoes = $_POST['acoes'] ?? ''; 
        
        $mecanico = $u['nome'];

        $id_maquina_relacionada = '';
        foreach ($manutencoes as &$manut) {
            if (count($manut) !== count($manutH)) {
                continue;
            }
            $manut_data = array_combine($manutH, $manut);
            if ($manut_data['id'] === $id_manutencao) {
                $manut_data['status'] = 'concluida';
                $manut_data['data_fim'] = date('c');
                $manut_data['saida'] = date('c'); // Adicionado para atualizar o campo 'saida'
                $manut_data['descricao_manutencao'] = $descricao_manutencao;
                $manut_data['mecanico'] = $mecanico;
                $manut_data['conclusao'] = $conclusao;
                $manut_data['acoes'] = $acoes;
                // Adiciona o novo campo 'anexo'
                $manut_data['anexos'] = $anexos_string;
                $id_maquina_relacionada = $manut_data['id_maquina'];
                $manut = array_values($manut_data);
                break;
            }
        }
        csvWrite(__DIR__.'/data/manutencoes.csv', $manutH, $manutencoes);

        if (!empty($id_maquina_relacionada)) {
            $maquinas_updated = $maquinas;
            foreach ($maquinas_updated as &$maq_row) {
                if (count($maq_row) === count($maqH)) {
                    $maq_data = array_combine($maqH, $maq_row);
                    if ($maq_data['id'] === $id_maquina_relacionada) {
                        $maq_data['status'] = 'em_liberacao';
                        $maq_row = array_values($maq_data);
                        break;
                    }
                }
            }
            csvWrite(__DIR__.'/data/maquinas.csv', $maqH, $maquinas_updated);
        }

        header('Location: mecanica.php');
        exit;
    }
}

// Filtrar as manutenções abertas
$manutencoes_abertas = array_filter($manutencoes, function($manut) use ($manutH) {
    if (count($manut) !== count($manutH)) {
        return false;
    }
    
    $manut_data = array_combine($manutH, $manut);
    
    $status_atual = $manut_data['status'] ?? '';
    return in_array($status_atual, ['em_reparo', 'aguardando_pecas','concluido','intivar']);
});

usort($manutencoes_abertas, function($a, $b) use ($manutH) {
    if (count($a) !== count($manutH) || count($b) !== count($manutH)) {
        return 0;
    }
    $a_data = array_combine($manutH, $a);
    $b_data = array_combine($manutH, $b);
    return strtotime($b_data['data_inicio']) - strtotime($a_data['data_inicio']);
});

$manutencoes_filtradas = [];
$maquinas_ja_processadas = [];
foreach ($manutencoes_abertas as $manut) {
    if (count($manut) !== count($manutH)) {
        continue;
    }
    $manut_data = array_combine($manutH, $manut);
    $id_maquina = $manut_data['id_maquina'];
    if (!in_array($id_maquina, $maquinas_ja_processadas)) {
        $manutencoes_filtradas[] = $manut;
        $maquinas_ja_processadas[] = $id_maquina;
    }
}


// --- Lógica para gerar dados dos gráficos ---
$manutencoes_por_mes = [];
$conclusoes_contagem = [];

foreach ($manutencoes as $manut) {
    if (count($manut) !== count($manutH)) {
        continue;
    }
    $manut_data = array_combine($manutH, $manut);

    // Dados para o gráfico de barras (entrada e saída por mês)
    if (!empty($manut_data['data_inicio'])) {
        $mes_entrada = date('Y-m', strtotime($manut_data['data_inicio']));
        if (!isset($manutencoes_por_mes[$mes_entrada])) {
            $manutencoes_por_mes[$mes_entrada] = ['entrada' => 0, 'saida' => 0];
        }
        $manutencoes_por_mes[$mes_entrada]['entrada']++;
    }
    if (!empty($manut_data['data_fim'])) {
        $mes_saida = date('Y-m', strtotime($manut_data['data_fim']));
        if (!isset($manutencoes_por_mes[$mes_saida])) {
            $manutencoes_por_mes[$mes_saida] = ['entrada' => 0, 'saida' => 0];
        }
        $manutencoes_por_mes[$mes_saida]['saida']++;
    }

    // Dados para o gráfico de pizza (conclusão)
    if (!empty($manut_data['conclusao'])) {
        $conclusao_motivo = $manut_data['conclusao'];
        if (!isset($conclusoes_contagem[$conclusao_motivo])) {
            $conclusoes_contagem[$conclusao_motivo] = 0;
        }
        $conclusoes_contagem[$conclusao_motivo]++;
    }
}

ksort($manutencoes_por_mes);


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
// ** CORREÇÃO: Usando a variável $manutencoes, que já está lida, em vez de $manutencoesRows
$manutencoes_por_status = array_reduce($manutencoes, function ($carry, $item) use ($manutH) {
    if (count($item) === count($manutH)) {
        $data = array_combine($manutH, $item);
        $status = $data['status'] ?? 'N/D';
        $carry[$status] = ($carry[$status] ?? 0) + 1;
    }
    return $carry;
}, []);

$labelsStatus = json_encode(array_keys($manutencoes_por_status));
$dataStatus = json_encode(array_values($manutencoes_por_status));

// ** CORREÇÃO: Adicionando variáveis vazias para evitar erros nas outras partes do JS
$labelsAcao = json_encode([]);
$dataAcao = json_encode([]);
$labelsCausasRaiz = json_encode([]);
$dataCausasRaiz = json_encode([]);


?>

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Mecânica</title>
    <link rel="stylesheet" href="assets/stylenew.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            color: #333;
        }

        .containerMaior {
            width: 95%;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: #555;
            text-align: center;
            margin-bottom: 20px;
        }

        /* Ajustes no CSS para o layout dos gráficos */
        .charts-container {
            display: flex;
            justify-content: space-between; /* Altera para distribuir o espaço entre os itens */
            align-items: flex-start;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .chart-box {
            width: 30%; /* Alterado para 30% para que os 3 gráficos caibam em uma linha */
            max-width: 600px;
            min-width: 300px;
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        thead tr {
            background-color: #eef1f4;
            color: #555;
            text-transform: uppercase;
            font-size: 14px;
        }

        tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        tbody tr:hover {
            background-color: #f1f3f5;
        }

        select, textarea, input[type="file"], button {
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ccc;
            font-size: 14px;
        }

        button.button-encerrar {
            background-color: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        button.button-encerrar:hover {
            background-color: #0056b3;
        }

        .container-checkboxes {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .checkbox-group label {
            display: block;
        }

        @media (max-width: 768px) {
            .charts-container {
                flex-direction: column;
                align-items: center;
            }
            .chart-box {
                width: 95%;
            }
        }
    </style>
</head>
<body>
    <main class="containerMaior">
        <div class="charts-container">
            <div class="chart-box">
                <canvas id="manutencoesMesChart"></canvas>
            </div>
            <div class="chart-box">
                <canvas id="conclusoesChart"></canvas>
            </div>
            <div class="chart-box">
                <canvas id="manutencaoStatusChart"></canvas>
            </div>
        </div>
        <h2>Painel de Mecânica</h2>
        <table>
            <thead>
                <tr>
                    <th>ID Manutenção</th>
                    <th>checklist_id</th>
                    <th>Máquina</th>
                    <th>Problema</th>
                    <th>Entrada</th>
                    <th>Status</th>
                    <th>Observação</th>
                    <th>Conclusão</th>
                    <th>Anexo</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php 
            foreach ($manutencoes_filtradas as $manut): 
                $manut_data = array_combine($manutH, $manut);
                $maquina_info = $maquinas_map[$manut_data['id_maquina']] ?? ['nome' => 'N/A'];
            ?>
            <tr>
                <td><?= htmlspecialchars($manut_data['id']) ?></td>
                <td><?= htmlspecialchars($manut_data['id_checklist']) ?></td>
                <td><?= htmlspecialchars($maquina_info['nome']) ?></td>
                <td><?= htmlspecialchars($manut_data['descricao_problema'] ?? '') ?></td>
                <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($manut_data['data_inicio']))) ?></td>
                <td>
                    <form method="post">
                        <input type="hidden" name="id_manutencao" value="<?= htmlspecialchars($manut_data['id']) ?>">
                        <input type="hidden" name="action" value="update_status">
                        <select name="status" onchange="this.form.submit()">
                            <option value="em_reparo" <?= ($manut_data['status'] ?? '') === 'em_reparo'?'selected':'' ?>>Em Reparo</option>
                            <option value="aguardando_pecas" <?= ($manut_data['status'] ?? '') === 'aguardando_pecas'?'selected':'' ?>>Aguardando Peças</option>
                            <option value="concluido" <?= ($manut_data['status'] ?? '') === 'concluido'?'selected':'' ?>>Concluido</option>
                            <option value="inativar" <?= ($manut_data['status'] ?? '') === 'inativar'?'selected':'' ?>>Inativar</option>
                            
                        </select>
                    </form>
                </td>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="id_manutencao" value="<?= htmlspecialchars($manut_data['id']) ?>">
                    <input type="hidden" name="action" value="concluir_manutencao">
                    <td>
                        <textarea name="descricao_manutencao" placeholder="Descrição da manutenção" required><?= htmlspecialchars($manut_data['descricao_manutencao'] ?? '') ?></textarea>
                    </td>
                    <td>
                        <select name="conclusaoNC" id="conclusaoNC_<?= htmlspecialchars($manut_data['id']) ?>" required>
                            <option value="">Selecione...</option>
                            <option value="Falha Operacional">Falha Operacional</option>
                            <option value="Qualidade Equipamento">Qualidade Equipamento</option>
                            <option value="Falta de manutenção">Falta de Manutenção</option>
                        </select>
                    </td>
                    <td>
                        <input type="file" placeholder="Inserir anexos" name="anexos[]" multiple>
                        <?php if (!empty($manut_data['anexos'])): ?>
                            <?php $anexos = explode(',', $manut_data['anexos']); ?>
                            <?php foreach ($anexos as $anexo_path): ?>
                                <?php if (!empty($anexo_path)): ?>
                                    <a class="btn btn-primary" href="<?= htmlspecialchars($anexo_path) ?>" target="_blank">Anexar</a><br>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="radio-group" style="display:block">
                            <label><input type="radio" name="acoes" value="Operação">Operação</label>
                            <label><input type="radio" name="acoes" value="HSE">HSE</label>
                            <label><input type="radio" name="acoes" value="Inativar">Inativar</label>
                        </div>
                        <button type="submit" class="button-encerrar">Encerrar</button>
                    </td>
                </form>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <script>

        // Dados para o Gráfico de Barras
        const manutencoesData = <?= json_encode($manutencoes_por_mes) ?>;
        const manutencoesLabels = Object.keys(manutencoesData);
        const entradaData = manutencoesLabels.map(label => manutencoesData[label].entrada);
        const saidaData = manutencoesLabels.map(label => manutencoesData[label].saida);

        const ctx1 = document.getElementById('manutencoesMesChart').getContext('2d');
        const manutencoesMesChart = new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: manutencoesLabels,
                datasets: [{
                    label: 'Entrada',
                    data: entradaData,
                    backgroundColor: '#007bff',
                    borderColor: '#007bff',
                    borderWidth: 1
                }, {
                    label: 'Saída',
                    data: saidaData,
                    backgroundColor: '#6c757d',
                    borderColor: '#6c757d',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });



        // Dados do PHP para o JavaScript
        const labelsStatus = <?= $labelsStatus; ?>;
        const dataStatus = <?= $dataStatus; ?>;
        const labelsAcao = <?= $labelsAcao; ?>;
        const dataAcao = <?= $dataAcao; ?>;
        const labelsCausasRaiz = <?= $labelsCausasRaiz; ?>;
        const dataCausasRaiz = <?= $dataCausasRaiz; ?>;
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
        // ** Verifique se a biblioteca chartjs-datalabels está carregada antes de usar
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
                    // Descomente o datalabels se a biblioteca estiver disponível
                    
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


        // Dados para o Gráfico de Pizza
        const conclusoesData = <?= json_encode($conclusoes_contagem) ?>;
        const conclusoesLabels = Object.keys(conclusoesData);
        const conclusoesValues = Object.values(conclusoesData);
        const bgColors = conclusoesLabels.map((_, i) => {
            const hue = (i * 137.508) % 360; // Geração de cores dinâmicas
            return `hsl(${hue}, 70%, 50%)`;
        });

        const ctx2 = document.getElementById('conclusoesChart').getContext('2d');
        const conclusoesChart = new Chart(ctx2, {
            type: 'pie',
            data: {
                labels: conclusoesLabels,
                datasets: [{
                    label: 'Conclusões',
                    data: conclusoesValues,
                    backgroundColor: [
                        '#007bff', '#28a745', '#dc3545', '#ffc107', '#17a2b8'
                    ],
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Motivos de Conclusão'
                    }
                }
            }
        });
    </script>
</body>
</html>