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
?>
