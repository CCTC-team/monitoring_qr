<?php

namespace CCTC\MonitoringQRModule;

use DateTime;

class Rendering
{
    //create the base row
    static function createRow($numRows, $ts, $username, $summaryStatus, $monStatus, $arr, $projId): string
    {
        $baseUrl = Utility::getBaseUrl();
        $goLink = Utility::MakeFormLink($baseUrl, $projId, $arr['record'], $arr['event_id'], $arr['form_name'], $arr['instance']);

        return
            "<tr>
        <td rowspan='$numRows'>&nbsp;&nbsp;{$goLink}</td>
        <td rowspan='$numRows'>$ts</td>
        <td rowspan='$numRows'>$username</td>
        <td rowspan='$numRows'>$summaryStatus</td>
        <td rowspan='$numRows'>$monStatus</td>
        <td rowspan='$numRows'>{$arr['record']}</td>
        <td rowspan='$numRows'>{$arr['form_name']}</td>
        <td rowspan='$numRows'>{$arr['event_name']} [{$arr['event_id']}]</td>
        <td rowspan='$numRows'>{$arr['instance']}</td>";
    }

    //make the row for when the query is closed
    static function createClosedRow($ts, $username, $summaryStatus, $monStatus, $arr, $projId): string
    {
        return
            self::createRow(1, $ts, $username, $summaryStatus, $monStatus, $arr, $projId)
            . "</tr>";
    }

    static function createOpenRow($rowI, $numRows, $ts, $username, $summaryStatus, $monStatus, $arr, $field, $fieldLabel, $flags,
                                  $query, $response, $comment, $projId): string
    {
        $idData = "";
        if($rowI == 1) {
            $idData = self::createRow($numRows, $ts, $username, $summaryStatus, $monStatus, $arr, $projId);
        }
        $response = $comment === null || $comment === "" ? "$response" : "$response<br/>[$comment]";
        return
            "<tr>$idData"
            . "<td>$field [$fieldLabel]</td>"
            . "<td>$flags</td>"
            . "<td>$query</td>"
            . "<td>$response</td>"
            . "</tr>";
    }

    //builds and returns the main display table
    public static function makeTable($projId, $monitoringData, $openCount, $userDateFormat) : string
    {

        //only show the headers for open queries when there are at least one
        $openHeaders =
            $openCount > 0
            ? "<td class='header' style='width:60px;'>Field [label]</td>
    <td class='header' style='width:60px;'>Flags</td>
    <td class='header' style='width:80px;'>Query</td>
    <td class='header' style='width:80px;'>Response</br>[comment]</td>"
            : "";


        $ret = "
<style>#monitor-query-data-log-table td { border: 1px solid #cccccc; padding: 5px; }</style>
<br/>
<div >
<table id='monitor-query-data-log-table' style='table-layout: fixed;width:95%; word-break: break-word'><tr>
    <td class='header' style='width:20px;'>
    <td class='header' style='width:40px;'>Timestamp</td>
    <td class='header' style='width:40px;'>Username</td>
    <td class='header' style='width:50px;'>Query status</td>
    <td class='header' style='width:50px;'>Monitor status</td>
    <td class='header' style='width:30px;'>Record</td>
    <td class='header' style='width:50px;'>Form</td>
    <td class='header' style='width:50px;'>Event name [event id]</td>
    <td class='header' style='width:30px;'>Instance</td>
    $openHeaders"
            .
            "</tr>";

        $noDate = "--";

        foreach ($monitoringData as $arr) {

            $json = json_decode($arr['comment']);
            $tsString = (string)$arr['ts'];
            $username = $arr['username'];

            //if the query has never been opened there is no date
            if($tsString == null || $tsString == "") {
                $ts = $noDate;
            } else {
                $tsDate = \DateTime::createFromFormat('YmdHis', $tsString);
                $ts = $tsDate->format($userDateFormat);
            }
            $monStatus = explode(",", $arr['mon_stat_value'])[1];

            if(json_last_error() == JSON_ERROR_NONE) {
                $summaryStatus = $arr['current_query_status'];

                $rowI = 1;
                $numRows = count($json);
                foreach ($json as $f) {
                    $ret .=
                        self::createOpenRow($rowI, $numRows, $ts, $username, $summaryStatus, $monStatus, $arr, $f->field, $f->field_label,
                            str_replace(" | ", "<br/>", $f->flags), $f->query, $f->response, $f->comment, $projId);
                    $rowI += 1;
                }
            } else {
                $summaryStatus =
                    $ts == $noDate
                    ? $arr['current_query_status']
                    : $arr['current_query_status'] . "<br/>[{$arr['comment']}]";
                $ret .= self::createClosedRow($ts, $username, $summaryStatus, $monStatus, $arr, $projId);
            }
        }

        $ret .= "</table></div>";
        return $ret;
    }

    public static function displayFromParts($part1, $part2): string
    {
        return $part1 == null ? "" : $part2 . " [" . $part1 . "]";
    }

