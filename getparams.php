<?php

global $module;
$modName = $module->getModuleDirectoryName();

require_once dirname(APP_PATH_DOCROOT, 1) . "/modules/$modName/Utility.php";
use CCTC\MonitoringQRModule\Utility;

//set the helper dates for use in the quick links
$oneDayAgo = Utility::NowAdjusted('-1 days');
$oneWeekAgo = Utility::NowAdjusted('-7 days');
$oneMonthAgo = Utility::NowAdjusted('-1 months');
$oneYearAgo = Utility::NowAdjusted('-1 years');

global $datetime_format;

$userDateFormat = str_replace('y', 'Y', strtolower($datetime_format));
if(ends_with($datetime_format, "_24")){
    $userDateFormat = str_replace('_24', ' H:i', $userDateFormat);
} else {
    $userDateFormat = str_replace('_12', ' H:i a', $userDateFormat);
}

//get form values
$recordId = "";
if (isset($_GET['record_id'])) {
    $recordId = $_GET['record_id'];
}

$minDate = $oneWeekAgo;
if (isset($_GET['startdt'])) {
    $minDate = $_GET['startdt'];
}
$maxDate = null;
if (isset($_GET['enddt'])) {
    $maxDate = $_GET['enddt'];
}

//set the default to one week
$defaultTimeFilter = "oneweekago";
$customActive = "";
$dayActive = "";
$weekActive = "active";
$monthActive = "";
$yearActive = "";
if (isset($_GET['defaulttimefilter'])) {
    $defaultTimeFilter = $_GET['defaulttimefilter'];
    $customActive = $defaultTimeFilter == "customrange" ? "active" : "";
    $dayActive = $defaultTimeFilter == "onedayago" ? "active" : "";
    $weekActive = $defaultTimeFilter == "oneweekago" ? "active" : "";
    $monthActive = $defaultTimeFilter == "onemonthago" ? "active" : "";
    $yearActive = $defaultTimeFilter == "oneyearago" ? "active" : "";
}

$dataDirection = "desc";
if (isset($_GET['retdirection'])) {
    $dataDirection = $_GET['retdirection'];
}
$pageSize = 25;
if (isset($_GET['pagesize'])) {
    $pageSize = $_GET['pagesize'];
}
$pageNum = 0;
if (isset($_GET['pagenum'])) {
    $pageNum = $_GET['pagenum'];
}
$currentStatus = "ANY";
if (isset($_GET['currentstatus'])) {
    $currentStatus = $_GET['currentstatus'];
}
$currMonStatus = "any";
if (isset($_GET['currmonstatus'])) {
    $currMonStatus = $_GET['currmonstatus'];
}
$dataevnt = null;
if (isset($_GET['dataevnt'])) {
    $dataevnt = $_GET['dataevnt'];
}
$usrname = null;
if (isset($_GET['usrname'])) {
    $usrname = $_GET['usrname'];
}
$datainstance = null;
if (isset($_GET['datainst'])) {
    $datainstance = $_GET['datainst'];
}
$datafrm = null;
if (isset($_GET['datafrm'])) {
    $datafrm = $_GET['datafrm'];
}
$fieldName = null;
if (isset($_GET['datafld'])) {
    $fieldName = $_GET['datafld'];
}
$flag = null;
if (isset($_GET['dataflg'])) {
    $flag = $_GET['dataflg'];
}
$response = null;
if (isset($_GET['dataresp'])) {
    $response = $_GET['dataresp'];
}
$queryText = null;
if (isset($_GET['dataquerytext'])) {
    $queryText = $_GET['dataquerytext'];
}

if (isset($_GET['inc-no-timestamp']) && $_GET['inc-no-timestamp'] == "yes") {
    $incNoTimestamp = "checked";
} else {
    $incNoTimestamp = "";
}


$skipCount = (int)$pageSize * (int)$pageNum;
$minDateDb = Utility::DateStringToDbFormat($minDate);
$maxDateDb = Utility::DateStringToDbFormat($maxDate);
