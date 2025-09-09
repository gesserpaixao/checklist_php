<?php
require_once __DIR__ . '/inc/auth.php';
requireLogin();

require_once __DIR__ . '/inc/csv.php';
require_once __DIR__ . '/inc/utils.php';

$checklist_id = $_GET['checklist_id'] ?? ($_GET['id'] ?? null);


if (!$checklist_id) {
    echo "ID do checklist não fornecido.";
    exit;
}

// Carregar os dados de checklists e manutenções
$checklistsCsv = csvRead(__DIR__ . '/data/checklists.csv');
$checklists_data = $checklistsCsv['rows'] ?? [];
$checklistH = $checklistsCsv['header'] ?? [];

$manutencoesCsv = csvRead(__DIR__ . '/data/manutencoes.csv');
$manutencoes_data = $manutencoesCsv['rows'] ?? [];
$manutencaoH = $manutencoesCsv['header'] ?? [];

// Procurar o checklist com o ID fornecido
$checklist = null;
if (is_array($checklists_data)) {
   foreach ($checklists_data as $row) {
        if (count($row) === count($checklistH)) {
            $assoc = array_combine($checklistH, $row);
            if (($assoc['id'] ?? null) == $checklist_id) {
                $checklist = $assoc;
                break;
            }
        }
    }

}

if (!$checklist) {
    echo "Checklist não encontrado.";
    exit;
}

// Procurar a manutenção associada ao checklist
$manutencao = null;
if (is_array($manutencoes_data)) {
    foreach ($manutencoes_data as $row) {
        if (count($row) === count($manutencaoH) && $row[0] === $checklist['id_manutencao']) {
            $manutencao = array_combine($manutencaoH, $row);
            break;
        }
    }
}

// Carregar informações da máquina
$maquinasCsv = csvRead(__DIR__ . '/data/maquinas.csv');
$maquinas_data = $maquinasCsv['rows'] ?? [];
$maqH = $maquinasCsv['header'] ?? [];

$maquina = null;
if (is_array($maquinas_data)) {
    foreach ($maquinas_data as $row) {
        if (count($row) === count($maqH) && $row[0] === $checklist['id_maquina']) {
            $maquina = array_combine($maqH, $row);
            break;
        }
    }
}

// Carregar informações do operador
$usuariosCsv = csvRead(__DIR__ . '/data/usuarios.csv');
$usuarios_data = $usuariosCsv['rows'] ?? [];
$usuarioH = $usuariosCsv['header'] ?? [];

$operador = null;
if (is_array($usuarios_data)) {
    foreach ($usuarios_data as $row) {
        if (count($row) === count($usuarioH) && $row[0] === $checklist['operador']) {
            $operador = array_combine($usuarioH, $row);
            break;
        }
    }
}

// Decodificar os dados JSON
$checklist_items = json_decode($checklist['respostas_json'], true) ?? [];

?>

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Imprimir Checklist</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .print-container { width: 80%; margin: 0 auto; border: 1px solid #000; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        h1 { margin: 0; }
        .info-section { margin-bottom: 20px; }
        .info-section p { margin: 5px 0; }
        .info-section span { font-weight: bold; }
        .checklist-items { border-collapse: collapse; width: 100%; }
        .checklist-items th, .checklist-items td { border: 1px solid #000; padding: 8px; text-align: left; }
        .checklist-items th { background-color: #f2f2f2; }
    </style>
</head>
<body>

<div class="print-container">
    <div class="header">
        <h1>Relatório de Checklist</h1>
    </div>

    <div class="info-section">
        <p><span>ID do Checklist:</span> <?= htmlspecialchars($checklist['id'] ?? 'N/A') ?></p>
        <p><span>Data de Abertura:</span> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($checklist['data_abertura'] ?? 'N/A'))) ?></p>
        <p><span>Máquina:</span> <?= htmlspecialchars($maquina['nome'] ?? 'N/A') ?> (ID: <?= htmlspecialchars($maquina['id'] ?? 'N/A') ?>)</p>
        <p><span>Operador:</span> <?= htmlspecialchars($operador['nome'] ?? 'N/A') ?></p>
        <p><span>Status:</span> <?= htmlspecialchars($checklist['status_checklist'] ?? 'N/A') ?></p>
    </div>

    <div class="info-section">
        <h3>Itens do Checklist</h3>
        <table class="checklist-items">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Resposta</th>
                    <th>Observação</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($checklist_items)): ?>
                    <?php foreach ($checklist_items as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['item'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($item['resposta'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($item['obs'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3">Nenhum item de checklist encontrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($manutencao): ?>
    <div class="info-section">
        <h3>Detalhes da Manutenção Associada</h3>
        <p><span>ID da Manutenção:</span> <?= htmlspecialchars($manutencao['id'] ?? 'N/A') ?></p>
        <p><span>Status da Manutenção:</span> <?= htmlspecialchars($manutencao['status'] ?? 'N/A') ?></p>
        <p><span>Descrição do Problema:</span> <?= htmlspecialchars($manutencao['descricao_problema'] ?? 'N/A') ?></p>
        <p><span>Observações:</span> <?= htmlspecialchars($manutencao['obs'] ?? 'N/A') ?></p>
    </div>
    <?php endif; ?>
    
</div>
</body>
</html>