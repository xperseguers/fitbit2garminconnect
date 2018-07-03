<?php
namespace Causal\F2GC;

define('LF', "\n");
define('TAB', "\t");

require_once('config.php');
require_once('AbstractClient.php');
require_once('FitbitClient.php');

$fitbitClient = new FitbitClient(FITBIT_USERNAME, FITBIT_PASSWORD);
$fitbitClient->connect();
$weightValues = $fitbitClient->getWeightValues();
print_r($weightValues);
