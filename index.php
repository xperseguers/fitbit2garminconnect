<?php
namespace Causal\F2GC;

define('LF', "\n");
define('TAB', "\t");

require_once('config.php');
require_once('AbstractClient.php');
require_once('FitbitClient.php');
require_once('GarminConnectClient.php');

$fitbitClient = new FitbitClient(FITBIT_USERNAME, FITBIT_PASSWORD);
$fitbitClient->connect();
$values = $fitbitClient->getWeightValues();

// Remark: you may remove this minimum date and the corresponding "continue"
//         in loop below when importing for the very first time
$minDate = date('Y-m-d', strtotime('-1 year'));

$weightSource = [];
foreach ($values as $data) {
    list($date, ) = explode('T', $data['dateTime'], 2);
    if ($date < $minDate) continue;
    if (!isset($weightSource[$date])) {
        $weightSource[$date] = $data['weight'];
    }
}

$gcClient = new GarminConnectClient(GARMIN_CONNECT_USERNAME, GARMIN_CONNECT_PASSWORD);
$gcClient->connect();
$values = $gcClient->getWeightValues();

$weightTarget = [];
foreach ($values as $data) {
    $date = date('Y-m-d', $data['date'] / 1000);
    $weight = $data['weight'] / 1000;
    if (!isset($weightTarget[$date])) {
        $weightTarget[$date] = $weight;
    }
}

$newDates = array_diff_key($weightSource, $weightTarget);
foreach ($newDates as $date => $weight) {
    $gcClient->addWeight($date, $weight);
}

echo count($newDates) . ' weight data points added.';

// Remove local cookies
$fitbitClient->disconnect();
$gcClient->disconnect();
