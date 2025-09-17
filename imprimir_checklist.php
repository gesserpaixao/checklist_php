<?php
// imprimir_checklist.php - versão robusta, procura o arquivo e fornece debug útil
declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/inc/auth.php';
requireLogin();

require_once __DIR__ . '/inc/csv.php'; // usa helper do projeto se disponível

// aceita checklist_id ou id
$checklistId = $_GET['checklist_id'] ?? ($_GET['id'] ?? null);
$debug = isset($_GET['debug']) && ($_GET['debug'] === '1' || strtolower($_GET['debug']) === 'true');

// função utilitária: tenta localizar o CSV em vários caminhos plausíveis
function locate_checklists_csv(): array {
    $candidates = [
        __DIR__ . '/data/checklists.csv',
        __DIR__ . '/data/checklist.csv',
        __DIR__ . '/../data/checklists.csv',
        __DIR__ . '/../data/checklist.csv',
        __DIR__ . '/../../data/checklists.csv',
        __DIR__ . '/../../data/checklist.csv',
        __DIR__ . '/data/checklists.CSV',
        getcwd() . '/data/checklists.csv',
        getcwd() . '/checklists.csv',
    ];
    $found = [];
    foreach ($candidates as $p) {
        if (file_exists($p) && is_readable($p)) {
            $found[] = $p;
        }
    }
    return $found; // array de caminhos válidos (podem ser 0 ou mais)
}

// fallback de leitura CSV (usa fgetcsv diretamente)
function csv_read_simple(string $path): array {
    $out = ['header' => [], 'rows' => []];
    if (!file_exists($path) || !is_readable($path)) return $out;
    if (($fh = fopen($path, 'r')) === false) return $out;
    // detectar delimitador simples: , ou ;
    $first = fgets($fh);
    if ($first === false) { fclose($fh); return $out; }
    rewind($fh);
    $delim = (substr_count($first, ';') > substr_count($first, ',')) ? ';' : ',';
    $header = fgetcsv($fh, 0, $delim);
    if ($header === false) { fclose($fh); return $out; }
    $out['header'] = $header;
    while (($row = fgetcsv($fh, 0, $delim)) !== false) {
        $out['rows'][] = $row;
    }
    fclose($fh);
    return $out;
}

// funções de normalização
function fixRowToHeader(array $row, int $expected_count): array {
    $cur = count($row);
    if ($cur === $expected_count) return $row;
    if ($cur < $expected_count) return array_pad($row, $expected_count, '');
    // merge extras na última coluna
    $first = array_slice($row, 0, $expected_count - 1);
    $last  = array_slice($row, $expected_count - 1);
    $first[] = implode(',', $last);
    return $first;
}

function firstField(array $assoc, array $candidates) {
    foreach ($candidates as $k) {
        if (array_key_exists($k, $assoc) && $assoc[$k] !== '') return $assoc[$k];
    }
    return null;
}

// ---------------------------------------------------------------------------
// valida entrada
if (!$checklistId) {
    http_response_code(400);
    echo "ID do checklist não fornecido. Use ?checklist_id=... ou ?id=...\n";
    exit;
}

// ---------------------------------------------------------------------------
// localizar CSV
$foundPaths = locate_checklists_csv();
$pathUsed = $foundPaths[0] ?? null;
$debug_lines = [];

if (!$pathUsed) {
    // tenta usar helper csvRead com caminho relativo esperado do projeto
    $try = __DIR__ . '/data/checklists.csv';
    if (file_exists($try)) {
        $pathUsed = $try;
    }
}

if (!$pathUsed) {
    // sem arquivo encontrado - se debug, mostre detalhes; se não, mensagem simples
    $msg = "Arquivo checklists.csv não encontrado.\nTentados caminhos:\n" . implode("\n", array_merge(locate_checklists_csv(), [
        __DIR__ . '/data/checklists.csv',
        __DIR__ . '/../data/checklists.csv',
        getcwd() . '/data/checklists.csv',
        getcwd() . '/checklists.csv'
    ]));
    if ($debug) {
        header('Content-Type: text/plain; charset=utf-8');
        echo $msg;
    } else {
        echo "Arquivo checklists.csv não encontrado.";
    }
    exit;
}

// ---------------------------------------------------------------------------
// ler CSV (tenta usar csvRead do projeto, se existir; senão, usa fallback)
$csv = null;
try {
    $tmp = csvRead($pathUsed);
    if (is_array($tmp) && isset($tmp['header']) && is_array($tmp['header'])) {
        $csv = $tmp;
    } else {
        $csv = csv_read_simple($pathUsed);
    }
} catch (Throwable $e) {
    // fallback
    $csv = csv_read_simple($pathUsed);
}

$header = $csv['header'] ?? [];
$rows = $csv['rows'] ?? [];

if (empty($header)) {
    if ($debug) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Cabeçalho vazio no CSV em: {$pathUsed}\n";
    } else {
        echo "Arquivo checklists.csv inválido (cabeçalho vazio).";
    }
    exit;
}

