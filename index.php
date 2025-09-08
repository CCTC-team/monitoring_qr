<?php

//DO NOT use a namespace here as gives access then to root e.g. RCView, Records etc

// APP_PATH_DOCROOT = /var/www/html/redcap_v13.8.1/

//add js
//https://github.com/vanderbilt-redcap/external-module-framework-docs/blob/main/methods/README.md#javascript-module-object
$module->initializeJavascriptModuleObject();

global $Proj;
$modName = $module->getModuleDirectoryName();

require_once APP_PATH_DOCROOT . "/Classes/REDCap.php";
require_once dirname(APP_PATH_DOCROOT, 1) . "/modules/$modName/MonitoringData.php";
require_once dirname(APP_PATH_DOCROOT, 1) . "/modules/$modName/Utility.php";
require_once dirname(APP_PATH_DOCROOT, 1) . "/modules/$modName/Rendering.php";

use CCTC\MonitoringQRModule\MonitoringData;
use CCTC\MonitoringQRModule\MonitoringQRModule;
use CCTC\MonitoringQRModule\Utility;
use CCTC\MonitoringQRModule\Rendering;

$projId = $module->getProjectId();
$moduleName = "monitoring_qr";
$page = "index.php";
$redcapPart = Utility::getREDCapUrlPart();

echo "
<div class='projhdr'>
    <div style='float:left;'>
        <i class='fas fa-clipboard-check'></i> Monitoring QR
    </div>   
</div>
<br/>
<p>
    Use this page to review the monitoring queries for your project. The options below can be used to filter the queries as required.
</p>
";

//get settings
$regex = $module->getProjectSetting('monitoring-flags-regex');
$monIgnore = $module->getProjectSetting('ignore-for-monitoring-action-tag');

if(!empty($monIgnore)) {
    $regex = "$regex|$monIgnore";
}

//check the regex is a valid one
if (preg_match('/'.$regex.'/', '') === false) {
    echo "<div class='red'>
        <p>The regex $regex is not valid - do not include the leading and trailing slashes in your config</p>
        <p>Change the setting 'monitoring-flags-regex' ensuring it is a valid regular expression</p>
</div>";
    return;
}

//check that the project is using Data Resolution work flows
//get query resolution data for project
$query = "select project_id, data_resolution_enabled from redcap_projects where project_id = ?;";
$result = $module->query($query, [ $projId ]);
$row = db_fetch_assoc($result);
$usesQueryResolution = $row['data_resolution_enabled'];

if($usesQueryResolution != 2)
{
    echo "<div class='red'>
        <p>The project must use the Data Resolution Workflow for the Monitoring QR functionality to work</p>
        <p>Either set the value accordingly in the Project setup or do not use the Monitor QR module</p>
</div>";
    return;
}

$noInstance = "-- no instance --";

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
/** @var $userDateFormat */
include "getparams.php";

//get the user dag membership
$user = $module->getUser();
$username = $user->getUsername();
$userDags = MonitoringData::GetUserDags(false, $username);
$dagUser = count($userDags) > 0 ? $username : null;

//run the query returning the various datasets
$logDataSets =
    MonitoringData::GetQueries(
            $skipCount, $pageSize, $dataDirection, $recordId, $minDateDb, $maxDateDb,
            str_replace("NOT-", "^", $currentStatus), $dataevnt, $datainstance, $datafrm, $fieldName, $flag,
            $response, $queryText, $currMonStatus,
        MonitoringQRModule::NO_QUERY, $incNoTimestamp, $usrname, $dagUser);

$totalCount = $logDataSets['totalCount'];
$events = $logDataSets['events'];
$instances = $logDataSets['instances'];
$dataForms = $logDataSets['dataForms'];
$dataFieldArrs = $logDataSets['dataFieldArrs'];
$dataFlagArrs = $logDataSets['dataFlagArrs'];
$dataResponseArrs = $logDataSets['dataResponseArrs'];
$usrnames = $logDataSets['usrnames'];
$monitoringData = $logDataSets['monitoringData'];
$showingCount = count($monitoringData);

//print_array($monitoringData);
$openCount = count(array_filter($monitoringData, function($entry) {
   return $entry["current_query_status"] == "OPEN";
}));
$disabled = $openCount == 0 ? "disabled" : "";

//filter form
$recordsSelect =
    Records::renderRecordListAutocompleteDropdown($projId, true, 5000,
        "record_id", "x-form-text x-form-field", "width: 150px",
        $recordId, "All records", "","submitForm('record_id')");