    public static function MakePageSizeSelect($pageSize) : string
    {
        $sel10 = $pageSize == 10 ? "selected" : "";
        $sel25 = $pageSize == 25 ? "selected" : "";
        $sel50 = $pageSize == 50 ? "selected" : "";
        $sel100 = $pageSize == 100 ? "selected" : "";
        $sel250 = $pageSize == 250 ? "selected" : "";

        return "
        <select id='pagesize' name='pagesize' class='x-form-text x-form-field' onchange='onFilterChanged(\"pagesize\")'>
            <option value='10' $sel10>10</option>
            <option value='25' $sel25>25</option>
            <option value='50' $sel50>50</option>
            <option value='100' $sel100>100</option>
            <option value='250' $sel250>250</option>
        </select>";
    }

    public static function MakeRetDirectionSelect($dataDirection) : string
    {
        $descSel = $dataDirection == "desc" ? "selected" : "";
        $ascSel = $dataDirection == "asc" ? "selected" : "";

        return "
        <select id='retdirection' name='retdirection' class='x-form-text x-form-field' onchange='onDirectionChanged()'>
            <option value='desc' $descSel>Descending</option>
            <option value='asc' $ascSel>Ascending</option>
        </select>";
    }

    public static function MakeCurrentStatusSelect($currentStatus) : string
    {
        $anySel = $currentStatus == "ANY" ? "selected" : "";
        $openSel = $currentStatus == "OPEN" ? "selected" : "";
        $notOpenSel = $currentStatus == "NOT-OPEN" ? "selected" : "";
        $closedSel = $currentStatus == "CLOSED" ? "selected" : "";
        $notClosedSel = $currentStatus == "NOT-CLOSED" ? "selected" : "";
        $noQuery = MonitoringQRModule::NO_QUERY;
        $noQuerySel = $currentStatus == $noQuery ? "selected" : "";
        $notNoQuerySel = $currentStatus == "NOT-$noQuery" ? "selected" : "";

        return "
        <select id='currentstatus' name='currentstatus' class='x-form-text x-form-field' onchange='onFilterChanged(\"currentstatus\")'>
            <option value='ANY' $anySel>Any status</option>
            <option value='OPEN' $openSel>OPEN</option>
            <option value='CLOSED' $closedSel>CLOSED</option>
            <option value='$noQuery' $noQuerySel>$noQuery</option>
            <option value='NOT-OPEN' $notOpenSel>not OPEN</option>
            <option value='NOT-CLOSED' $notClosedSel>not CLOSED</option>
            <option value='NOT-$noQuery' $notNoQuerySel>not $noQuery</option>
        </select>";
    }

    public static function MakeMonitorStatusSelect($currentStatus) : string
    {
        $anySel = $currentStatus == "any" ? "selected" : "";
        $verSel = $currentStatus == "1" ? "selected" : "";
        $notVerSel = $currentStatus == "-1" ? "selected" : "";
        $reqVerSel = $currentStatus == "2" ? "selected" : "";
        $notReqVerSel = $currentStatus == "-2" ? "selected" : "";
        $reqVerDueChangeSel = $currentStatus == "3" ? "selected" : "";
        $notReqVerDueChangeSel = $currentStatus == "-3" ? "selected" : "";
        $notReqSel = $currentStatus == "4" ? "selected" : "";
        $notNotReqSel = $currentStatus == "-4" ? "selected" : "";
        $inProgSel = $currentStatus == "5" ? "selected" : "";
        $notInProgSel = $currentStatus == "-5" ? "selected" : "";

        return "
        <select id='currmonstatus' name='currmonstatus' class='x-form-text x-form-field' onchange='onFilterChanged(\"currmonstatus\")'>
            <option value='any' $anySel>Any monitor status</option>
            <option value='1' $verSel>Verified</option>
            <option value='2' $reqVerSel>Requires verification</option>
            <option value='3' $reqVerDueChangeSel>Requires verification due to data change</option>
            <option value='4' $notReqSel>Not required</option>
            <option value='5' $inProgSel>Verification in progress</option>
            <option value='-1' $notVerSel>not Verified</option>
            <option value='-2' $notReqVerSel>not Requires verification</option>
            <option value='-3' $notReqVerDueChangeSel>not Requires verification due to data change</option>
            <option value='-4' $notNotReqSel>not Not required</option>
            <option value='-5' $notInProgSel>not Verification in progress</option>
        </select>";
    }

    public static function MakeEventSelect($events, $selected) : string
    {
        $anySelected = $selected == null ? "selected": "";
        $evnts = "<option value='' $anySelected>any event</option>";
        foreach ($events as $evnt) {
            $id = $evnt["eventId"];
            $display = self::displayFromParts($id, $evnt["eventName"]);
            $sel = $selected == $id ? "selected" : "";
            $evnts .= "<option value='$id' {$sel}>$display</option>";
        }

        return
            "<select id='dataevnt' name='dataevnt' class='x-form-text x-form-field' onchange='onFilterChanged(\"dataevnt\")' style='max-width: 180px;'>
        {$evnts}
        </select>";
    }

