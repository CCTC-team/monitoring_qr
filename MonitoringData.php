<?php

namespace CCTC\MonitoringQRModule;

use DateTime;

class MonitoringData
{
    // calls the GetMonitorQueries stored procedure with the given parameters and returns the relevant data
    public static function GetQueries(
        $skipCount, $pageSize, $retDirection, $recordId, $minDate, $maxDate,
        $currentQueryStatus, $eventId, $instance, $dataForm, $dataField,
        $dataFlag, $dataResponse, $queryText, $currentMonStatus,
        $noQueryText, $incNoTimestamp,  $usrname, $dagUser, $runForExport = false
    )
    : array
    {

        global $module;
        global $conn;

        $projId = $module->getProjectId();
        $queryFieldSuffix = $module->getProjectSetting("monitoring-field-suffix");
        $requiresVerificationIndex = $module->getProjectSetting("monitoring-requires-verification-key");

        $retDirection = $retDirection == null ? "desc" : $retDirection;
        $recordId = $recordId == null ? "null" : "'$recordId'";
        $minDate = $minDate == null ? "null" : $minDate;
        $maxDate = $maxDate == null ? "null" : $maxDate;
        $currentQueryStatus = $currentQueryStatus == null || $currentQueryStatus == "ANY" ? "null" : "'$currentQueryStatus'";
        $currentMonStatus = $currentMonStatus == null || $currentMonStatus == "any" ? "null" : "$currentMonStatus";
        $eventId = $eventId == null ? "null" : $eventId;
        $instance = $instance == null ? "null" : $instance;
        $dataForm = $dataForm == null ? "null" : "'$dataForm'";
        $dataField = $dataField == null ? "null" : "'$dataField'";
        $dataFlag = $dataFlag == null ? "null" : "'$dataFlag'";
        $dataResponse = $dataResponse == null ? "null" : "'$dataResponse'";
        $dataResponse = $dataResponse == "'no-response'" ? "'no response'" : $dataResponse;
        $queryText = $queryText == null ? "null" : "'$queryText'";
        $incNoTimestamp = $incNoTimestamp === "checked" ? 1 : 0;
        $usrname = $usrname == null ? "null" : "'$usrname'";
        $dagUser = $dagUser == null ? "null" : "'$dagUser'";

        $query = "call GetMonitorQueries('$queryFieldSuffix', $skipCount, $pageSize, $projId, '$retDirection', $recordId, 
                $minDate, $maxDate, $currentQueryStatus, $eventId, $instance, $dataForm, $dataField, $dataFlag,
                $dataResponse, $queryText, $currentMonStatus, '$noQueryText', $requiresVerificationIndex, $incNoTimestamp,
                $usrname, $dagUser);";

        $currentIndex = 0;

        $totalCount = 0;
        $events = array();
        $instances = array();
        $monitoringData = array();
        $dataForms = array();
        $dataFieldArrs = array();
        $dataFlagArrs = array();
        $dataResponseArrs = array();
        $usrnames = array();

        if (mysqli_multi_query($conn, $query)) {
            do {
                if ($result = mysqli_store_result($conn)) {

                    //total number of records
                    if($currentIndex == 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $totalCount = $row['total_count'];
                        }
                    }

                    //the distinct list of event id and event name
                    if($currentIndex == 1) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $events[] =
                                [
                                    "eventId" => $row['event_id'],
                                    "eventName" => $row['event_name'],
                                ];
                        }
                    }

                    //get the distinct list of instances
                    if($currentIndex == 2) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $instances[] = $row['instance'];
                        }
                    }

                    //get the distinct list of data forms
                    if($currentIndex == 3) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $dataForms[] = $row['form_name'];
                        }
                    }

                    //get the list of data fields - this needs further processing to convert to
                    //a distinct array from a list of json arrays
                    if($currentIndex == 4) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $dataFieldArrs[] = $row['fieldArr'];
                        }
                    }

                    //get the list of data flags - this needs further processing to convert to
                    //a distinct array from a list of json arrays
                    if($currentIndex == 5) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $dataFlagArrs[] = $row['flagArr'];
                        }
                    }

                    //get the list of data responses - this needs further processing to convert to
                    //a distinct array from a list of json arrays
                    if($currentIndex == 6) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $dataResponseArrs[] = $row['responseArr'];
                        }
                    }

                    //get the list of usernames - this needs further processing to convert to
                    //a distinct array from a list of json arrays
                    if($currentIndex == 7) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $usrnames[] = $row['username'];
                        }
                    }

                    //the results
                    if($currentIndex == 8) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $monitoringData[] =
                                [
                                    "urn" => $row['urn'],
                                    "ts" => $row['ts'],
                                    "username" => $row['username'],
                                    "current_query_status" => $row['current_query_status'],
                                    "mon_stat_value" => $row['mon_stat_value'],
                                    "record" => $row['record'],
                                    "form_name" => $row['form_name'],
                                    "event_id" => $row['event_id'],
                                    "event_name" => $row['event_name'],
                                    "instance" => $row['instance'],
                                    "comment" => $row['comment'],
                                    "field_name" => $row['field_name']
                                ];
                        }
                    }

                    mysqli_free_result($result);

                    $currentIndex++;
                }
            } while (mysqli_next_result($conn));
        } else {
            echo "Error: " . $conn->error;
        }

        if($runForExport) {
            return $monitoringData;
        } else {
            return
                [
                    "totalCount" => $totalCount,
                    "events" => $events,
                    "instances" => $instances,
                    "dataForms" => $dataForms,
                    "dataFieldArrs" => $dataFieldArrs,
                    "dataFlagArrs" => $dataFlagArrs,
                    "dataResponseArrs" => $dataResponseArrs,
                    "usrnames" => $usrnames,
                    "monitoringData" => $monitoringData,

                ];
        }
    }

    // returns all the dags for the current project and user
    // if the username is not given, just assumes the current user
    public static function GetUserDags(bool $getAll, ?string $username): array
    {
        global $module;

        $projId = $module->getProjectId();

        // if getting for a specific user set the username for current user if not given
        if(!$getAll && $username == null)
        {
            $user = $module->getUser();
            $username = $user->getUsername();
        }

        $getForAllSql = "";
        if(!$getAll) {
            $getForAllSql = "and u.username = ?";
        }

        $query = "
        select 
            g.*, u.username 
        from 
            redcap_data_access_groups g, 
            (
                select project_id, group_id, username from redcap_data_access_groups_users
                union
                select project_id, group_id, username from redcap_user_rights
            )  u
        where
            g.group_id = u.group_id 
            and u.project_id = g.project_id 
            and g.project_id = ?
            " . $getForAllSql . ";";

        // add the username param if needed
        $p = [$projId];
        if(!$getAll) {
            $p[] = $username;
        }
        $result = $module->query($query, $p);

        $userDags = array();

        while ($row = db_fetch_assoc($result))
        {
            $userDag = [];
            $userDag['project_id'] = $row['project_id'];
            $userDag['username'] = $row['username'];
            $userDag['group_name'] = $row['group_name'];
            $userDag['group_id'] = $row['group_id'];

            $userDags[] = $userDag;
        }

        return $userDags;
    }
}