$currentStatusSelect = Rendering::MakeCurrentStatusSelect($currentStatus);
$currMonStatusSelect = Rendering::MakeMonitorStatusSelect($currMonStatus);
$dataEventSelect = Rendering::MakeEventSelect($events, $dataevnt);
$dataInstanceSelect = Rendering::MakeInstanceSelect($instances, $datainstance, $noInstance);
$dataFormSelect = Rendering::MakeFormSelect($dataForms, $datafrm);
$dataFieldSelect = Rendering::MakeFieldSelect($dataFieldArrs, $fieldName, $disabled);
$dataFlagSelect = Rendering::MakeFlagSelect($dataFlagArrs, $flag, $disabled);
$dataResponseSelect = Rendering::MakeResponseSelect($dataResponseArrs, $response, $disabled);
$retDirectionSelect = Rendering::MakeRetDirectionSelect($dataDirection);
$pageSizeSelect = Rendering::MakePageSizeSelect($pageSize);
$usrnameSelect = Rendering::MakeUsernameSelect($usrnames, $usrname);
$totPages = ceil($totalCount / $pageSize);
$actPage = (int)$pageNum + 1;

//create the reset to return to default original state
$resetUrl = Utility::getBaseUrl() . "/ExternalModules/?prefix=$moduleName&page=$page&pid=$projId";
$doReset = "window.location.href='$resetUrl';";

$skipFrom = $showingCount == 0 ? 0 : $skipCount + 1;

// adjust skipTo in cases where last page isn't a full page
if($showingCount < $pageSize) {
    $skipTo = $skipCount + $showingCount;
} else {
    $skipTo = $skipCount + (int)$pageSize;
}
$pagingInfo = "records {$skipFrom} to {$skipTo} of {$totalCount}";
$csvExportPage = $module->getUrl('csv_export.php');

echo "
<script>
    function resetForm() {
        showProgress(1);
        $doReset
    }
</script>