// trim nos headers
$header = array_map(function($h){ return trim((string)$h); }, $header);
$hc = count($header);

// procurar checklist
$found = null;
$lineNo = 1;
$problems = [];
$validIds = [];

foreach ($rows as $idx => $rowOrig) {
    $lineNo = $idx + 2;
    $row = $rowOrig;
    if (!is_array($row)) $row = (array)$row;
    if (count($row) < $hc) {
        $row = array_pad($row, $hc, '');
        $problems[] = "Linha {$lineNo}: pad (" . count($rowOrig) . " -> {$hc})";
    } elseif (count($row) > $hc) {
        $first = array_slice($row, 0, $hc - 1);
        $last  = array_slice($row, $hc - 1);
        $first[] = implode(',', $last);
        $row = $first;
        $problems[] = "Linha {$lineNo}: merge (" . count($rowOrig) . " -> {$hc})";
    }
    $assoc = @array_combine($header, $row);
    if ($assoc === false) {
        $problems[] = "Linha {$lineNo}: array_combine falhou após pad/merge.";
        continue;
    }

    // identificar campo id
    $id_field = null;
    foreach (['id','checklist_id','ID','Id','_id'] as $k) {
        if (array_key_exists($k, $assoc)) { $id_field = $k; break; }
    }
    if ($id_field === null) $id_field = $header[0];

    $curId = trim((string)($assoc[$id_field] ?? ''));
    if ($curId !== '') $validIds[] = $curId;

    if ($curId !== '' && (string)$curId === (string)$checklistId) {
        $found = $assoc;
        $found['_line_no'] = $lineNo;
        break;
    }
}

// se não encontrou - debug output ou mensagem simples
if (!$found) {
    if ($debug) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Checklist não encontrado para id: {$checklistId}\n";
        echo "CSV usado: {$pathUsed}\n";
        echo "Header (count={$hc}):\n"; print_r($header);
        echo "\nPrimeiras 50 linhas processadas (após pad/merge):\n";
        $count = 0;
        foreach ($rows as $idx => $r) {
            if ($count++ >= 50) break;
            $rproc = $r;
            if (count($rproc) < $hc) $rproc = array_pad($rproc,$hc,'');
            elseif (count($rproc) > $hc) {
                $first = array_slice($rproc,0,$hc-1);
                $last = array_slice($rproc,$hc-1);
                $first[] = implode(',', $last);
                $rproc = $first;
            }
            $assoc2 = @array_combine($header, $rproc);
            echo "Linha " . ($idx+2) . " -> ";
            if ($assoc2 === false) {
                echo "assoc_failed (orig_cols=" . count($r) . ")\n";
            } else {
                $idval = '';
                foreach (['id','checklist_id','ID','Id',$header[0]] as $k) {
                    if (isset($assoc2[$k])) { $idval = $assoc2[$k]; break; }
                }
                echo "orig_cols=" . count($r) . " id=" . substr((string)$idval,0,160) . "\n";
            }
        }
        echo "\nProblemas detectados (pad/merge):\n";
        foreach ($problems as $p) echo $p . "\n";
        echo "\nIDs válidos detectados (total " . count($validIds) . "):\n";
        foreach (array_slice($validIds,0,500) as $i) echo $i . "\n";
        if (count($validIds) > 500) echo "... (mais)\n";
        exit;
    } else {
        echo "Checklist não encontrado.";
        exit;
    }
}

// ---------------------------------------------------------------------------
// temos o checklist. prepara exibição
$checklist = $found;

// tentar decodificar respostas JSON (campo comum: respostas_json ou respostas)
$respostas_raw = $checklist['respostas_json'] ?? ($checklist['respostas'] ?? '');
$respostas = [];
if ($respostas_raw !== '') {
    if (is_array($respostas_raw)) {
        $respostas = $respostas_raw;
    } else {
        $decoded = json_decode($respostas_raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $respostas = $decoded;
        } else {
            // às vezes os valores vêm como "[]" ou "{}" no CSV - aceitar como string fallback
            $respostas = $respostas_raw;
        }
    }
}

// tenta achar informação da máquina (opcional)
$maquinaInfo = null;
$pathM = __DIR__ . '/data/maquinas.csv';
if (!file_exists($pathM)) $pathM = __DIR__ . '/../data/maquinas.csv';
if (file_exists($pathM)) {
    $mcsv = csv_read_simple($pathM);
    $mh = $mcsv['header'] ?? [];
    $mrows = $mcsv['rows'] ?? [];
    foreach ($mrows as $r) {
        $rproc = $r;
        if (count($rproc) < count($mh)) $rproc = array_pad($rproc, count($mh), '');
        elseif (count($rproc) > count($mh)) {
            $first = array_slice($rproc, 0, count($mh)-1);
            $last  = array_slice($rproc, count($mh)-1);
            $first[] = implode(',', $last);
            $rproc = $first;
        }
        $assocm = @array_combine($mh, $rproc);
        if (!$assocm) continue;
        $idk = null;
        foreach (['id','codigo','maquina_id'] as $k) { if (isset($assocm[$k])) { $idk = $k; break; } }
        if ($idk && (string)($assocm[$idk]) === (string)($checklist['id_maquina'] ?? '')) {
            $maquinaInfo = $assocm; break;
        }
    }
}

