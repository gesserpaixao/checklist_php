<?php
require_once __DIR__.'/inc/auth.php';
requireLogin();
$u = currentUser();
$erro_checklist = '';

require_once __DIR__.'/inc/csv.php';
require_once __DIR__.'/inc/utils.php'; // Adicionado para incluir a função getLatestMaintenanceId

$maqCsv = csvRead(__DIR__.'/data/maquinas.csv');
$maquinas = $maqCsv['rows'] ?? [];
$maqH = $maqCsv['header'] ?? [];

$perguntaCsv = csvRead(__DIR__.'/data/perguntas.csv');
$perguntas = $perguntaCsv['rows'] ?? [];
$perguntaH = $perguntaCsv['header'] ?? [];

$checklistsCsv = csvRead(__DIR__.'/data/checklists.csv');
$checklists = $checklistsCsv['rows'] ?? [];
$checklistH = $checklistsCsv['header'] ?? [];

$manutencaoCsv = csvRead(__DIR__.'/data/manutencoes.csv');
$manutencoes = $manutencaoCsv['rows'] ?? [];
$manutH = $manutencaoCsv['header'] ?? [];

$maquinas_map = [];
if (is_array($maquinas)) {
    foreach ($maquinas as $r) {
        if (count($r) === count($maqH)) {
            $maq = array_combine($maqH, $r);
            $maquinas_map[$maq['id']] = $maq;
        }
    }
}

// Lógica para enviar a maquina para manutencao
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enviar_manutencao') {
    $id_maquina = $_POST['id_maquina'] ?? null;
    $obs_manut = $_POST['obs_manut'] ?? '';
    
    // Atualizar status da máquina para 'em_manutencao'
    $maquinas_temp = $maquinas;
    foreach($maquinas_temp as &$maq_row) {
        if (count($maq_row) === count($maqH)) {
            $maq_data = array_combine($maqH, $maq_row);
            if ($maq_data['id'] === $id_maquina) {
                $maq_data['status'] = 'em_manutencao';
                $maq_row = array_values($maq_data);
                break;
            }
        }
    }
    csvWrite(__DIR__.'/data/maquinas.csv', $maqH, $maquinas_temp);

    // Adicionar novo registro de manutenção
    $novo_registro_manut = [
        uniqid('m_'), // ID único da manutenção
        $id_maquina,
        date('c'), // Data de início
        '', // Data de fim (vazio)
        $u['nome'], // Responsável
        'pendente', // Status da manutenção
        $obs_manut, // Descrição do problema
        '' // Descrição da manutenção (vazio)
    ];
    csvAppend(__DIR__.'/data/manutencoes.csv', $novo_registro_manut);

    header('Location: workplace.php');
    exit;
}

// Lógica para salvar o checklist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && ($_POST['action'] === 'abrir_turno' || $_POST['action'] === 'fechar_turno')) {
    $id_maquina = $_POST['maquina'] ?? null;
    $orimetro = $_POST['orimetro'] ?? '';
    $observacoes = $_POST['observacoes'] ?? '';
    $respostas_raw = $_POST['respostas'] ?? [];
    $id_checklist_aberto = $_POST['id_checklist_aberto'] ?? '';
    $tipo_checklist = $_POST['action'] === 'abrir_turno' ? 'abertura' : 'fechamento';

    // Verificação se já existe um checklist aberto para a máquina
    $checklist_aberto_existe = false;
    foreach ($checklists as $c) {
        if (count($c) !== count($checklistH)) continue;
        $c_data = array_combine($checklistH, $c);
        if ($c_data['id_maquina'] === $id_maquina && $c_data['tipo_checklist'] === 'abertura' && $c_data['status_checklist'] === 'aberto') {
            $checklist_aberto_existe = true;
            $id_checklist_aberto = $c_data['id'];
            break;
        }
    }

    if ($tipo_checklist === 'abertura') {
        if ($checklist_aberto_existe) {
            $erro_checklist = 'Já existe um checklist de abertura de turno em andamento para esta máquina.';
        } else {
            $respostas_serializadas = json_encode($respostas_raw);
            $falhas = count(array_filter($respostas_raw, function($r) { return $r !== 'ok'; }));

            $novo_registro = [
                uniqid('c_'), // ID único do checklist
                $u['nome'],
                $id_maquina,
                date('Y-m-d H:i:s'),
                (date('H') >= 6 && date('H') < 18) ? 'Dia' : 'Noite',
                'pendente',
                $falhas,
                '', // id_manutencao
                '', // aprovado_por
                $observacoes,
                $respostas_serializadas,
                $orimetro, // orimetro_inicial
                '', // orimetro_final (vazio)
                'aberto' // tipo_checklist
            ];
            csvAppend(__DIR__.'/data/checklists.csv', $novo_registro);
            
            header('Location: workplace.php');
            exit;
        }
    } elseif ($tipo_checklist === 'fechamento') {
        if (!$checklist_aberto_existe) {
            $erro_checklist = 'Não há um checklist de abertura de turno para esta máquina.';
        } else {
            foreach ($checklists as &$c) {
                if (count($c) !== count($checklistH)) continue;
                $c_data = array_combine($checklistH, $c);
                if ($c_data['id'] === $id_checklist_aberto) {
                    $c_data['orimetro_final'] = $orimetro;
                    $c_data['tipo_checklist'] = 'fechamento';
                    $c_data['status_checklist'] = 'pendente'; // Retorna para status pendente para aprovação
                    $c = array_values($c_data);
                    break;
                }
            }
            csvWrite(__DIR__.'/data/checklists.csv', $checklistH, $checklists);

            // Redireciona para a página de impressão com o ID do checklist
            header('Location: imprimir_checklist.php?checklist_id=' . $id_checklist_aberto);
            exit;
        }
    }
}