<div class='blue' style='padding-left:8px; padding-right:8px; border-width:1px; '>
    <form class='mt-1' id='filterForm' name='queryparams' method='get' action=''>
        <input type='hidden' id='prefix' name='prefix' value='$moduleName'>
        <input type='hidden' id='page' name='page' value='$page'>
        <input type='hidden' id='pid' name='pid' value='$projId'>
        <input type='hidden' id='totpages' name='totpages' value='$totPages'>
        <input type='hidden' id='pagenum' name='pagenum' value='$pageNum'>

        <input type='hidden' id='defaulttimefilter' name='defaulttimefilter' value='$defaultTimeFilter'>
        <input type='hidden' id='onedayago' name='onedayago' value='$oneDayAgo'>
        <input type='hidden' id='oneweekago' name='oneweekago' value='$oneWeekAgo'>
        <input type='hidden' id='onemonthago' name='onemonthago' value='$oneMonthAgo'>
        <input type='hidden' id='oneyearago' name='oneyearago' value='$oneYearAgo'>

        <table border='0'>            
            <tr>
                <td style='width: 100px;'><label for='record_id'>Record id</label></td>
                <td style='width: 190px;'>$recordsSelect</td>
                <td></td>
                <td><label for='currentstatus'>Query status</label></td>
                <td>$currentStatusSelect<td/>                
                <td><label style='margin: 0 10px' for='currmonstatus'>Monitor status</label>$currMonStatusSelect</td>
            </tr>            
            <tr>
                <td><label style='margin-top: 10px' for='min_date'>Min edit date</label></td>
                <td><input id='startdt' style='width: 150px' name='startdt' class='x-form-text x-form-field' type='text' data-df='$userDateFormat' value='$minDate'></td>
                <td><button class='clear-button' type='button' onclick='resetDate(\"startdt\")'><small><i class='fas fa-eraser'></i></small></button></td>

                <td><label for='max_date'>Max edit date</label></td>
                <td><input id='enddt' name='enddt' class='x-form-text x-form-field' type='text' data-df='$userDateFormat' value='$maxDate'></td>
                <td><button style='margin-left: 0' class='clear-button' type='button' onclick='resetDate(\"enddt\")'><small><i class='fas fa-eraser'></i></small></button></td>
                <td>
                    <div style='margin-left:10px; ' class='btn-group bg-white' role='group'>
                        <button type='button' class='btn btn-outline-primary btn-xs $customActive' onclick='setCustomRange()'>Custom range</button>
                        <button type='button' class='btn btn-outline-primary btn-xs $dayActive' onclick='setTimeFrame(\"onedayago\")'>Past day</button>
                        <button type='button' class='btn btn-outline-primary btn-xs $weekActive' onclick='setTimeFrame(\"oneweekago\")'>Past week</button>
                        <button type='button' class='btn btn-outline-primary btn-xs $monthActive' onclick='setTimeFrame(\"onemonthago\")'>Past month</button>
                        <button type='button' class='btn btn-outline-primary btn-xs $yearActive' onclick='setTimeFrame(\"oneyearago\")'>Past year</button>
                    </div>
                </td>
            </tr>
            <tr>
                <td><label for='retdirection'>Order by</label></td>
                <td>$retDirectionSelect</td>
                <td/>
                <td><label for='pagesize' class='mr-2'>Page size</label></td>
                <td>$pageSizeSelect</td>
                <td/>
                <td>
                    <label style='margin: 0 10px' for='inc-no-timestamp'> 
                        <input type='checkbox' id='inc-no-timestamp' name='inc-no-timestamp' value='yes' $incNoTimestamp
                                onchange='onFilterChanged(\"inc-no-timestamp\")'> 
                        <small>always include items without a timestamp</small>
                    </label>
                </td>
            </tr>
            <tr>
                <td><label for='dataevnt'>Event</label></td>
                <td>$dataEventSelect</td>
                <td/>
                <td><label for='datainst'>Instance</label></td>
                <td>$dataInstanceSelect</td>
                <td></td>
                <td><label style='margin-left:10px; width: 70px' for='datafrm'>Form</label>$dataFormSelect</td>                
            </tr>
            <tr>
                <td><label for='datafld'>Field</label></td>
                <td>$dataFieldSelect</td>
                <td/>
                <td><label for='dataflg'>Flag</label></td>
                <td>$dataFlagSelect</td>
                <td></td>
                <td><label style='margin-left:10px; width: 70px' for='dataresp'>Response</label>$dataResponseSelect</td>                
            </tr>
            <tr>
                <td><label for='datafld'>Query</label></td>
                <td>
                    <div style='display: flex; flex-direction: row'>                        
                        <div><input id='dataquerytext' name='dataquerytext' type='text' size='20' maxlength='100'
                            value='$queryText'
                            class='x-form-text x-form-field ui-autocomplete-input' autocomplete='off'
                            onchange='onFilterChanged(\"querytext\")'>
                          </div>                                            
                        <div>                            
                        </div>                        
                    </div>
                </td>                     
                <td>
                <button class='clear-button' type='button' onclick='clearFilter(\"dataquerytext\")'><small><i class='fas fa-eraser'></i></small></button>
                </td>
                <td><label for='usrname'>Username</label></td>
                <td>$usrnameSelect</td>
                </td>
            </tr>
        </table>
        <div class='p-2 mt-1' style='display: flex; flex-direction: row;'>
            <button id='btnprevpage' type='button' class='btn btn-outline-primary btn-xs mr-2' onclick='prevPage()'>
                <i class='fas fa-arrow-left fa-fw' style='font-size: medium; margin-top: 1px;'></i>
            </button>
            <button id='btnnextpage' type='button' class='btn btn-outline-primary btn-xs mr-4' onclick='nextPage()'>
                <i class='fas fa-arrow-right fa-fw' style='font-size: medium; margin-top: 1px;'></i>
            </button>
            $pagingInfo
            <button class='clear-button' style='margin-left: 10px' type='button' onclick='resetForm()'><i class='fas fa-broom'></i> reset</button>
            <div class='ms-auto'>                            
                <button class='jqbuttonmed ui-button ui-corner-all ui-widget export-records' type='button' onclick='cleanUpParamsAndRun(\"$moduleName\", \"$projId\", \"current_page\")'>
                    <img src='" . APP_PATH_WEBROOT  . "Resources/images/xls.gif' style='position: relative;top: -1px;' alt=''>
                    Export current page
                </button>
                <button class='jqbuttonmed ui-button ui-corner-all ui-widget export-records' type='button' onclick='cleanUpParamsAndRun(\"$moduleName\", \"$projId\", \"all_pages\")'>
                    <img src='" . APP_PATH_WEBROOT . "Resources/images/xls.gif' style='position: relative;top: -1px;' alt=''>
                    Export all pages
                </button>
                <button class='jqbuttonmed ui-button ui-corner-all ui-widget export-all' type='button' onclick='cleanUpParamsAndRun(\"$moduleName\", \"$projId\", \"everything\")'>
                    <img src='" . APP_PATH_WEBROOT . "Resources/images/xls.gif' style='position: relative;top: -1px;' alt=''>
                    Export everything ignoring filters
                </button>                
            </div>
        </div>
    </form>    
</div>
<br/>
";


echo "<script type='text/javascript'>
    function cleanUpParamsAndRun(moduleName, projId, exportType) {
        
        //construct the params from the current page params
        let finalUrl = app_path_webroot+'ExternalModules/?prefix=' + moduleName + '&page=csv_export&pid=' + projId;
        let params = new URLSearchParams(window.location.search);
        //ignore some params
        params.forEach((v, k) => {            
            if(k !== 'prefix' && k !== 'page' && k !== 'pid' && k !== 'redcap_csrf_token' ) {                
                finalUrl += '&' + k + '=' + encodeURIComponent(v);                                    
            }
        });
        
        //add the param to determine what to export        
        finalUrl += '&export_type=' + exportType;
        
        window.location.href=finalUrl;                
    }
