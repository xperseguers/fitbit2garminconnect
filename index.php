<?php
namespace Causal\F2GC;

require_once('config.php');
require_once('AbstractClient.php');
require_once('FitbitClient.php');
require_once('GarminConnectClient.php');

echo "Initializing connection to Fitbit         ... ";
$fitbitClient = new FitbitClient(FITBIT_USERNAME, FITBIT_PASSWORD);
if ($fitbitClient->connect()) {
    echo "success\n";
} else {
    echo "fail\n";
    exit(1);
}

echo "Fetching weight data points               ... ";
$values = $fitbitClient->getWeightValues();
echo count($values) . " data points\n";

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

echo "Initializing connection to Garmin Connect ... ";
$gcClient = new GarminConnectClient(GARMIN_CONNECT_USERNAME, GARMIN_CONNECT_PASSWORD);
if ($gcClient->connect()) {
    echo "success\n";
} else {
    echo "fail\n";
    exit(2);
}

echo "Fetching weight data points               ... ";
$values = $gcClient->getWeightValues();
echo count($values) . " data points\n";

$weightTarget = [];
foreach ($values as $data) {
    $date = date('Y-m-d', $data['date'] / 1000);
    $weight = $data['weight'] / 1000;
    if (!isset($weightTarget[$date])) {
        $weightTarget[$date] = $weight;
    }
}

echo "Looking for new weight data points        ... ";
$newDates = array_diff_key($weightSource, $weightTarget);
echo count($newDates) . " new data points\n";

foreach ($newDates as $date => $weight) {
    $gcClient->addWeight($date, $weight);
}

// Remove local cookies
echo "Disconnecting from Fitbit                 ... ";
$fitbitClient->disconnect();
echo "success\n";

echo "Disconnecting from Garmin Connect         ... ";
$gcClient->disconnect();
echo "success\n";
