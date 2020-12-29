<?php
/** Archivo que permite mover los archivos a la carpeta de exportados */


ini_set('display_errors', 1);

$response = array();
$succes = false;
$year = date("Y");
$month = date("m");
$day = date("d");
$hoy = $_POST['hoy'] != "" ? $_POST['hoy'] : $day . $month . $year;

$urlsMv = $_POST['urls'];


$path = "/home/reportes/$hoy/exportados";
if (!is_dir($path)) {
    mkdir($path, 0777, true);
}

foreach ($urlsMv as $url) {
    $urlTrimed = trim($url, "/home/reportes/$hoy/");
    rename($url, "$path/$urlTrimed");
}


$response = array(
    "success" => true,
    "msg" => "Documentos Archivados correctamente <br>",
);

print_r(json_encode($response));