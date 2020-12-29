<?php
/** Archivo de funciones genericas */

ini_set('display_errors', 1);
/**
 * Created by: Camilo Cruz
 * Date: 26/12/2020
 * Metodo que establece la conexion a la base de datos segun 
 * el ambiente seleccionado
 *
 * @return object $conn
 */
function getConnection()
{
    // $enviroment = "Pruebas";
    $enviroment = "Produccion";
    $serverName = ""; //serverName\instanceName
    $connectionInfo = array();

    switch ($enviroment) {
        case 'Pruebas':
            //Conexion a DB
            //Servidor de Pruebas
            $serverName = "192.168.1.131\SQLEXPRESS, 1433"; //serverName\instanceName
            $connectionInfo = array("Database" => "Backoffice", "UID" => "sa", "PWD" => "Sistemas1");
            break;
        case 'Produccion':
            //Servidor de Produccion
            $serverName = "192.168.1.6\integra2"; //serverName\instanceName
            $connectionInfo = array("Database" => "BackOffice", "UID" => "sa", "PWD" => "Jetours_123");
            break;
        default:
            return false;
            break;
    }
    $conn = array($serverName, $connectionInfo);
    return $conn;
}

/**
 * Created by: Camilo Cruz
 * Date: 26/12/2020
 * Metodo que detecta el delimitador de un archivo .csv
 *
 * @param string $url
 * @return string $delimiter
 */
function detectDelimiter($url)
{
    $fh = fopen($url, "r");

    $delimiters = array("*", ";", "|", ",");
    $data_1 = array();
    $data_2 = array();
    $delimiter = $delimiters[0];
    foreach ($delimiters as $d) {


        $data_1 = fgetcsv($fh, 4096, $d);
        if (sizeof($data_1) > sizeof($data_2)) {
            $delimiter = $d;
            $data_2 = $data_1;
        }
        rewind($fh);
    }
    fclose($fh);
    return $delimiter;
}
