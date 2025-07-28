<?php

//receives a request to update the db for the monitoring status

header('Content-Type: application/json');

global $module;

try {

    global $Proj;

    //get all the parameters
    $projectId = $_POST['projectId'] ?? throw new Exception("MonQR_ajax - project id is missing");
    $eventId = $_POST['eventId'] ?? throw new Exception("MonQR_ajax - event id is missing");
    $record = $_POST['record'] ?? throw new Exception("MonQR_ajax - record is missing");
    $monitorField = $_POST['monitorField'] ?? throw new Exception("MonQR_ajax - monitorField is missing");
    $statusInt = $_POST['statusInt'] ?? throw new Exception("MonQR_ajax - statusInt is missing");
    $instrument = $_POST['instrument'] ?? throw new Exception("MonQR_ajax - instrument is missing");
    //not always given
    $repeat_instance = $_POST['repeatInstance'];

    //call the update using the module process
    \CCTC\MonitoringQRModule\MonitoringQRModule::setMonitorStatus(
        $projectId, $eventId, $record, $monitorField, $statusInt,
        $repeat_instance, $instrument);

} catch (Exception $e) {
    $module->log("Failed to write saveData in MonQR_ajax with error",
            [
               "error" => $e->getMessage(),
            ]);
}
