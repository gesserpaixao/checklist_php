<?php
// imprimir_checklist.php - versão robusta com debug e pad/merge de colunas
require_once __DIR__ . '/inc/auth.php';
requireLogin();

require_once __DIR__ . '/inc/csv.php';

// aceitar checklist_id ou id
$checklistId = $_GET['checklist_id'] ?? ($_GET['id'] ?? null);
$debug = isset($_GET['debug']) && ($_GET['debug'] == '1' || $_GET['debug'] == 'true');

if (!$checklistId) {
    http_response_code(400);
    echo "ID do checklist não fornecido.";
    exit;
}

$path = __DIR__ . '/data/checklists.csv';
if (!file_exists($path)) {
    http_response_code(500);
    echo "Arquivo de checklists não encontrado em: " . htmlspecialchars($path);
    exit;
}

// lê CSV pelo helper do projeto
$csv = csvRead($path);
$header = $csv['header'] ?? [];
$rows = $csv['rows'] ?? [];

if (empty($header)) {
    echo "Cabeçalho do CSV vazio ou inválido.";
    exit;
}

// trim nos cabeçalhos
$header = array_map(function($h){ return trim($h); }, $header);
$hc = count($header);

$found = null;
$lineNo = 1; // contando a partir do cabeçalho como 1
$problems = [];
$validIds = [];

foreach ($rows as $idx => $row) {
    $lineNo = $idx + 2; // +1 para cabeçalho, +1 para índice 0-based

    // se row tem menos colunas que header -> pad à direita
    if (count($row) < $hc) {
        $row = array_pad($row, $hc, '');
        $problems[] = "Linha {$lineNo}: pad (cols " . count($rows[$idx]) . " -> {$hc})";
    } elseif (count($row) > $hc) {
        // se row tem mais colunas: junta extras na última coluna (merge)
        $first = array_slice($row, 0, $hc - 1);
        $last  = array_slice($row, $hc - 1);
        $first[] = implode(',', $last);
        $row = $first;
        $problems[] = "Linha {$lineNo}: merge (cols " . count($rows[$idx]) . " -> {$hc})";
    }

    // agora seguro para array_combine
    $assoc = @array_combine($header, $row);
    if ($assoc === false) {
        $problems[] = "Linha {$lineNo}: array_combine falhou mesmo após pad/merge.";
        continue;
    }

    // identificar campo id em várias possibilidades
    $id_field = null;
    foreach (['id','checklist_id','ID','Id'] as $k) {
        if (isset($assoc[$k])) { $id_field = $k; break; }
    }
    if ($id_field === null) {
        // fallback: primeiro header
        $id_field = $header[0];
    }

    $curId = trim((string)($assoc[$id_field] ?? ''));
    if ($curId !== '') $validIds[] = $curId;

    if ($curId !== '' && $curId === (string)$checklistId) {
        $found = $assoc;
        $found['_line_no'] = $lineNo;
        break;
    }
}

// se não encontrou, e modo debug, imprime info útil
if (!$found) {
    if ($debug) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Checklist não encontrado para id: {$checklistId}\n";
        echo "Caminho do CSV: {$path}\n";
        echo "Header (count={$hc}):\n";
        print_r($header);
        echo "\nPrimeiras 20 linhas (após processar pad/merge):\n";
        $count = 0;
        foreach ($rows as $idx => $row) {
            if ($count++ >= 20) break;
            $r = $row;
            if (count($r) < $hc) $r = array_pad($r,$hc,'');
            elseif (count($r) > $hc) {
                $first = array_slice($r,0,$hc-1);
                $last = array_slice($r,$hc-1);
                $first[] = implode(',', $last);
                $r = $first;
            }
            $assoc = @array_combine($header, $r);
            echo "Linha " . ($idx+2) . " -> ";
            if ($assoc === false) {
                echo "assoc_failed\n";
            } else {
                // mostra o id do assoc (se existir) e quantas colunas originais tinha
                $origCount = count($rows[$idx]);
                $idval = '';
                foreach (['id','checklist_id','ID','Id',$header[0]] as $k) {
                    if (isset($assoc[$k])) { $idval = $assoc[$k]; break; }
                }
                echo "orig_cols={$origCount} id=" . substr($idval,0,40) . "\n";
            }
        }
        echo "\nProblemas detectados (pad/merge):\n";
        foreach ($problems as $p) echo $p . "\n";
        echo "\nIDs válidos detectados (total " . count($validIds) . "):\n";
        foreach (array_slice($validIds,0,200) as $i) echo $i . "\n";
        if (count($validIds) > 200) echo "... (mais)\n";
        exit;
    } else {
        // sem debug, mostra mensagem simples
        echo "Checklist não encontrado.";
        exit;
    }
}

// OK, achou o checklist. Agora prepara dados para exibir
$checklist = $found;

