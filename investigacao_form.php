<?php
// investigacao_form.php
declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/header.php';
require_once __DIR__ . '/inc/csv.php';
require_once __DIR__ . '/inc/utils.php';

requireLogin();

if (!isHse() && !isMaster()) {
    header('Location: dashboard.php');
    exit;
}

$u = currentUser();
$msg = '';

// Preenche os dados do formulário a partir dos parâmetros da URL, se existirem
$id_maquina_val = $_GET['id_maquina'] ?? '';
$id_checklist_val = $_GET['id_checklist'] ?? '';
$operador_val = $_GET['operador'] ?? '';
$supervisor_val = $_GET['supervisor'] ?? '';

// Lógica para lidar com a submissão do formulário de investigação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Coleta e sanitiza os dados do formulário
    $id_maquina = $_POST['id_maquina'] ?? '';
    $id_checklist = $_POST['id_checklist'] ?? '';
    $operador = $_POST['operador'] ?? '';
    $supervisor = $_POST['supervisor'] ?? '';
    $analise_hse = $_POST['analise_hse'] ?? '';
    $parecerhse = $_POST['parecerhse'] ?? '';
    $causa_raiz_raw = $_POST['causa_raiz'] ?? [];
    
    // 2. Cria um ID único e define a data
    $id = uniqid('inv_');
    $data_invest = date('c'); 
    $nome_investigador = $u['nome'] ?? 'N/D';

    // 3. Processa o JSON para a análise da causa raiz
    $causa_raiz_json = json_encode($causa_raiz_raw, JSON_UNESCAPED_UNICODE);

    // 4. Lógica para upload de evidências
    $uploadDir = __DIR__ . '/store/hse/';
    $evidencias_caminhos = [];

    // Cria a pasta de destino se não existir
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    if (isset($_FILES['evidencias']) && is_array($_FILES['evidencias']['tmp_name'])) {
        foreach ($_FILES['evidencias']['tmp_name'] as $key => $tmpName) {
            $fileName = $_FILES['evidencias']['name'][$key];
            $fileSize = $_FILES['evidencias']['size'][$key];
            $fileError = $_FILES['evidencias']['error'][$key];
            
            // Verifica se o arquivo foi enviado com sucesso e não excede 10MB
            if ($fileError === UPLOAD_ERR_OK && $fileSize <= 10485760) { // 10MB em bytes
                $extensao = pathinfo($fileName, PATHINFO_EXTENSION);
                $novoNome = uniqid('evidencia_') . '.' . $extensao;
                $caminhoCompleto = $uploadDir . $novoNome;

                if (move_uploaded_file($tmpName, $caminhoCompleto)) {
                    $evidencias_caminhos[] = 'store/hse/' . $novoNome; // Salva o caminho relativo
                }
            }
        }
    }
    $evidencias_json = json_encode($evidencias_caminhos, JSON_UNESCAPED_UNICODE);

    // 5. Prepara a nova linha para o CSV, agora incluindo as evidências
    $nova_investigacao = [
        'id'                => $id,
        'data_invest'       => $data_invest,
        'id_maquina'        => $id_maquina,
        'id_checklist'      => $id_checklist,
        'operador'          => $operador,
        'supervisor'        => $supervisor,
        'nome_investigador' => $nome_investigador,
        'causa_raiz'        => $causa_raiz_json,
        'analise_hse'       => $analise_hse,
        'parecerhse'        => $parecerhse,
        'aprovacao_parecer' => '',
        'data_aprovacao'    => '',
        'evidencias_path'   => $evidencias_json,
    ];

    // 6. Salva no CSV
    $csvFile = __DIR__ . '/data/invest_hse.csv';
    $csvData = csvRead($csvFile);
    if ($csvData === false || !isset($csvData['header'])) {
        $header = explode(',', 'id,data_invest,id_maquina,id_checklist,operador,supervisor,nome_investigador,causa_raiz,analise_hse,parecerhse,aprovacao_parecer,data_aprovacao,evidencias_path');
        $csvData = ['header' => $header, 'rows' => []];
    }

    $csvData['rows'][] = array_values($nova_investigacao);
    csvWrite($csvFile, $csvData['header'], $csvData['rows']);

    header('Location: investigacoes.php');
    exit;
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Lançar Investigação HSE</title>
    <link rel="stylesheet" href="assets/stylenew.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .invest-form label { display: block; margin-top: 10px; font-weight: bold; }
        .invest-form input[type="text"], .invest-form textarea { width: 100%; padding: 8px; box-sizing: border-box; }
        .invest-form textarea { min-height: 100px; }
        .invest-form button { margin-top: 20px; }
        .question-group { border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; border-radius: 8px; }
        .question-group h4 { margin-top: 0; }
        #causa-raiz-container .por-que-input { margin-bottom: 10px; }
        .add-btn { margin-top: 10px; }
    </style>