// Verifica se há um checklist aberto para o usuário e máquina
$checklist_aberto = null;
foreach ($checklists as $c) {
    if (count($c) !== count($checklistH)) continue;
    $c_data = array_combine($checklistH, $c);
    if ($c_data['usuario'] === $u['nome'] && $c_data['tipo_checklist'] === 'abertura' && $c_data['status_checklist'] === 'aberto') {
        $checklist_aberto = $c_data;
        break;
    }
}
?>

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Workplace</title>
    <link rel="stylesheet" href="assets/stylenew.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .form-group {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .form-group label {
            flex: 1;
            margin-right: 15px;
        }
        .radio-group {
            display: flex;
            gap: 15px;
        }
        .radio-group label {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
        }
        /* Style para a seção do checklist */
        #checklist-form {
            display: none;
        }
        #checklist-form.visible {
            display: block;
        }
    </style>
    <script>
    function showChecklist() {
        var tipoMaquina = document.getElementById('maquina').options[document.getElementById('maquina').selectedIndex].getAttribute('data-tipo');
        var checklistForm = document.getElementById('checklist-form');
        var questions = document.getElementById('questions');
        var maquinaId = document.getElementById('maquina').value;
        
        if (maquinaId === '' || tipoMaquina === null) {
            checklistForm.style.display = 'none';
            questions.innerHTML = '';
            return;
        }

        questions.innerHTML = '';
        var perguntas = <?= json_encode($perguntas) ?>;
        var perguntaH = <?= json_encode($perguntaH) ?>;

        perguntas.forEach(function(p) {
            if (p[0] === tipoMaquina) {
                var perguntaData = {};
                perguntaH.forEach(function(h, i) {
                    perguntaData[h] = p[i];
                });

                var h3 = document.createElement('h3');
                h3.textContent = perguntaData['label'];
                questions.appendChild(h3);

                var div = document.createElement('div');
                div.className = 'radio-group';
                div.innerHTML = `
                    <label><input type="radio" name="respostas[${perguntaData['chave']}]" value="OK" required> OK</label>
                    <label><input type="radio" name="respostas[${perguntaData['chave']}]" value="NC"> NC</label>
                    <label><input type="radio" name="respostas[${perguntaData['chave']}]" value="N/A"> N/A</label>
                `;
                questions.appendChild(div);
            }
        });
        checklistForm.style.display = 'block';
    }
    </script>
</head>
<body>

 <header class="header-painel">
    <h1>Painel</h1>
    <ul class="main-menu">
        <li><a href="dashboard.php"><i class="fa-solid fa-clipboard-list"></i>Dashboard</a></li>
    </ul>
    <div class="user-info">
        <span><?=htmlspecialchars($u['nome'])?> (<?=htmlspecialchars($u['perfil'])?>)</span>
        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i>Sair</a>
    </div>
</header>
<ul class="main-menu">
    <?php if(isOperador() || isMaster()): ?>
        <li><a href="workplace.php"><i class="fa-solid fa-clipboard-list"></i>Workplace</a></li>
    <?php endif; ?>
    <?php if(isSupervisor() || isMaster()): ?>
        <li><a href="aprovacao.php"><i class="fa-solid fa-check-to-slot"></i>Aprovação</a></li>
        <li><a href="manutencao.php"><i class="fa-solid fa-wrench"></i>Manutenção</a></li>
    <?php endif; ?>
    <?php if(isMaster()): ?>
        <li><a href="admin.php"><i class="fa-solid fa-user-gear"></i>Administração</a></li>
    <?php endif; ?>
    <?php if(isMecanica()): ?>
        <li><a href="mecanica.php"><i class="fa-solid fa-wrench"></i>Mecânica</a></li>
    <?php endif; ?>
    <li><a href="imprimir.php"><i class="fa-solid fa-download"></i>Imprimir</a></li>
    <li><a href="download.php"><i class="fa-solid fa-wrench"></i>Dowload</a></li>
    <li><a href="dashboard.php"><i class="fa-solid fa-wrench"></i>Voltar</a></li>
