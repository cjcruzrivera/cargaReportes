<?php
ini_set('display_errors', 1);

date_default_timezone_set('America/Bogota');

require 'connection.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

$response = array();
$succes = false;
$year = date("Y");
$month = date("m");
$day = date("d");
$hoy = $_POST['hoy'] != "" ? $_POST['hoy'] : $day . $month . $year;

$urls = $_POST['urls'];
$urlsMv = array_key_exists("urlsMv", $_POST) ? $_POST['urlsMv'] : array();
$urlsTrimeds = "";
foreach ($urls as  $urlsReporte) {
    foreach ($urlsReporte as $url) {
        $urlTrimed = trim($url, "/home/reportes/$hoy");
        $urlsTrimeds .= "$urlTrimed <br>";
    }
}
/**
 * Lectura de los archivos
 */

$arrayData = array(
    "dataSabre" => array(),
    "dataGalileo" => array(),
    "dataKiu" => array(),
    "dataAmadeus" => array(),
);

foreach ($urls as $key => $value) {
    switch ($key) {
        case 'Sabre':
            foreach ($value as $url) {
                $arrayData['dataSabre'][] = readSabre($url);
            }
            break;
        case 'Galileo':
            foreach ($value as $url) {
                $arrayData['dataGalileo'][] = readGalileo($url);
            }
            break;
        case 'Kiu':
            foreach ($value as $url) {
                $arrayData['dataKiu'][] = readKiu($url);
            }
            break;
        case 'Amadeus':
            foreach ($value as $url) {
                $arrayData['dataAmadeus'][] = readAmadeus($url);
            }
            break;

        default:
            # code...
            break;
    }
}


/** Fin lectura */

/** Empezar procesamiento de datos. */

$queryAmadeus1 = $queryAmadeus2 = $querySabre = $queryGalileo = $queryKiu = array();
$arrayMonths = array(
    "Dec" => array(
        "name" => "DIC",
        "number" => 12,
    ),
    "Nov" => array(
        "name" => "NOV",
        "number" => 11,
    ),
    "Oct" => array(
        "name" => "OCT",
        "number" => 10,
    ),
    "Sep" => array(
        "name" => "SEP",
        "number" => 9,
    ),
    "Aug" => array(
        "name" => "AGO",
        "number" => 8,
    ),
    "Jul" => array(
        "name" => "JUL",
        "number" => 7,
    ),
    "Jun" => array(
        "name" => "JUN",
        "number" => 6,
    ),
    "May" => array(
        "name" => "MAY",
        "number" => 5,
    ),
    "Apr" => array(
        "name" => "ABR",
        "number" => 4,
    ),
    "Mar" => array(
        "name" => "MAR",
        "number" => 3,
    ),
    "Feb" => array(
        "name" => "FEB",
        "number" => 2,
    ),
    "Jan" => array(
        "name" => "ENE",
        "number" => 1,
    ),
);

foreach ($arrayData as $reporte => $arrayDataReporte) {
    foreach ($arrayDataReporte as $file) {
        switch ($reporte) {
            case 'dataAmadeus':
                $querysAmadeus = processAmadeus($file, $arrayMonths);
                $queryAmadeus1[] = $querysAmadeus['dataAmadeus1211J'];
                $queryAmadeus2[] = $querysAmadeus['dataAmadeus12248'];
                break;
            case 'dataSabre':
                $querySabre[] = processSabre($file, $arrayMonths);
                break;
            case 'dataGalileo':
                $queryGalileo[] = processGalileo($file, $arrayMonths);
                break;
            case 'dataKiu':
                $queryKiu[] = processKiu($file, $arrayMonths);
                break;
            default:
                # code...
                break;
        }
    }
}

$querys = array(
    "queryAmadeus1" => $queryAmadeus1,
    "queryAmadeus2" => $queryAmadeus2,
    "querySabre" => $querySabre,
    "queryGalileo" => $queryGalileo,
    "queryKiu" => $queryKiu,
);
/** Fin procesamiento */

/** Carga en BD */
$connInfo = getConnection();

$conn = sqlsrv_connect($connInfo[0], $connInfo[1]);

