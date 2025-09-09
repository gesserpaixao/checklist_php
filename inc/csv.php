<?php
function csvRead($path) {
    if (!file_exists($path)) return ['header' => [], 'rows' => []];
    $f = fopen($path, 'r');
    if (!$f) return ['header' => [], 'rows' => []];
    $header = fgetcsv($f);
    $rows = [];
    while (($row = fgetcsv($f)) !== false) {
        $rows[] = $row;
    }
    fclose($f);
    return ['header' => $header ?: [], 'rows' => $rows];
}
function csvWrite($path, $header, $rows) {
    $f = fopen($path, 'w');
    if (!$f) return false;
    fputcsv($f, $header);
    foreach ($rows as $row) {
        fputcsv($f, $row);
    }
    fclose($f);
    return true;
}
function csvAppend($path, $row) {
    $f = fopen($path, 'a');
    if (!$f) return false;
    fputcsv($f, $row);
    fclose($f);
    return true;
}

// function getLatestMaintenanceId($maquina_id, $manutencoes, $manutH) {
//     $latestId = null;
//     $latestDate = null;
//     foreach ($manutencoes as $m) {
//         // Verifica se a linha tem o mesmo número de colunas do cabeçalho
//         if (count($m) !== count($manutH)) continue;
        
//         $m_data = array_combine($manutH, $m);
//         if ($m_data['id_maquina'] === $maquina_id) {
//             $currentDate = new DateTime($m_data['data_inicio']);
//             if ($latestDate === null || $currentDate > $latestDate) {
//                 $latestDate = $currentDate;
//                 $latestId = $m_data['id'];
//             }
//         }
//     }
//     return $latestId;
// }
?>