// ---------------------------------------------------------------------------
// Render HTML da página de impressão
?><!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Checklist <?= htmlspecialchars($checklist['id'] ?? '') ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <style>
    body { background:#fff; color:#111; font-family: Inter, Arial, sans-serif; padding:22px; }
    .card { border:1px solid #ddd; border-radius:10px; padding:18px; max-width:1100px; margin:8px auto; }
    .hdr { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; }
    .brand { font-weight:700; font-size:18px; }
    .muted { color:#666; font-size:13px; }
    table.resp-table { width:100%; border-collapse:collapse; margin-top:12px; }
    table.resp-table th, table.resp-table td { border:1px solid #e6e6e6; padding:8px; text-align:left; vertical-align:top; }
    th { background:#f8f9fa; font-weight:600; width:30%; }
    .btn-print { margin-top:14px; }
    pre.raw { background:#f7f7f7; padding:12px; border-radius:6px; overflow:auto; }
    @media print { .no-print { display:none; } }
  </style>
</head>
<body>
  <div class="card">
    <div class="hdr">
      <div>
        <div class="brand">Checklist - <?= htmlspecialchars($checklist['id'] ?? '') ?></div>
        <div class="muted">Operador: <?= htmlspecialchars($checklist['operador'] ?? '') ?>
           — Máquina: <?= htmlspecialchars($maquinaInfo['nome'] ?? ($checklist['id_maquina'] ?? '')) ?></div>
      </div>
      <div class="text-end muted">
        <?php
          $d = $checklist['data_abertura'] ?? ($checklist['data'] ?? ($checklist['data_fechamento'] ?? ''));
          if ($d) {
              try { $dt = new DateTime($d); echo $dt->format('d/m/Y H:i'); }
              catch (Exception $e) { echo htmlspecialchars((string)$d); }
          }
        ?>
      </div>
    </div>

    <div class="row mb-2">
      <div class="col-md-4"><strong>Operador</strong><div class="muted"><?= htmlspecialchars($checklist['operador'] ?? '') ?></div></div>
      <div class="col-md-4"><strong>Máquina (ID)</strong><div class="muted"><?= htmlspecialchars($checklist['id_maquina'] ?? '') ?></div></div>
      <div class="col-md-4"><strong>Status</strong><div class="muted"><?= htmlspecialchars($checklist['status_checklist'] ?? $checklist['status'] ?? '') ?></div></div>
    </div>

    <div>
      <strong>Observações</strong>
      <div class="muted"><?= nl2br(htmlspecialchars($checklist['obs'] ?? '')) ?></div>
    </div>

    <h5 class="mt-3">Respostas</h5>

    <?php
    if (!is_array($respostas)) {
        echo '<div class="muted">Resposta JSON não pôde ser decodificada. Exibir raw:</div>';
        echo '<pre class="raw">' . htmlspecialchars((string)$respostas) . '</pre>';
    } else {
        $isAssoc = function($a) {
            if (!is_array($a)) return false;
            return array_keys($a) !== range(0, count($a)-1);
        };
        if ($isAssoc($respostas)) {
            echo '<table class="resp-table"><thead><tr><th>Item</th><th>Valor</th></tr></thead><tbody>';
            foreach ($respostas as $k=>$v) {
                $val = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v;
                echo '<tr><td>' . htmlspecialchars((string)$k) . '</td><td>' . htmlspecialchars((string)$val) . '</td></tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<table class="resp-table"><thead><tr><th>Item</th><th>Resposta</th><th>Observação</th></tr></thead><tbody>';
            foreach ($respostas as $it) {
                if (is_array($it)) {
                    $itName = $it['item'] ?? ($it['label'] ?? '');
                    $itResp = $it['resposta'] ?? ($it['value'] ?? '');
                    $itObs  = $it['obs'] ?? ($it['note'] ?? '');
                } else {
                    $itName = '';
                    $itResp = (string)$it;
                    $itObs = '';
                }
                echo '<tr><td>' . htmlspecialchars((string)$itName) . '</td><td>' . htmlspecialchars((string)$itResp) . '</td><td>' . htmlspecialchars((string)$itObs) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
    }
    ?>

    <div class="text-center no-print">
      <button class="btn btn-primary btn-print no-print" onclick="window.print()">🖨 Imprimir</button>
    </div>

    <?php if ($debug): ?>
      <hr>
      <h6>Debug info</h6>
      <pre><?php
        echo "Arquivo usado: {$pathUsed}\n";
        echo "Encontrado na linha: " . ($checklist['_line_no'] ?? 'n/a') . "\n";
        echo "Problemas detectados (pad/merge):\n";
        echo implode("\n", $problems);
      ?></pre>
    <?php endif; ?>

  </div>
</body>
</html>
