<?php

require '../vendor/autoload.php';
require 'connection.php';

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

ini_set('display_errors', 1);
date_default_timezone_set('America/Bogota');

$exportar = false;
$year = date("Y");
$month = date("m");
$day = date("d");
$hoy = $_POST['hoy'] != "" ? $_POST['hoy'] : $day . $month . $year;
$response = array();

if (!file_exists("/home/reportes/$hoy")) {
    $response['success'] = false;
    $response['error'] = "No se encuentra carpeta para el dia $hoy";
    print_r(json_encode($response));
    exit;
}


//Buscar los archivos
$sabreFile = "All Reports"; //nombres mediante el cual comparar
$galileoFile = "ETrackerReport";
$kiuFile = "REPORT_SALES";
$amadeusFile = "SalesReportTJQ";



$globSabre = glob("/home/reportes/$hoy/$sabreFile*");
$globGalileo = glob("/home/reportes/$hoy/$galileoFile*");
$globKiu = glob("/home/reportes/$hoy/$kiuFile*");
$globAmadeus = glob("/home/reportes/$hoy/$amadeusFile*");

if (!$globKiu && !$globAmadeus && !$globGalileo && !$globSabre) {
    $response['error'] = "No se encuentran archivos para cargar el dia $hoy";
    print_r(json_encode($response));
    exit;
}

$connInfo = getConnection();

$conn = sqlsrv_connect($connInfo[0], $connInfo[1]);
 /** Carga en BD */
//Servidor de Pruebas
// $serverName = "192.168.1.131\SQLEXPRESS, 1433"; //serverName\instanceName
// $connectionInfo = array("Database" => "Backoffice", "UID" => "sa", "PWD" => "Sistemas1");

// //Servidor de Produccion
// // $serverName = "192.168.1.6\integra2"; //serverName\instanceName
// // $connectionInfo = array("Database" => "BackOffice", "UID" => "sa", "PWD" => "Jetours_123");

// $conn = sqlsrv_connect($serverName, $connectionInfo);

if ($conn === false) {
    $response['success'] = false;
    $response['error'] = "Error de conexion con la Base de Datos";
    $response['sqlerror'] = sqlsrv_errors();
    print_r(json_encode($response));
    exit;
}

$urls = array(
    "Sabre" => array(),
    "Amadeus" => array(),
    "Galileo" => array(),
    "Kiu" => array(),
);

$urlsMv = array();

$msg = "Se encuentran los siguientes archivos: <br>";

foreach ($globSabre as $url) {
    $trimedUrl = trim($url, "/home/reportes/$hoy");
    $msg .= "$trimedUrl ";
    $isValid = validateSabreFile($url, $conn);
    if ($isValid['isValid']) {
        $urls["Sabre"][] = $url;
        $exportar = true;
    } else {
        $urlsMv[] = $url;
        $msg .= $isValid['msg'];
    }
    $msg .= "<br>";
}

foreach ($globAmadeus as $url) {
    $trimedUrl = trim($url, "/home/reportes/$hoy");
    $msg .= "$trimedUrl ";
    $isValid = validateAmadeusFile($url, $conn);
    if ($isValid['isValid']) {
        $urls["Amadeus"][] = $url;
        $exportar = true;
    } else {
        $urlsMv[] = $url;
        $msg .= $isValid['msg'];
    }
    $msg .= "<br>";
}

foreach ($globGalileo as $url) {
    $trimedUrl = trim($url, "/home/reportes/$hoy");
    $msg .= "$trimedUrl ";
    $isValid = validateGalileoFile($url, $conn);
    if ($isValid['isValid']) {
        $urls["Galileo"][] = $url;
        $exportar = true;
    } else {
        $urlsMv[] = $url;
        $msg .= $isValid['msg'];
    }
    $msg .= "<br>";
}


foreach ($globKiu as $url) {
    $trimedUrl = trim($url, "/home/reportes/$hoy");
    $msg .= "$trimedUrl ";
    $isValid = validateKiuFile($url, $conn);
    if ($isValid['isValid']) {
        $urls["Kiu"][] = $url;
        $exportar = true;
    } else {
        $urlsMv[] = $url;
        $msg .= $isValid['msg'];
    }
    $msg .= "<br>";
}

sqlsrv_close($conn);

if ($exportar) {
    $msg .= "<button class='btn btn-primary mt-2' onclick='exportar(\"$hoy\")'>Cargar en Base de Datos</button>";
}elseif ($urlsMv) {
    $msg .= "<button class='btn btn-primary mt-2' onclick='archivar(\"$hoy\")'>Archivar documentos ya cargados</button>";
}


