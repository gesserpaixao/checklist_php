<?php
// investigacoes.php
declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/header.php';
requireLogin();

if (!isHse() && !isMaster()) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/inc/csv.php';
require_once __DIR__ . '/inc/utils.php';

$u = currentUser();

// Leitura de dados de todos os arquivos CSV
$investCsv = csvRead(__DIR__ . '/data/invest_hse.csv');
$investH = $investCsv['header'] ?? [];
$investRows = $investCsv['rows'] ?? [];

$maquinasCsv = csvRead(__DIR__ . '/data/maquinas.csv');
$maquinasH = $maquinasCsv['header'] ?? [];
$maquinasRows = $maquinasCsv['rows'] ?? [];

$checklistCsv = csvRead(__DIR__ . '/data/checklists.csv');
$checklistH = $checklistCsv['header'] ?? [];
$checklistRows = $checklistCsv['rows'] ?? [];

// Mapeamento de dados para buscas eficientes
$maquinasMap = [];
foreach ($maquinasRows as $r) {
    if (count($r) === count($maquinasH)) {
        $maq = array_combine($maquinasH, $r);
        $maquinasMap[$maq['id']] = $maq;
    }
}

$checklistsMap = [];
foreach ($checklistRows as $r) {
    if (count($r) === count($checklistH)) {
        $checklistsMap[$r[0]] = array_combine($checklistH, $r);
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Lista de Investigações HSE</title>
    <link rel="stylesheet" href="assets/stylenew.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .containerMaior { max-width: 1200px; margin: 20px auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 14px; }
        th { background-color: #f2f2f2; }
        .modal {display:none; position:fixed; inset:0; background:rgba(0,0,0,.6);z-index:9999; align-items:center; justify-content:center;}
        .modal .card { width: min(800px, 95%); background:#fff; border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.2); overflow:hidden;}
        .modal-header {display:flex; justify-content:space-between; align-items:center;background:#0d6efd; color:#fff; padding:12px 16px;}
        .close-btn { background:#ef4444; color:#fff; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; }
        .modal-body { padding:18px; font-size:14px; }
        .modal-table { width:100%; border-collapse:collapse; }
        .modal-table th, .modal-table td { text-align:left; padding:8px; border-bottom:1px solid #eee; }
        .modal-table th { background:#f2f4f7; width:20%; font-weight:600; }
        .modal-table .q-title { font-weight: bold; margin-top: 10px; padding: 10px 0; }
        .evidencia-img { max-width: 100px; height: auto; margin: 5px; border-radius: 4px; border: 1px solid #ccc; cursor: pointer; }
    </style>
</head>
<body>
    <main class="containerMaior">
        <h2>Lista de Investigações HSE</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Data</th>
                    <th>Máquina</th>
                    <th>Investigador</th>
                    <th>Análise HSE</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($investRows as $r):
                    if (count($r) < count($investH)) continue;
                    $invest_data = array_combine($investH, $r);
                    $maquina_info = $maquinasMap[$invest_data['id_maquina']] ?? ['nome' => 'N/D'];
                    $data_formatada = date('d/m/Y H:i', strtotime($invest_data['data_invest']));

                    // Preparando dados para o modal
                    $modalData = $invest_data;
                    $modalData['maquina_nome'] = $maquina_info['nome'] ?? 'N/D';
                    
                    // Busca as perguntas do checklist relacionado
                    $checklist_relacionado = $checklistsMap[$invest_data['id_checklist']] ?? null;
                    $modalData['checklist_perguntas'] = $checklist_relacionado ? json_decode($checklist_relacionado['perguntas'], true) : [];
                    
                    $jsonAttr = htmlspecialchars(json_encode($modalData, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
                ?>
                <tr>
                    <td><?= htmlspecialchars($invest_data['id'] ?? '') ?></td>
                    <td><?= htmlspecialchars($data_formatada) ?></td>
                    <td><?= htmlspecialchars($maquina_info['nome'] ?? '') ?></td>
                    <td><?= htmlspecialchars($invest_data['nome_investigador'] ?? '') ?></td>
                    <td><?= htmlspecialchars(substr($invest_data['analise_hse'] ?? '', 0, 50)) . '...' ?></td>
                    <td>
                        <button onclick="openModalFromBtn(this)" data-json='<?= $jsonAttr ?>'>Ver Detalhes</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>

    <div id="modal" class="modal" onclick="if(event.target.id==='modal') closeModal()">
        <div class="card" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
            <div class="modal-header">
                <h3 id="modalTitle">Detalhes da Investigação</h3>
                <button onclick="closeModal()" class="close-btn">✕</button>
            </div>
            <div id="modalBody" class="modal-body"></div>
        </div>
    </div>
    <script>
        function openModalFromBtn(btn) {
            try {
                const data = JSON.parse(btn.getAttribute('data-json'));
                let html = '<table class="modal-table">';
                
                // Exibindo campos fixos
                html += `<tr><th>ID da Investigação</th><td>${data.id}</td></tr>`;
                html += `<tr><th>Data da Investigação</th><td>${new Date(data.data_invest).toLocaleString('pt-BR')}</td></tr>`;
                html += `<tr><th>Máquina</th><td>${data.maquina_nome}</td></tr>`;
                html += `<tr><th>ID do Checklist</th><td>${data.id_checklist}</td></tr>`;
                html += `<tr><th>Operador</th><td>${data.operador}</td></tr>`;
                html += `<tr><th>Supervisor</th><td>${data.supervisor}</td></tr>`;
                html += `<tr><th>Investigador</th><td>${data.nome_investigador}</td></tr>`;
                html += `<tr><th>Análise HSE</th><td>${data.analise_hse.replace(/\n/g, '<br>')}</td></tr>`;
                html += `<tr><th>Parecer HSE</th><td>${data.parecerhse.replace(/\n/g, '<br>')}</td></tr>`;

                // Exibindo as perguntas do checklist
                if (data.checklist_perguntas && Object.keys(data.checklist_perguntas).length > 0) {
                    html += `<tr><td colspan="2"><div class="q-title">Perguntas do Checklist</div></td></tr>`;
                    for (const [pergunta, resposta] of Object.entries(data.checklist_perguntas)) {
                        html += `<tr><th>${pergunta}</th><td>${resposta}</td></tr>`;
                    }
                } else {
                    html += `<tr><td colspan="2">Nenhum checklist associado ou perguntas não encontradas.</td></tr>`;
                }
                
                // Exibindo a análise de causa raiz
                try {
                    const causaRaiz = JSON.parse(data.causa_raiz);
                    html += `<tr><td colspan="2"><div class="q-title">Análise da Causa Raiz</div></td></tr>`;
                    if (Object.keys(causaRaiz).length > 0) {
                        for (const [pergunta, resposta] of Object.entries(causaRaiz)) {
                            html += `<tr><th>${pergunta.replace('pq', 'Por quê? (Nível ')}</th><td>${resposta}</td></tr>`;
                        }
                    } else {
                        html += `<tr><td colspan="2">Nenhuma análise de causa raiz registrada.</td></tr>`;
                    }
                } catch (e) {
                    html += `<tr><th>Causa Raiz (raw)</th><td>${data.causa_raiz}</td></tr>`;
                }

                // Exibindo as evidências
                if (data.evidencias_path) {
                    try {
                        const evidencias = JSON.parse(data.evidencias_path);
                        if (evidencias.length > 0) {
                            html += `<tr><td colspan="2"><div class="q-title">Evidências (Fotos)</div></td></tr>`;
                            html += `<tr><th>Fotos</th><td>`;
                            evidencias.forEach(path => {
                                html += `<a href="${path}" target="_blank"><img src="${path}" class="evidencia-img" alt="Evidência da investigação"></a>`;
                            });
                            html += `</td></tr>`;
                        }
                    } catch (e) {
                        html += `<tr><th>Evidências (raw)</th><td>${data.evidencias_path}</td></tr>`;
                    }
                }

                html += '</table>';
                document.getElementById('modalBody').innerHTML = html;
                document.getElementById('modal').style.display = 'flex';
            } catch (e) {
                console.error("Erro ao abrir modal:", e);
            }
        }

        function closeModal() {
            document.getElementById('modal').style.display = 'none';
        }
    </script>
</body>
</html>