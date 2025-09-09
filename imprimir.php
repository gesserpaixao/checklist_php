<?php 
// imprimir.php
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/csv.php';

requireLogin();

// ---------- Helpers ----------
function safeTrim($v){ return is_string($v) ? trim($v, " \t\n\r\0\x0B\"") : $v; }
function fixRowToHeader(array $row, int $headerCount): array {
    if (count($row) === $headerCount) return $row;
    if (count($row) < $headerCount) return array_pad($row, $headerCount, '');
    $first = array_slice($row, 0, $headerCount - 1);
    $last = array_slice($row, $headerCount - 1);
    $first[] = implode(',', $last);
    return $first;
}
function firstField(array $assoc, array $candidates) {
    foreach ($candidates as $k) {
        if (array_key_exists($k, $assoc) && $assoc[$k] !== '') return $assoc[$k];
    }
    return null;
}
function tryParseDate($raw) {
    $raw = safeTrim($raw);
    if ($raw === '' || $raw === null) return null;
    try { return new DateTime($raw); } catch (Exception $e) {}
    $formats = ['Y-m-d H:i:s', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s', 'Y-m-d'];
    foreach ($formats as $fmt) {
        $d = DateTime::createFromFormat($fmt, $raw);
        if ($d) return $d;
    }
    if (preg_match('/(\d{4}-\d{2}-\d{2})/', $raw, $m)) {
        $d = DateTime::createFromFormat('Y-m-d', $m[1]);
        if ($d) return $d;
    }
    return null;
}

// ---------- Carrega CSVs ----------
$checklistsData = csvRead(__DIR__ . '/data/checklists.csv');
$checklists_header = $checklistsData['header'] ?? [];
$checklists_rows = $checklistsData['rows'] ?? [];

$maquinasData = csvRead(__DIR__ . '/data/maquinas.csv');
$maquinas_header = $maquinasData['header'] ?? [];
$maquinas_rows = $maquinasData['rows'] ?? [];

// ---------- Mapeia máquinas ----------
$maquinas_map = [];
$tipos_maquinas = [];
foreach ($maquinas_rows as $r) {
    if (count($r) !== count($maquinas_header)) {
        $r = fixRowToHeader($r, count($maquinas_header));
    }
    if (count($r) !== count($maquinas_header)) continue;
    $m = array_combine($maquinas_header, $r);

    $id   = firstField($m, ['id', 'codigo', 'maquina_id']);
    $nome = firstField($m, ['nome', 'descricao', 'modelo']);
    $tipo = firstField($m, ['tipo', 'categoria', 'classe']);

    if (!$id) continue;
    if (!$nome) $nome = $id;
    if (!$tipo) $tipo = 'N/D';

    $maquinas_map[$id] = ['nome' => $nome, 'tipo' => $tipo];
    $tipos_maquinas[$tipo] = $tipo;
}

// ---------- Filtros ----------
$data_inicio_filtro = $_GET['data_inicio'] ?? '';
$data_fim_filtro = $_GET['data_fim'] ?? '';
$tipo_filtro = $_GET['tipo'] ?? '';

$start = !empty($data_inicio_filtro) ? new DateTime($data_inicio_filtro . ' 00:00:00') : null;
$end   = !empty($data_fim_filtro) ? new DateTime($data_fim_filtro . ' 23:59:59') : null;

// ---------- Normaliza checklists e aplica filtros ----------
$checklists_visiveis = [];
foreach ($checklists_rows as $row) {
    if (count($row) !== count($checklists_header)) {
        $row = fixRowToHeader($row, count($checklists_header));
    }
    if (count($row) !== count($checklists_header)) continue;
    $c = array_combine($checklists_header, $row);

    $id_chk     = firstField($c, ['id', 'checklist_id']);
    $maquina_id = firstField($c, ['id_maquina', 'maquina_id', 'maquina']);
    $operador   = firstField($c, ['operador', 'usuario', 'id_usuario']);
    $status     = firstField($c, ['status_checklist','status','situacao','aprovado_por']);
    $data_raw   = firstField($c, ['data_abertura','data','inicio','data_fechamento','created_at']);

    $dt = tryParseDate($data_raw);
    $maq_info = $maquinas_map[$maquina_id] ?? ['nome' => ($maquina_id ?: 'N/D'), 'tipo' => 'N/D'];
    $tipo_maquina = $maq_info['tipo'] ?? 'N/D';

    $passa_tipo = empty($tipo_filtro) || $tipo_maquina === $tipo_filtro;
    $passa_data = true;
    if ($start || $end) {
        if (!$dt) $passa_data = false;
        else {
            if ($start && $dt < $start) $passa_data = false;
            if ($end && $dt > $end) $passa_data = false;
        }
    }

    if ($passa_tipo && $passa_data) {
        $checklists_visiveis[] = [
            'id' => $id_chk ?: '',
            'maquina_id' => $maquina_id ?: '',
            'operador' => $operador ?: '',
            'status' => $status ?: '',
            'data_obj' => $dt,
            'data_raw' => $data_raw ?: '',
            'raw' => $c
        ];
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Checklists</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .container { max-width: 1100px; margin: 28px auto; background:#fff; padding:20px; border-radius:10px; box-shadow:0 6px 20px rgba(0,0,0,.06);}
    .filter-form { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:14px; align-items:flex-end; }
    .filter-form label { font-weight:600; font-size:13px; color:#333; display:block; }
    .filter-form input, .filter-form select { padding:8px; border-radius:6px; border:1px solid #ddd; }
    .btn { background:#0d6efd;color:#fff;padding:8px 12px;border-radius:6px;border:none;cursor:pointer; }
    .table { width:100%; border-collapse:collapse; margin-top:10px; }
    .table th, .table td { padding:10px; border-bottom:1px solid #eee; text-align:left; font-size:14px; }
    .table th { background:#f2f4f7; font-weight:700; }
    .action-buttons button, .action-buttons a { margin-right:6px; padding:6px 10px; border-radius:6px; border:none; cursor:pointer; color:#fff; text-decoration:none; font-size:13px; }
    .view-btn { background:#28a745; }
    .print-btn { background:#6c63ff; }
    .no-data { text-align:center; color:#777; padding:30px 0; font-style:italic; }
    /* .modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:9999; align-items:center; justify-content:center; }
    .modal .card { width: min(900px, 96%); max-height:85vh; overflow:auto; padding:22px; background:#fff; border-radius:12px; box-shadow:0 10px 40px rgba(0,0,0,.25); animation:fadeIn .25s ease; }
    .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
    .modal-header h3 { margin:0; font-size:18px; }
    .modal pre { white-space:pre-wrap; word-break:break-word; background:#f7f7f7; padding:14px; border-radius:8px; font-size:13px; }
    @keyframes fadeIn { from {opacity:0; transform:scale(.95);} to {opacity:1; transform:scale(1);} } */

  .modal {display:none; position:fixed; inset:0; background:rgba(0,0,0,.6);z-index:9999; align-items:center; justify-content:center; animation: fadeIn .3s ease-in-out;}
  .modal .card { width: min(600px, 95%); background:#fff; border-radius:12px;box-shadow:0 10px 30px rgba(5, 45, 19, 0.3); overflow:hidden;animation: slideIn .3s ease;}
  .modal-header {display:flex; justify-content:space-between; align-items:center;background:#0d6efd; color:#fff; padding:12px 16px;}
  .modal-header h3 { margin:0; font-size:18px; display:flex; gap:8px; align-items:center; }
  .close-btn {background:none; border:none; color:#fff; font-size:20px; cursor:pointer;transition: transform 0.2s;}
  .close-btn:hover { transform: scale(1.2); }
  .modal pre { white-space:pre-wrap; word-break:break-word; background:#f7f7f7; padding:14px; border-radius:8px; font-size:13px; }
  .modal-body { padding:18px; font-size:14px; }
  .modal-table { width:100%; border-collapse:collapse; }
  .modal-table th, .modal-table td {text-align:left; padding:8px; border-bottom:1px solid #eee;}
  .modal-table th { background:#f2f4f7; width:30%; font-weight:600; }.status-badge {display:inline-block; padding:4px 10px; border-radius:20px; font-size:12px; font-weight:600; }
  .status-ok { background:#d1e7dd; color:#0f5132; }
  .status-pendente { background:#fff3cd; color:#664d03; }
  .status-erro { background:#f8d7da; color:#842029; }

  @keyframes fadeIn { from {opacity:0;  transform:scale(.95);}} to {opacity:1; transform:scale(1);} }
  @keyframes slideIn { from {transform:translateY(-20px);opacity:0;} to {transform:translateY(0);opacity:1;} }



  </style>
</head>
<body style="background:#f3f4f6;font-family:Inter,system-ui,Arial;">
  <div class="container">
    <h2>Checklists</h2>

    <form class="filter-form" method="get" action="imprimir.php">
        <div>
            <label for="data_inicio">De</label>
            <input type="date" id="data_inicio" name="data_inicio" value="<?= htmlspecialchars($data_inicio_filtro) ?>">
        </div>
        <div>
            <label for="data_fim">Até</label>
            <input type="date" id="data_fim" name="data_fim" value="<?= htmlspecialchars($data_fim_filtro) ?>">
        </div>
        <div>
            <label for="tipo">Tipo da Máquina</label>
            <select id="tipo" name="tipo">
                <option value="">-- Todos --</option>
                <?php foreach($tipos_maquinas as $t): ?>
                    <option value="<?=htmlspecialchars($t)?>" <?= ($t===$tipo_filtro)?'selected':''?>><?=htmlspecialchars($t)?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="align-self:flex-end;">
            <button class="btn">Filtrar</button>
        </div>
    </form>

    <?php if (!empty($checklists_visiveis)): ?>
      <table class="table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Máquina</th>
            <th>Tipo</th>
            <th>Operador</th>
            <th>Status</th>
            <th>Data</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($checklists_visiveis as $r): 
              $mid = $r['maquina_id'] ?? '';
              $minfo = $maquinas_map[$mid] ?? ['nome'=>($mid?:'N/D'),'tipo'=>'N/D'];
              $dateDisplay = $r['data_obj'] ? $r['data_obj']->format('d/m/Y H:i') : ($r['data_raw'] ?: '');
              $jsonAttr = htmlspecialchars(json_encode($r['raw'], JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT), ENT_QUOTES);
          ?>
            <tr>
              <td><?=htmlspecialchars($r['id'])?></td>
              <td><?=htmlspecialchars($minfo['nome'])?></td>
              <td><?=htmlspecialchars($minfo['tipo'])?></td>
              <td><?=htmlspecialchars($r['operador'])?></td>
              <td><?=htmlspecialchars($r['status'])?></td>
              <td><?= $dateDisplay ?></td>
              <td>
                <div class="action-buttons">
                  <button class="view-btn" data-json='<?=$jsonAttr?>' onclick="openFromBtn(this)">Ver</button>
                  <a class="print-btn" href="imprimir_checklist.php?checklist_id=<?=urlencode($r['id'])?>">Imprimir</a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="no-data">Nenhum checklist encontrado para os filtros informados.</div>
    <?php endif; ?>
  </div>

  <!-- Modal -->
  <div id="modal" class="modal" onclick="if(event.target.id==='modal') closeModal()">
    <div class="card" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
      <div class="modal-header">
        <h3 id="modalTitle">Detalhes do Checklist</h3>
        <button onclick="closeModal()" 
        style="background:#ef4444;color:#fff;border:none;padding:6px 12px;border-radius:6px;cursor:pointer">✕</button>
      </div>
      <div id="modalBody"></div>
    </div>
  </div>

<script>
function openModal(obj){
  let html = '<table class="modal-table">';
  for (const [key,val] of Object.entries(obj)) {
    let value = val;
    if (key.toLowerCase().includes('status')) {
      let cls = 'status-badge';
      if (/ok|aprovado/i.test(val)) cls += ' status-ok';
      else if (/pendente|aguardo/i.test(val)) cls += ' status-pendente';
      else cls += ' status-erro';
      value = `<span class="${cls}">${val}</span>`;
    } else {
      value = val || '<span class="muted">-</span>';
    }
    html += `<tr><th>${key}</th><td>${value}</td></tr>`;
  }
  html += '</table>';
  document.getElementById('modalBody').innerHTML = html;
  document.getElementById('modal').style.display = 'flex';
  
}

function openFromBtn(btn){
    try {
        const raw = btn.getAttribute('data-json');
        const parsed = JSON.parse(raw);
        openModal(parsed);
    } catch(e) {
        openModal({'erro':'Não foi possível ler os dados do checklist.'});
    }
}

function closeModal(){
    document.getElementById('modal').style.display = 'none';
}
</script>
</body>
</html>
