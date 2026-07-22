<?php
session_start();
require_once "../authCookieSessionValidate.php";

// Check if user is logged in
if (!$isLoggedIn) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit();
}

// Check if request is POST and has required data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Nur POST-Anfragen erlaubt']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['client_id']) || !is_numeric($input['client_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungültige Client-ID']);
    exit();
}

$client_id = intval($input['client_id']);
$new_user_id = $_SESSION["member_id"]; // Current logged in user

require '../mysql_config.php'; // Database connection

// First check if client exists and is active
$check_sql = "SELECT id, name, vorname, user_id FROM clients WHERE id = ? AND status = 'active'";
$check_stmt = $conn->prepare($check_sql);

if (!$check_stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler: ' . $conn->error]);
    exit();
}

$check_stmt->bind_param('i', $client_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Client nicht gefunden oder bereits entlassen']);
    exit();
}

$client = $result->fetch_assoc();

// Check if client is already assigned to current user
if ($client['user_id'] == $new_user_id) {
    echo json_encode(['success' => false, 'error' => 'Sie sind bereits für diesen Patienten zuständig']);
    exit();
}

// Update the responsible user
$update_sql = "UPDATE clients SET user_id = ? WHERE id = ? AND status = 'active'";
$update_stmt = $conn->prepare($update_sql);

if (!$update_stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler: ' . $conn->error]);
    exit();
}

$update_stmt->bind_param('ii', $new_user_id, $client_id);

if ($update_stmt->execute()) {
    if ($update_stmt->affected_rows > 0) {
        // Get the updated responsible person name
        $user_sql = "SELECT SUBSTRING_INDEX(name, ',', 1) AS worker_name FROM user WHERE id = ?";
        $user_stmt = $conn->prepare($user_sql);
        $user_stmt->bind_param('i', $new_user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user = $user_result->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'message' => 'Zuständigkeit erfolgreich übernommen',
            'client_name' => $client['name'] . ', ' . $client['vorname'],
            'new_responsible' => $user['worker_name']
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Client konnte nicht aktualisiert werden']);
    }
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler beim Update: ' . $conn->error]);
}

$check_stmt->close();
$update_stmt->close();
if (isset($user_stmt)) $user_stmt->close();
$conn->close();
?>