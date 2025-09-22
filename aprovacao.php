<?php
// aprovacao.php
declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__.'/inc/auth.php'; 
require_once __DIR__.'/inc/csv.php';
require_once __DIR__ . '/inc/header.php';

requireLogin();
$u = currentUser();

if (isset($u) && is_array($u)) {
    $safe_value = htmlspecialchars($u['some_key'] ?? '');
} else {
    $safe_value = '';
}

if(!isSupervisor() && !isMaster()){ header('Location: dashboard.php'); exit; }

// Ler os arquivos uma única vez no início
$checklists_data = csvRead(__DIR__.'/data/checklists.csv'); 
$h = $checklists_data['header']; 
$rows = $checklists_data['rows'];

$maquinas_data = csvRead(__DIR__.'/data/maquinas.csv');
$maquinasH = $maquinas_data['header'];
$maquinasRows = $maquinas_data['rows'];

// Mapear os dados das máquinas para exibição na tabela
$maquinas_map = [];
if (is_array($maquinasRows)) {
    foreach ($maquinasRows as $maquina_row) {
        if (count($maquina_row) >= 3) {
            $maquinas_map[$maquina_row[0]] = [
                'nome' => $maquina_row[1],
                'tipo' => $maquina_row[2]
            ];
        }
    }
}

$manutencoes_data = csvRead(__DIR__.'/data/manutencoes.csv');
$manutencoesH = $manutencoes_data['header'];
$manutencoesRows = $manutencoes_data['rows'];


if($_SERVER['REQUEST_METHOD']==='POST'){
    $id = $_POST['id'];
    $action = $_POST['action'];

    $maquinas_data_changed = false; // Flag para verificar se o arquivo maquinas.csv foi alterado
    $checklists_data_changed = false; // Flag para verificar se o arquivo checklists.csv foi alterado
    $manutencoes_data_changed = false; // Flag para verificar se o arquivo manutencoes.csv foi alterado

    // Processar o arquivo de checklists
    foreach($rows as &$r){ 
        if(count($r) !== count($h)) {
            continue; // Ignora linhas inválidas
        }
        
        $checklist_data = array_combine($h, $r);

        if($checklist_data['id'] === $id){
            if($action === 'aprovar'){ 
                $r[array_search('status_checklist', $h)] = 'aprovado'; 
                $r[array_search('check_aprovado_por', $h)] = currentUser()['nome'];
                $checklists_data_changed = true;
                
                // --- LÓGICA DE ATUALIZAÇÃO DO STATUS DA MÁQUINA ---
                $maquina_id_checklist = $checklist_data['id_maquina'];
                
                // Encontra a linha da máquina e atualiza o status
                foreach ($maquinasRows as &$maq_row) {
                    if (count($maq_row) !== count($maquinasH)) continue;
                    $maq_data = array_combine($maquinasH, $maq_row);
                    if (($maq_data['id'] ?? '') === $maquina_id_checklist) {
                        $status_index = array_search('status', $maquinasH);
                        if ($status_index !== false) {
                             $maq_row[$status_index] = 'disponivel';
                             $maquinas_data_changed = true;
                        }
                        break;
                    }
                }
                // --- FIM DA LÓGICA DE ATUALIZAÇÃO ---
            }else if($action === 'reprovar'){ 
                $r[array_search('status_checklist', $h)] = 'reprovado'; 
                $r[array_search('aprovado_por', $h)] = currentUser()['nome'];
                $checklists_data_changed = true;
            } else if($action === 'enviar_para_manutencao'){
                $r[array_search('status_checklist', $h)] = 'em_reparo'; 
                $checklists_data_changed = true;
                
                // Pega o ID da máquina
                $idmaq = $checklist_data['id_maquina'];
                
                // Marca a máquina com status 'em_manutencao'
                foreach($maquinasRows as &$mr){ 
                    if(count($mr)===count($maquinasH) && $mr[0]===$idmaq){ 
                        $status_index = array_search('status', $maquinasH);
                        if ($status_index !== false) {
                            $mr[$status_index] = 'em_manutencao';
                            $maquinas_data_changed = true;
                        }
                        break;
                    } 
                }
                
                // Adiciona um novo registro em manutencoes.csv
                $nova_manutencao = [
                    uniqid('m_'), 
                    $idmaq, 
                    date('c'), 
                    '', 
                    currentUser()['nome'], 
                    '', 
                    date('c'), 
                    '', 
                    '', 
                    'em_reparo', 
                    'Falhas encontradas no checklist pré-operacional.', 
                    '', 
                    '', 
                    $checklist_data['id'], 
                    '', 
                    '', 
                    ''
                ];
                // Adiciona a nova linha ao array em memória
                $manutencoesRows[] = $nova_manutencao;
                $manutencoes_data_changed = true;
            }
        }
    }
    unset($r, $maq_row, $mr); // Quebra a referência para evitar efeitos colaterais

    // Salva os arquivos SOMENTE se houver alterações
    if ($checklists_data_changed) {
        csvWrite(__DIR__.'/data/checklists.csv', $h, $rows);
    }
    if ($maquinas_data_changed) {
        csvWrite(__DIR__.'/data/maquinas.csv', $maquinasH, $maquinasRows);
    }
    if ($manutencoes_data_changed) {
        csvWrite(__DIR__.'/data/manutencoes.csv', $manutencoesH, $manutencoesRows);
    }
    
    header('Location: aprovacao.php'); 
    exit;
}

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Aprovação</title>
        <link rel="icon" href="assets/emp.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/stylenew.css">