if ($conn === false) {
    $response['success'] = false;
    $response['error'] = "Error de conexion con la Base de Datos";
    $response['sqlerror'] = sqlsrv_errors();
    print_r(json_encode($response));
    exit;
}

$errores = array();

$params = array();

if (sqlsrv_begin_transaction($conn) === false) {
    die(print_r(sqlsrv_errors(), true));
}
foreach ($querys as $queryName => $arrayQuerys) {

    foreach ($arrayQuerys as $file => $value) {
        foreach ($value as $key => $sqlQuery) {

            // print_r("Query N#$key del archivo N#$file de $queryName");
            // print_r('<br>');
            // print_r($sqlQuery);
            // print_r('<br>');
            // print_r('<br>');
            $stmt = sqlsrv_query($conn, $sqlQuery, $params);
            if ($stmt === false) {
                $errores[] = array(
                    "sqlError" => sqlsrv_errors(),
                    "error" => "Error en query N#$key del archivo N#$file de $queryName"
                );
                // die(print_r(sqlsrv_errors(), true));
            } else {
                sqlsrv_free_stmt($stmt);
            }
        }
    }
}

if (sizeof($errores) == 0) {
    sqlsrv_commit($conn);

    $path = "/home/reportes/$hoy/exportados";
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }

    foreach ($urls as $reporte) {
        foreach ($reporte as $url) {
            $urlTrimed = trim($url, "/home/reportes/$hoy/");
            rename($url, "$path/$urlTrimed");
        }
    }

    foreach ($urlsMv as $url) {
        $urlTrimed = trim($url, "/home/reportes/$hoy/");
        rename($url, "$path/$urlTrimed");
    }
} else {
    sqlsrv_rollback($conn);
    $response['success'] = false;
    $response['error'] = "Error de insercion en BD";
    $response['erroresInsercion'] = $errores;
    $response['querys'] = $querys;
    print_r(json_encode($response));
    exit;
}
/** Fin carga */

$response = array(
    "success" => true,
    "msg" => "Reportes cargados correctamente <br> $urlsTrimeds",
    "data" => $arrayData,
    "querys" => $querys,
    "errores" => $errores,
);

print_r(json_encode($response));


//Definicion de funciones

function readSabre($urlSabre)
{
    $dataArray = array();
    $delimiter = detectDelimiter($urlSabre);
    if (($handle = fopen($urlSabre, "r")) !== FALSE) {
        $header = true;
        while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
            if ($data != null && !empty($data)) {
                if (!$header && sizeof($data) > 1) {
                    $row = array_map("utf8_encode", $data);
                    $dataArray[] = $row;
                } else {
                    $header = false;
                }
            }
        }
        fclose($handle);
    }

    return $dataArray;
}

function readGalileo($urlGalileo)
{
    $dataArray = array();
    if (($handle = fopen($urlGalileo, "r")) !== FALSE) {
        $header = true;
        while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
            if ($data != null && !empty($data)) {
                if (!$header && sizeof($data) > 1) {
                    $row = array_map("utf8_encode", $data);
                    $dataArray[] = $row;
                } else {
                    $header = false;
                }
            }
        }
        fclose($handle);
    }
    return $dataArray;
}

