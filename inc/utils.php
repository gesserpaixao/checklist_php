<?php
// Formata a data para um formato mais legível
function formatDate($dateString) {
    if (empty($dateString)) {
        return 'N/A';
    }
    // Cria um objeto DateTime a partir da string de data
    $date = new DateTime($dateString);
    // Retorna a data formatada
    return $date->format('d/m/Y H:i');
}

/**
 * Pega o ID da última manutenção de uma máquina,
 * que ainda não foi concluída.
 * @param string $id_maquina O ID da máquina.
 * @param array $manutencoes Array com todas as manutenções.
 * @param array $manutH Array com o cabeçalho das manutenções.
 * @return string|null O ID da última manutenção ou null se não houver.
 */
function getLatestMaintenanceId($id_maquina, $manutencoes, $manutH) {
    $latest_id = null;
    $latest_date = null;

    if (is_array($manutencoes)) {
        foreach ($manutencoes as $m) {
            // Pular linhas que não têm o número correto de colunas
            if (count($m) !== count($manutH)) {
                continue;
            }
            $m_data = array_combine($manutH, $m);

            // Verifica se a manutenção é para a máquina correta e se não está concluída
            if ($m_data['id_maquina'] === $id_maquina && $m_data['status'] !== 'concluida') {
                $current_date = new DateTime($m_data['data_inicio']);
                if ($latest_date === null || $current_date > $latest_date) {
                    $latest_date = $current_date;
                    $latest_id = $m_data['id'];
                }
            }
        }
    }
    return $latest_id;
}


// Fun\u00e7\u00f5es auxiliares para uso em m\u00faltiplos scripts
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
    $formats = ['Y-m-d H:i:s', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s', 'Y-m-d', 'd/m/Y'];
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

?>
