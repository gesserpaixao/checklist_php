<?php

// workplace.php

declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/inc/auth.php';
requireLogin();

// Usuário logado
$u = currentUser();

require_once __DIR__ . '/inc/csv.php';
require_once __DIR__ . '/inc/utils.php';

// Leitura inicial de dados (fora dos blocos de requisição)
$maqCsv = csvRead(__DIR__ . '/data/maquinas.csv');
$maquinas = $maqCsv['rows'] ?? [];
$maqH = $maqCsv['header'] ?? [];

$perguntaCsv = csvRead(__DIR__ . '/data/perguntas.csv');
$perguntas = $perguntaCsv['rows'] ?? [];
$perguntaH = $perguntaCsv['header'] ?? [];

$manutencaoCsv = csvRead(__DIR__ . '/data/manutencoes.csv');
$manutencoes = $manutencaoCsv['rows'] ?? [];
$manutH = $manutencaoCsv['header'] ?? [];

$checklistsCsv = csvRead(__DIR__ . '/data/checklists.csv');
$checklists = $checklistsCsv['rows'] ?? [];
$checklistH = $checklistsCsv['header'] ?? [];

// Garante que o campo 'check_fechado_por' exista no cabeçalho
if (!in_array('check_fechado_por', $checklistH)) {
    $checklistH[] = 'check_fechado_por';
}

$erro_checklist = '';

$maquinas_map = [];
if (is_array($maquinas)) {
    foreach ($maquinas as $r) {
        if (count($r) === count($maqH)) {
            $maq = array_combine($maqH, $r);
            $maquinas_map[$maq['id']] = $maq;
        }
    }
}

// ---
// Lógica para carregar as perguntas via requisição AJAX (DEVE ESTAR ANTES DE QUALQUER SAÍDA)
// ---
if (isset($_GET['action']) && $_GET['action'] === 'get_perguntas' && isset($_GET['tipo_maquina'])) {
    header('Content-Type: application/json');
    $tipo_maquina = $_GET['tipo_maquina'];
    
    $perguntas_filtradas = [];
    foreach ($perguntas as $p) {
        if (count($p) === count($perguntaH)) {
            $pergunta_data = array_combine($perguntaH, $p);
            if ($pergunta_data['tipo_maquina'] === 'geral' || $pergunta_data['tipo_maquina'] === $tipo_maquina) {
                $perguntas_filtradas[] = $pergunta_data;
            }
        }
    }
    echo json_encode($perguntas_filtradas);
    exit;
}

// ---
// Lógica para enviar a maquina para manutencao (DEVE ESTAR ANTES DE QUALQUER SAÍDA)
// ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enviar_manutencao') {
    $id_maquina = $_POST['id_maquina'] ?? null;
    $obs_manut = $_POST['obs_manut'] ?? '';
    
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

    $novo_registro_manut = [
        uniqid('m_'),
        $id_maquina,
        date('c'),
        '',
        $u['nome'],
        'pendente',
        $obs_manut,
        ''
    ];
    csvAppend(__DIR__.'/data/manutencoes.csv', $novo_registro_manut);

    header('Location: workplace.php');
    exit;
}

