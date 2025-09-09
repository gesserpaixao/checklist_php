<?php
// Define o fuso hor\u00e1rio padr\u00e3o para evitar avisos
date_default_timezone_set('America/Sao_Paulo');

// --- Fun\u00e7\u00f5es de Utilit\u00e1rios ---

/**
 * Tenta converter uma string para um objeto DateTime.
 * Suporta v\u00e1rios formatos, incluindo ISO 8601.
 * @param string $dateString A string de data a ser parseada.
 * @return DateTime|false Um objeto DateTime se o parsing for bem-sucedido, ou false caso contr\u00e1rio.
 */
function tryParseDate(string $dateString) {
    try {
        // Tenta criar um objeto DateTime a partir da string
        $d = new DateTime($dateString);
        return $d;
    } catch (Exception $e) {
        // Retorna false se a string n\u00e3o puder ser parseada
        return false;
    }
}

/**
 * Encontra o valor do primeiro campo v\u00e1lido em um array, com base em uma lista de chaves.
 * @param array $data O array de dados a ser pesquisado.
 * @param array $keys Uma lista de chaves em ordem de prefer\u00eancia.
 * @return mixed O valor do primeiro campo encontrado ou null se nenhum for encontrado.
 */
function firstField(array $data, array $keys) {
    foreach ($keys as $key) {
        if (isset($data[$key]) && $data[$key] !== '') {
            return $data[$key];
        }
    }
    return null;
}

/**
 * Ajusta uma linha de dados para corresponder ao n\u00famero de colunas do cabe\u00e7alho.
 * @param array $row A linha de dados.
 * @param int $expected_count O n\u00famero de colunas esperado.
 * @return array A linha ajustada.
 */
function fixRowToHeader(array $row, int $expected_count): array {
    $current_count = count($row);
    if ($current_count < $expected_count) {
        return array_pad($row, $expected_count, '');
    } elseif ($current_count > $expected_count) {
        return array_slice($row, 0, $expected_count);
    }
    return $row;
}

// --- Fun\u00e7\u00f5es de Autentica\u00e7\u00e3o e CSV ---

function requireLogin() {
    // Implementa\u00e7\u00e3o de autentica\u00e7\u00e3o real
}

function csvRead(string $filename): array {
    if (!file_exists($filename)) {
        return ['header' => [], 'rows' => []];
    }
    $file = fopen($filename, 'r');
    if ($file === false) {
        return ['header' => [], 'rows' => []];
    }
    $header = fgetcsv($file);
    $rows = [];
    while (($row = fgetcsv($file)) !== false) {
        $rows[] = $row;
    }
    fclose($file);
    return ['header' => $header, 'rows' => $rows];
}

// --- L\u00f3gica Principal do Relat\u00f3rio ---
requireLogin();

// Processamento dos par\u00e2metros da URL
$id_maquina = $_GET['id_maquina'] ?? null;
$data_inicio = $_GET['data_inicio'] ?? null;
$data_fim = $_GET['data_fim'] ?? null;

$start_date_obj = DateTime::createFromFormat('Y-m-d', $data_inicio);
$end_date_obj = DateTime::createFromFormat('Y-m-d', $data_fim);

if ($end_date_obj) {
    $end_date_obj->setTime(23, 59, 59);
}

// Carrega os dados dos checklists e das m\u00e1quinas
$checklistsCsv = csvRead(__DIR__ . '/data/checklists.csv');
$rows = $checklistsCsv['rows'] ?? [];
$header = $checklistsCsv['header'] ?? [];

$maquinas_data = csvRead(__DIR__ . '/data/maquinas.csv');
$maquinas_rows = $maquinas_data['rows'] ?? [];
$maquinas_header = $maquinas_data['header'] ?? [];

$maquinas_map = [];
foreach ($maquinas_rows as $row) {
    $row = fixRowToHeader($row, count($maquinas_header));
    
    $m = array_combine($maquinas_header, $row);
    
    $id = firstField($m, ['id', 'codigo', 'maquina_id']);
    $nome = firstField($m, ['nome', 'descricao', 'modelo']);
    $tipo = firstField($m, ['tipo', 'categoria', 'classe']);
    
    if (!$id) continue;
    $maquinas_map[$id] = ['nome' => $nome, 'tipo' => $tipo];
}

$maquina_info = $maquinas_map[$id_maquina] ?? ['nome' => 'N/A', 'tipo' => 'N/A'];

// Consolida\u00e7\u00e3o dos Dados do Checklist
$dados = []; 
$itens = []; 
$debug_log = [];

