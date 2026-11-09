<?php

global $module;
$modName = $module->getModuleDirectoryName();

require_once dirname(APP_PATH_DOCROOT, 1) . "/modules/$modName/Utility.php";
use CCTC\MonitoringQRModule\Utility;
use DateTimeRC;

// Set the helper dates for use in the quick links
$oneDayAgo = Utility::NowAdjusted('-1 days');
$oneWeekAgo = Utility::NowAdjusted('-7 days');
$oneMonthAgo = Utility::NowAdjusted('-1 months');
$oneYearAgo = Utility::NowAdjusted('-1 years');

// Get and validate form values
$recordId = Utility::sanitizeString($_GET['record_id'] ?? null, 100) ?? "";

$minDate = Utility::sanitizeString($_GET['startdt'] ?? null, 20) ?? $oneWeekAgo;
$maxDate = Utility::sanitizeString($_GET['enddt'] ?? null, 20);

// Set the default to one week - validate against allowed values
$allowedTimeFilters = ['customrange', 'onedayago', 'oneweekago', 'onemonthago', 'oneyearago'];
$defaultTimeFilter = Utility::allowedValue($_GET['defaulttimefilter'] ?? 'oneweekago', $allowedTimeFilters, 'oneweekago');

$customActive = $defaultTimeFilter === "customrange" ? "active" : "";
$dayActive = $defaultTimeFilter === "onedayago" ? "active" : "";
$weekActive = $defaultTimeFilter === "oneweekago" ? "active" : "";
$monthActive = $defaultTimeFilter === "onemonthago" ? "active" : "";
$yearActive = $defaultTimeFilter === "oneyearago" ? "active" : "";

// Validate direction (only allow 'asc' or 'desc')
$dataDirection = Utility::allowedValue($_GET['retdirection'] ?? 'desc', ['asc', 'desc'], 'desc');

// Validate numeric values with reasonable bounds (use defaults if invalid)
$pageSize = Utility::validateInt($_GET['pagesize'] ?? null, 1, 500) ?? 25;
$pageNum = Utility::validateInt($_GET['pagenum'] ?? null, 0, 100000) ?? 0;

// Validate status values against allowed options
$allowedStatuses = ['ANY', 'OPEN', 'CLOSED', 'NONE', 'NOT-OPEN', 'NOT-CLOSED', 'NOT-NONE'];
$currentStatus = Utility::allowedValue(strtoupper(Utility::sanitizeString($_GET['currentstatus'] ?? null) ?? 'ANY'), $allowedStatuses, 'ANY');

$currMonStatus = Utility::sanitizeString($_GET['currmonstatus'] ?? null, 20) ?? "any";

// Validate event ID as integer (null means "any event")
$dataevnt = Utility::validateInt($_GET['dataevnt'] ?? null, 1, PHP_INT_MAX);

$usrname = Utility::sanitizeString($_GET['usrname'] ?? null, 100);

// Validate instance as integer (null means "any instance")
$datainstance = Utility::validateInt($_GET['datainst'] ?? null, 1, PHP_INT_MAX);

$datafrm = Utility::sanitizeString($_GET['datafrm'] ?? null, 100);
$fieldName = Utility::sanitizeString($_GET['datafld'] ?? null, 100);
$flag = Utility::sanitizeString($_GET['dataflg'] ?? null, 100);
$response = Utility::sanitizeString($_GET['dataresp'] ?? null, 100);
$queryText = Utility::sanitizeString($_GET['dataquerytext'] ?? null, 500);

// Default to checked on first load, but respect user's choice on form submission
// Check if form was submitted by looking for the 'prefix' parameter
$isFormSubmission = isset($_GET['prefix']);
if ($isFormSubmission) {
    // Form was submitted - respect user's choice (unchecked checkboxes are not submitted)
    $incNoTimestamp = ($_GET['inc-no-timestamp'] ?? '') === 'yes' ? "checked" : "";
} else {
    // First page load - default to checked
    $incNoTimestamp = "checked";
}

$skipCount = $pageSize * $pageNum;
$minDateDb = Utility::DateStringToDbFormat($minDate);
$maxDateDb = Utility::DateStringToDbFormat($maxDate);

// Get user's preferred date format for the date picker (jQuery UI format)
$userDateFormat = DateTimeRC::get_user_format_jquery();