// decode respostas_json (pode ser string "[]" ou "{}", ou valor inválido)
$respostas_raw = $checklist['respostas_json'] ?? ($checklist['respostas'] ?? '');
$respostas = [];
if ($respostas_raw !== '') {
    // se já for array (por algum motivo), aceita
    if (is_array($respostas_raw)) $respostas = $respostas_raw;
    else {
        // tenta json_decode com tolerância
        $decoded = json_decode($respostas_raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $respostas = $decoded;
        } else {
            // tenta tratar campos como "['a': 'OK']" ou sem aspas
            // fallback: não podemos decodificar -> manter string
            $respostas = $respostas_raw;
        }
    }
}

// pegar dados da máquina (se disponível)
$maqName = $checklist['id_maquina'] ?? '';
$maquinaInfo = null;
$pathM = __DIR__ . '/data/maquinas.csv';
if (file_exists($pathM)) {
    $mcsv = csvRead($pathM);
    $mh = $mcsv['header'] ?? [];
    $mrows = $mcsv['rows'] ?? [];
    foreach ($mrows as $r) {
        if (count($r) !== count($mh)) {
            // pad/merge like above
            if (count($r) < count($mh)) $r = array_pad($r, count($mh), '');
            else {
                $first = array_slice($r, 0, count($mh)-1);
                $last = array_slice($r, count($mh)-1);
                $first[] = implode(',', $last);
                $r = $first;
            }
        }
        $assocm = @array_combine($mh, $r);
        if (!$assocm) continue;
        // tentar identificar id column
        $idk = null;
        foreach (['id','codigo','maquina_id'] as $k) if (isset($assocm[$k])) { $idk = $k; break; }
        if ($idk && (string)($assocm[$idk]) === (string)$maqName) {
            $maquinaInfo = $assocm; break;
        }
    }
}

// ====== HTML de impressão (formatado) ======
?><!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Checklist <?= htmlspecialchars($checklist['id'] ?? '') ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <style>
    body { background: #fff; color:#111; font-family: Inter, Arial, sans-serif; padding:18px; }
    .card { border:1px solid #ddd; border-radius:10px; padding:18px; max-width:1100px; margin:8px auto; }
    .hdr { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; }
    .brand { font-weight:700; font-size:18px; }
    .muted { color:#666; font-size:13px; }
    table.resp-table { width:100%; border-collapse:collapse; margin-top:12px; }
    table.resp-table th, table.resp-table td { border:1px solid #e6e6e6; padding:8px; text-align:left; vertical-align:top; }
    th { background:#f8f9fa; font-weight:600; width:30%; }
    .btn-print { margin-top:14px; }
    @media print {
      .btn-print { display:none; }
    }
  </style>
</head>
<body>
  <div class="card">
    <div class="hdr">
      <div>
        <div class="brand">Checklist - <?= htmlspecialchars($checklist['id'] ?? '') ?></div>
        <div class="muted">Gerado por: <?= htmlspecialchars($checklist['operador'] ?? '') ?> — Máquina: <?= htmlspecialchars($maquinaInfo['nome'] ?? $checklist['id_maquina'] ?? '') ?></div>
      </div>
      <div class="text-end muted">
        <?php
          $d = $checklist['data_abertura'] ?? ($checklist['data'] ?? '');
          if ($d) {
            // tentar formatar
            $fmt = htmlspecialchars($d);
            try {
                $dt = new DateTime($d);
                $fmt = $dt->format('d/m/Y H:i');
            } catch (Exception $e) {}
            echo $fmt;
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
    // se respostas for string (não decodável), mostra raw
    if (!is_array($respostas)) {
        echo '<div class="muted">Resposta JSON não pôde ser decodificada. Exibir raw:</div>';
        echo '<pre>' . htmlspecialchars((string)$respostas) . '</pre>';
    } else {
        // caso seja associative (chave => valor) ou array de objetos
        // detectar se array associativo (has string keys)
        $isAssoc = function($a) {
            if (!is_array($a)) return false;
            return array_keys($a) !== range(0, count($a)-1);
        };

        if ($isAssoc($respostas)) {
            echo '<table class="resp-table"><thead><tr><th>Item</th><th>Valor</th></tr></thead><tbody>';
            foreach ($respostas as $k=>$v) {
                // v pode ser string ou array/object
                $val = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v;
                echo '<tr><td>' . htmlspecialchars($k) . '</td><td>' . htmlspecialchars((string)$val) . '</td></tr>';
            }
            echo '</tbody></table>';
        } else {
            // lista de itens (array indexed) - pode ser array de {item,resposta,obs}
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

    <div class="text-center">
      <button class="btn btn-primary btn-print" onclick="window.print()">🖨 Imprimir</button>
    </div>

    <?php if ($debug): ?>
      <hr>
      <h6>Debug info</h6>
      <pre><?php
        echo "Encontrado na linha: " . ($checklist['_line_no'] ?? 'n/a') . "\n";
        echo "Problemas detectados (pad/merge):\n";
        echo implode("\n", $problems);
      ?></pre>
    <?php endif; ?>

  </div>
</body>
</html>