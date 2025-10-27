<?php

require_once APP_PATH_DOCROOT . "/Config/init_project.php";
$lang = Language::getLanguage('English');

global $Proj;
$project_id = $module->getProjectId();
global $module;
$modName = $module->getModuleDirectoryName();

require_once dirname(APP_PATH_DOCROOT, 1) . "/modules/$modName/MonitoringData.php";
use CCTC\MonitoringQRModule\MonitoringData;
use CCTC\MonitoringQRModule\MonitoringQRModule;

// Increase memory limit in case needed for intensive processing
//System::increaseMemory(2048);

// File: getparams.php
/** @var $skipCount */
/** @var $pageSize */
/** @var $pageNum */
/** @var $dataDirection */
/** @var $recordId */
/** @var $minDateDb */
/** @var $maxDateDb */
/** @var $currentStatus */
/** @var $dataevnt */
/** @var $usrname */
/** @var $datainstance */
/** @var $datafrm */
/** @var $fieldName */
/** @var $flag */
/** @var $response */
/** @var $queryText */
/** @var $currMonStatus */
/** @var $incNoTimestamp */
/** @var $defaultTimeFilter */
/** @var $oneDayAgo */
/** @var $oneWeekAgo */
/** @var $oneMonthAgo */
/** @var $oneYearAgo */
/** @var $customActive */
/** @var $dayActive */
/** @var $weekActive */
/** @var $monthActive */
/** @var $yearActive */
/** @var $maxDate */
/** @var $minDate */
include "getparams.php";

//run the query using the same params as on the index page when the query called
//runForExport means it only returns the actual data requested (and not data for filters)

//use the export_type param to determine what to export and adjust params accordingly
$exportType = $_GET['export_type'];

//if current_page then keep the params already captured from getparams.php

//change paging to include everything
if($exportType == 'all_pages' || $exportType == 'everything') {
    //change the pagesize to a sensible 'unlimited' max. Actual max for limit as unsigned int is 18446744073709551615
    //but use 1 million
    $skipCount = 0;
    $pageSize = 1000000;
}

//set all filters to null
if($exportType == 'everything') {
    $recordId = null;
    $minDateDb = null;
    $maxDateDb = null;
    $currentStatus = null;
    $dataevnt = null;
    $usrname = null;
    $datainstance = null;
    $datafrm = null;
    $fieldName = null;
    $flag = null;
    $response = null;
    $queryText = null;
    $currMonStatus = null;
    //includes items with no timestamp
    $incNoTimestamp = "checked";
}

//get the user dag membership
$user = $module->getUser();
$username = $user->getUsername();

$userDags = MonitoringData::GetUserDags(false, $username);
$dagUser = count($userDags) > 0 ? $username : null;

$result =
    MonitoringData::GetQueries(
        $skipCount, $pageSize, $dataDirection, $recordId, $minDateDb, $maxDateDb,
        str_replace("NOT-", "^", $currentStatus), $dataevnt, $datainstance, $datafrm, $fieldName, $flag,
        $response, $queryText, $currMonStatus,
        MonitoringQRModule::NO_QUERY, $incNoTimestamp, $usrname, $dagUser,  true);

// Set headers
$headers = array("Timestamp","Username","Query status","Monitor status","Record","Form","Event id","Event name",
    "Instance","Field", "Field label", "Flags", "Query", "Response", "Response comment");

// Set file name and path
$filename = APP_PATH_TEMP . date("YmdHis") . '_' . PROJECT_ID . '_monitor_logs.csv';

// Begin writing file from query result
$fp = fopen($filename, 'w');

if ($fp && $result)
{
    try {

        $delim = User::getCsvDelimiter();

        // Write headers to file
        fputcsv($fp, $headers, $delim);

        // Set values for this row and write to file
        foreach ($result as $row) {

            //timestamp
            $row["ts"] =
                $row["ts"] == null || $row["ts"] == ""
                    ? ""
                    : DateTime::createFromFormat('YmdHis', $row["ts"])->format('Y-m-d H:i:s');

            //query status
            $row["current_query_status"] =
                $row["current_query_status"] == "CLOSED"
                    ? $row["current_query_status"]." [".$row["comment"]."]"
                    : $row["current_query_status"];

            //monitor status
            $row["mon_stat_value"] = trim(substr($row["mon_stat_value"], 3));

            $json = json_decode($row['comment']);

            //remove comment and field_name
            unset($row['urn']);
            unset($row["comment"]);
            unset($row["field_name"]);

            if(json_last_error() == JSON_ERROR_NONE) {
                //add a row for each field being queried
                foreach ($json as $f) {
                    $row["field"] = $f->field;
                    $row["field label"] = $f->field_label;
                    $row["flags"] = $f->flags;
                    $row["query"] = $f->query;
                    $row["response"] = $f->response;
                    $row["response_comment"] = $f->comment;

                    fputcsv($fp, $row, $delim);
                }
            } else {

                $row["field"] = "";
                $row["field label"] = "";
                $row["flags"] = "";
                $row["query"] = "";
                $row["response"] = "";
                $row["response_comment"] = "";

                fputcsv($fp, $row, $delim);
            }
        }

        // Close file for writing
        fclose($fp);
        db_free_result($result);

        // Open file for downloading
        $app_title = strip_tags(label_decode($Proj->project['app_title']));
        $download_filename = camelCase(html_entity_decode($app_title, ENT_QUOTES)) . "_MonitorLog_" . date("Y-m-d_Hi") . ".csv";

        header('Pragma: anytextexeptno-cache', true);
        header("Content-type: application/csv");
        header("Content-Disposition: attachment; filename=$download_filename");

        // Open file for reading and output to user
        $fp = fopen($filename, 'rb');
        print addBOMtoUTF8(fread($fp, filesize($filename)));

        // Close file and delete it from temp directory
        fclose($fp);
        unlink($filename);

        // Logging
        Logging::logEvent("", Logging::getLogEventTable($project_id),"MANAGE",$project_id,"project_id = $project_id", "Export monitor logging (custom)");

    } catch (Exception $e) {
        $module->log("ex: ". $e->getMessage());
    }
} else if (!$result) {
    print "There are no monitoring queries available for export. Please return to the previous page.";
}
else
{
    //error
    print $lang['global_01'];
}
