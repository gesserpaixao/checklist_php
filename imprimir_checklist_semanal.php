<?php
declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/csv.php';
require_once __DIR__ . '/inc/utils.php';
require_once __DIR__ . '/inc/header.php';

use Dompdf\Dompdf;
use Dompdf\Options;

requireLogin();
// CORREÇÃO: Garante que a variável do usuário ($u) seja sempre um array válido.
$u = currentUser() ?? ['nome' => 'Visitante', 'perfil' => 'N/A'];

$logo_path = __DIR__ . '/assets/emp.png';

$logo_base64 = '';
if (file_exists($logo_path) && is_readable($logo_path)) {
    $type = pathinfo($logo_path, PATHINFO_EXTENSION);
    $data = file_get_contents($logo_path);
    if ($data !== false) {
        $logo_base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
    }
}

// --- 1. Parâmetros ---
$id_maquina_url = $_GET['id_maquina'] ?? null;
$data_inicio_url = $_GET['data_inicio'] ?? null;
$data_fim_url = $_GET['data_fim'] ?? null;

if (!$id_maquina_url || !$data_inicio_url || !$data_fim_url) {
    die("Parâmetros de filtro ausentes (id_maquina, data_inicio, data_fim).");
}

try {
    $data_inicio_obj = new DateTime($data_inicio_url . ' 00:00:00');
    $data_fim_obj = new DateTime($data_fim_url . ' 23:59:59');
} catch (Exception $e) {
    die("Formato de data inválido.");
}

// --- 2. CSV ---
$checklistsData = csvRead(__DIR__ . '/data/checklists.csv');
$maquinasData   = csvRead(__DIR__ . '/data/maquinas.csv');
$perguntasData  = csvRead(__DIR__ . '/data/perguntas.csv');

$checklists_header = $checklistsData['header'] ?? [];
$checklists_rows   = $checklistsData['rows'] ?? [];
$maquinas_header   = $maquinasData['header'] ?? [];
$maquinas_rows     = $maquinasData['rows'] ?? [];
$perguntas_header  = $perguntasData['header'] ?? [];
$perguntas_rows    = $perguntasData['rows'] ?? [];

// Mapeia máquinas
$maquinas_map = [];
foreach ($maquinas_rows as $row) {
    if (count($row) !== count($maquinas_header)) continue;
    $m = array_combine($maquinas_header, $row);
    $id = firstField($m, ['id', 'codigo', 'maquina_id']);
    if ($id) $maquinas_map[$id] = $m;
}

$maquina_info    = $maquinas_map[$id_maquina_url] ?? ['nome' => 'N/D', 'modelo' => 'N/D', 'tipo' => 'N/D'];
$nome_maquina    = firstField($maquina_info, ['nome', 'descricao', 'modelo']);
$equipamento_num = firstField($maquina_info, ['id', 'codigo', 'maquina_id']);
$tipo_maquina    = firstField($maquina_info, ['tipo', 'categoria']) ?? 'Equipamento';
$setor_maquina    = firstField($maquina_info, ['setor']) ?? 'Equipamento';

// --- 3. Itens de verificação ---
$itens_verificados = [];
foreach ($perguntas_rows as $row) {
    if (count($row) !== count($perguntas_header)) continue;
    $p = array_combine($perguntas_header, $row);
    if (strtolower($p['tipo_maquina']) === 'geral' || strtolower($p['tipo_maquina']) === strtolower($tipo_maquina)) {
        $itens_verificados[$p['chave']] = $p['label'];
    }
}
if (empty($itens_verificados)) {
    $itens_verificados = [
        'cracha_operador_valido' => 'Crachá Operador Válido',
        'freios' => 'Freios',
        'buzina_sirene_re' => 'Buzina / Sirene de Ré / Luz Azul Seg.',
        'extintor' => 'Extintor',
        'cinto_seguranca' => 'Cinto de Segurança',
        'limpeza_geral' => 'Limpeza Geral',
    ];
}

// Turnos baseados no CSV (A, B, C)
$turnos_info = [
    'A' => ['label' => '1º Turno'],
    'B' => ['label' => '2º Turno'],
    'C' => ['label' => '3º Turno'],
];

