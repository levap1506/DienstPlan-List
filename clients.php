<?php
session_start();
require_once "../authCookieSessionValidate.php";

if (!$isLoggedIn) {
    header("Location: ../login.php");
    exit();
}

header('Content-Type: application/json');
require '../mysql_config.php'; // adjust path if needed

$sql = "
  SELECT
    c.id,
    c.user_id,
    SUBSTRING_INDEX(u.name, ',', 1) AS worker_name,
    c.name,
    c.vorname,
    c.geschlecht,
    DATE_FORMAT(c.geburtsdatum,'%Y-%m-%d') AS geburtsdatum,
    TIMESTAMPDIFF(YEAR, c.geburtsdatum, CURDATE()) AS `alter`,
    DATE_FORMAT(c.aufnahmedatum,'%Y-%m-%d') AS aufnahmedatum,
    c.status
  FROM clients c
  LEFT JOIN user u ON u.id = c.user_id
  ORDER BY c.name, c.vorname
";

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => $conn->error]);
    exit;
}

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