</ul>
<main class="container">
    <h2>Checklist Pré-Operacional</h2>
    <?php if ($erro_checklist): ?>
        <div class="alert"><?= htmlspecialchars($erro_checklist) ?></div>
    <?php endif; ?>
    <form method="post" id="checklistForm">
        <div class="form-group">
            <label for="maquina">Máquina</label>
            <select id="maquina" name="maquina" onchange="showChecklist()">
                <option value="">Selecione uma máquina</option>
                <?php 
                $maquinas_disponiveis = array_filter($maquinas, function($r) use ($maqH) {
                    if (count($r) !== count($maqH)) return false;
                    $maq_data = array_combine($maqH, $r);
                    return $maq_data['status'] === 'disponivel';
                });
                foreach($maquinas_disponiveis as $r):
                    if (count($r) !== count($maqH)) continue;
                    $maq_data = array_combine($maqH, $r);
                    $selected = ($checklist_aberto && $checklist_aberto['id_maquina'] === $maq_data['id']) ? 'selected' : '';
                    ?>
                    <option value="<?= htmlspecialchars($maq_data['id']) ?>" data-tipo="<?= htmlspecialchars($maq_data['tipo']) ?>" <?= $selected ?>>
                        <?= htmlspecialchars($maq_data['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div id="checklist-form" class="checklist-form">
            <?php if ($checklist_aberto): ?>
                <h3>Fechamento de Turno</h3>
                <p>Fechando o turno para a máquina: <strong><?= htmlspecialchars($maquinas_map[$checklist_aberto['id_maquina']]['nome']) ?></strong></p>
                <input type="hidden" name="id_checklist_aberto" value="<?= htmlspecialchars($checklist_aberto['id']) ?>">
                <div class="form-group">
                    <label for="orimetro">Horímetro Final</label>
                    <input type="number" step="0.01" id="orimetro" name="orimetro" required>
                </div>
                <input type="hidden" name="action" value="fechar_turno">
                <button type="submit" class="btn-submit">Fechar Turno</button>
            <?php else: ?>
                <h3>Abertura de Turno</h3>
                <div class="form-group">
                    <label for="orimetro">Horímetro Inicial</label>
                    <input type="number" step="0.01" id="orimetro" name="orimetro" required>
                </div>
                <div id="questions"></div>
                <div class="form-group">
                    <label for="observacoes">Observações Gerais</label>
                    <textarea id="observacoes" name="observacoes" rows="4"></textarea>
                </div>
                <input type="hidden" name="action" value="abrir_turno">
                <button type="submit" class="btn-submit">Abrir Turno</button>
            <?php endif; ?>
        </div>
    </form>
</main>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const checklistForm = document.getElementById('checklistForm');
        const maquinaSelect = document.getElementById('maquina');
        
        // Show checklist if a machine is already selected (on page load)
        if (maquinaSelect.value !== "") {
            showChecklist();
        }

        function showChecklist() {
            const selectedOption = maquinaSelect.options[maquinaSelect.selectedIndex];
            const tipo = selectedOption.getAttribute('data-tipo');
            const questionsDiv = document.getElementById('questions');
            const checklistSection = document.getElementById('checklist-form');
            
            // Clear previous questions
            questionsDiv.innerHTML = '';
            
            // Only populate questions if opening a new checklist
            if (!"<?= $checklist_aberto ? 'true' : 'false' ?>") {
                fetch('data/perguntas.csv')
                    .then(response => response.text())
                    .then(text => {
                        const lines = text.split('\n').filter(line => line.trim() !== '');
                        const header = lines[0].split(',');
                        const rows = lines.slice(1).map(line => line.split(','));
                        
                        const perguntas = rows.filter(row => row[header.indexOf('tipo')] === tipo);
                        
                        perguntas.forEach(pergunta => {
                            const id = pergunta[header.indexOf('id')];
                            const texto = pergunta[header.indexOf('texto')];
                            
                            const questionHtml = `
                                <div class="form-group">
                                    <label>${texto}</label>
                                    <div class="radio-group">
                                        <label><input type="radio" name="respostas[${id}]" value="OK" required> OK</label>
                                        <label><input type="radio" name="respostas[${id}]" value="NC" required> NC</label>
                                        <label><input type="radio" name="respostas[${id}]" value="N/A" required> N/A</label>
                                    </div>
                                </div>
                            `;
                            questionsDiv.innerHTML += questionHtml;
                        });
                    });
            }
            
            checklistSection.classList.add('visible');
        }
    });
</script>
</body>
</html>