foreach ($rows as $index => $row) {
    $row = fixRowToHeader($row, count($header));
    
    $ch = array_combine($header, $row);

    $maquina_chk = firstField($ch, ['id_maquina', 'maquina_id', 'maquina']);
    $data_raw = firstField($ch, ['data_abertura', 'data', 'inicio', 'data_fechamento', 'created_at']);
    $turno = firstField($ch, ['turno', 'shift']);

    if ($maquina_chk != $id_maquina) {
        $debug_log[] = "Linha " . ($index + 2) . ": Ignorada. ID da m\u00e1quina n\u00e3o corresponde ({$maquina_chk} != {$id_maquina}).";
        continue;
    }
    
    $data_obj = tryParseDate($data_raw);
    
    if (!$data_obj || $data_obj < $start_date_obj || $data_obj > $end_date_obj) {
        $debug_log[] = "Linha " . ($index + 2) . ": Ignorada. Data fora do per\u00edodo de filtro ({$data_raw}).";
        continue;
    }

    if (!$turno) {
        $debug_log[] = "Linha " . ($index + 2) . ": Ignorada. Turno n\u00e3o encontrado.";
        continue;
    }
    
    $data_chk = $data_obj->format('Y-m-d');
    
    $respostas_raw = $ch['respostas_json'] ?? '';
    
    // NOVO: Refatorado para lidar com strings vazias e JSONs inv\u00e1lidos
    if (empty(trim($respostas_raw))) {
        $respostas = [];
        $debug_log[] = "Linha " . ($index + 2) . ": Campo JSON vazio detectado. Assumindo array vazio.";
    } else {
        $respostas = json_decode($respostas_raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $debug_log[] = "Linha " . ($index + 2) . ": Erro ao decodificar JSON. Conte\u00fado lido: '" . htmlspecialchars($respostas_raw) . "'. Erro: " . json_last_error_msg();
            continue;
        }
    }

    foreach ($respostas as $item) {
        $nome_item = $item['item'] ?? '';
        $resp = $item['resposta'] ?? '';
        
        if ($nome_item) {
            $dados[$data_chk][$turno][$nome_item] = $resp;
            $itens[$nome_item] = true;
        }
    }
    if (count($respostas) > 0) {
      $debug_log[] = "Linha " . ($index + 2) . ": Processada com sucesso. Encontrados dados para {$data_chk}, {$turno}.";
    }
}

$datas = array_keys($dados);
sort($datas);
$itens = array_keys($itens);
sort($itens);

?>

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Relat\u00f3rio Semanal - <?= htmlspecialchars($maquina_info['nome']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; }
        .print-container { max-width: 1200px; margin: auto; padding: 20px; }
        .report-header { text-align: center; margin-bottom: 20px; }
        .report-header h2 { margin: 0; }
        .report-header p { margin: 5px 0; font-size: 14px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 4px; text-align: center; }
        th { background: #eee; }
        .item { text-align: left; }
        .btn-container { text-align: center; margin-top: 20px; }
        .btn-print { 
            background:#6c63ff; 
            color:#fff;
            padding:10px 20px;
            border-radius:6px;
            border:none;
            cursor:pointer;
            font-size:16px;
        }
        .debug-section { background: #f0f0f0; border: 1px solid #ccc; padding: 10px; margin-bottom: 20px; font-size: 12px; }
        .debug-section h3 { margin-top: 0; }
        .debug-section pre { margin: 0; white-space: pre-wrap; word-wrap: break-word; }
        @media print {
            .btn-print, .debug-section { display: none; }
        }
    </style>
</head>
<body>
    <div class="print-container">
        <!-- SE\u00c7\u00c3O DE DIAGN\u00d3STICO -->
        <div class="debug-section">
            <h3>Diagn\u00f3stico da Consulta</h3>
            <pre>
Par\u00e2metros recebidos: <?= json_encode($_GET, JSON_PRETTY_PRINT) ?>
ID da M\u00e1quina: <?= htmlspecialchars($id_maquina) ?>
Data In\u00edcio: <?= $start_date_obj ? $start_date_obj->format('Y-m-d H:i:s') : 'Inv\u00e1lida' ?>
Data Fim: <?= $end_date_obj ? $end_date_obj->format('Y-m-d H:i:s') : 'Inv\u00e1lida' ?>

Mapeamento da M\u00e1quina '<?= htmlspecialchars($id_maquina) ?>':
<?= json_encode($maquina_info, JSON_PRETTY_PRINT) ?>

N\u00famero de colunas do cabe\u00e7alho do checklist: <?= count($header) ?>
N\u00famero de linhas lidas no checklists.csv: <?= count($rows) ?>

Logs de processamento do CSV:
<?= implode("\n", $debug_log) ?>

N\u00famero de linhas processadas que correspondem aos filtros: <?= count($dados) ?>
            </pre>
        </div>

        <div class="report-header">
            <h2>Relat\u00f3rio Semanal de Checklist</h2>
            <h3>M\u00e1quina: <?= htmlspecialchars($maquina_info['nome']) ?> (<?= htmlspecialchars($id_maquina) ?>)</h3>
            <p>Per\u00edodo: <?= date('d/m/Y', strtotime($data_inicio)) ?> a <?= date('d/m/Y', strtotime($data_fim)) ?></p>
        </div>

        <table>
            <thead>
                <tr>
                    <th rowspan="2">Itens a verificar</th>
                    <?php foreach ($datas as $d): ?>
                        <th colspan="3"><?= date('d/m/Y', strtotime($d)) ?></th>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <?php foreach ($datas as $d): ?>
                        <th>A</th><th>B</th><th>C</th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($itens)): ?>
                    <tr><td colspan="100%">Nenhum dado encontrado para o per\u00edodo e m\u00e1quina selecionados.</td></tr>
                <?php else: ?>
                    <?php foreach ($itens as $item): ?>
                        <tr>
                            <td class="item"><?= htmlspecialchars($item) ?></td>
                            <?php foreach ($datas as $d): ?>
                                <?php foreach (['A','B','C'] as $t): ?>
                                    <td>
                                        <?= htmlspecialchars($dados[$d][$t][$item] ?? '-') ?>
                                    </td>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="btn-container">
            <button class="btn-print" onclick="window.print()">Imprimir</button>
        </div>
    </div>
</body>
</html>