// Dias da semana
$dias_semana = [];
$periodo = new DatePeriod($data_inicio_obj, new DateInterval('P1D'), $data_fim_obj->modify('+1 day'));
foreach ($periodo as $dia) {
    $dias_semana[$dia->format('Y-m-d')] = [
        'data_br' => $dia->format('d/m/Y'),
        'dados_dia' => [
            'A' => ['operador' => '', 'horimetro_inicial' => '', 'horimetro_final' => '', 'gestor_responsavel' => '', 'itens' => []],
            'B' => ['operador' => '', 'horimetro_inicial' => '', 'horimetro_final' => '', 'gestor_responsavel' => '', 'itens' => []],
            'C' => ['operador' => '', 'horimetro_inicial' => '', 'horimetro_final' => '', 'gestor_responsavel' => '', 'itens' => []],
        ]
    ];
}

// --- 4. Preenche dados ---
foreach ($checklists_rows as $row) {
    if (count($row) < count($checklists_header)) {
        $row = array_pad($row, count($checklists_header), '');
    }
    if (count($row) !== count($checklists_header)) continue;

    $c = array_combine($checklists_header, $row);

    $maquina_id   = $c['id_maquina'] ?? null;
    $maquina_setor   = $c['setor'] ?? null;
    $data_raw     = $c['data_abertura'] ?? null;
    $turno        = strtoupper(trim($c['turno'] ?? ''));
    $operador     = trim($c['operador'] ?? '');
    $h_ini        = trim($c['orimetro_inicial'] ?? '');
    $h_fim        = trim($c['orimetro_final'] ?? '');
    $gestor       = trim($c['aprovado_por'] ?? '');
    $respostas_json = $c['respostas_json'] ?? '';

    $dt_checklist = tryParseDate($data_raw);
    if ($maquina_id === $id_maquina_url && $dt_checklist && $dt_checklist >= $data_inicio_obj && $dt_checklist <= $data_fim_obj) {
        $data_fmt = $dt_checklist->format('Y-m-d');

        if (isset($dias_semana[$data_fmt]['dados_dia'][$turno])) {
            $dias_semana[$data_fmt]['dados_dia'][$turno]['operador'] = $operador;
            $dias_semana[$data_fmt]['dados_dia'][$turno]['horimetro_inicial'] = $h_ini;
            $dias_semana[$data_fmt]['dados_dia'][$turno]['horimetro_final']   = $h_fim;
            $dias_semana[$data_fmt]['dados_dia'][$turno]['gestor_responsavel'] = $gestor;

            $respostas = json_decode($respostas_json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($respostas)) {
                foreach ($respostas as $item => $status) {
                    if (isset($itens_verificados[$item])) {
                        $dias_semana[$data_fmt]['dados_dia'][$turno]['itens'][$item] = $status;
                    }
                }
            }
        }
    }
}


ob_start();
?>


<style>
    @page {
        margin: 5mm;
    }
    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 8px;
        margin: 0;
        padding: 0;
    }

    .main-header-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 5px;
    }
    .main-header-table td {
        text-align: center;
        vertical-align: middle;
    }
    .main-header-table .logo-cell {
        text-align: left;
        width: 100px;
    }
    .main-header-table .title-cell {
        text-align: center;
    }
    .logo {
        max-width: 100px;
        max-height: 50px;
        display: block;
    }

    h1 {
        font-size: 14px;
        text-align: center;
        margin: 5px 0;
    }
    .header-box {
        border: 1px solid #000;
        padding: 2px;
        margin-bottom: 2px;
        background-color: #f2f2f2;
    }
    .header-box table {
        width: 100%;
        border-collapse: collapse;
    }
    .header-box td {
        vertical-align: middle;
        padding: 0 5px;
    }
    .header-box .left-align {
        text-align: left;
    }
    .header-box .right-align {
        text-align: right;
    }
    p {
        margin: 2px 0;
    }

    .checklist-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 5px;
    }
    .checklist-table th, .checklist-table td {
        border: 1px solid #000;
        padding: 1px;
        text-align: center;
    }
    .checklist-table th {
        background-color: #f2f2f2;
        font-weight: bold;
    }
    .item-col {
        text-align: left;
        font-weight: bold;
        width: 15%;
    }
    .group-header {
        background-color: #d3d3d3;
        font-weight: bold;
        text-align: left;
    }
    .problema {
        border: 2px solid red !important;
    }
</style>

<head>
    <meta charset="UTF-8">
    <title>PDF Visualizar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/stylenew.css">
</head>