function readKiu($urlKiu)
{
    $dataArray = array();
    if (($handle = fopen($urlKiu, "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
            if ($data != null && !empty($data)) {
                if (sizeof($data) > 1) {
                    $row = array_map("utf8_encode", $data);
                    $dataArray[] = $row;
                }
            }
        }
        fclose($handle);
    }
    return $dataArray;
}

function readAmadeus($urlAmadeus)
{
    $dataArray = array(
        'dataAmadeus1211J' => array(),
        'dataAmadeus12248' => array(),
    );
    $reader = new Xlsx();
    $spreadsheet = $reader->load($urlAmadeus);
    $pestanas = $spreadsheet->getSheetNames();
    foreach ($pestanas as $key => $value) {
        $data = $spreadsheet->getSheet($key)->toArray(null, true, true, true);
        if ($value == "CLOZ1211J") {
            $dataArray['dataAmadeus1211J'] = $data;
        } elseif ($value == "CLOZ12248") {
            $dataArray['dataAmadeus12248'] = $data;
        }
    }

    return $dataArray;
}

function processSabre($dataSabre, $arrayMonths)
{
    $querys = array();
    foreach ($dataSabre as $value) {
        $arrayDate = str_split($value[0]);
        $numberDate = $monthDate = $yearDate = "";
        foreach ($arrayDate as $element) {

            if (is_numeric($element) && $monthDate == "") {
                $numberDate .= $element;
            } elseif (is_numeric($element) && $monthDate != "") {
                $yearDate .= $element;
            } else {
                $monthDate .= $element;
            }
        }

        $dateSabre = "{$arrayMonths[ucfirst(strtolower($monthDate))]['number']}/$numberDate/20$yearDate";
        $alCode = trim($value[2], "'");
        $docNumber = trim($value[3], "'");
        $created = array_key_exists(19, $value) ? $value[19] : "";
        $issued = array_key_exists(20, $value) ? $value[20] : "";
        $dk = trim($value[18], "'");
        $query = "INSERT INTO SABRETIQUETES$ ([DATE], CARRIER, [AL CODE], [DOC NUMBER], PNR, [FIRST NAME], [LAST NAME], CUR, [TKT AMT], TAXES, COMM, [NET FARE], [OB FEES], FOP, [AGT SIGN], [TIME], [ACTION], FCI, DK, [Created By], [Issued By])
                   VALUES ('$dateSabre', '{$value[1]}', '$alCode', '$docNumber', '{$value[4]}', '{$value[5]}', '{$value[6]}', '{$value[7]}', '{$value[8]}', '{$value[9]}', '{$value[10]}', '{$value[11]}', '{$value[12]}', '{$value[13]}', '{$value[14]}', '{$value[15]}', '{$value[16]}', '{$value[17]}', '$dk', '$created', '$issued')";
        $querys[] = $query;
    }
    return $querys;
}

function processGalileo($dataGalileo, $arrayMonths)
{
    $querys = array();

    foreach ($dataGalileo as $value) {
        $taxes2 = array_key_exists(21, $value) ? $value[21] : "";
        $arrayDate = explode('/', $value[9]);
        $issuedDate = "{$arrayMonths[ucfirst(strtolower($arrayDate[1]))]['number']}/{$arrayDate[0]}/{$arrayDate[2]}"; //"{$arrayMonths[ucfirst(strtolower($monthDate))]['number']}/$numberDate/$year";
        $query = "INSERT INTO Galileo ([Ticket number], [Coupon status], [Travel date], [Origin airport], [Destination airport], Airline, Locator, [Passenger name], PCC, [Issued date], [Tour Code], [Base Fare], [Total Fare], [Fare Basis], OBFee1, OBFee2, OBFee3, OBFee4, OBFee5, OBFee6, taxes1, taxes2) 
                  VALUES (''{$value[0]}', '{$value[1]}', '{$value[2]}', '{$value[3]}', '{$value[4]}', '{$value[5]}', '{$value[6]}', '{$value[7]}', '{$value[8]}', '$issuedDate', '{$value[10]}', '{$value[11]}', '{$value[12]}', '{$value[13]}', '{$value[14]}', '{$value[15]}', '{$value[16]}', '{$value[17]}', '{$value[18]}', '{$value[19]}', '{$value[20]}', '$taxes2')";
        $querys[] = $query;
    }

    return $querys;
}

function processKiu($dataKiu, $arrayMonths)
{
    $querys = array();

    $arrayDateKiu = explode(" ", trim($dataKiu[0][5]));
    $dateKiu = "{$arrayMonths[ucfirst(strtolower($arrayDateKiu[3]))]['number']}/{$arrayDateKiu[2]}/{$arrayDateKiu[4]}";
    foreach ($dataKiu as $value) {
        if (is_numeric($value[0])) {
            $query = "INSERT INTO KIUREPORTS (SEQ, VACIO, TICKET, FARE, TAX, FEE, COMM, NET, FP, TRANS, RELOC, [PAX NAME], OBSERVACIONS, FECHA, ASESOR) 
                  VALUES ('{$value[0]}', '{$value[1]}', '{$value[2]}', '{$value[3]}', '{$value[4]}', '{$value[5]}', '{$value[6]}', '{$value[7]}', '{$value[8]}', '{$value[9]}', '{$value[10]}', '{$value[11]}', '{$value[12]}', '$dateKiu', '')";
            $querys[] = $query;
        }
    }
    return $querys;
}

function processAmadeus($dataAmadeus, $arrayMonths)
{
    $querys = array(
        "dataAmadeus1211J" => array(),
        "dataAmadeus12248" => array(),
    );
    $dataAmadeus1211J = $dataAmadeus['dataAmadeus1211J'];
    $dataAmadeus12248 = $dataAmadeus['dataAmadeus12248'];
    //Procesar Amadeus1211J
    if ($dataAmadeus1211J) {
        $dateAmadeus1 = $dataAmadeus1211J['1']['D'];
        $arrayDate = str_split($dateAmadeus1);
        $numberDate =  $monthDate = "";

        foreach ($arrayDate as $element) {
            if (is_numeric($element)) {
                $numberDate .= $element;
            } else {
                $monthDate .= $element;
            }
        }
        $year = date("Y");
        $PERIODO = "{$arrayMonths[$monthDate]['name']}$year";
        $FECHAEMISION = "{$arrayMonths[$monthDate]['number']}/$numberDate/$year";


        foreach ($dataAmadeus1211J as $key => $value) {
            if (intval($key) >= 6) {
                $query = "INSERT INTO CLOZ1211J$ ([SEQ NO], CONFIRMED, [A/L], [DOC NUMBER], [TOTAL DOC], TAX, FEE, COMM , AGENT ,FP , [PAX NAME] ,[AS] , TRNC, RECLOC , PERIODO, FECHAEMISION)
                      VALUES ('{$value['A']}', '{$value['B']}', '{$value['C']}', '{$value['D']}', '{$value['E']}', '{$value['F']}', '{$value['G']}', '{$value['H']}' ,'{$value['I']}' ,'{$value['J']}' ,'{$value['K']}' ,'{$value['L']}' ,'{$value['M']}' ,'{$value['N']}' ,'$PERIODO' ,'$FECHAEMISION' )";
                $querys['dataAmadeus1211J'][] = $query;
            }
        }
    }

    //Procesar Amadeus12248
    if ($dataAmadeus12248) {
        $dateAmadeus2 = $dataAmadeus12248['1']['D'];
        $arrayDate = str_split($dateAmadeus2);
        $numberDate =  $monthDate = "";
        foreach ($arrayDate as $element) {

            if (is_numeric($element)) {
                $numberDate .= $element;
            } else {
                $monthDate .= $element;
            }
        }

        $year = date("Y");
        $PERIODO = "{$arrayMonths[$monthDate]['name']}$year";
        $FECHAEMISION = "{$arrayMonths[$monthDate]['number']}/$numberDate/$year";
        foreach ($dataAmadeus12248 as $key => $value) {
            if (intval($key) >= 6) {
                // A         B            C       D              E          F      G      H       I       J          K      L       M         N          CALCULADOS                           
                $query = "INSERT INTO CLOZ12248$ ([SEQ NO], CONFIRMED, [A/L], [DOC NUMBER], [TOTAL DOC], TAX, FEE, COMM , AGENT ,FP , [PAX NAME] ,[AS] , TRNC, RECLOC , PERIODO, FECHAEMISION)
                  VALUES ('{$value['A']}', '{$value['B']}', '{$value['C']}', '{$value['D']}', '{$value['E']}', '{$value['F']}', '{$value['G']}', '{$value['H']}' ,'{$value['I']}' ,'{$value['J']}' ,'{$value['K']}' ,'{$value['L']}' ,'{$value['M']}' ,'{$value['N']}' ,'$PERIODO' ,'$FECHAEMISION' )";
                $querys['dataAmadeus12248'][] = $query;
            }
        }
    }

    return $querys;
}
