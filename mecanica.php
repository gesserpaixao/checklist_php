<?php
require_once __DIR__.'/inc/auth.php';
requireLogin();
$u = currentUser();

// Permissão para acessar a página
if(!isMecanica() && !isMaster()) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__.'/inc/csv.php';

// Leitura dos dados das máquinas
$maqCsv = csvRead(__DIR__.'/data/maquinas.csv');
$maquinas = $maqCsv['rows'] ?? [];
$maqH = $maqCsv['header'] ?? [];

// Mapear dados das máquinas para facilitar o acesso
$maquinas_map = [];
foreach ($maquinas as $r) {
    if (count($r) === count($maqH)) {
        $maq = array_combine($maqH, $r);
        $maquinas_map[$maq['id']] = $maq;
    }
}

// Leitura dos dados de manutenções
$manutCsv = csvRead(__DIR__.'/data/manutencoes.csv');
$manutencoes = $manutCsv['rows'] ?? [];
$manutH = $manutCsv['header'] ?? [];

// Lógica para processar as ações do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
        $id_manutencao = $_POST['id_manutencao'];
        $novo_status = $_POST['status'];
        $mecanico = $u['nome'];

        foreach ($manutencoes as &$manut) {
            if (count($manut) !== count($manutH)) {
                continue; // Pula a linha se o número de colunas estiver incorreto
            }
            $manut_data = array_combine($manutH, $manut);
            if ($manut_data['id'] === $id_manutencao) {
                $manut_data['status'] = $novo_status;
                $manut_data['mecanico'] = $mecanico;
                $manut = array_values($manut_data);
                break;
            }
        }
        csvWrite(__DIR__.'/data/manutencoes.csv', $manutH, $manutencoes);
        header('Location: mecanica.php');
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'concluir_manutencao') {
        $id_manutencao = $_POST['id_manutencao'];
        $descricao_manutencao = $_POST['descricao_manutencao'] ?? '';
        $mecanico = $u['nome'];

        $id_maquina_relacionada = '';
        foreach ($manutencoes as &$manut) {
            if (count($manut) !== count($manutH)) {
                continue; // Pula a linha se o número de colunas estiver incorreto
            }
            $manut_data = array_combine($manutH, $manut);
            if ($manut_data['id'] === $id_manutencao) {
                $manut_data['status'] = 'concluida';
                $manut_data['data_fim'] = date('c');
                $manut_data['descricao_manutencao'] = $descricao_manutencao;
                $manut_data['mecanico'] = $mecanico;
                $id_maquina_relacionada = $manut_data['id_maquina'];
                $manut = array_values($manut_data);
                break;
            }
        }
        csvWrite(__DIR__.'/data/manutencoes.csv', $manutH, $manutencoes);

        // Lógica para atualizar o status da máquina para "disponivel"
        if (!empty($id_maquina_relacionada)) {
            $maquinas_updated = $maquinas;
            foreach ($maquinas_updated as &$maq_row) {
                if (count($maq_row) === count($maqH)) {
                    $maq_data = array_combine($maqH, $maq_row);
                    if ($maq_data['id'] === $id_maquina_relacionada) {
                        $maq_data['status'] = 'disponivel';
                        $maq_row = array_values($maq_data);
                        break;
                    }
                }
            }
            csvWrite(__DIR__.'/data/maquinas.csv', $maqH, $maquinas_updated);
        }

        header('Location: mecanica.php');
        exit;
    }
}

// Filtrar as manutenções abertas
$manutencoes_abertas = array_filter($manutencoes, function($manut) use ($manutH) {
    if (count($manut) !== count($manutH)) {
        return false;
    }
    $manut_data = array_combine($manutH, $manut);
    return ($manut_data['status'] !== 'concluida' && $manut_data['status'] !== 'liberada');
});

// Ordenar as manutenções abertas por data de início, da mais recente para a mais antiga
usort($manutencoes_abertas, function($a, $b) use ($manutH) {
    if (count($a) !== count($manutH) || count($b) !== count($manutH)) {
        return 0;
    }
    $a_data = array_combine($manutH, $a);
    $b_data = array_combine($manutH, $b);
    return strtotime($b_data['data_inicio']) - strtotime($a_data['data_inicio']);
});

// Filtrar para manter apenas a mais recente por máquina
$manutencoes_filtradas = [];
$maquinas_ja_processadas = [];
foreach ($manutencoes_abertas as $manut) {
    if (count($manut) !== count($manutH)) {
        continue;
    }
    $manut_data = array_combine($manutH, $manut);
    $id_maquina = $manut_data['id_maquina'];
    if (!in_array($id_maquina, $maquinas_ja_processadas)) {
        $manutencoes_filtradas[] = $manut;
        $maquinas_ja_processadas[] = $id_maquina;
    }
}
?>

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Mecânica</title>
    <link rel="stylesheet" href="assets/stylenew.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        <h2>Painel de Mecânica</h2>
        <table>
            <thead>
                <tr>
                    <th>ID Manutenção</th>
                    <th>Máquina</th>
                    <th>Problema</th>
                    <th>Entrada</th>
                    <th>Status</th>
                    <th>Descrição da Manutenção</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php 
            foreach ($manutencoes_filtradas as $manut): 
                $manut_data = array_combine($manutH, $manut);
                $maquina_info = $maquinas_map[$manut_data['id_maquina']] ?? ['nome' => 'N/A'];
            ?>
            <tr>
                <td><?= htmlspecialchars($manut_data['id']) ?></td>
                <td><?= htmlspecialchars($maquina_info['nome']) ?></td>
                <td><?= htmlspecialchars($manut_data['descricao_problema'] ?? '') ?></td>
                <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($manut_data['entrada']))) ?></td>
                <td>
                    <form method="post">
                        <input type="hidden" name="id_manutencao" value="<?= htmlspecialchars($manut_data['id']) ?>">
                        <input type="hidden" name="action" value="update_status">
                        <select name="status" onchange="this.form.submit()">
                            <option value="em_reparo" <?= ($manut_data['status'] ?? '') === 'em_reparo'?'selected':'' ?>>Em Reparo</option>
                            <option value="aguardando_pecas" <?= ($manut_data['status'] ?? '') === 'aguardando_pecas'?'selected':'' ?>>Aguardando Peças</option>
                        </select>
                    </form>
                </td>
                <td class="descricao-cell">
                    <form method="post">
                        <input type="hidden" name="id_manutencao" value="<?= htmlspecialchars($manut_data['id']) ?>">
                        <input type="hidden" name="action" value="concluir_manutencao">
                        <textarea name="descricao_manutencao" placeholder="Descrição da manutenção" required><?= htmlspecialchars($manut_data['descricao_manutencao'] ?? '') ?></textarea>
                        <button type="submit">Concluir Manutenção</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</body>
</html>