</head>
<body>

    <main class="containerMaior">
        <h2>Lançar Nova Investigação HSE</h2>
        <form class="invest-form" method="post" action="investigacao_form.php" enctype="multipart/form-data">
            <label for="id_maquina">ID da Máquina:</label>
            <input type="text" id="id_maquina" name="id_maquina" value="<?= htmlspecialchars($id_maquina_val) ?>" required>

            <label for="id_checklist">ID do Checklist/Manutenção Relacionado:</label>
            <input type="text" id="id_checklist" name="id_checklist" value="<?= htmlspecialchars($id_checklist_val) ?>" required>

            <label for="operador">Operador:</label>
            <input type="text" id="operador" name="operador" value="<?= htmlspecialchars($operador_val) ?>" required>
            
            <label for="supervisor">Supervisor:</label>
            <input type="text" id="supervisor" name="supervisor" value="<?= htmlspecialchars($supervisor_val) ?>" required>

            <div class="question-group">
                <h4>Análise da Causa Raiz (5 Por quês?)</h4>
                <div id="causa-raiz-container">
                    <p>Comece com o problema e clique para adicionar os "Por quês?".</p>
                </div>
                <button type="button" class="add-btn btn-success" onclick="addPorQue()">Adicionar Por quê?</button>
            </div>

            <label for="analise_hse">Análise do HSE:</label>
            <textarea id="analise_hse" name="analise_hse" required></textarea>

            <label for="parecerhse">Parecer do HSE:</label>
            <textarea id="parecerhse" name="parecerhse" required></textarea>

            <label>Evidências (Fotos):</label>
            <div class="file-upload-container">
                <input type="file" id="evidencias" name="evidencias[]" multiple accept="image/*">
                
                <label for="evidencias" class="custom-file-upload">
                    <i class="fa-solid fa-camera"></i> Escolher Arquivos
                </label>
                
                <span id="file-name">Nenhum arquivo selecionado</span>
            </div>
            <small>Tamanho máximo: 10MB por arquivo.</small>

       
            <button type="submit" class="fab-button button-encerrar">
                        <input type="hidden" name="action" value="abrir_turno">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="24" height="24">
                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                        </svg>
                    </button>



        </form>
    </main>

    <script>
        let pq_counter = 0;
        const container = document.getElementById('causa-raiz-container');

        function addPorQue() {
            pq_counter++;
            const newDiv = document.createElement('div');
            newDiv.className = 'por-que-input';
            
            const label = document.createElement('label');
            label.textContent = `Por quê? (Nível ${pq_counter}):`;
            
            const input = document.createElement('input');
            input.type = 'text';
            input.name = `causa_raiz[pq${pq_counter}]`;
            
            newDiv.appendChild(label);
            newDiv.appendChild(input);
            container.appendChild(newDiv);
        }

        const fileInput = document.getElementById('evidencias');
        const fileNameSpan = document.getElementById('file-name');

        fileInput.addEventListener('change', (event) => {
            const files = event.target.files;
            if (files.length > 0) {
                if (files.length === 1) {
                    fileNameSpan.textContent = files[0].name;
                } else {
                    fileNameSpan.textContent = `${files.length} arquivos selecionados`;
                }
            } else {
                fileNameSpan.textContent = 'Nenhum arquivo selecionado';
            }
        });
    </script>
</body>
</html>