    public static function MakeUsernameSelect($usernames, $selected) : string
    {
        $anySelected = $selected == null ? "selected": "";
        $usrnames = "<option value='' $anySelected>any username</option>";
        foreach ($usernames as $usrname) {
            $sel = $selected == $usrname ? "selected" : "";
            $usrnames .= "<option value='{$usrname}' {$sel}>{$usrname}</option>";
        }

        return
            "<select id='usrname' name='usrname' class='x-form-text x-form-field' onchange='onFilterChanged(\"usrname\")' style='max-width: 180px;'>
            {$usrnames}
            </select>";
    }

    public static function MakeInstanceSelect($instances, $selected, $noInstance) : string
    {
        $anySelected = $selected == null ? "selected": "";
        $insts = "<option value='' $anySelected>any instance</option>";
        foreach ($instances as $inst) {
            $id = $inst == null ? -1 : $inst;
            $display = $id == -1 ? $noInstance : $inst;
            $sel = $selected == $id ? "selected" : "";
            $insts .= "<option value='$id' {$sel}>$display</option>";
        }

        return
            "<select id='datainst' name='datainst' class='x-form-text x-form-field' onchange='onFilterChanged(\"datainst\")'>
        {$insts}
        </select>";
    }

    public static function MakeFormSelect($frms, $selected) : string
    {
        $anySelected = $selected == null ? "selected": "";
        $forms = "<option value='' $anySelected>any form</option>";
        foreach ($frms as $frm) {
            $sel = $selected == $frm ? "selected" : "";
            $forms .= "<option value='{$frm}' {$sel}>{$frm}</option>";
        }

        return
            "<select id='datafrm' name='datafrm' class='x-form-text x-form-field' onchange='onFilterChanged(\"datafrm\")' style='max-width: 180px;'>
        {$forms}
        </select>";
    }

    //$src contains a list of arrays (e.g. ["f1q1", "f1q2"], ["f1q1"])
    static function FlattenAndDistinctListOfArray($srcArrs) : array
    {
        //get a distinct list of fields
        $collect = [];
        foreach ($srcArrs as $srcArr) {
            $json = json_decode($srcArr);
            $collect = array_merge($collect, $json);
        }
        return array_unique($collect);
    }

    public static function MakeFieldSelect($fldArrs, $selected, $disable) : string
    {
        //get a distinct list of fields
        $distinct = self::FlattenAndDistinctListOfArray($fldArrs);

        //provide the select
        $anySelected = $selected == null || $disable == "disabled" ? "selected": "";

        $fields = "<option value='' $anySelected>any field</option>";
        foreach ($distinct as $fld) {
            $sel = $selected == $fld ? "selected" : "";
            $fields .= "<option value='{$fld}' {$sel}>{$fld}</option>";
        }

        return
            "<select $disable id='datafld' name='datafld' class='x-form-text x-form-field' onchange='onFilterChanged(\"datafld\")' style='max-width: 180px;'>
        {$fields}
        </select>";
    }

    public static function MakeFlagSelect($flgArrs, $selected, $disable) : string
    {
        $res = [];
        foreach ($flgArrs as $flgArr) {
            foreach (json_decode($flgArr) as $flg) {
                $split = explode(" | ", $flg);
                if(count($split) > 0) {
                    foreach ($split as $sp) {
                        $res[] = $sp;
                    }
                } else {
                    $res[] = $flg;
                }
            }
        }

        $distinct = array_unique($res);

        //provide the select
        $anySelected = $selected == null || $disable == "disabled" ? "selected": "";

        $flags = "<option value='' $anySelected>any flag</option>";
        foreach ($distinct as $flg) {
            $sel = $selected == $flg ? "selected" : "";
            $flags .= "<option value='{$flg}' {$sel}>{$flg}</option>";
        }

        return
            "<select $disable id='dataflg' name='dataflg' class='x-form-text x-form-field' onchange='onFilterChanged(\"dataflg\")' style='max-width: 180px;'>
        {$flags}
        </select>";
    }

    public static function MakeResponseSelect($responseArrs, $selected, $disable) : string
    {
        //get a distinct list of responses
        $distinct = self::FlattenAndDistinctListOfArray($responseArrs);

        //provide the select
        $anySelected = $selected == null || $disable == "disabled" ? "selected": "";
        $noResponseSelected = $selected == "no-response" ? "selected": "";

        $responses = "<option value='' $anySelected>any response</option>";
        $responses .= "<option value='no-response' $noResponseSelected>no response</option>";
        foreach ($distinct as $resp) {
            $sel = $selected == $resp ? "selected" : "";
            $responses .= "<option value='{$resp}' {$sel}>{$resp}</option>";
        }

        return
            "<select $disable id='dataresp' name='dataresp' class='x-form-text x-form-field' onchange='onFilterChanged(\"dataresp\")' style='max-width: 180px;'>
        {$responses}
        </select>";
    }
}

