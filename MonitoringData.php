<?php

namespace CCTC\MonitoringQRModule;

use DateTime;

class MonitoringData
{
    // Escapes a string value for safe SQL inclusion, or returns 'null' if null
    private static function escapeString($conn, $value): string
    {
        if ($value === null) {
            return 'null';
        }
        return "'" . mysqli_real_escape_string($conn, (string)$value) . "'";
    }

    // Validates and returns an integer value, or 'null' if null/invalid
    private static function escapeInt($value): string
    {
        if ($value === null) {
            return 'null';
        }
        return (string)(int)$value;
    }

    // Validates direction parameter (only allows 'asc' or 'desc')
    private static function validateDirection($value): string
    {
        $value = strtolower((string)$value);
        return in_array($value, ['asc', 'desc']) ? $value : 'desc';
    }

    // Validates query status parameter
    private static function validateQueryStatus($value): ?string
    {
        if ($value === null || $value === 'ANY') {
            return null;
        }
        $allowed = ['OPEN', 'CLOSED', 'NONE', 'NOT-OPEN', 'NOT-CLOSED', 'NOT-NONE'];
        return in_array(strtoupper((string)$value), $allowed) ? strtoupper((string)$value) : null;
    }

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

        $projId = (int)$module->getProjectId();
        $queryFieldSuffix = $module->getProjectSetting("monitoring-field-suffix");
        $requiresVerificationIndex = (int)$module->getProjectSetting("monitoring-requires-verification-key");

        // Sanitize and validate all inputs
        $safeRetDirection = self::validateDirection($retDirection);
        $safeSkipCount = self::escapeInt($skipCount);
        $safePageSize = self::escapeInt($pageSize);
        $safeRecordId = ($recordId === null || $recordId === '') ? 'null' : self::escapeString($conn, $recordId);
        $safeMinDate = $minDate === null ? 'null' : self::escapeInt($minDate);
        $safeMaxDate = $maxDate === null ? 'null' : self::escapeInt($maxDate);

        $validatedQueryStatus = self::validateQueryStatus($currentQueryStatus);
        $safeCurrentQueryStatus = $validatedQueryStatus === null ? 'null' : self::escapeString($conn, $validatedQueryStatus);

        $safeCurrentMonStatus = ($currentMonStatus === null || $currentMonStatus === 'any') ? 'null' : self::escapeInt($currentMonStatus);
        $safeEventId = self::escapeInt($eventId);
        $safeInstance = self::escapeInt($instance);
        $safeDataForm = $dataForm === null ? 'null' : self::escapeString($conn, $dataForm);
        $safeDataField = $dataField === null ? 'null' : self::escapeString($conn, $dataField);
        $safeDataFlag = $dataFlag === null ? 'null' : self::escapeString($conn, $dataFlag);

        // Handle special case for response
        $responseValue = $dataResponse === 'no-response' ? 'no response' : $dataResponse;
        $safeDataResponse = $responseValue === null ? 'null' : self::escapeString($conn, $responseValue);

        $safeQueryText = $queryText === null ? 'null' : self::escapeString($conn, $queryText);
        $safeIncNoTimestamp = $incNoTimestamp === 'checked' ? 1 : 0;
        $safeUsrname = $usrname === null ? 'null' : self::escapeString($conn, $usrname);
        $safeDagUser = $dagUser === null ? 'null' : self::escapeString($conn, $dagUser);
        $safeQueryFieldSuffix = mysqli_real_escape_string($conn, (string)$queryFieldSuffix);
        $safeNoQueryText = mysqli_real_escape_string($conn, (string)$noQueryText);
        $query = "call GetMonitorQueries('$safeQueryFieldSuffix', $safeSkipCount, $safePageSize, $projId, '$safeRetDirection', $safeRecordId,
                $safeMinDate, $safeMaxDate, $safeCurrentQueryStatus, $safeEventId, $safeInstance, $safeDataForm, $safeDataField, $safeDataFlag,
                $safeDataResponse, $safeQueryText, $safeCurrentMonStatus, '$safeNoQueryText', $requiresVerificationIndex, $safeIncNoTimestamp,
                $safeUsrname, $safeDagUser);";
        // echo "QUERY: " . $query . "<br>";

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