// ---
// Lógica para salvar o checklist (Fechamento/Abertura) (DEVE ESTAR ANTES DE QUALQUER SAÍDA)
// ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    $id_maquina = $_POST['maquina'] ?? null;
    $orimetro = $_POST['orimetro'] ?? '';
    $observacoes = $_POST['observacoes'] ?? '';
    $respostas_raw = $_POST['respostas'] ?? [];
    $id_checklist_aberto = $_POST['id_checklist_aberto'] ?? '';
    $turno_selecionado = $_POST['turno'] ?? '';
    $action = $_POST['action'];

    // Lógica para Abertura
    if ($action === 'abrir_turno') {
        $respostas_serializadas = json_encode($respostas_raw);
        $falhas = count(array_filter($respostas_raw, function($r) { return $r !== 'OK' && $r !== 'N/A'; }));
        $setor = $maquinas_map[$id_maquina]['setor'] ?? '';

        $novo_registro_data = [
            'id' => uniqid('c_'),
            'operador' => $u['nome'],
            'id_maquina' => $id_maquina,
            'data_abertura' => date('c'),
            'data_fechamento' => '',
            'turno' => $turno_selecionado,
            'status_checklist' => 'pendente',
            'obs' => $observacoes,
            'respostas_json' => $respostas_serializadas,
            'falhas' => $falhas,
            'orimetro_inicial' => $orimetro,
            'orimetro_final' => '',
            'tipo_checklist' => 'abertura',
            'id_manutencao' => '',
            'aprovado_por' => '',
            'setor' => $setor,
            'check_aprovado_por' => '',
            'check_fechado_por' => '',
        ];

        // Mapeia o novo registro para a ordem do cabeçalho
        $novo_registro = [];
        foreach ($checklistH as $header_field) {
            $novo_registro[] = $novo_registro_data[$header_field] ?? '';
        }
        
        csvAppend(__DIR__.'/data/checklists.csv', $novo_registro);
        
        header('Location: workplace.php');
        exit;
        
    } elseif ($action === 'fechar_turno') {
        $checklist_encontrado = false;
        foreach ($checklists as &$c) {
            if (count($c) !== count($checklistH)) continue;
            $c_data = array_combine($checklistH, $c);
            if (($c_data['id'] ?? '') === $id_checklist_aberto) {
                // Valida o turno
                if (($c_data['turno'] ?? '') !== $turno_selecionado) {
                    $erro_checklist = 'Este checklist foi aberto no turno ' . htmlspecialchars($c_data['turno']) . '. Não é possível fechá-lo no turno ' . htmlspecialchars($turno_selecionado) . '.';
                    break;
                }

                // Atualiza os dados no array original
                $obs_antiga = $c_data['obs'] ?? '';
                $nova_obs_completa = trim($obs_antiga . ' - ' . $observacoes, ' -'); // Concatena e limpa espaços e hífen extra
                
                $c_data['orimetro_final'] = $orimetro;
                $c_data['data_fechamento'] = date('c');
                $c_data['status_checklist'] = 'pendente';
                $c_data['tipo_checklist'] = 'fechamento';
                $c_data['check_fechado_por'] = $u['nome'];
                $c_data['respostas_json'] = ''; // Limpa as respostas antigas, conforme a nova lógica
                $c_data['falhas'] = 0; // Zera as falhas
                $c_data['obs'] = $nova_obs_completa; // Atualiza a observação
                
                // Mapeia os dados atualizados para a ordem da linha no CSV
                $c = array_values($c_data);
                
                $checklist_encontrado = true;
                break;
            }
        }
        
        if ($checklist_encontrado) {
            csvWrite(__DIR__.'/data/checklists.csv', $checklistH, $checklists);
            header('Location: dashboard.php' . $id_checklist_aberto); //imprimir_checklist.php?checklist_id=
            exit;
        } else {
            $erro_checklist = 'Não foi possível encontrar ou fechar o checklist. O checklist pode não estar mais aberto.';
        }
    }
}

// ---
// Lógica de verificação para o carregamento inicial da página (GET)
// ---
$maquina_selecionada_id = $_GET['maquina'] ?? '';
$tipo_form = 'abertura';
$id_checklist_aberto = '';
$ultimo_checklist = null;

if ($maquina_selecionada_id) {
    $checklists_para_maquina = [];
    foreach ($checklists as $c) {
        if (count($c) !== count($checklistH)) continue;
        $c_data = array_combine($checklistH, $c);
        if (($c_data['id_maquina'] ?? '') === $maquina_selecionada_id) {
            $checklists_para_maquina[] = $c_data;
        }
    }
    
    usort($checklists_para_maquina, function($a, $b) {
        return strtotime($b['data_abertura']) <=> strtotime($a['data_abertura']);
    });

    $ultimo_checklist = $checklists_para_maquina[0] ?? null;

    if ($ultimo_checklist) {
        // CORREÇÃO: Usa 'tipo_checklist' e 'data_fechamento' para determinar o formulário
        if (($ultimo_checklist['tipo_checklist'] ?? '') === 'abertura' && empty($ultimo_checklist['data_fechamento'] ?? '')) {
            $tipo_form = 'fechamento';
            $id_checklist_aberto = $ultimo_checklist['id'] ?? '';
        } else {
            $tipo_form = 'abertura';
        }
    } else {
        $tipo_form = 'abertura';
    }
}


