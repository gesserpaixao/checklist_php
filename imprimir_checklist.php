<?php
if (!isset($_GET['checklist_id'])) {
    die("ID do checklist não fornecido.");
}

$checklistId = $_GET['checklist_id'];
$arquivo = __DIR__ . "/checklists.csv";

if (!file_exists($arquivo)) {
    die("Arquivo de checklists não encontrado.");
}

$fp = fopen($arquivo, "r");
$cabecalho = fgetcsv($fp);
$checklist = null;

while (($linha = fgetcsv($fp)) !== false) {
    $dados = array_combine($cabecalho, $linha);

    // Aqui fazemos a comparação com a coluna "id" (aquela string tipo c_68b1cc96da2e9)
    if ($dados['id'] === $checklistId) {
        $checklist = $dados;
        break;
    }
}
fclose($fp);

if (!$checklist) {
    die("Checklist não encontrado.");
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Imprimir Checklist</title>
</head>
<body>
    <h2>Checklist #<?= htmlspecialchars($checklist['id']) ?></h2>
    <p><b>Operador:</b> <?= htmlspecialchars($checklist['operador']) ?></p>
    <p><b>Máquina:</b> <?= htmlspecialchars($checklist['id_maquina']) ?></p>
    <p><b>Data Abertura:</b> <?= htmlspecialchars($checklist['data_abertura']) ?></p>
    <p><b>Status:</b> <?= htmlspecialchars($checklist['status_checklist']) ?></p>
    <p><b>Observações:</b> <?= htmlspecialchars($checklist['obs']) ?></p>

    <button onclick="window.print()">🖨️ Imprimir</button>
</body>
</html>
