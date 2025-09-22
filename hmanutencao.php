<?php
// hmanutencao.php
declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/header.php';
requireLogin();

// Verifica se o usuário tem permissão para acessar esta página
if (!isSupervisor() && !isMaster() && !isHse() && !isMecanica()) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/inc/csv.php';
require_once __DIR__ . '/inc/utils.php';

$u = currentUser();

// Leitura do arquivo CSV de máquinas
$maquinaCsv = csvRead(__DIR__ . '/data/maquinas.csv');
$maquinasH = $maquinaCsv['header'] ?? [];
$maquinasRows = $maquinaCsv['rows'] ?? [];

// Mapear dados das máquinas para facilitar o acesso
$maquinas_map = [];
foreach ($maquinasRows as $r) {
    if (count($r) === count($maquinasH)) {
        $maq = array_combine($maquinasH, $r);
        $maquinas_map[$maq['id']] = $maq;
    }
}

// Leitura do arquivo CSV de manutenções
$manutencaoCsv = csvRead(__DIR__ . '/data/manutencoes.csv');
$manutencoesH = $manutencaoCsv['header'] ?? [];
$manutencoesRows = $manutencaoCsv['rows'] ?? [];

// Obter os valores dos filtros
$filter_id_maquina = $_GET['maquina'] ?? '';
$filter_data_range = $_GET['data_range'] ?? '';
$filter_data_inicio = '';
$filter_data_fim = '';

if (!empty($filter_data_range)) {
    list($filter_data_inicio, $filter_data_fim) = explode(' - ', $filter_data_range);
}

$manutencoesFiltradas = [];

foreach ($manutencoesRows as $r) {
    if (count($r) !== count($manutencoesH)) {
        continue;
    }

    $manutencao = array_combine($manutencoesH, $r);

    // Aplicar filtros
    $passou_filtro_maquina = true;
    if (!empty($filter_id_maquina)) {
        $maquina_encontrada = false;
        if (isset($maquinas_map[$manutencao['id_maquina']])) {
            $nome_maquina = $maquinas_map[$manutencao['id_maquina']]['nome'];
            if (strpos(strtolower($nome_maquina), strtolower($filter_id_maquina)) !== false) {
                $maquina_encontrada = true;
            }
        }
        $passou_filtro_maquina = $maquina_encontrada;
    }
    
    $passou_filtro_datas = true;
    if (!empty($filter_data_inicio) && !empty($filter_data_fim) && !empty($manutencao['data_inicio'])) {
        $manutencao_data_obj = new DateTime(explode('T', $manutencao['data_inicio'])[0]);
        $filtro_data_inicio_obj = new DateTime($filter_data_inicio);
        $filtro_data_fim_obj = new DateTime($filter_data_fim);
        
        if ($manutencao_data_obj < $filtro_data_inicio_obj || $manutencao_data_obj > $filtro_data_fim_obj) {
            $passou_filtro_datas = false;
        }
    }

    if ($passou_filtro_maquina && $passou_filtro_datas) {
        $manutencoesFiltradas[] = $manutencao;
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Histórico de Manutenções</title>
    <link rel="stylesheet" href="assets/stylenew.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .header-logo {
            height: 40px;
        }
        .filter-form {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        .filter-form input[type="text"], .filter-form input[type="date"] {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .filter-form {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 20px;
    }
    .filter-form input[type="text"], .filter-form input[type="date"] {
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }
    /* Adicione esta regra para corrigir a sobreposição */
    .daterangepicker {
        z-index: 9999 !important;
    }
    </style>
</head>
<body>

    <main class="containerMaior">
        <h2 class="center">Histórico de Manutenções</h2>
        
        <form method="GET" action="hmanutencao.php" class="filter-form">
            <div class="filter-group">
                <label for="maquina">Filtrar por Máquina:</label>
                <input type="text" name="maquina" id="maquina" value="<?= htmlspecialchars($filter_id_maquina) ?>" 
                placeholder="Digite o nome da máquina">
            </div>
            
            <div class="filter-group" style="z-index: 9999; backgroud-color: #696952ff;">
                <label for="data_range">Intervalo de Data:</label>
                <input type="text" name="data_range" id="data_range"  style="z-index: 9999;"
                value="<?= htmlspecialchars($filter_data_range) ?>">
            </div>

            <button type="submit">Filtrar</button>
        </form>

        <table>
            <thead>
                <tr>
                    <th>ID Manutenção</th>
                    <th>Máquina</th>
                    <th>Data Entrada</th>
                    <th>Data Fim</th>
                    <th>Aberto Por</th>
                    <th>Responsável</th>
                    <th>Mecânico</th>
                    <th>Status</th>
                    <th>Descrição do Problema</th>
                    <th>Descrição da Manutenção</th>
                     <th>Ações</th>
                    <th>Anexos</th>
                </tr>
            </thead>
            <tbody>
            <?php
            // Exibe as linhas filtradas
            foreach($manutencoesFiltradas as $manutencao){ 
                $nome_maquina = $maquinas_map[$manutencao['id_maquina']]['nome'] ?? 'N/A';
                echo '<tr>';
                echo '<td>' . htmlspecialchars($manutencao['id'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($nome_maquina) . '</td>';
                echo '<td>' . htmlspecialchars($manutencao['entrada'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($manutencao['data_fim'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($manutencao['aberto_por'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($manutencao['responsavel'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($manutencao['mecanico'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($manutencao['status'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($manutencao['descricao_problema'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($manutencao['descricao_manutencao'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($manutencao['acoes'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($manutencao['anexos'] ?? '') . '</td>';
                echo '</tr>';
            }
            ?>
            </tbody>
        </table>
    </main>

    <script type="text/javascript" src="https://cdn.jsdelivr.net/jquery/latest/jquery.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />

    <script type="text/javascript">
        $(function() {
            $('input[name="data_range"]').daterangepicker({
                "locale": {
                    "format": "YYYY-MM-DD",
                    "separator": " - ",
                    "applyLabel": "Aplicar",
                    "cancelLabel": "Cancelar",
                    "fromLabel": "De",
                    "toLabel": "Até",
                    "customRangeLabel": "Customizado",
                    "weekLabel": "S",
                    "daysOfWeek": [
                        "Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sáb"
                    ],
                    "monthNames": [
                        "Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"
                    ],
                    "firstDay": 1
                }
            });
        });
    </script>

</body>
</html>