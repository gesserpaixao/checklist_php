<?php
// compacta e força download dos CSVs
$files = ['data/usuarios.csv','data/maquinas.csv','data/checklists.csv','data/manutencoes.csv','data/perguntas.csv'];
$zipname = 'backup_'.date('Ymd_His').'.zip';
$zip = new ZipArchive();
$zip->open($zipname, ZipArchive::CREATE);
foreach($files as $f){ if(file_exists($f)) $zip->addFile($f, basename($f)); }
$zip->close();
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.$zipname.'"');
readfile($zipname); unlink($zipname); exit;