<style>
        .modal {display:none; position:fixed; inset:0; background:rgba(0,0,0,.6);z-index:9999; align-items:center; justify-content:center; animation: fadeIn .3s ease-in-out;}
        .modal .card { width: min(400px, 95%); background:#fff; border-radius:12px;box-shadow:0 10px 30px rgba(5, 45, 19, 0.3); overflow:hidden;animation: slideIn .3s ease;}
        .modal-header {display:flex; justify-content:space-between; align-items:center;background:#0d6efd; color:#fff; padding:12px 16px;}
        .modal-header h3 { margin:0; font-size:18px; display:flex; gap:8px; align-items:center; }
        .close-btn { background:#ef4444; color:#fff; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; }
        .modal pre { white-space:pre-wrap; word-break:break-word; background:#f7f7f7; padding:14px; border-radius:8px; font-size:13px; }
        .modal-body { padding:18px; font-size:14px; }
        .modal-table { width:80%; border-collapse:collapse; }
        .modal-table th, .modal-table td {text-align:left; padding:8px; border-bottom:1px solid #eee;}
        .modal-table th { background:#f2f4f7; width:30%; font-weight:600; }.status-badge {display:inline-block; padding:4px 10px; border-radius:20px; font-size:12px; font-weight:600; }
        .status-ok { background:#d1e7dd; color:#0f5132; }
        .status-pendente { background:#fff3cd; color:#664d03; }
        .status-erro { background:#f8d7da; color:#842029; }
        @keyframes fadeIn { from {opacity:0;  transform:scale(.95);}} to {opacity:1; transform:scale(1);} }
        @keyframes slideIn { from {transform:translateY(-20px);opacity:0;} to {transform:translateY(0);opacity:1;} }
        @media print {.btn, .action-buttons, .no-print {display: none !important;}}
        @media (max-width: 600px) {
        .modal{
            /* Ajuste para telas menores, como celulares */
            width: 90%;
            margin: 0 10px; /* Adiciona um pequeno espaço nas laterais */
        }
}
</style>

</head>
<body>

    <main class="containerMaior">
        <h2>Checklists Pendentes</h2>
        <table>
            <thead><tr><th>ID</th><th>Data Check</th><th>Id Máq&nbsp;&nbsp;</th><th>Tipo</th><th>Frota</th><th>Operador</th><th>Falhas (NC)</th><th>&nbsp;Ações&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</th></tr></thead>
            <tbody>
            <?php
            foreach ($rows as $r) {
                if (count($r) !== count($h)) continue;
                $checklist_data = array_combine($h, $r);

                // Mostra o checklist se o status for pendente
                if (($checklist_data['status_checklist'] ?? '') === 'pendente') {
                    
                    // Conta e coleta as falhas (NC)
                    $respostas_json = $checklist_data['respostas_json'] ?? '[]';
                    $respostas = json_decode($respostas_json, true);
                    $falhas_nc_itens = [];
                    $falhas_nc_count = 0;
                    
                    if (is_array($respostas)) {
                        foreach ($respostas as $item => $resposta) {
                            if ($resposta === 'NC') {
                                $falhas_nc_count++;
                                // Adiciona o nome do item e a resposta 'NC'
                                $falhas_nc_itens[$item] = 'NC'; 
                            }
                        }
                    }


                    // Pega o nome e o tipo da máquina do array
                    $id_maquina = $checklist_data['id_maquina'];
                    $maquina_info = $maquinas_map[$id_maquina] ?? ['nome' => 'N/A', 'tipo' => 'N/A'];
                    $nome_maquina = $maquina_info['nome'];
                    $tipo_maquina = $maquina_info['tipo'];

                    // Cria um JSON com os itens não conformes para o modal
                    $falhas_json_attr = htmlspecialchars(json_encode($falhas_nc_itens, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
                    
                    echo '<tr>';
                    echo '<td>'.htmlspecialchars($checklist_data['id']).'</td>';
                    echo '<td>'.htmlspecialchars($checklist_data['data_abertura']).'</td>';
                    echo '<td>'.htmlspecialchars($checklist_data['id_maquina']).'</td>';
                    echo '<td>'.htmlspecialchars($nome_maquina).'</td>'; // Adiciona o nome da máquina
                    echo '<td>'.htmlspecialchars($tipo_maquina).'</td>'; // Adiciona o tipo da máquina
                    echo '<td>'.htmlspecialchars($checklist_data['operador']).'</td>';
                    echo '<td>';
                        // Adiciona o botão "Enviar para Manutenção" apenas se houver falhas (NC)
                        if ($falhas_nc_count > 0) {
                            echo '<span class="status-badge status-erro">'.htmlspecialchars((string)$falhas_nc_count).'</span>';
                        }else{
                             echo '<span class="status-badge status-ok">'.htmlspecialchars((string)$falhas_nc_count).'</span>';
                        }

                    '</td>';
                    echo '<td>';
                    
                    // Adiciona o botão "Ver" apenas se houver falhas (NC)
                    if ($falhas_nc_count > 0) {
                        // Passa os dados JSON dos itens NC diretamente para a função JS
                        echo ' <button class="view-btn" onclick="openModalWithDetails(\''.htmlspecialchars($checklist_data['id']).'\', \''.$falhas_json_attr.'\')">Ver</button>';
                    }

                    echo '<form method="post" style="display:inline"><input type="hidden" name="id" value="'.htmlspecialchars($checklist_data['id']).'"><button name="action" value="aprovar">Aprovar</button></form>';
                    echo ' <form method="post" style="display:inline"><input type="hidden" name="id" value="'.htmlspecialchars($checklist_data['id']).'"><button name="action" value="reprovar">Reprovar</button></form>';
                    
                    // Adiciona o botão "Enviar para Manutenção" apenas se houver falhas (NC)
                    if ($falhas_nc_count > 0) {
                        echo ' <form method="post" style="display:inline">
                        <input type="hidden" name="id" value="'.htmlspecialchars($checklist_data['id']).'">
                        <button name="action" value="enviar_para_manutencao">Enviar para Manutenção</button></form>';
                    }

                    echo '</td>';
                    echo '</tr>';
                }
            }
            ?>
            </tbody>
        </table>
    </main>

      <div id="modal" class="modal" onclick="if(event.target.id==='modal') closeModal()">
        <div class="card" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
            <div class="modal-header">
                <h3 id="modalTitle">Detalhes do Checklist</h3>
                <button onclick="closeModal()" class="close-btn">✕</button>
            </div>
            <div id="modalBody"></div>
        </div>
    </div>

    <script>
        function openModalWithDetails(checklistId, jsonNcData) {
            let parsedData;
            try {
                parsedData = JSON.parse(jsonNcData);
            } catch (e) {
                console.error("Erro ao decodificar JSON:", e);
                parsedData = {'erro': 'Não foi possível ler os dados das falhas.'};
            }

            let html = '<h4>Itens Não Conformes (NC) do Checklist #' + checklistId + '</h4>';
            html += '<table class="modal-table">';
            
            const hasNcItems = Object.keys(parsedData).length > 0;
            
            if (hasNcItems) {
                for (const [key, val] of Object.entries(parsedData)) {
                    html += `<tr><th>${key}</th><td><span class="status-badge status-erro">${val}</span></td></tr>`;
                }
            } else {
                html += '<tr><td colspan="2">Nenhum item não conforme (NC) encontrado.</td></tr>';
            }

            html += '</table>';
            
            document.getElementById('modalBody').innerHTML = html;
            document.getElementById('modalTitle').textContent = 'Detalhes do Checklist';
            document.getElementById('modal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('modal').style.display = 'none';
        }
    </script>
</body>
</html>