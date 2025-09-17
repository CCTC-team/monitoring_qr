<?php

namespace CCTC\MonitoringQRModule;

use DateTime;
use DataQuality;
use Exception;
use REDCap;
use ExternalModules\AbstractExternalModule;
require_once APP_PATH_DOCROOT . "/Classes/Language.php";
use Language;

class MonitoringQRModule extends AbstractExternalModule
{

    /*
        See README.md file for details of how this module works

        TODO: need to implement language
     */

    public const NO_QUERY = 'NONE';

    function exec($query): void
    {
        db_query($query);
    }

    function execFromFile($file): void
    {
        $sql = file_get_contents(dirname(__FILE__) . "/sql-setup/$file");
        db_query($sql);
    }

    const HookFilePath = APP_PATH_DOCROOT . "/Classes/Hooks.php";
    const HookCode =
'//****** inserted by Monitoring QR module ******
public static function redcap_save_record_mon_qr($result){}
//****** end of insert ******' . PHP_EOL;
    const HookSearchTerm = '		// Call the appropriate method to process the return values, then return anything returned by the custom function
		return call_user_func_array(__CLASS__ . \'::\' . $function_name, array($result));
	}
';

    const DataEntryFilePath = APP_PATH_DOCROOT . "/Classes/DataEntry.php";
    const DataEntryCode =
'//****** inserted by Monitoring QR module ******
Hooks::call(\'redcap_save_record_mon_qr\', array($field_values_changed, PROJECT_ID, $fetched, $_GET[\'page\'], $_GET[\'event_id\'], $group_id, ($isSurveyPage ? $_GET[\'s\'] : null), $response_id, $_GET[\'instance\']));
//****** end of insert ******' . PHP_EOL;
    const DataEntrySearchTerm = '            if (!is_numeric($group_id)) $group_id = null;
            Hooks::call(\'redcap_save_record\', array(PROJECT_ID, $fetched, $_GET[\'page\'], $_GET[\'event_id\'], $group_id, ($isSurveyPage ? $_GET[\'s\'] : null), $response_id, $_GET[\'instance\']));
        }';

    const DataQualityFilePath = APP_PATH_DOCROOT . "/Resources/js/DataQuality.js";
    const DataQualityCode =
'//****** inserted by Monitoring QR module ******
hideCommentsButton();
//****** end of insert ******' . PHP_EOL;
    const DataQualitySearchTerm = "// Modify URL without reloading page
		modifyURL(app_path_webroot+page+'?'+query_string);
    ";

    const ShowHistory =
    "<button id='show-hide-history-button' class='btn btn-secondary btn-xs' type='button'
                style='width: 90px'
                 onclick='showHistory()'>Show history</button>";

    //adds the $insertCode into the $filePath after the $searchTerm
    function addCodeToFile($filePath, $searchTerm, $insertCode) : void
    {
        $file_contents = file($filePath);
        $found = false;

        $searchArray = explode("\n", $searchTerm);
        $matched = 0;

        foreach ($file_contents as $index => $line) {
            //increment $matched so checks next line on next iteration
            if (str_contains($line, $searchArray[$matched])) {
                $matched++;
            }

            //if all the lines were found then mark as found
            if($matched == count($searchArray) - 1) {
                array_splice($file_contents, $index + 1, 0, $insertCode);
                $found = true;
                break;
            }
        }

        //write it back if was found
        if ($found) {
            file_put_contents($filePath, implode('', $file_contents));
        }
    }

    //removes the inserted hook in the Hooks.php file
    function removeCodeFromFile($filePath, $removeCode) : void
    {
        $file_contents = file_get_contents($filePath);
        if(str_contains($file_contents, $removeCode)) {
            $modified_contents = str_replace($removeCode, "", $file_contents);
            file_put_contents($filePath, $modified_contents);
        }
    }

    function redcap_module_system_enable($version): void
    {
        //just creates the required sql stored procedure
        self::execFromFile("0010__create_GetMonitorQueries.sql");

        //adds the code to the files as needed
        self::addCodeToFile(self::HookFilePath, self::HookSearchTerm, self::HookCode);
        self::addCodeToFile(self::DataEntryFilePath, self::DataEntrySearchTerm, self::DataEntryCode);
        self::addCodeToFile(self::DataQualityFilePath, self::DataQualitySearchTerm, self::DataQualityCode);
    }


    function redcap_module_system_disable($version): void
    {
        //just drops the sql stored proc required for the module to work
        self::exec("drop procedure if exists GetMonitorQueries;");

        //removes the previously added code
        self::removeCodeFromFile(self::HookFilePath, self::HookCode);
        self::removeCodeFromFile(self::DataEntryFilePath, self::DataEntryCode);
        self::removeCodeFromFile(self::DataQualityFilePath, self::DataQualityCode);
    }


    public function validateSettings($settings): ?string
    {
        if (array_key_exists("monitoring-field-suffix", $settings)) {
            if(empty($settings['monitoring-field-suffix'])) {
                return "Monitoring Field Suffix should not be empty";
            }
        }

        if (array_key_exists("monitoring-flags-regex", $settings)) {
            if(empty($settings['monitoring-flags-regex'])) {
                return "Regex used to identify fields that should be monitored should not be empty";
            }
        }

        if (array_key_exists("monitoring-role", $settings)) {
            if(empty($settings['monitoring-role'])) {
                return "Monitoring Role should not be empty";
            }
        }

        if (array_key_exists("data-entry-roles", $settings)) {
            $lastIndex = array_key_last($settings['data-entry-roles']);
            if(empty($settings['data-entry-roles'][$lastIndex])) {
                return "Data Entry Roles in Monitoring QR External Module should not be empty";
            }
        }

        if (array_key_exists("data-manager-role", $settings)) {
            if(empty($settings['data-manager-role'])) {
                return "Data Manager Role in Monitoring QR External Module should not be empty";
            }
        }

        if (array_key_exists("monitoring-field-verified-key", $settings)) {
            if(empty($settings['monitoring-field-verified-key'])) {
                return "Id of monitoring status field for 'Verification complete' should not be empty";
            }
        }

        if (array_key_exists("monitoring-requires-verification-key", $settings)) {
            if(empty($settings['monitoring-requires-verification-key'])) {
                return "Id of monitoring status field for 'Requires verification' should not be empty";
            }
        }

        if (array_key_exists("monitoring-requires-verification-due-to-data-change-key", $settings)) {
            if(empty($settings['monitoring-requires-verification-due-to-data-change-key'])) {
                return "Id of monitoring status field for 'Requires verification due to data change' should not be empty";
            }
        }

        if (array_key_exists("monitoring-not-required-key", $settings)) {
            if(empty($settings['monitoring-not-required-key'])) {
                return "Id of monitoring status field for 'Not required' should not be empty";
            }
        }

        if (array_key_exists("monitoring-verification-in-progress-key", $settings)) {
            if(empty($settings['monitoring-verification-in-progress-key'])) {
                return "Id of monitoring status field for 'Verification in progress' should not be empty";
            }
        }

        if (array_key_exists("trigger-requires-verification-for-change", $settings)) {
            if(empty($settings['trigger-requires-verification-for-change'])) {
                return "The field that captures monitoring status behaviour—where the status is automatically set to 'Requires verification due to data change' should not be empty";
            }
        }

        if (array_key_exists("resolve-issues-behaviour", $settings)) {
            if(empty($settings['resolve-issues-behaviour'])) {
                return "Field to handle the behaviour of monitoring status field in Resolve Issues page should not be empty";
            }
        }

        return null;
    }

    //does the user have the requested role as defined in project set up?
    function userHasRole($roleSettingKey): bool
    {
        $roleId = $this->getProjectSetting($roleSettingKey);
        $user = $this->getUser();
        $rights = $user->getRights();

        return $rights['role_id'] == (int)$roleId;
    }

    function userHasMonitorRole(): bool
    {
        return $this->userHasRole('monitoring-role');
    }

    function userHasDataEntryRole(): bool
    {
        $deRoles = $this->getProjectSetting('data-entry-roles');
        $user = $this->getUser();
        $rights = $user->getRights();

        foreach ($deRoles as $role) {
            if($rights['role_id'] == (int)$role) {
                return true;
            }
        }

        return false;
    }

    function userHasDataManagerRole(): bool
    {
        return $this->userHasRole('data-manager-role');
    }

    public function redcap_module_link_check_display($project_id, $link)
    {
        return $link;
    }

    // can return null. this was required when a new form triggers this function
    // not sure, but think results from a calc text
    function getFormData($regex, $project_id, $record, $fields, $event_id, $instrument, $repeat_instance,
                         $qualHist, $lastQualHistEntry): ?array
    {
        global $Proj;

        //get all data limiting as much as possible using the getData function as it doesn't handle repeat_instance
        $params =
            array(
                'project_id' => $project_id,
                'records' => array($record),
                'fields' => $fields,
                'events' => array($event_id)
            );
        $data = REDCap::getData($params);

        //NOTE: if there is an issue with the Status not being updated when the buttons are clicked, it is likely
        //that the issue is here and the forms _monstat value is not being picked up from the return array
        //from the built in REDCap::getData() function

        $isRepeatingForm = !empty($data[$record]['repeat_instances']);

        if(!$isRepeatingForm) {
            $thisFormData = $data[$record][$event_id];
        } else {
            //the structure of the array depends on project settings and existing instances of the form
            if(!empty($data[$record]['repeat_instances'][$event_id])){
                if(!empty($data[$record]['repeat_instances'][$event_id][$instrument])){
                    if(!empty($data[$record]['repeat_instances'][$event_id][$instrument][$repeat_instance])) {
                        $thisFormData = $data[$record]['repeat_instances'][$event_id][$instrument][$repeat_instance];
                    } else {
                        //set the default new value that is given when new forms shown i.e. 0 for Incomplete
                        $thisFormData["{$instrument}_complete"] = 0;
                    }
                } else {
                    //the form name is not always given when only one form


                    //if a new form, the max repeat instance will be less than the given repeat_instance so
                    //return null to signify to caller that form data not found
                    $instances = array_keys($data[$record]['repeat_instances'][$event_id]['']);
                    $max = max($instances);
                    if($max < $repeat_instance) {
                        return null;
                    }

                    $thisFormData = $data[$record]['repeat_instances'][$event_id][''][$repeat_instance];
                }
            } else {
                $thisFormData = $data[$record][''][$event_id][$instrument][$repeat_instance];
            }
        }

        //if still empty then the form hasn't been created yet so get field names
        if (empty($data)) {
            $fields = REDCap::getFieldNames($instrument);
            foreach ($fields as $field) {
                $thisFormData[$field] = '';
            }
        }

        //get the monitor field json
        $monJson =
            $lastQualHistEntry != null ? json_decode($lastQualHistEntry["comment"], true)
                : null;

        $formInfo = [];

        //add form data
        foreach ($Proj->metadata as $field => $attrs) if (array_key_exists($field, $thisFormData)) {

            $fieldValue = $thisFormData[$field];
            if(is_array($thisFormData[$field])) {
                $fieldValue = "[ " . implode(', ', $thisFormData[$field]) . " ]";
            }

            //field name
            $formInfo[$field] = array("fieldValue" => $fieldValue);

            //field label
            $formInfo[$field]['fieldLabel'] = $attrs['element_label'];

            //flags - both include and ignore
            $regex = empty($monitorIgnoreActionTag)
                ? $regex
                : $regex . "|" . $monitorIgnoreActionTag;

            preg_match_all("/$regex/", $attrs['misc'], $matches);
            if (!empty($matches[0])) {
                $flags = [];
                foreach ($matches[0] as $match) {
                    $flags[] = $match;
                }

                $formInfo[$field]["flags"] = $flags;
            }

            //include the query raised and the response if present
            if ($monJson) {
                for ($i = 0; $i < count($monJson); $i++) {
                    if ($monJson[$i]["field"] == $field) {
                        $formInfo[$field]['query'] = $monJson[$i]['query'];
                        $formInfo[$field]['response'] = $monJson[$i]['response'];
                        $formInfo[$field]['comment'] = $monJson[$i]['comment'];
                    }
                }
            }
        }

        return $formInfo;
    }


    /**
     * @throws Exception
     */
    function getFormInfo($project_id, $instrument, $record, $event_id, $repeat_instance, $monitorIgnoreActionTag): ?array
    {
        //this function can return null when triggering in a new form
        //its probably triggered as a result of a calctext

        $ret = [];
        $nowt = [];

        //get the monitoring field suffix from settings
        $monitoring_field_suffix = $this->getProjectSetting('monitoring-field-suffix');
        if (empty($monitoring_field_suffix)) return $nowt;

        //this is the name of monitoring field
        //the monitoring field can have any name though must end with the given monitoring field suffix
        $fields = REDCap::getFieldNames($instrument);
        $monFields = array_filter($fields, function ($field) use ($monitoring_field_suffix) {
            return str_ends_with($field, $monitoring_field_suffix);
        });

        $cntMonFields = count($monFields);
        if ($cntMonFields > 1) {
            //should never be more than one of these
            throw new Exception("There should never be more than one monitoring field (i.e. fields with suffix $monitoring_field_suffix) on a form");
        }
        if ($cntMonFields == 0) {
            return $nowt;
        }

        $monitorField = reset($monFields);
        $ret["monitorField"] = $monitorField;

        //get the monitor role form settings
        $monitor_role = $this->getProjectSetting('monitoring-role');
        if (empty($monitor_role))
        {
            //still return the monitorField as needed when new form
            $nowt["monitorField"] = $monitorField;
            return $nowt;
        }

        //get the regex for identifying action tags that should be monitored
        $regex = $this->getProjectSetting('monitoring-flags-regex');

        //add the monitoring form ignore action tag if given
        if (!empty($monitorIgnoreActionTag)) {
            $regex = "$regex|$monitorIgnoreActionTag";
        }
        if (empty($regex)){
            //still return the monitorField as needed when new form
            $nowt["monitorField"] = $monitorField;
            return $nowt;
        }

        $ret["regex"] = $regex;
        $ret["fields"] = $fields;

        //get the history of queries
        $dq = new DataQuality();
        $qualHist = $dq->getFieldDataResHistory($record, $event_id, $monitorField, '', $repeat_instance);

        //add full history of the monitor field
        $ret["qualHist"] = $qualHist;

        //add last qual entry and current status
        if (empty($qualHist)) {
            $ret["lastQualHistEntry"] = null;
            $ret["currentQueryStatus"] = self::NO_QUERY;
        } else {
            $ret["lastQualHistEntry"] = end($qualHist);
            $ret["currentQueryStatus"] = $ret["lastQualHistEntry"]['current_query_status'];
        }


        //get form info with value and flags for monitoring
        $formInfo =
            self::getFormData($regex, $project_id, $record, $fields, $event_id, $instrument, $repeat_instance,
                $qualHist, $ret["lastQualHistEntry"]);

        if(!$formInfo) {
            //still return the monitorField as needed when new form
            $nowt["monitorField"] = $monitorField;
            return $nowt;
        }

        $currMonStatValue = $formInfo[$monitorField];

        $ret["formInfo"] = $formInfo;
        $ret["currMonStatValue"] = $currMonStatValue;

        //are any fields flagged for monitoring?
        $ret["flaggedFields"] = array_filter($formInfo, function ($arr) {
            return !empty($arr['flags']);
        });

        return $ret;
    }

    function addCommonJS(): void
    {
        $redcapVersion = "redcap_v" . REDCAP_VERSION;

        echo "<script type='text/javascript'>

//adds an inline monitor query notification
function highlightFieldIfMonitored(field, query) {
    let elem = document.querySelector('#label-' + field);
    if(elem) {
        const monSpan = document.createElement('span');       
        monSpan.setAttribute('style', 'padding: 2px; margin-left: 5px; font-weight: normal');
        
        const icon = document.createElement('i');
        icon.classList.add('fas', 'fa-flag', 'mr-3');
        icon.setAttribute('style', 'color: blue');                
        const mess = document.createElement('span');
        mess.setAttribute('style', 'color: blue');
        let q = query;
        if(query.length > 50) {
            q = query.substring(0, 47) + '...';
        } 
        mess.textContent = q;
        
        monSpan.appendChild(icon);
        monSpan.appendChild(mess);
        elem.insertAdjacentElement('beforeend', monSpan);    
    }    
}

//changes the monitor status
//updates the ui and writes to the db
//reload is required to update ui
function changeMonitoringStatus(ajaxPath, projectId, eventId, record, field, newStatus, instance, instrument, showProgressSpinner = false) {    
    
    let monField = document.querySelector('[name=' + field + ']');
    monField.value = newStatus;    
    
    if(showProgressSpinner) {
        showProgress(1);
    }
    
    //run the ajax query to update the db    
    $.post(ajaxPath, 
    { 
        projectId: projectId, 
        eventId: eventId,
        record: record,
        monitorField: field,
        statusInt: newStatus,
        repeatInstance : instance,
        instrument : instrument
    }, function (data) {        
        console.log('data ' + data.result);        
    })
    
    //reloads the page to refresh ui    
    window.location.reload();
}

//writes the query and optionally updates the monitoring status 
function writeQueryAndChangeStatus(
    ajaxPath, allQueries, field, pid, instance, 
    event_id, record, reopen, status, send_back, 
    newIndex, response, response_requested, instrument,
    changeMonStatFunc) {
    
    showProgress(1);
    
    $.post(app_path_webroot+'DataQuality/data_resolution_popup.php?pid='+pid+'&instance='+instance, 
        { action: 'save', field_name: field, event_id: event_id, record: record,
        comment: allQueries,
        response_requested: response_requested,
        upload_doc_id: null, 
        delete_doc_id: null,
        assigned_user_id: null,
        assigned_user_id_notify_email: 0,
        assigned_user_id_notify_messenger: 0,
        status: status, 
        send_back: send_back,
        response: response, 
        reopen_query: reopen,
        rule_id: null
    }, function(data) {
        if (data === '0') {
            alert(woops);
        } else {                  
            //set the status of the query if function given
            if(changeMonStatFunc) {               
                changeMonStatFunc(ajaxPath, pid, event_id, record, field, newIndex, instance, instrument);
            }
        }
    })
}

//hides the monitoring field icons
function hideMonitoringStatusField(monitorField) {
    document.querySelector('#' + monitorField + '-tr').classList.add('@HIDDEN');
}

//hides the cancel button row when it's not applicable to the role logged in
function hideCancelButtonRow() {       
    let cancelButtonRow = document.querySelector('[value=\'-- Cancel --\']');
    if(cancelButtonRow) {
        cancelButtonRow.parentElement.parentElement.parentElement;
        cancelButtonRow.style.display = 'none';    
    }
}

function makeFieldsReadonly(fields, monitorField) {    
    fields.forEach(function(field) {
        //exclude monitor field and _complete field
        if(field !== monitorField && !field.endsWith('_complete')) {
            document.querySelector('#' + field + '-tr').classList.add('@READONLY');                
        }       
    })
}

//shows the monitor query status - i.e. whether the query on the monitor field is open or closed
//also shows the actual monitor query value - e.g. verified, requires verification due to data change etc. 
function showMonitoringStatus(currentQueryStatus, monStatusValue) {
    
    let elem = document.querySelector('.formtbody');
    if(elem) {        
        const tr = document.createElement('tr');
        
        tr.classList.add('labelrc');        
        const td1 = document.createElement('td');
        td1.setAttribute('style', 'padding-top: 10px; padding-bottom: 10px;');        
        const monSpan = document.createElement('span');
        monSpan.setAttribute('style', 'padding: 2px; margin-left: 5px; font-weight: normal; margin-top: 5px;');        
        const mess = document.createElement('span');
        
        let col = 'blue';
        if(currentQueryStatus === 'CLOSED') {
            col = 'green';
        }
        
        mess.setAttribute('style', 'color: ' + col + ';');
        mess.textContent = 'Monitor query status: ' + currentQueryStatus;
        
        const icon = document.createElement('i');
        icon.classList.add('fas', 'fa-flag', 'mr-3');
        icon.setAttribute('style', 'color: ' + col + ';');
                
        monSpan.appendChild(icon);
        monSpan.appendChild(mess);
        td1.appendChild(monSpan);
        tr.appendChild(td1);
        
        const td2 = document.createElement('td');
        const monSpan2 = document.createElement('span');
        const icon2 = document.createElement('i');
        icon2.classList.add('fas', 'fa-clipboard-question', 'mr-3');
        
        let valCol = 'blue';
        if(monStatusValue === 'Verified' || monStatusValue === 'Not required') {
            valCol = 'green';
        }
        
        icon2.setAttribute('style', 'color: ' + valCol + ';');        
        const mess2 = document.createElement('span');        
        mess2.textContent = monStatusValue;
        td2.setAttribute('style', 'color: ' + valCol + '; font-weight: normal');
        
        //fix to stop the icon showing on its own when there is no resolution status
        //the parts are created so could be improved but this is sufficient and has no impact on performance
        if(monStatusValue) {
            monSpan2.appendChild(icon2);
            monSpan2.appendChild(mess2);            
        }
        
        //need to still append the td2 to keep the style consistent
        td2.appendChild(monSpan2);                                    
        tr.appendChild(td2);
        
        //add a monitoring section header
        const monSectionHeaderRow = document.createElement('tr');
        const monSectHeaderCell = document.createElement('td');        
        monSectHeaderCell.className = 'header';
        monSectHeaderCell.setAttribute('colspan', '2');        
        // Create the inner div
        const div = document.createElement('div');    
        div.setAttribute('data-mlm-type', 'header');
        div.textContent = 'Form Status';        
        // Append div to td
        monSectHeaderCell.appendChild(div);            
        monSectHeaderCell.textContent = 'Monitoring Status';
        
        //add header
        monSectionHeaderRow.appendChild(monSectHeaderCell);
        elem.insertAdjacentElement('beforeend', monSectionHeaderRow);       
        
        //add monitoring info
        elem.insertAdjacentElement('beforeend', tr);
    }      
}

//sets the monitoring status to the given value (note: newValue is id)
function setMonitoringStatus(monitorField, newValue) {
    
    //change the status
    let sel = '#' + monitorField + '-tr select';
    let ele = document.querySelector(sel);
    ele.value = newValue;
    
    //alert the user
    const userAlert = document.createElement('small');    
    userAlert.classList = 'text-success';
    userAlert.innerHTML = '<div>status set automatically to ' + newValue + '</div>';

    ele.insertAdjacentElement('afterend', userAlert);
}

function showHistory() {
    let showHistButton = document.getElementById('show-hide-history-button');    
    if(showHistButton.textContent === 'Show history') {
        document.getElementById('form-query-history').style.display = 'block';
        showHistButton.textContent = 'Hide history';
    } else {
        document.getElementById('form-query-history').style.display = 'none';
        showHistButton.textContent = 'Show history';
    }
}

function addHistoryButton(showHistory) {
    document.addEventListener('DOMContentLoaded', function() {
        let elem = document.querySelector('.formtbody');
        if (elem) {
            // Create a new <td> element
            const tr = document.createElement('tr');
            tr.classList.add('labelrc');
            const td1 = document.createElement('td');
            td1.setAttribute('style', 'padding-top: 10px; padding-bottom: 10px;');
            const td2 = document.createElement('td');
            td2.setAttribute('style', 'padding-top: 10px; padding-bottom: 10px;');
            td2.innerHTML = showHistory;
            tr.appendChild(td1);
            tr.appendChild(td2);
            elem.appendChild(tr);
        }
    });
}

</script>";
    }

    //highlights fields with queries by calling javascript function
    function highlightInline($projSetting, $lastQualHistEntry): void
    {
        //call the js to show the inline queries where set to be shown for monitors
        if ($this->getProjectSetting($projSetting)) {
            $queryJson = json_decode($lastQualHistEntry["comment"]);
            foreach ($queryJson as $json) {
                echo "<script type='text/javascript'> highlightFieldIfMonitored(\"$json->field\",\"$json->query\")</script>";
            }
        }
    }

    //builds the rows for the query table ui and returns the cleaned query content
    function createTableAndQueryContent($formInfo, $monitorField, $monitorIgnoreActionTag,
                                        $isMonitor, $currentQueryStatus, $isDataManager): array
    {
        $header = "";
        $rows = "";
        $queryData = [];

        //get setting to include or exclude non-flagged fields
        $excludeNonFlagged = $this->getProjectSetting('monitors-only-query-flagged-fields');
        //get setting to include or not the field label alongside the field name
        $includeFieldLabel = $this->getProjectSetting('include-field-label-in-inline-form');

        //create content rows
        foreach (array_keys($formInfo["formInfo"]) as $field) {
            $fieldInfo = $formInfo["formInfo"][$field];

            //ignore monitor field, _complete field and any flagged to ignore
            if ($field == $monitorField
                || str_ends_with($field, "_complete")) {
                continue;
            }

            //if excludeNonFlagged is true and field is not flagged ignore it
            $isFlagged = array_key_exists("flags", $formInfo["formInfo"][$field])
                && !in_array($monitorIgnoreActionTag, $formInfo["formInfo"][$field]["flags"]);

            //if not flagged and excluding non flagged fields then ignore and continue
            if ($excludeNonFlagged && !$isFlagged) {
                continue;
            }

            if (array_key_exists("flags", $formInfo["formInfo"][$field])) {
                $flagsStr = implode(" <br/> ", $formInfo["formInfo"][$field]["flags"]);
            } else {
                $flagsStr = "-- not flagged for monitoring --";
            }

            //exclude any monitor ignore fields
            if ($monitorIgnoreActionTag != null && $monitorIgnoreActionTag != ""
                && str_contains($flagsStr, $monitorIgnoreActionTag)) {
                continue;
            }

            if($includeFieldLabel) {
                $fieldTxt = "$field [{$fieldInfo["fieldLabel"]}]";
            } else {
                $fieldTxt = "$field";
            }

            //add the row as required by role and current status
            if ($isMonitor) {
                switch ($currentQueryStatus) {
                    case "OPEN":
                        if (!empty($fieldInfo["query"])) {
                            if ($fieldInfo["response"] == null || $fieldInfo["response"] == "") {
                                $respAndComment = "";
                            } else {
                                if ($fieldInfo["comment"] == null || $fieldInfo["comment"] == "") {
                                    $respAndComment = "<div>" . $fieldInfo["response"] . "</div>";
                                } else {
                                    $respAndComment = "<div><div>" . $fieldInfo["response"] . "</div><br\><div>[" . $fieldInfo["comment"] . "]<div/><div/>";
                                }
                            }

                            $rows .= "<tr>
                            <td style='padding: 5px; word-break: break-word'>$fieldTxt</td>
                            <td style='padding: 5px; word-break: break-word'><div>" . $fieldInfo["fieldValue"] . "<div/></td>
                            <td style='padding: 5px; word-break: break-word'>$respAndComment</td>";
                            if (!empty($fieldInfo["response"])) {
                                $rows .=
                                    "<td>
                                        <select name='mon-q-response-outcome-$field' id='mon-q-response-outcome-$field'>
                                            <option value='accept'>accept</option>
                                            <option value='reraise'>reraise</option>                                    
                                        </select>
                                    </td>
                                    <td style='padding: 5px; word-break: break-word'>
                                        <textarea class='x-form-text x-form-field ' style='width: 100%' id='mon-q-entry-$field' rows='3'>". $fieldInfo["query"] . "</textarea>
                                    </td>
                                ";
                            } else {
                                $rows .= "
                                <td></td>
                                <td style='padding: 5px; word-break: break-word'><div>" . $fieldInfo["query"] . "<div/></td>";
                            }
                            $rows .= "</tr>";
                        }

                        break;
                    case self::NO_QUERY:
                    case "CLOSED":
                        //monitors-only-query-flagged-fields
                        $rows .= "<tr>
                        <td style='padding: 5px'>$fieldTxt</td>
                        <td style='padding: 5px'>$flagsStr</td>
                        <td style='padding: 5px; word-break: break-word'>
                            <textarea class='x-form-text x-form-field ' style='width: 100%' id='mon-q-entry-$field' placeholder='enter monitor query here' rows='3'></textarea>
                        </td>
                        </tr>";
                        break;
                }
            } else {
                //the user is either a data entry user and can respond, or the user is a data manager who can only
                //respond if the config option to do so is set
                if ($currentQueryStatus == "OPEN") {
                    if (!empty($fieldInfo["query"])) {

                        $rows .= "<tr>
                        <td style='padding: 5px; word-break: break-word'>$fieldTxt</td>
                        <td style='padding: 5px; word-break: break-word'><div>" . $fieldInfo["fieldValue"] . "<div/></td>
                        <td style='padding: 5px; word-break: break-word'><div>" . $fieldInfo["query"] . "<div/></td>
                                                                
                        ";

                        //determines whether data managers can also respond to a query - default is not
                        $allowDMToRespondToQueries = $this->getProjectSetting("allow-data-managers-to-respond-to-queries");

                        //if the user is a data manager and the option to allow dms to respond is true, OR the user
                        //is not a dm (i.e. they are data entry user) then include the response options
                        if(($isDataManager && $allowDMToRespondToQueries) || !$isDataManager) {
                            $rows .= "<td>
                            <div>
                                <select style='width: 100%;' name='mon-q-response-$field' id='mon-q-response-$field' title='response-options'
                                    onchange='changeCommentAvailability(\"$field\")'>
                                    <option value='Not responded'></option>
                                    <option value='Value updated as per source'>Value updated as per source</option>
                                    <option value='Value correct as per source'>Value correct as per source</option>
                                    <option value='Value correct, error in source updated'>Value correct, error in source updated</option>
                                    <option value='Missing data not done'>Missing data not done</option>
                                </select>
                                <textarea class='x-form-text x-form-field ' style='width: 100%; margin-top: 5px;' id='mon-q-response-comment-$field' rows='3' placeholder='any comments here'></textarea>
                            </div>
                        </td>";
                        } else {
                            $rows .= "<td><small>You are not permitted to respond</small></td>";
                        }

                        $rows .= "</tr>";
                    }
                }

                //for other statuses, there's nothing for the non monitor to do
            }

            echo
            "<script type='text/javascript'>
                    window.onload = function() {
                        document.querySelectorAll('select[id^=mon-q-response-]').forEach(function(element) {
                            changeCommentAvailability(element.id.replace('mon-q-response-',''));
                        });                        
                    }

                    function changeCommentAvailability(srcFld) {                                                                
                        let choice = document.getElementById('mon-q-response-' + srcFld);      
                        let comm = document.getElementById('mon-q-response-comment-' + srcFld);
                            if(choice && comm) {
                                if(choice.value === 'Value correct, error in source updated' || choice.value === 'Missing data not done') {
                                comm.style.display = 'block';
                            } else {
                                comm.value = '';                            
                                comm.style.display = 'none';
                            }    
                        }
                        
                    }                                                        
                </script>";

            //tidy for use within query box itself
            $flagsStr = str_replace("<br/>", "|", $flagsStr);
            $flagsStr = str_replace("-- not flagged for monitoring --", "unflagged", $flagsStr);

            //strip any markup to allow this to succeed
            //fix for #112. replace any " or ' in the label. This means the history won't show the exact label as these chars
            //will be removed, but better than bombing out and the solution is tricky
            $fieldDataCleaned = str_replace('"', '', str_replace("'", "", (strip_tags($formInfo["formInfo"][$field]["fieldLabel"]))));
            $queryData[] = ["field" => $field, "field_label" => $fieldDataCleaned, "flags" => $flagsStr];
        }

        if($includeFieldLabel) {
            $fieldHeader = "Field [label]";
        } else {
            $fieldHeader = "Field";
        }

        //prefix with header
        if ($isMonitor) {
            switch ($currentQueryStatus) {
                case "OPEN":
                    if (!empty($rows)) {
                        $header = "<tr>
                        <th style='width: 20%;padding: 5px'><strong>$fieldHeader</strong></th>
                        <th style='width: 40%;padding: 5px'><strong>Field value</strong></th>
                        <th style='width: 40%;padding: 5px'><strong>Query response<br/>[comment]</strong></th>
                        <th style='width: 15%;padding: 5px'><strong>Reply</strong></th>
                        <th style='width: 40%;padding: 5px'><strong>Query</strong></th>
                        </tr>";
                    }
                    break;
                case self::NO_QUERY:
                case "CLOSED":
                    $header = "<tr>
                        <th style='width: 20%;padding: 5px'><strong>$fieldHeader</strong></th>
                        <th style='width: 40%;padding: 5px'><strong>Flags</strong></th>
                        <th style='width: 40%;padding: 5px'><strong>Query to raise</strong></th>
                        </tr>";
            }
        } else {
            switch ($currentQueryStatus) {
                case "OPEN":
                    if (!empty($rows)) {
                        $header = "<tr>
                        <th style='width: 20%;padding: 5px'><strong>$fieldHeader</strong></th>
                        <th style='width: 45%;padding: 5px'><strong>Field value</strong></th>
                        <th style='width: 45%;padding: 5px'><strong>Query</strong></th>
                        <th style='width: 40%;padding: 5px'><strong>Response</strong></th>
                        </tr>";
                    }
                    break;
                case self::NO_QUERY:
                case "CLOSED":
                    $header = "<tr>
                        <th style='width: 20%;padding: 5px'><strong>$fieldHeader</strong></th>
                        </tr>";
            }
        }

        return
            [
                "rows" => $header . $rows,
                "queryData" => $queryData
            ];
    }

    /**
     * @throws Exception
     */
    function AsMonitor($project_id, $repeat_instance, $event_id, $record,
                       $formInfo, $monitorIgnoreActionTag, $currMonStatValue, $monitorField,
                       $currentQueryStatus, $lastQualHistEntry, $instrument): array
    {
        //current user is a monitor

        $verReqDueChange = $this->getProjectSetting('monitoring-requires-verification-due-to-data-change-key');
        $verifiedCompletedIndex = $this->getProjectSetting('monitoring-field-verified-key');
        $verificationInProgressIndex = $this->getProjectSetting('monitoring-verification-in-progress-key');
        $verificationNotRequiredIndex = $this->getProjectSetting('monitoring-not-required-key');
        $verRequired = $this->getProjectSetting('monitoring-requires-verification-key');
        $ajaxPath = $this->getUrl("MonQR_ajax.php");
        $qualHist = $formInfo["qualHist"];

        /*
            the ui is determined by the current status of the query
                $mess contains the header message above the table
                $furtherMessContent contains any further content to render after the message
                $rows contains the table content
                $endContent contains anything that should be rendered after the table - i.e. javascript needing the table
         */

        $showHistory = self::ShowHistory;

        //if a query has never been opened or is currently closed, give opportunity to create or reopen
        if ($currentQueryStatus == "CLOSED" || $currentQueryStatus == self::NO_QUERY) {
            //no history to show if new query
            if ($currentQueryStatus == self::NO_QUERY) {
                $showHistory = "";
            }

            echo "<script type='text/javascript'>

//directly raises the query in the system and stores it to the db
function raiseVerificationQuery(ajaxPath, queryContent, field, pid, instance, event_id, record, instrument, reopen, verInProgressIndex) {
        
    //with the given queries, add the value from the query form to the json object    
    let json = JSON.parse(queryContent);    
    let queries = [];
    json.forEach(function(item) {
        let pageQuery = document.getElementById('mon-q-entry-' + item.field);
        if(pageQuery.value) {                                               
            item.query = pageQuery.value;            
            queries.push(item);
        }        
    });
    
    if (queries.length === 0) {
        alert('You have not provided any queries.');
        return;
    }
    
    let allQueries = JSON.stringify(queries);
       
    //apply the changes to db and ui
    writeQueryAndChangeStatus(
        ajaxPath, allQueries, field, pid, instance, 
        event_id, record, reopen, null, 0, 
        verInProgressIndex, null, 1, instrument, changeMonitoringStatus)
}

</script>";

            $requiresVerDueToChange = $formInfo["formInfo"][$formInfo["monitorField"]]["fieldValue"] == $verReqDueChange;
            $requiresVer = $formInfo["formInfo"][$formInfo["monitorField"]]["fieldValue"] == $verRequired;

            $onlyQueryFlaggedFields = $this->getProjectSetting("monitors-only-query-flagged-fields");
            if ($currentQueryStatus == "CLOSED") {
                if ($requiresVerDueToChange) {
                    $mess = "This form has been previously queried and the query closed, but verification is required again due to a data change. Use the buttons to confirm the verification status or raise a further query.";
                } else {
                    if ($onlyQueryFlaggedFields) {
                        $mess = "This form has been previously queried and the query closed. You can reopen the query and raise further queries against flagged fields only. Ignored fields are not shown.";
                    } else {
                        $mess = "This form has been previously queried and the query closed. You can reopen the query and raise further queries against any fields. Ignored fields are not shown.";
                    }
                }
            } else {
                if ($onlyQueryFlaggedFields) {
                    $mess = "This form has never had a monitor query raised. The Monitoring QR module project settings limit the fields you can query to the flagged fields only. Ignored fields are not shown.";
                } else {
                    $mess = "This form has never had a monitor query raised. You can raise queries against any fields using the form below. Ignored fields are not shown.";
                }
            }

            $furtherMessContent = "";

            //create rows and query data
            $rowsAndQueryContent = self::createTableAndQueryContent($formInfo, $monitorField, $monitorIgnoreActionTag,
                true, $currentQueryStatus, false);
            $rows = $rowsAndQueryContent["rows"];
            $queryData = $rowsAndQueryContent["queryData"];
            $sdvText = json_encode($queryData);

            $reopen = count($qualHist) > 0 ? 1 : 0;

            //check if current value is 'requires validation' due to data change, allow the monitor to close
            //as verified not required, or open afresh
            $moreButtons =
                $requiresVerDueToChange || $requiresVer
                    ? "<button class='btn btn-secondary btn-xs ml-3' type='button'
        onclick='changeMonitoringStatus(\"$ajaxPath\", $project_id, $event_id, \"$record\", \"$monitorField\", $verificationNotRequiredIndex, $repeat_instance, \"$instrument\", true )'>
        Close as not required
    </button>
    <button class='btn btn-secondary btn-xs ml-3' type='button'
        onclick='changeMonitoringStatus(\"$ajaxPath\", $project_id, $event_id, \"$record\", \"$monitorField\", $verifiedCompletedIndex, $repeat_instance, \"$instrument\", true )'>
        Close as verified
    </button>"
                    : "";

            //add the monitor button for writing the query
            //note the use of backticks ` to resolve issue 'resolve Uncaught SyntaxError: missing )' when using \"
            $endContent =
                "
<div class='d-flex justify-content-end mt-3 mb-2'>
    $moreButtons
    <button class='btn btn-secondary btn-xs ml-5' type='button'
        onclick='raiseVerificationQuery(`$ajaxPath`, `$sdvText`, `$monitorField`, $project_id, $repeat_instance, $event_id, `$record`, `$instrument`, $reopen, $verificationInProgressIndex )'>
        Raise monitor query
    </button>
</div>";


        } elseif ($currentQueryStatus == "OPEN") {

            //check if a response has been previously given
            $responses = [];
            foreach ($formInfo["formInfo"] as $field => $arr) {
                $responses[] = $arr["response"];
            }

            $waitingForResponse = $lastQualHistEntry["response_requested"];

            $hasResponse = !empty($responses);
            $mess = "Showing queried fields only" . ($hasResponse ? "" : " - waiting for a response");

            echo "<script type='text/javascript'>

//send back the query for further attention, picking up any fields which are marked as re-raise
function sendBackForFurtherAttention(ajaxPath, queryContent, field, pid, instance, event_id, record, instrument, verInProgressIndex) {
    
    //with the given queries, if the reply is re-raised, add the query text and send back
    let json = JSON.parse(queryContent);
    let queries = [];
    json.forEach(function(item) {        
        let replyQuery = document.getElementById('mon-q-response-outcome-' + item.field);
        if(replyQuery) {
            if(replyQuery.value === 'reraise') {
                //get the new value from query field
                let newQuery = document.getElementById('mon-q-entry-' + item.field);
                item.query = newQuery.value;            
                queries.push(item);    
            }
        }        
    });
    
    if(queries.length > 0) {        
        let allQueries = JSON.stringify(queries);        
        //apply the changes to db and ui
        writeQueryAndChangeStatus(
            ajaxPath, allQueries, field, pid, instance, 
            event_id, record, 'OPEN', null, 1, 
            verInProgressIndex, null, 1, instrument, changeMonitoringStatus)
    }
    else {
        alert('There are no queries with a reply of `reraise`. Set the reply dropdown to reraise and update the query text (or leave unchanged) for each field you wish to requery');
    }
}
</script>";

            //create rows and query data
            $rowsAndQueryContent = self::createTableAndQueryContent($formInfo, $monitorField, $monitorIgnoreActionTag,
                "true", $currentQueryStatus, false);
            $rows = $rowsAndQueryContent["rows"];
            $queryData = $rowsAndQueryContent["queryData"];
            $escaped = htmlspecialchars(json_encode($queryData), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            $createArgsForSendBack =
                "'$ajaxPath', '$escaped', '$monitorField', '$project_id', $repeat_instance, 
                '$event_id', '$record', '$instrument', $verificationInProgressIndex";

            //include the send back button if responses have been received
            $sendBackButton =
                $hasResponse && !$waitingForResponse
                    ? "<button class='ml-4 btn btn-secondary btn-xs' type='button'
                    onclick=\"sendBackForFurtherAttention($createArgsForSendBack )\">
                    Send back for further attention
                </button>"
                    : "";

            //create the buttons for 'closing as not required' and 'closing as verified'
            $endContent = "
<div class='d-flex justify-content-end mt-3 mb-2'>
<button class='ml-5 btn btn-secondary btn-xs' type='button'
    onclick='writeQueryAndChangeStatus(
        \"$ajaxPath\",\"query closed as not required\", \"$monitorField\", \"$project_id\", $repeat_instance, \"$event_id\", \"$record\", 0, \"CLOSED\", 0, $verificationNotRequiredIndex, null, 1, \"$instrument\", changeMonitoringStatus)'>
    Close as not required
</button>
<button class='ml-4 btn btn-secondary btn-xs' type='button'
    onclick='writeQueryAndChangeStatus(\"$ajaxPath\",\"query closed as verified\", \"$monitorField\", \"$project_id\", $repeat_instance, \"$event_id\", \"$record\", 0, \"CLOSED\", 0, $verifiedCompletedIndex, null, 1, \"$instrument\", changeMonitoringStatus)'>
    Close as verified
</button>
$sendBackButton
</div>
    ";

            //highlight inline if requested
            self::highlightInline("monitoring-role-show-inline", $lastQualHistEntry);

        } else {
            throw new Exception("not expecting a query status of [$currentQueryStatus]; expecting OPEN, CLOSED or " . self::NO_QUERY);
        }

        // Add Show History Button
        echo "<script type='text/javascript'>addHistoryButton(" . json_encode($showHistory) . ");</script>";

        return
            [
                "message" => $mess,
                "furtherMessageContent" => $furtherMessContent,
                "rows" => $rows,
                "endContent" => $endContent,
                "monStatSetTo" => 0
            ];
    }

    /**
     * @throws Exception
     */
    function NotMonitor($project_id, $repeat_instance, $event_id, $record,
                        $formInfo, $currMonStatValue, $monitorField, $currentQueryStatus, $lastQualHistEntry,
                        $isDataEntry, $isDataManager, $monitorIgnoreActionTag, $instrument): array
    {
        $requireVerKey = (int)$this->getProjectSetting("monitoring-requires-verification-key");
        $notRequireVerKey = (int)$this->getProjectSetting("monitoring-not-required-key");

        //determines whether data managers can also respond to a query - default is not
        $allowDMToRespondToQueries = $this->getProjectSetting("allow-data-managers-to-respond-to-queries");

        $flaggedFields = $formInfo["flaggedFields"];
        $ajaxPath = $this->getUrl("MonQR_ajax.php");
        $qualHist = $formInfo["qualHist"];

        $showHistory = self::ShowHistory;

        echo "<script type='text/javascript'>

//directly raises the query in the system and stores it to the db
function respondToQuery(ajaxPath, queryContent, field, pid, instance, event_id, record, instrument, reopen) {            
    //with the given queries, add the value from the query form to the json object
    
    let json = JSON.parse(queryContent);  
    
    let queries = [];
    let emptyResponse = true;
    json.forEach(function(item) {
        let resp = document.getElementById('mon-q-response-' + item.field);
        if(resp.value) {
            item.response = resp.value;

            if(resp.value != 'Not responded') {
                emptyResponse = false;
            }

            if(resp.value === 'Value correct, error in source updated' || resp.value === 'Missing data not done') {
                let comm = document.getElementById('mon-q-response-comment-' + item.field);
                //only add the comment if comment has a value
                if(comm && comm.value) {                    
                    item.comment = comm.value;
                }
            }

            queries.push(item);
        }        
    });

    if(emptyResponse) {
        alert('You have not provided a response for any of the queries. Please provide a response.');
        return;
    }

    let allQueriesAndResponses = JSON.stringify(queries);

    //apply the changes to db and ui
    writeQueryAndChangeStatus(ajaxPath, allQueriesAndResponses, field, pid, instance, event_id, record, reopen, null, 0, null, 'OTHER', 0, instrument, null);

    window.location.reload();
}
</script>";

        $mess = "";
        $furtherMessContent = "";
        $rows = "";
        $endContent = "";
        $monStatSetTo = 0;

        if ($currentQueryStatus == "OPEN") {
            //even if open, a response may have been sent. if already sent shouldn't be able to resend

            //check if last response is OTHER and the json contains at least one response
            if ($lastQualHistEntry["response"] == "OTHER") {
                //a query has a response?
                $responses = array_filter(json_decode($lastQualHistEntry["comment"]), function ($item) {
                    return $item->response != null || $item->response != "";
                });
                if (count($responses) > 0) {
                    $mess = "You have responded to this query - waiting for review by a monitor";
                } else {
                    throw new Exception("not expecting this. if the response is OTHER then there should also be a responses entry in the json");
                }
            } else {
                $mess = "Showing queried fields only. Waiting for responses.";

                //create rows and query data
                $rowsAndQueryContent = self::createTableAndQueryContent($formInfo, $monitorField, $monitorIgnoreActionTag,
                    false, $currentQueryStatus, $isDataManager);
                $rows = $rowsAndQueryContent["rows"];
                $currQuery = $lastQualHistEntry["comment"];
                $escaped = htmlspecialchars(json_encode($currQuery), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                $createArgsForResponse = "'$ajaxPath', $escaped, '$monitorField', '$project_id', $repeat_instance, '$event_id', '$record', '$instrument'";

                //if the user is data entry, or is a data manager and the option to allow data managers to respond
                //to queries is true, then show the send response button
                if($isDataEntry || ($isDataManager && $allowDMToRespondToQueries)) {
                    $endContent = "
                <div class='d-flex justify-content-end mt-3 mb-2'>
                <button class='ml-5 btn btn-secondary btn-xs' type='button'
                    onclick=\"respondToQuery($createArgsForResponse)\">
                    Send response
                </button>
                </div>                
                                ";
                }

            }

            //highlight inline if requested
            $showInlineForRole = $isDataEntry ? "data-entry-role-show-inline" : "data-manager-role-show-inline";
            self::highlightInline($showInlineForRole, $lastQualHistEntry);
        } elseif ($currentQueryStatus == "CLOSED") {
            $mess = "";
        } elseif ($currentQueryStatus == self::NO_QUERY) {
            //form has no query history
            $showHistory = "";

            //if monitoring status field has no value set it accordingly
            if ($currMonStatValue == null || $currMonStatValue == "") {

                $flaggedNotIgnoredCount = $flaggedFields != null
                    ? count(array_filter($flaggedFields, function ($flaggedField) use ($monitorIgnoreActionTag) {
                        return !in_array($monitorIgnoreActionTag, $flaggedField["flags"]);
                    }))
                    : 0;

                //no flagged fields so set status to not required
                if ($flaggedNotIgnoredCount == 0) {
                    echo "<script type='text/javascript'>setMonitoringStatus(\"$monitorField\", $notRequireVerKey);</script>";
                    $monStatSetTo = $notRequireVerKey;
                    $mess = "No monitoring queries expected as the form has no flagged fields";
                } else {
                    //flagged fields exist so set status to requires monitoring
                    echo "<script type='text/javascript'>setMonitoringStatus(\"$monitorField\", $requireVerKey);</script>";
                    $monStatSetTo = $requireVerKey;
                    $plural = $flaggedNotIgnoredCount == 1 ? "" : "s";
                    $mess = "Expecting monitoring queries to be raised due to $flaggedNotIgnoredCount flagged field$plural";
                }
            }

        } else {
            throw new Exception("not expecting a query status of [$currentQueryStatus]; expecting OPEN, CLOSED or " . self::NO_QUERY);
        }

        // Add Show History Button
        echo "<script type='text/javascript'>addHistoryButton(" . json_encode($showHistory) . ");</script>";

        return
            [
                "message" => $mess,
                "furtherMessageContent" => $furtherMessContent,
                "rows" => $rows,
                "endContent" => $endContent,
                "monStatSetTo" => $monStatSetTo
            ];
    }

    //returns the ui to display the history of the monitor queries
    function getHistoryUi($formInfo): string
    {
        global $datetime_format;
        $userDateFormat = str_replace('y', 'Y', strtolower($datetime_format));
        if(ends_with($datetime_format, "_24")){
            $userDateFormat = str_replace('_24', ' H:i', $userDateFormat);
        } else {
            $userDateFormat = str_replace('_12', ' H:i a', $userDateFormat);
        }

        $projId = $this->getProjectId();

        //get userinfo for the project
        $query = "select ui_id, a.username from redcap_user_information a
            inner join
            redcap_user_rights b
            on a.username = b.username
            where b.project_id = ?;";
        $result = $this->query($query, $projId);
        $userArrays = array();

        while ($row = db_fetch_assoc($result))
        {
            $userArrays[$row['ui_id']] = $row['username'];
        }

        $ui = "<small><table id='monitor-query-data-log' style='table-layout: fixed; width: 100%; word-break: break-word'>";
        $ui .=
            "<tr style='font-weight: bold'>" .
            "<td style='width: 100px;'>Timestamp</td>" .
            "<td style='width: 100px;'>Username</td>" .
            "<td style='width: 120px;'>Query status</td>" .
            "<td>Field [label]</td>" .
            "<td>Query</td>" .
            "<td>Response [comment]</td>" .
            "</tr>";

        foreach (array_reverse($formInfo["qualHist"]) as $hist) {
            $json = json_decode($hist["comment"], true);

            $tsDate = DateTime::createFromFormat('Y-m-d H:i:s', $hist["ts"]);
            $ts = $tsDate->format($userDateFormat);
            $ui_id = $hist["user_id"];

            // get username from user_id
            if (isset($userArrays[$ui_id])) {
                $username = $userArrays[$ui_id];
            }

            if (json_last_error() == JSON_ERROR_NONE) {
                $numRows = count($json);

                $ui .=
                    "<tr>" .
                    "<td rowspan='$numRows'>" . $ts . "</td>" .
                    "<td rowspan='$numRows'>" . $username . "</td>" .
                    "<td rowspan='$numRows'>" . $hist["current_query_status"] . "</td>";

                foreach ($json as $row) {
                    $resp = $row["comment"] !== null && $row["comment"] !== ""
                        ? $row["response"] . "</br>[" . $row["comment"] . "]"
                        : $row["response"];
                    $ui .=
                        "<td>" . $row["field"] . " [" . $row["field_label"] . "]</td>" .
                        "<td>" . $row["query"] . "</td>" .
                        "<td>$resp</td>" .
                        "</tr>";
                }
            } else {
                $ui .=
                    "<tr>" .
                    "<td>" . $ts . "</td>" .
                    "<td>" . $username . "</td>" .
                    "<td>" . $hist["current_query_status"] . "</br>[" . $hist["comment"] . "]</td>" .
                    "</tr>";
            }
        }

        return $ui . "</table></small>";
    }

    /**
     * @throws Exception
     */
    public function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance)
    {
        if (empty($project_id)) return;

        // Retrieve the mandatory fields for the external module from the configuration settings
        $monFieldSuffix = $this->getProjectSetting("monitoring-field-suffix");
        $monFlagRegex = $this->getProjectSetting("monitoring-flags-regex");
        $monRole = $this->getProjectSetting("monitoring-role");
        $deRoles = $this->getProjectSetting("data-entry-roles");
        $dmRole = $this->getProjectSetting("data-manager-role");
        $idVerified = $this->getProjectSetting("monitoring-field-verified-key");
        $idRequiresVerification = $this->getProjectSetting("monitoring-requires-verification-key");
        $idVerificationDataChange = $this->getProjectSetting("monitoring-requires-verification-due-to-data-change-key");
        $idNotRequired = $this->getProjectSetting("monitoring-not-required-key");
        $idVerficationInProgress = $this->getProjectSetting("monitoring-verification-in-progress-key");
        $triggerRequiresVerification = $this->getProjectSetting("trigger-requires-verification-for-change");
        $resolveIssues = $this->getProjectSetting("resolve-issues-behaviour");

         //if the mandatory fields are not set, then do nothing
        if (empty($monFieldSuffix) || empty($monFlagRegex) || empty($monRole) || empty($deRoles[0]) || empty($dmRole)
         || empty($idVerified) || empty($idRequiresVerification) || empty($idVerificationDataChange)
         || empty($idNotRequired) || empty($idVerficationInProgress) || empty($triggerRequiresVerification) || empty($resolveIssues)) {
            echo "<script type='text/javascript'>
                    alert('Please ensure the mandatory fields in the Monitoring QR External Module are configured.');
                </script>";
            return;
        }

        $delayOutcome = $this->delayModuleExecution();
        if($delayOutcome) return;

        global $Proj;

        //get the action tag for ignoring fields
        $monitorIgnoreActionTag = $this->getProjectSetting('ignore-for-monitoring-action-tag');

        //add the js and hide the monitoring field early in case early return
        //hiding the field should still happen
        self::addCommonJS();

        //get the details of the form
        //handles no monitoring field so the process is completely ignored where no monitoring field present
        $formInfo = $this->getFormInfo($project_id, $instrument, $record, $event_id, $repeat_instance, $monitorIgnoreActionTag);

        //auto hide the monitoring status field so can't be interacted with by anyone
        //monitoring field suffix is always given unless there's an error
        $monitorField = $formInfo["monitorField"];

        echo "<script type='text/javascript'>
hideMonitoringStatusField('$monitorField');
</script>";

        //bomb out here though unless some fields have been returned
        if (!isset($formInfo["fields"])) return;

        $isMonitor = self::userHasMonitorRole();
        $isDataEntry = self::userHasDataEntryRole();
        $isDataManager = self::userHasDataManagerRole();


        //set relevant vars

        $currMonStatValue = $formInfo["formInfo"][$monitorField]["fieldValue"];
        $lastQualHistEntry = $formInfo["lastQualHistEntry"];
        $currentQueryStatus = $formInfo["currentQueryStatus"];
        $currentMonStatString = parseEnum($Proj->metadata[$monitorField]['element_enum'])[$currMonStatValue];

        //this array is populated and displayed according to the user role
        $ui = [];

        //adjust the ui unless data entry
        if (!$isDataEntry) {
            $fields = json_encode($formInfo["fields"]);

            //turn off default hiding of cancel row when checked
            $dontHideSaveAndCancel = $this->getProjectSetting("do-not-hide-save-and-cancel-buttons-for-non-data-entry");
            if(!$dontHideSaveAndCancel) {
                echo "<script type='text/javascript'>
//auto hide the cancel button unless the user is data entry user
hideCancelButtonRow();
</script>";
            }

            //turn off default of making all fields (except monitor and form status) readonly when checked
            //NOTE: the same sort of effect can be applied using built in permissions, though the advantage of
            //doing it this way is that form status and monitoring status can be excluded and remain editable
            $dontMakeReadOnly = $this->getProjectSetting("do-not-make-fields-readonly");
            if(!$dontMakeReadOnly) {
                echo "<script type='text/javascript'>
//make all fields readonly
makeFieldsReadonly($fields, '$monitorField');
</script>";
            }
        }

        if ($isMonitor) {
            //run process when user is a monitor
            $ui =
                self::AsMonitor($project_id, $repeat_instance, $event_id, $record,
                    $formInfo, $monitorIgnoreActionTag, $currMonStatValue, $monitorField,
                    $currentQueryStatus, $lastQualHistEntry, $instrument
                );
        } else {
            //run process when user is NOT a monitor
            //note: data entry will always be able to respond, but optional whether data managers can respond though
            //they will be able to view the queries
            if ($isDataEntry || $isDataManager) {
                $ui =
                    self::NotMonitor($project_id, $repeat_instance, $event_id, $record,
                        $formInfo, $currMonStatValue, $monitorField,
                        $currentQueryStatus, $lastQualHistEntry,
                        $isDataEntry, $isDataManager, $monitorIgnoreActionTag, $instrument
                    );
            }
        }

        //display if relevant
        if (\Records::fieldsHaveData($record, $formInfo["fields"], $event_id, $repeat_instance)) {

            //if the records exist, there is already a monitoring status value set in $currentMonStatString

            //display the monitoring ui
            if (!empty($ui["rows"])) {
                echo "
<br/>
<style>#mon-q-fields-table td { border: 1px solid #a7c3f1; padding: 5px; }</style>
<style>#mon-q-fields-table th { border: 1px solid #a7c3f1; padding: 5px; }</style>

<div class='blue d-flex align-items-center' style='width:800px;'>"
                    . $ui["message"]
                    . $ui["furtherMessageContent"]
                    . "</div>
<div id='mon-q-fields-container' class='blue' style='width:800px;'>    
    <table id='mon-q-fields-table' style='table-layout: fixed; width: 100%; word-break: break-word'>" . $ui["rows"]
                    . "</table>" . $ui["endContent"] . "</div>";
            }

            //only shows the monitoring status if there are values - i.e. if new form doesn't show
            echo "<script type='text/javascript'>
    showMonitoringStatus('$currentQueryStatus', '$currentMonStatString');
    </script>";
        }

        $historyUi = self::getHistoryUi($formInfo);
        echo "<br/>
        <style>#monitor-query-data-log td { border: 1px solid #fad42a; padding: 5px; }</style>
        <div id='form-query-history' class='yellow' style='display:none; width: 800px;'>
            <div style='margin-bottom: 10px'>Monitor query history</div>
            $historyUi
        </div>";
    }

    function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance) : void
    {
//        global $module;
//        $module->log('resp id');

        //REDCap::logEvent("Note", "some changes made $response_id", $sql = NULL, "something", $event = NULL);
    }

    /**
     * @throws Exception
     */
    function redcap_save_record_mon_qr($changedFields, $project_id, $record, $instrument, $event_id,
                                 $group_id, $survey_hash, $response_id, $repeat_instance): void
    {
        /*
            - requires a new hook in Hooks.php and new call to new hook in DataEntry.php
            - only applies if the form contains a monitor field and if the current status is verified

            options for changing a verified field back to a 'requires verification due to data change'
             - never - regardless of any data change, never reset the monitor status
             - always - change the status always with any field update regardless of flags or history
             - flagged_only - change the status if the changed field is flagged
             - previously_queried - change the status only if the field has been queried previously
             - previously_queried_or_flagged - change the status only if the field has been queried previously or is flagged
         */

        //get the index for setting the monitoring back to requiring verification due to data change
        $requireVerDataChangeKey = (int)$this->getProjectSetting("monitoring-requires-verification-due-to-data-change-key");
        //get the action tag for ignoring fields
        $monitorIgnoreActionTag = $this->getProjectSetting('ignore-for-monitoring-action-tag');

        $formInfo = $this->getFormInfo($project_id, $instrument, $record, $event_id, $repeat_instance, $monitorIgnoreActionTag);
        if(!$formInfo) {
            return;
        }
        $monitorField = $formInfo["monitorField"];
        //if no monitor field present just return
        if($monitorField == null || $monitorField == "") {
            return;
        }

        //if the current value is not 'verified' then we shouldn't change it so return
        //index meaning form is verified
        $isVerifiedKey = (int)$this->getProjectSetting("monitoring-field-verified-key");
        //current status of monitor field
        $currVerIndex = (int)$formInfo["formInfo"][$monitorField]["fieldValue"];
        if($currVerIndex != $isVerifiedKey) {
            //the current status is not 'verified' so don't change anything and return early
            return;
        }

        //only gets here if the field is marked as verified
        $trigger = $this->getProjectSetting("trigger-requires-verification-for-change");

        //the trigger is never so stop
        if($trigger == "never") {
            return;
        }

        //do nothing if the field being updated is the _complete field or monitoring field, or is ignored by monitoring flag
        //iterate list of changed fields and remove if meet the above criteria
        $monFieldSuffix = $this->getProjectSetting("monitoring-field-suffix");

        //remove _complete and monitor field
        $validChangedFields = array_filter($changedFields, function ($fld) use ($monitorIgnoreActionTag, $monFieldSuffix) {
            return !(str_ends_with($fld, "_complete") || str_ends_with($fld, $monFieldSuffix));
        });

        //get ignored field names
        $ignoredFields = array_keys(array_filter($formInfo["flaggedFields"], function ($flaggedField) use ($monitorIgnoreActionTag) {
            return in_array($monitorIgnoreActionTag, $flaggedField["flags"]);
        }));

        $finalChangedFields = array_diff($validChangedFields, $ignoredFields);

        //if set to always and there are fields that are not ignored and not monitor field or complete field then update
        if($trigger == "always" && count($finalChangedFields) > 0) {
            self::setMonitorStatus($project_id, $event_id, $record, $monitorField, $requireVerDataChangeKey, $repeat_instance, $instrument);
            return;
        }

        $flaggedFields = array_keys($formInfo["flaggedFields"]);
        $affectedFlaggedFields = array_intersect($flaggedFields, $finalChangedFields);

        //if set to flagged, only update when the valid fields are flagged
        if($trigger == "flagged" && count($affectedFlaggedFields) > 0) {
            self::setMonitorStatus($project_id, $event_id, $record, $monitorField, $requireVerDataChangeKey, $repeat_instance, $instrument);
            return;
        }

        //get fields that have previously been queried
        $flds = [];
        foreach ($formInfo["qualHist"] as $q) {
            $jsonArr = json_decode($q["comment"], true);
            foreach ($jsonArr as $j) {
                $flds[] = $j["field"];
            }
        }

        //if set to previously_queried, only update when the field changed has previously had a query raised against it
        $validHasPrevious = array_intersect($flds, $finalChangedFields);
        if($trigger == "previously_queried" && count($validHasPrevious) > 0) {
            self::setMonitorStatus($project_id, $event_id, $record, $monitorField, $requireVerDataChangeKey, $repeat_instance, $instrument);
            return;
        }

        //if set to previously_queried_or_flagged, only update when previously had a query raised against it
        //or the field is flagged
        if($trigger == "previously_queried_or_flagged"
            && (count($validHasPrevious) > 0 || count($affectedFlaggedFields) > 0)) {
            self::setMonitorStatus($project_id, $event_id, $record, $monitorField, $requireVerDataChangeKey, $repeat_instance, $instrument);
            return;
        }
    }

    //sets the monitoring status directly via the REDCap api
    //this function is called in MonQR_ajax when the process is triggered by javascript
    //NOTE: uses same logic as in Enhance Form status module to determine repeat params so if this needs to change
    //due to an issue then so does that
    public static function setMonitorStatus(
        $project_id, $event_id, $record, $monitorField, $requireVerDataChangeKey,
        $repeat_instance, $instrument) : void
    {
        /*
            Note: found lots of issues when trying to implement this. It is important to test different variations
                of repeating events and repeating forms to ensure works in all cases

            - found that $Proj->isLongitudinal is a bit iffy but seems to work when used below
            - redcap_repeat_instrument is always the $instrument but this only needs to be given when is a repeating form
        */

        global $Proj;
        global $module;

        //use the project specific 'record id' - not to be confused with the record number, i.e. the first record
        //number is 1. This specifically refers to the variable name of the very first field in the first form that
        //is used to identify each record or patient. i.e. can change to something else if the user decides to
        //defaults to 'record_id', but often is 'patient_id'
        $record_id_field =  \REDCap::getRecordIdField();

        $json =
            [
                "$record_id_field" => $record,
                "$monitorField" => $requireVerDataChangeKey,
            ];

        //find out some initial facts
        $hasRepeatingFormsEvents = $Proj->hasRepeatingFormsEvents();
        $isRepeatingForm = $Proj->isRepeatingForm($event_id, $instrument);
        $isRepeatingFormOrEvent = $Proj->isRepeatingFormOrEvent($event_id, $instrument);

        //get the event name taking account of whether longitudinal or not
        //mimics Records.php line 5000
        if ($Proj->longitudinal) {
            $eventName =
                $event_id != null
                    ? \Event::getEventNameById($project_id, $event_id)
                    : $Proj->getUniqueEventNames($Proj->firstEventId);
        } else {
            $eventName = $Proj->getUniqueEventNames($Proj->firstEventId);
        }

        //redcap_event_name
        $json["redcap_event_name"] = $eventName;

        //redcap_repeat_instance
        if($isRepeatingForm) {
            $json["redcap_repeat_instance"] = $repeat_instance;
        }

        //redcap_repeat_instance
        if($isRepeatingFormOrEvent) {
            $json["redcap_repeat_instance"] = $repeat_instance;
        }

        //the instrument uses events and this form repeats so need this param too
        if ($hasRepeatingFormsEvents && $isRepeatingForm) {
            $json["redcap_repeat_instrument"] = $instrument;
        }

        $resp = \REDCap::saveData(
            $project_id,
            'json',
            json_encode(array($json)),
            'normal'
        );

        //raise a log entry if errors or warnings
        if(!empty($resp["errors"]) || !empty($resp["warnings"])){
            $data = [];

            if(!empty($resp["errors"])){
                $data["errors"] = json_encode($resp["errors"]);
            }

            if(!empty($resp["warnings"])){
                $data["warnings"] = json_encode($resp["warnings"]);
            }

            $module->log("setMonitorStatus failed to write saveData with errors or warnings", $data);
            $module->log("setMonitorStatus failed - sent data", $json);
        }
    }

    function redcap_every_page_top($project_id)
    {
        //if not Data Quality resolve issues page then do nothing
        if(PAGE != "DataQuality/resolve.php") {
            return;
        }

        /*
            - uses javascript to handle the data quality resolve page to ensure consistency
            - when the setting resolve-issues-behaviour is;
                - removing-row
                    - it removes any queries that relate to monitoring i.e. have the mon status field suffix, by
                        removing the row in the table
                    - due to the impact on the counts in the tab and dropdowns, it simply rewrites those
                        to exclude the counts that are given
                - hiding-button
                    - simply removes the button allowing interaction with the query but leaves everything else as is
         */

        $lang = Language::getLanguage('English');
        $allStatusTypes = $lang['dataqueries_300'];
        $verified = $lang['dataqueries_220'];
        $deverified = $lang['dataqueries_222'];
        $openUnresolved = $lang['dataqueries_186'];
        $openUnresponded = $lang['dataqueries_187'];
        $openResponded = $lang['dataqueries_188'];
        $closed = $lang['dataqueries_189'];

        $monFieldSuffix = $this->getProjectSetting("monitoring-field-suffix");
        $regex = "/\w+$monFieldSuffix/";

        $rowBehaviour = $this->getProjectSetting("resolve-issues-behaviour");
        if($rowBehaviour == "hiding-button"){
            $hideFunction = "keepMonitorRowsButHideButton";
        } else {
            $hideFunction = "removeMonitorRows";
        }

        echo "<script type='text/javascript'>
            
            //script uses a regex to find rows in the table that are monitoring fields and removes them if so
            //uses a regex to match fields that end in the monitoring status suffix
            async function removeMonitorRows() {                   
                let regexMatchMonStatForm = $regex;
                
                let table = document.getElementById('table-dq_resolution_table');
                if(table) {                    
                    let rows = table.getElementsByTagName('tr');
                    let rowsToRemove = [];
                    
                    for (let i = 0; i < rows.length; i++) { 
                        let cells = rows[i].getElementsByTagName('td');   
                        let fieldContent = cells[2].textContent.trim();
                        let match = fieldContent.match(regexMatchMonStatForm);
                        
                        if(match) {                        
                            rowsToRemove.push(i);                        
                        }                                        
                    }
    
                    for (let k = rowsToRemove.length - 1; k >= 0; k--) {
                        table.deleteRow(rowsToRemove[k]);                                            
                    }
                    
                    return rowsToRemove.length;
                }
                
                //nothing happened so just return 0 changes to table
                return 0;
            }
            
            //leaves the row in place, but hides the button that allows a user to interact with the query            
            async function keepMonitorRowsButHideButton() {
                
                let regexMatchMonStatForm = $regex;
                
                let fieldCols = document.querySelectorAll('#table-dq_resolution_table tr td:nth-child(3)');
                for(let i = 0; i < fieldCols.length; i++) { 
                    let fieldContent = fieldCols[i].textContent.trim();                    
                    let match = fieldContent.match(regexMatchMonStatForm);
                    if(match) {                        
                        let siblingButton = fieldCols[i].previousElementSibling.previousElementSibling.querySelector('button');
                        siblingButton.setAttribute('style', 'display: none');
                    }                    
                }    
            } 

//            function hideCountsIfHidingRows() {
//                if('$hideFunction' == 'removeMonitorRows' ) {        
//                    
//                    console.log('hiding values');
//                    //fix the count displays that may be wrong as the monitor queries have been removed
//                    document.getElementById('dq_tab_issue_count').hidden = true;                       
//                    document.querySelector('#choose_status_type option:nth-child(1)').textContent = '$allStatusTypes';
//                    document.querySelector('#choose_status_type option:nth-child(2)').textContent = '$verified';
//                    document.querySelector('#choose_status_type option:nth-child(3)').textContent = '$deverified';
//                    document.querySelector('#choose_status_type option:nth-child(4)').textContent = '$openUnresolved';
//                    document.querySelector('#choose_status_type option:nth-child(5)').textContent = '- $openUnresponded';
//                    document.querySelector('#choose_status_type option:nth-child(6)').textContent = '- $openResponded';
//                    document.querySelector('#choose_status_type option:nth-child(7)').textContent = '$closed';
//                }
//            }
//            
//            //flag to prevent endless trigger
//            let isProcessing = false;
//            
//            //the observer is required to pick up changes to the form and change behaviour accordingly
//            //for monitoring queries, either remove the whole row (and counts) or simply hide button             
//            const observer = new MutationObserver(async () => { 
//                
//                if(isProcessing) {
//                    return;
//                }
//                
//                console.log('Observer triggered!');
//                
//                try {
//                    isProcessing = true;
//                    
//                    // Disconnect observer temporarily while making changes
//                    observer.disconnect();
//                    
//                    
//                    let removedRows = await $hideFunction();                                    
//                    hideCountsIfHidingRows();
//                    
//                    //hide any fields in the field list that are monitoring queries                
//                    let monFields = document.querySelectorAll('#choose_field_rule option[value$=\'$monFieldSuffix\']');
//                    monFields.forEach(field => {
//                        field.hidden = true;
//                    })    
//                } finally {
//                    
//                    //reconnect observer
//                    const targetNode = document.querySelector('#dq_resolution_table > div:first-child');
//                    
//                    console.log('attempt reconnecting');
//                    if (targetNode) {
//                        console.log('attempt reconnecting adding');
//                        observer.observe(targetNode, { subtree: true, childList: true });
//                    } else {
//                        console.log('not reinstated observer');
//                    }
//                    
//                    isProcessing = false;
//                }                                   
//            });
            
            // make sure only runs after document is loaded
            
            //for now, this just hides the buttons as hiding rows is disabled as not working correctly.
            document.addEventListener('DOMContentLoaded', function() {                                                                
                (async function() {
                    let rows = await $hideFunction();                    
                    //hideCountsIfHidingRows();
                })();
                
                //set up the observer for listening to changes in filters
                //gets the filter div not whole doc                
//                const targetNode = document.querySelector('#dq_resolution_table > div:first-child');
//                if (targetNode) {
//                    observer.observe(targetNode, { subtree: true, childList: true });
//                }                                
            });
  
            // function to hide he comment button in Resolve Issues page
            function hideCommentsButton() {
                document.querySelectorAll('tr').forEach(function(row) {
                    const thirdCol = row.querySelectorAll('td')[2];
                    if (thirdCol && thirdCol.innerText.split(/\s+/)[1].endsWith('$monFieldSuffix')) {
                        const button = row.querySelector('.jqbuttonmed');
                        if (button) {
                            button.style.display = 'none';
                        }
                    }
                });
            }

//this could potentially be a solution but not reall           
//            (function() {
//                const originalXHR = window.XMLHttpRequest;
//            
//                function customXHR() {
//                    const xhr = new originalXHR();
//            
//                    xhr.addEventListener('readystatechange', function() {
//                        if (xhr.readyState === 4) { // 4 means the request is completed
//                            console.log('AJAX request completed:');
//                            //console.log('Response:', xhr.responseText);
//                            
//                        let monFields = document.querySelectorAll('#choose_field_rule option[value$=\'$monFieldSuffix\']');
//                        monFields.forEach(field => {
//                            field.hidden = true;
//                        });    
//                            
//                        (async function() {
//                            let rows = await $hideFunction();                    
//                            hideCountsIfHidingRows();
//                        })();
//                        }
//                    });
//            
//                    return xhr;
//                }
//            
//                window.XMLHttpRequest = customXHR;
//            })();
</script>";
    }
}