// ---
// A PARTIR DAQUI, É SEGURO INSERIR O HTML
// ---
require_once __DIR__ . '/inc/header.php';
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
        #checklist-form {
            display: none;
        }
        #checklist-form.visible {
            display: block;
        }
        .fab-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            background-color: #3f51b5;
            color: white;
            border: none;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            transition: background-color 0.3s, transform 0.2s;
        }
        .fab-button:hover {
            background-color: #303f9f;
            transform: scale(1.05);
        }
        .fab-button svg {
            width: 24px;
            height: 24px;
        }
        .pending-message {
            color: #ff9800;
            font-weight: bold;
            margin-top: 10px;
        }
        .warning-message {
            color: #d32f2f;
            font-weight: bold;
            margin-top: 10px;
        }

        .header-logo {
            height: 40px; /* Define a altura da imagem. Ajuste conforme necessário */
            
            
        }
         .instrucao-selecao {
            text-align: center;
            margin-top: 20px;
            font-size: 1.1em;
            color: #555;
            font-style: italic;
        }

    </style>
</head>

<body>

    

    <main class="container">
        <h2>Checklist Pré-Operacional</h2>
        <?php if ($erro_checklist): ?>
            <div class="alert"><?= htmlspecialchars($erro_checklist) ?></div>
        <?php endif; ?>

        <form method="post" id="checklistForm">
            <div class="form-group">
                <label for="maquina">Máquina</label>
                <select id="maquina" name="maquina">
                    <option value="">Selecione uma máquina</option>
                    <?php 
                    $maquinas_status = [];
                    $checklists_agrupados = [];
                    foreach ($checklists as $c) {
                        if (count($c) !== count($checklistH)) continue;
                        $c_data = array_combine($checklistH, $c);
                        $maquina_id = $c_data['id_maquina'] ?? '';
                        if (!isset($checklists_agrupados[$maquina_id])) {
                            $checklists_agrupados[$maquina_id] = [];
                        }
                        $checklists_agrupados[$maquina_id][] = $c_data;
                    }
                    foreach ($checklists_agrupados as $maquina_id => $entries) {
                        usort($entries, function($a, $b) {
                            return strtotime($b['data_abertura']) <=> strtotime($a['data_abertura']);
                        });
                        $ultimo = $entries[0];
                        $maquinas_status[$maquina_id] = $ultimo;
                    }

                    foreach($maquinas as $r):
                        if (count($r) !== count($maqH)) continue;
                        $maq_data = array_combine($maqH, $r);
                        $maq_id = $maq_data['id'] ?? '';
                        $maq_nome = $maq_data['nome'] ?? '';
                        $maq_tipo = $maq_data['tipo'] ?? '';
                        $maq_status = $maq_data['status'] ?? 'disponivel';
                        $display_name = htmlspecialchars($maq_nome) . ' - ' . htmlspecialchars($maq_tipo);
                        $disabled = '';
                        $status_info = $maquinas_status[$maq_id] ?? null;

                        if ($status_info) {
                            if ($status_info['status_checklist'] === 'aprovado') {
                                // Máquina está disponível para novo turno
                                $display_name .= ' (Disponível)';
                            } elseif ($status_info['status_checklist'] === 'pendente' || ($status_info['tipo_checklist'] ?? '') === 'abertura' && empty($status_info['data_fechamento'] ?? '')) {
                                // Checklist aberto ou aguardando aprovação
                                $display_name .= ' (Aguardando Fechamento ou Aprovação)';
                                if ($ultimo_checklist['id_maquina'] === $maq_id && $tipo_form === 'fechamento') {
                                    $disabled = '';
                                } else {
                                    $disabled = 'disabled';
                                }
                            } elseif (($status_info['tipo_checklist'] ?? '') === 'abertura' && empty($status_info['data_fechamento'] ?? '')) {
                                $display_name .= ' (Aberto)';
                            }
                        }
                        if ($maq_status !== 'disponivel') {
                            $display_name .= ' (' . htmlspecialchars($maq_status) . ')';
                            $disabled = 'disabled';
                        }
                        
                        $selected = ($maquina_selecionada_id === $maq_id) ? 'selected' : '';
                        
                        ?>
                        <option class="letramaiuscula" value="<?= htmlspecialchars($maq_id) ?>" data-tipo="<?= htmlspecialchars($maq_tipo) ?>" data-nome="<?= htmlspecialchars($maq_nome) ?>" <?= $selected ?> <?= $disabled ?>>
                            <?= $display_name ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php if (empty($maquina_selecionada_id)): ?>
                <p class="instrucao-selecao">Por favor, selecione uma máquina para iniciar o checklist.</p>
            <?php endif; ?>

            <div id="checklist-form" class="checklist-form" style="display: <?= $maquina_selecionada_id ? 'block' : 'none' ?>;">
                <?php if ($tipo_form === 'fechamento'): ?>
                    <h3 id="fechamento-titulo">Fechamento de Turno - 
                    <?= htmlspecialchars($maquinas_map[$maquina_selecionada_id]['nome'] ?? 'N/A') ?>
                    </h3>
                    <div class="form-group">
                        <label for="turno">Turno</label>
                        <input type="text" id="turno" name="turno" value="<?= htmlspecialchars($ultimo_checklist['turno'] ?? '') ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="orimetro">Horímetro Final</label>
                        <input type="number" step="0.01" id="orimetro" name="orimetro" required>
                    </div>
                    <input type="hidden" name="id_checklist_aberto" value="<?= htmlspecialchars($id_checklist_aberto) ?>">
                    <input type="hidden" name="maquina" value="<?= htmlspecialchars($maquina_selecionada_id) ?>">
                    <div class="form-group">
                        <label for="observacoes">Observações Gerais</label>
                        <textarea id="observacoes" name="observacoes" rows="4"></textarea>
                    </div>
                    <button type="submit" class="fab-button">
                        <input type="hidden" name="action" value="fechar_turno">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="24" height="24">
                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                        </svg>
                    </button>
                <?php else: ?>
                    <h3 id="abertura-titulo">Abertura de Turno - <?= htmlspecialchars($maquinas_map[$maquina_selecionada_id]['nome'] ?? 'N/A') ?></h3>
                    <div class="form-group">
                        <label for="orimetro">Horímetro Inicial</label>
                        <input type="number" step="0.01" id="orimetro" name="orimetro" required>
                    </div>
                    <div class="form-group">
                        <label for="turno">Turno</label>
                        <select id="turno" name="turno" required>
                            <option value="">Selecione o turno</option>
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="C">C</option>
                        </select>
                    </div>
                    <input type="hidden" name="maquina" value="<?= htmlspecialchars($maquina_selecionada_id) ?>">
                    <div id="questions"></div>
                    <div class="form-group">
                        <label for="observacoes">Observações Gerais</label>
                        <textarea id="observacoes" name="observacoes" rows="4"></textarea>
                    </div>
                    <button type="submit" class="fab-button">
                        <input type="hidden" name="action" value="abrir_turno">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="24" height="24">
                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                        </svg>
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            const maquinaSelect = document.getElementById('maquina');
            const questionsDiv = document.getElementById('questions');

            maquinaSelect.addEventListener('change', async () => {
                const selectedOption = maquinaSelect.options[maquinaSelect.selectedIndex];
                const maquinaId = selectedOption.value;
                
                if (maquinaId) {
                    window.location.href = `workplace.php?maquina=${maquinaId}`;
                } else {
                    window.location.href = `workplace.php`;
                }
            });

            const maquinaIdInicial = maquinaSelect.value;
            if (maquinaIdInicial && !document.querySelector('.alert')) {
                const selectedOption = maquinaSelect.options[maquinaSelect.selectedIndex];
                const tipo = selectedOption.getAttribute('data-tipo');
                const nome = selectedOption.getAttribute('data-nome');

                questionsDiv.innerHTML = ''; 
                
                // CORREÇÃO: Carrega as perguntas apenas para o formulário de abertura
                if ("<?= $tipo_form ?>" === "abertura") {
                    try {
                        const response = await fetch(`workplace.php?action=get_perguntas&tipo_maquina=${tipo}`);
                        if (!response.ok) {
                            throw new Error('Erro ao carregar as perguntas.');
                        }
                        const perguntas = await response.json();
                        
                        perguntas.forEach(pergunta => {
                            const questionHtml = `
                                <div class="form-group">
                                    <label>${pergunta.label}</label>
                                    <div class="radio-group">
                                        <label><input type="radio" name="respostas[${pergunta.chave}]" value="OK" required> OK</label>
                                        <label><input type="radio" name="respostas[${pergunta.chave}]" value="NC" required> NC</label>
                                        <label><input type="radio" name="respostas[${pergunta.chave}]" value="N/A" required> N/A</label>
                                    </div>
                                </div>`;
                            questionsDiv.innerHTML += questionHtml;
                        });
                    } catch (error) {
                        console.error('Erro:', error);
                        questionsDiv.innerHTML = '<p>Não foi possível carregar as perguntas.</p>';
                    }
                }
            }
        });
    </script>
</body>
</html>