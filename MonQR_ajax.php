<?php

// Receives a request to update the db for the monitoring status

header('Content-Type: application/json');

global $module;

try {
    global $Proj;

    // Validate and sanitize all parameters
    $projectId = filter_var($_POST['projectId'] ?? null, FILTER_VALIDATE_INT);
    if ($projectId === false || $projectId === null) {
        throw new Exception("MonQR_ajax - project id is missing or invalid");
    }

    $eventId = filter_var($_POST['eventId'] ?? null, FILTER_VALIDATE_INT);
    if ($eventId === false || $eventId === null) {
        throw new Exception("MonQR_ajax - event id is missing or invalid");
    }

    $record = filter_var($_POST['record'] ?? null, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    if (empty($record)) {
        throw new Exception("MonQR_ajax - record is missing");
    }

    $monitorField = filter_var($_POST['monitorField'] ?? null, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    if (empty($monitorField)) {
        throw new Exception("MonQR_ajax - monitorField is missing");
    }

    $statusInt = filter_var($_POST['statusInt'] ?? null, FILTER_VALIDATE_INT);
    if ($statusInt === false || $statusInt === null) {
        throw new Exception("MonQR_ajax - statusInt is missing or invalid");
    }

    $instrument = filter_var($_POST['instrument'] ?? null, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    if (empty($instrument)) {
        throw new Exception("MonQR_ajax - instrument is missing");
    }

    // Optional: repeat_instance (can be null)
    $repeat_instance = isset($_POST['repeatInstance'])
        ? filter_var($_POST['repeatInstance'], FILTER_VALIDATE_INT)
        : null;

    // Call the update using the module process
    \CCTC\MonitoringQRModule\MonitoringQRModule::setMonitorStatus(
        $projectId, $eventId, $record, $monitorField, $statusInt,
        $repeat_instance, $instrument);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $module->log("Failed to write saveData in MonQR_ajax with error",
            [
               "error" => $e->getMessage(),
            ]);
    http_response_code(400);
    echo json_encode(['error' => 'Request failed']);
}