</script>";

if(count($monitoringData) > 0) {

    //get the user's preferred format
    global $datetime_format;
    $userDateFormat = str_replace('y', 'Y', strtolower($datetime_format));
    if(ends_with($datetime_format, "_24")){
        $userDateFormat = str_replace('_24', ' H:i', $userDateFormat);
    } else {
        $userDateFormat = str_replace('_12', ' H:i a', $userDateFormat);
    }

    //render the form
    echo Rendering::makeTable($projId, $monitoringData, $openCount, $userDateFormat);
} else {
    echo "No records matching your query were found.";
    echo "<script type='text/javascript'>
        //hide export buttons
        document.querySelectorAll('.jqbuttonmed.export-records').forEach(button => {
            button.disabled = true;
        });
    </script>";
}

?>

<style>

    /*#filterForm > table > tbody > tr > td:nth-child(2) {*/
    /*    width: 150px;*/
    /*}*/

    #startdt + button, #enddt + button {
        background-color: transparent;
        border: none;
    }

    .clear-button {
        background-color: transparent;
        border: none;
        color: #0a53be;
        margin-right: 14px;
        margin-left: -10px;
        margin-top: 1px;
    }

    #log-data-entry-event td {
        border-width:1px;
        text-align:left;
        padding:2px 4px 2px 4px;
    }
</style>

<script>

    //gets the user date format which comes from data-df attributes for date picker fields
    let dateFormat = document.getElementById('startdt').getAttribute('data-df');

    $('#startdt').datetimepicker({
        dateFormat: dateFormat,
        showOn: 'button', buttonImage: app_path_images+'date.png',
        onClose: function () {
            if(document.getElementById('startdt').value) {
                document.getElementById('defaulttimefilter').value = 'customrange';
                submitForm('startdt');
            }
        }
    });
    $('#enddt').datetimepicker({
        dateFormat: dateFormat,
        showOn: 'button', buttonImage: app_path_images+'date.png',
        onClose: function () {
            if(document.getElementById('enddt').value) {
                document.getElementById('defaulttimefilter').value = 'customrange';
                submitForm('enddt');
            }
        }
    });

    function setCustomRange() {
        document.getElementById('defaulttimefilter').value = 'customrange';
        document.querySelector('#startdt + button').click();
    }

    function setTimeFrame(timeframe) {
        document.getElementById('startdt').value = document.getElementById(timeframe).value;
        document.getElementById('enddt').value = '';
        document.getElementById('defaulttimefilter').value = timeframe;
        resetPaging();
        submitForm('startdt');
    }

    function resetEditor() {
        let editor = document.getElementById('editor');
        editor.value = '';
    }

    function resetDataForm() {
        let dataForm = document.getElementById('datafrm');
        dataForm.value = '';
    }

    function nextPage() {
        let currPage = document.getElementById('pagenum');
        let totPages = document.getElementById('totpages');
        if (currPage.value < totPages.value) {
            currPage.value = Number(currPage.value) + 1;
            submitForm('pagenum');
        }
    }

    function prevPage() {
        let currPage = document.getElementById('pagenum');
        if(currPage.value > 0) {
            currPage.value = Number(currPage.value) - 1;
            submitForm('pagenum');
        }
    }

    function resetPaging() {
        let currPage = document.getElementById('pagenum');
        currPage.value = 0;
        let totPages = document.getElementById('totpages');
        totPages.value = 0;
    }

    function onDirectionChanged() {
        submitForm('retdirection');
    }

    function onFilterChanged(id) {
        resetPaging();
        submitForm(id);
    }

    // use this when a field changes so can run request on any change
    function submitForm(src) {
        showProgress(1);

        let frm = document.getElementById('filterForm');
        // apply this for the record drop down to work
        let logRec = document.getElementById('record_id');
        logRec.name = 'record_id';

        //clear the csrfToken
        let csrfToken = document.querySelector('input[name="redcap_csrf_token"]');
        csrfToken.value = '';

        frm.submit();
    }

    function resetDate(dateId) {
        if(document.getElementById(dateId).value) {
            document.getElementById(dateId).value = '';
            document.getElementById('defaulttimefilter').value = 'customrange';
            submitForm(dateId);
        }
    }

    function clearFilter(id) {
        if(document.getElementById(id).value) {
            document.getElementById(id).value = '';
            submitForm(id);
        }
    }

    $(window).on('load', function() {

        //handle disabling nav buttons when not applicable
        let currPage = document.getElementById('pagenum');
        let totPages = document.getElementById('totpages');

        document.getElementById('btnprevpage').disabled = currPage.value === '0';
        document.getElementById('btnnextpage').disabled = parseInt(currPage.value) + 1 === parseInt(totPages.value);

    });

</script>






