$response = array(
    "success" => true,
    "urls" => $urls,
    "urlsMv" => $urlsMv,
    "msg" => $msg,
);

print_r(json_encode($response));



function validateSabreFile($url, $conn)
{
    $isValid = false;
    $docNumber = "";

    $delimiter = detectDelimiter($url);

    if (($handle = fopen($url, "r")) !== FALSE) {
        $data = fgetcsv($handle, 0, $delimiter); //header
        $data = fgetcsv($handle, 0, $delimiter); //first row
        $docNumber = array_key_exists('3', $data) ? trim($data['3'], "'") : "";
        fclose($handle);
    }

    if ($docNumber == "") {
        return ["isValid" => $isValid, "msg" => "(No se encuentran registros en el archivo)"];
    }

    $sql = "SELECT * FROM SABRETIQUETES$ WHERE [DOC NUMBER] = '$docNumber'";
    $params = array();

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt != false) {
        $registro = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($registro === null) { //No encuetra el registro en BD por tanto se puede cargar el archivo
            $isValid = true;
        }
    }
    // else{
    // die(print_r(sqlsrv_errors(), true)); //Descomentar solo para debug
    // }

    return ["isValid" => $isValid, "msg" => "(Archivo ya cargado en BD)"];
}

function validateGalileoFile($url, $conn)
{
    $isValid = false;
    $docNumber = "";

    if (($handle = fopen($url, "r")) !== FALSE) {
        $data = fgetcsv($handle, 0, ","); //header
        $data = fgetcsv($handle, 0, ","); //first row
        $docNumber = array_key_exists('0', $data) ? $data['0'] : "";
        fclose($handle);
    }

    if ($docNumber == "") {
        return ["isValid" => $isValid, "msg" => "(No se encuentran registros en el archivo)"];
    }

    $sql = "SELECT * FROM Galileo WHERE [Ticket number] = ''$docNumber'";
    $params = array();

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt != false) {
        $registro = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($registro === null) { //No encuetra el registro en BD por tanto se puede cargar el archivo
            $isValid = true;
        }
    }
    // else{
    // die(print_r(sqlsrv_errors(), true)); //Descomentar solo para debug
    // }

    return ["isValid" => $isValid, "msg" => "(Archivo ya cargado en BD)"];
}


function validateKiuFile($url, $conn)
{
    $isValid = false;
    $docNumber = "";
    if (($handle = fopen($url, "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
            if ($data != null && !empty($data)) {
                if (sizeof($data) > 1) {
                    $row = array_map("utf8_encode", $data);
                    if (is_numeric($row[0])) {
                        $docNumber = $row[2];
                        break;
                    }
                }
            }
        }
        fclose($handle);
    }

    if ($docNumber == "") {
        return ["isValid" => $isValid, "msg" => "(No se encuentran registros en el archivo)"];
    }

    $sql = "SELECT * FROM KIUREPORTS WHERE TICKET = '$docNumber'";
    $params = array();

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt != false) {
        $registro = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($registro === null) { //No encuetra el registro en BD por tanto se puede cargar el archivo
            $isValid = true;
        }
    }
    // else{
    // die(print_r(sqlsrv_errors(), true)); //Descomentar solo para debug
    // }
    return ["isValid" => $isValid, "msg" => "(Archivo ya cargado en BD)"];
}

function validateAmadeusFile($url, $conn)
{
    $isValid = false;
    $docNumber = "";


    $reader = new Xlsx();
    $spreadsheet = $reader->load($url);
    $tableName = $spreadsheet->getSheetNames()[0];
    $dataAmadeus12248 = $spreadsheet->getSheet(0)->toArray(null, true, true, true);
    $docNumber = $dataAmadeus12248['6']['D'];
    if ($docNumber == "") {
        return ["isValid" => $isValid, "msg" => "(No se encuentran registros en el archivo)"];
    }

    $sql = "SELECT * FROM $tableName$ WHERE [DOC NUMBER] = '$docNumber'";
    $params = array();

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt != false) {
        $registro = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($registro === null) { //No encuetra el registro en BD por tanto se puede cargar el archivo
            $isValid = true;
        }
    }
    // else{
    // die(print_r(sqlsrv_errors(), true)); //Descomentar solo para debug
    // }
    return ["isValid" => $isValid, "msg" => "(Archivo ya cargado en BD)"];
}
