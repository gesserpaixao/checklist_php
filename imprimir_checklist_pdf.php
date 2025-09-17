<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/inc/auth.php';
requireLogin();

use Dompdf\Dompdf;

// captura o mesmo HTML do imprimir_checklist.php
ob_start();
include __DIR__ . '/imprimir_checklist.php';
$html = ob_get_clean();

// gerar PDF
$dompdf = new Dompdf([
    'defaultFont' => 'DejaVu Sans', // suporta acentos
    'isRemoteEnabled' => true
]);

$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait'); // pode trocar pra 'landscape'
$dompdf->render();

// nome do arquivo
$filename = "checklist_" . ($checklist['id'] ?? 'semid') . ".pdf";

// força download
$dompdf->stream($filename, ["Attachment" => true]);
exit;