<body>

    <table class="main-header-table">
        <tr>
            <td class="logo-cell">
                <img src="<?= htmlspecialchars($logo_base64) ?>" alt="Logo da Empresa" class="logo">
            </td>
            <td class="title-cell">
                <h1>Check List Operacional de <?= htmlspecialchars($tipo_maquina ?? 'Equipamento') ?></h1>
            </td>
            <td></td>
        </tr>
    </table>

    <div class="header-box">
        <table>
            <tr>
                <td class="left-align">
                    <p><strong>SETOR:</strong> Armazém <?= htmlspecialchars($setor_maquina ?? 'Equipamento') ?> | <strong>EQUIP. Nº:</strong> <?= htmlspecialchars($equipamento_num ?? 'N/D') ?></p>
                </td>
                <td class="right-align">
                    <p><strong>Período:</strong> <?= htmlspecialchars($data_inicio_obj->format('d/m/Y')) ?> até <?= htmlspecialchars($data_fim_obj->format('d/m/Y')) ?></p>
                </td>
            </tr>
        </table>
    </div>

    <p style="font-size: 7px; text-align: center; line-height: 1;">
        ● MARCAR "OK" NOS ÍTENS QUE ESTIVEREM EM ORDEM ● MARCAR COM UM "N/A" ITENS NÃO APLICÁVEIS ● MARCAR "X" NOS ITENS QUE ESTIVEREM COM PROBLEMAS
    </p>

    <table class="checklist-table">
        <thead>
            <tr>
                <th rowspan="2">ITENS A SEREM VERIFICADOS</th>
                <?php foreach ($dias_semana as $dia): ?>
                    <th colspan="3"><?= $dia['data_br'] ?></th>
                <?php endforeach; ?>
            </tr>
            <tr>
                <?php foreach ($dias_semana as $dia): ?>
                    <th>A</th><th>B</th><th>C</th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($itens_verificados as $k => $label): ?>
                <tr>
                    <td class="item-col"><?= htmlspecialchars($label) ?></td>
                    <?php foreach ($dias_semana as $dia): ?>
                        <?php foreach (['A', 'B', 'C'] as $t): ?>
                            <?php
                            $st = strtolower($dia['dados_dia'][$t]['itens'][$k] ?? '');
                            $class_problema = '';
                            $celula_conteudo = '';

                            if ($st === 'ok' || $st === 'aprovado') {
                                $celula_conteudo = 'OK';
                            } elseif ($st === 'n/a') {
                                $celula_conteudo = 'N/A';
                            } elseif ($st === 'nc' || $st === 'x' || $st === 'problema') {
                                $celula_conteudo = 'X';
                                $class_problema = 'problema';
                            }
                            ?>
                            <td class="<?= htmlspecialchars($class_problema) ?>">
                                <?= htmlspecialchars($celula_conteudo) ?>
                            </td>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            
            <?php foreach ($turnos_info as $turno_key => $turno_details): ?>
                <tr class="group-header">
                    <td colspan="<?= 1 + count($dias_semana) * 3 ?>">
                        <?= htmlspecialchars($turno_details['label']) ?> (___/___/____) (<?= htmlspecialchars($turno_key) ?>)
                    </td>
                </tr>
                <tr>
                    <td class="item-col">NOME DO OPERADOR (LEGÍVEL)</td>
                    <?php foreach ($dias_semana as $data_str => $dia_info): ?>
                        <td colspan="3"><?= htmlspecialchars($dia_info['dados_dia'][$turno_key]['operador'] ?? '') ?></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td class="item-col">HORÍMETRO - INICIAL</td>
                    <?php foreach ($dias_semana as $data_str => $dia_info): ?>
                        <td colspan="3"><?= htmlspecialchars($dia_info['dados_dia'][$turno_key]['horimetro_inicial'] ?? '') ?></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td class="item-col">HORÍMETRO - FINAL</td>
                    <?php foreach ($dias_semana as $data_str => $dia_info): ?>
                        <td colspan="3"><?= htmlspecialchars($dia_info['dados_dia'][$turno_key]['horimetro_final'] ?? '') ?></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td class="item-col">GESTOR RESPONSÁVEL</td>
                    <?php foreach ($dias_semana as $data_str => $dia_info): ?>
                        <td colspan="3"><?= htmlspecialchars($dia_info['dados_dia'][$turno_key]['gestor_responsavel'] ?? '') ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <p>Em caso de qualquer não conformidade o operador deverá informar seu superior imediato, e esse é o responsável por acompanhar o cumprimento do checklist e dar providências. <br><strong>OBSERVAÇÕES / NÃO CONFORMIDADES (VIDE VERSO)</strong></p>

</body>
<?php
$html = ob_get_clean();

// --- 6. PDF ---
$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$filename = "checklist_semanal_" . urlencode($id_maquina_url) . "_" . urlencode($data_inicio_url) . ".pdf";
$dompdf->stream($filename, ["Attachment" => false]);
exit;