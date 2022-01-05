<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SLA</title>
</head>
<body style="font-family: 'DejaVu Sans'">

<?php

date_default_timezone_set('America/Fortaleza');
setlocale(LC_ALL, 'pt_BR', 'pt_BR.utf-8', 'pt_BR.utf-8', 'portuguese');

/**
 * Exportar como planilha Excel
 */
header("Content-type: application/excel; charset=utf-8");
header('Cache-Control: no-store, no-cache, must-revalidate');     // HTTP/1.1
header('Cache-Control: pre-check=0, post-check=0, max-age=0');    // HTTP/1.1
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Transfer-Encoding: none');
header('Content-Disposition: attachment; filename="'.basename('SLA_NAGIOS.xls').'"');

require_once 'vendor/autoload.php';
include_once 'circuitos.php';

use Guzzle\Http\Client;
use Symfony\Component\DomCrawler\Crawler;

$start_month = $_GET["month"];
//$start_month = "7";
$start_day = "1";
$start_year = "2019";
$end_month = $start_month;
$end_day = $_GET["eday"];
//$end_day = 31;
$end_year = $start_year;

echo "<table border=1>
        <tr>
            <th>CIRCUITO</th>
            <th>DISPONIBILIDADE (%)</th>
            <th>LATENCIA MEDIA (ms)</th>
            <th>TAXA DE ENTREGA (%)</th>
            <th>DADOS DISPONIB ILIDADE</th>
            <th>DADOS TX. ENTREGA</th>
            <th>". $start_month. "/" .$start_year. "</th>
        </tr>";

foreach ($circuitos as $circuito) {
    $client_service = new Client('http://user:password@nagios.domain');
    $request_service = $client_service->get("/nagios/cgi-bin/avail.cgi?show_log_entries=&host=$circuito&service=ping&timeperiod=custom&smon=$start_month&sday=$start_day&syear=$start_year&shour=0&smin=0&ssec=0&emon=$end_month&eday=$end_day&eyear=$end_year&ehour=24&emin=0&esec=0&rpttimeperiod=&assumeinitialstates=yes&assumestateretention=yes&assumestatesduringnotrunning=yes&includesoftstates=no&initialassumedservicestate=0&backtrack=0");
    $response_service = $request_service->send();
    $html_service = $response_service->getBody(true);
    $crawler_service = new Crawler($html_service);
    $filter_service = $crawler_service->filter('td.serviceOK');
    $filter_latencia = $crawler_service->filter('td.logEntriesEven,td.logEntriesOdd');

    $client_host = new Client('http://user:password@nagios.domain');
    $request_host = $client_host->get("/nagios/cgi-bin/avail.cgi?show_log_entries=&host=$circuito&timeperiod=custom&smon=$start_month&sday=$start_day&syear=$start_year&shour=0&smin=0&ssec=0&emon=$end_month&eday=$end_day&eyear=$end_year&ehour=24&emin=0&esec=0&rpttimeperiod=&assumeinitialstates=yes&assumestateretention=yes&assumestatesduringnotrunning=yes&includesoftstates=no&initialassumedservicestate=0&backtrack=0");
    $response_host = $request_host->send();
    $html_host = $response_host->getBody(true);
    $crawler_host = new Crawler($html_host);
    $filter_host = $crawler_host->filter('td.hostUP');

    /**
     * Latência
     */
    $lines_service = [];
    $plugin_timed_out = 0;
    foreach ($filter_latencia as $item => $content) {
        if (strpos($content->textContent, 'RTA') == true) {
            $lines_service[] = explode(" ", $content->textContent);
        }
    }

    foreach ($lines_service as $line) {
        $latencias[] = $line[9];
    }

    sort($latencias);
    $count_latencias = count($latencias);
    if ($count_latencias % 2 == 0) {
        $valor1 = $latencias[$count_latencias / 2];
        $valor2 = $latencias[($count_latencias / 2) + 1];
        $latencia_mediana = round(floatval(($valor1 + $valor2) / 2), 2);
    } else {
        $latencia_mediana = round(floatval($latencias[($count_latencias + 1) / 2]), 2);
    }

    /**
     * Taxa de entrega
     */
    foreach ($filter_service as $item => $content) {
        if (strpos($content->textContent, '%') == true) {
            $taxa_entrega = round(floatval(str_replace("%", "", $content->textContent)), 2);
        }
    }

    $dados_tx_entrega = "http://user:password@nagios.domain/nagios/cgi-bin/avail.cgi?show_log_entries=&host=$circuito&service=ping&timeperiod=custom&smon=$start_month&sday=$start_day&syear=$start_year&shour=0&smin=0&ssec=0&emon=$end_month&eday=$end_day&eyear=$end_year&backtrack=0";

    /**
     * Disponibilidade
     */
    foreach ($filter_host as $item => $content) {
        if (strpos($content->textContent, '%') == true) {
            $disponibilidade = round(floatval(str_replace("%", "", $content->textContent)), 2);
        }
    }

    $dados_disponibilidade = "http://user:password@nagios.domain/nagios/cgi-bin/avail.cgi?show_log_entries=&host=$circuito&timeperiod=custom&smon=$start_month&sday=$start_day&syear=$start_year&shour=0&smin=0&ssec=0&emon=$end_month&eday=$end_day&eyear=$end_year&backtrack=0";

    echo "<tr>";
    echo    "<td>" .$circuito. "</td>";
    echo    "<td>" .$disponibilidade. "</td>";
    echo    "<td>" .$latencia_mediana. "</td>";
    echo    "<td>" .$taxa_entrega. "</td>";
    echo    "<td>=HIPERLINK(\"" .$dados_disponibilidade. "\";\"Ver dados\")</td>";
    echo    "<td>=HIPERLINK(\"" .$dados_tx_entrega. "\";\"Ver dados\")</td>";
    echo "</tr>";

    $latencia_mediana = null;
    $taxa_entrega = null;
    $disponibilidade = null;

    unset($lines_service);
    unset($lines_host);
    unset($latencias);
}

echo "</table>";
?>

</body>
</html>
