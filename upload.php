<?php
session_start();
require_once "../authCookieSessionValidate.php";
require_once "../ft.php";

if (!$isLoggedIn) {
    http_response_code(403);
    echo "Forbidden";
    header("Location: ../login.php");
    exit();
}
require '../mysql_config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

if (!isset($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

function getNextWorkday($conn) {
    $nextDate = new DateTime();

    do {
        $nextDate->modify('+1 day');
        $dayOfWeek = $nextDate->format('N'); // 6 = Saturday, 7 = Sunday

        // Check if it's a holiday via your ft.php logic (you might adapt this inline)
        $timestamp = strtotime($nextDate->format('Y-m-d'));
        $feasts = getFeastsMonth($timestamp, "BW");

        $isHoliday = false;
        $currentDay = (int) $nextDate->format('d');
        
        // getFeastsMonth returns array of day numbers, not objects with 'date' property
        if (is_array($feasts) && in_array($currentDay, $feasts)) {
            $isHoliday = true;
        }

    } while ($dayOfWeek >= 6 || $isHoliday); // loop while weekend or holiday

    return $nextDate->format('Y-m-d');
}

$tmp = $_FILES['file']['tmp_name'];
$inserted_count = 0;
$reactivated_count = 0;
$expired_count = 0;
$newClients = [];

if (file_exists($tmp)) {
    // Read file content first
    $original = file_get_contents($tmp);

    // Detect encoding (try common Windows encodings)
    $encoding = mb_detect_encoding($original, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);

    // Convert to UTF-8 if necessary
    if ($encoding !== 'UTF-8') {
        $original = mb_convert_encoding($original, 'UTF-8', $encoding);
    }

    // Use a memory stream for consistent fgetcsv parsing
    $fh = fopen('php://temp', 'r+');
    fwrite($fh, $original);
    rewind($fh);

    if ($fh !== false) {
        $rawHeader = fgetcsv($fh, 1000, ';');
        while (end($rawHeader) === '') {
            array_pop($rawHeader);
        }
        $header = array_map(function($h) {
            return strtolower(trim($h));
        }, $rawHeader);
        $cols = array_flip($header);

        // Check for alternative column names and create mappings
        $required = ['name', 'vorname', 'geschlecht', 'geburtsdatum', 'aufnahmedatum'];
        foreach ($required as $col) {
            if (!isset($cols[$col])) {
                // Add debugging information about available columns
                $available_cols = implode(', ', array_keys($cols));
                echo json_encode([
                    'success' => false, 
                    'error' => "Missing required column: $col. Available columns: $available_cols"
                ]);
                fclose($fh);
                exit;
            }
        }

        while (($row = fgetcsv($fh, 1000, ';')) !== false) {
            while (end($row) === '') {
                array_pop($row);
            }

            $zust_col = $cols['zuständig'] ?? null;
            $entlassen_col = $cols['entlassen'] ?? null;
            $name = trim($row[$cols['name']] ?? '');
            $vorname = trim($row[$cols['vorname']] ?? '');
            $geschlecht = trim($row[$cols['geschlecht']] ?? '');
            $ps = isset($cols['p/s']) ? trim($row[$cols['p/s']] ?? '') : '';
            $raum = isset($cols['raum']) ? trim($row[$cols['raum']] ?? '') : '';
            $geburtsdatum_raw = trim(str_replace("\r", "", $row[$cols['geburtsdatum']] ?? ''));
            $aufnahmedatum_raw = trim(str_replace("\r", "", $row[$cols['aufnahmedatum']] ?? ''));

            if (!$name || !$vorname || !$geburtsdatum_raw || !$aufnahmedatum_raw) continue;
            if ($ps !== '') continue;
            if ($raum !== '' && strpos($raum, '53') !== 0) continue;

            $gd = DateTime::createFromFormat('d.m.Y', $geburtsdatum_raw);
            $ad = DateTime::createFromFormat('d.m.Y H:i', $aufnahmedatum_raw)
                ?: DateTime::createFromFormat('d.m.Y', $aufnahmedatum_raw);
            if (!$gd || !$ad) continue;

            $geburtsdatum = $gd->format('Y-m-d');
            $aufnahmedatum = $ad->format('Y-m-d');
            
            // Handle discharge date - check for various formats
          $entlassen_raw = '';
if ($entlassen_col !== null && isset($row[$entlassen_col])) {
    $entlassen_raw = trim($row[$entlassen_col] ?? '');
}

// Setze Status je nachdem, ob "Entlassen" leer ist oder nicht
$status = $entlassen_raw !== '' ? 'expired' : 'active';

// Entlassdatum nur dann auslesen, falls Entlassen gesetzt (nicht leer)
$entlassdatum = '';
if ($status === 'expired' && isset($cols['entlassdatum'])) {
    $entlassdatum_raw = trim($row[$cols['entlassdatum']] ?? '');
    if ($entlassdatum_raw !== '') {
        $ed = DateTime::createFromFormat('d.m.Y H:i', $entlassdatum_raw)
            ?: DateTime::createFromFormat('d.m.Y', $entlassdatum_raw)
            ?: DateTime::createFromFormat('Y-m-d H:i:s', $entlassdatum_raw)
                ?: DateTime::createFromFormat('Y-m-d', $entlassdatum_raw);
        if ($ed) {
            $entlassdatum = $ed->format('Y-m-d');
        }
    }
}
            $key = mb_strtolower($name) . '|' . mb_strtolower($vorname) . '|' . $geburtsdatum . '|' . $aufnahmedatum;
            $newClients[$key] = [
                'name' => $name,
                'vorname' => $vorname,
                'geschlecht' => $geschlecht,
                'geburtsdatum' => $geburtsdatum,
                'aufnahmedatum' => $aufnahmedatum,
                'status' => $status,
                'zuständig' => $zust_col !== null ? trim($row[$zust_col] ?? '') : ''
            ];
        }
        fclose($fh);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'File not found']);
    exit;
}

// Step 2: Load existing clients
$existing = [];
$res = $conn->query("SELECT id, name, vorname, geburtsdatum, aufnahmedatum, status FROM clients");
while ($row = $res->fetch_assoc()) {
    $key = mb_strtolower($row['name']) . '|' . mb_strtolower($row['vorname']) . '|' . $row['geburtsdatum'] . '|' . $row['aufnahmedatum'];
    $existing[$key] = $row;
}

// Step 3: Expire missing clients
foreach ($existing as $key => $client) {
    if ($client['status'] === 'active' && !isset($newClients[$key])) {
        $stmt = $conn->prepare("UPDATE clients SET status = 'expired', expired_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $client['id']);
        $stmt->execute();
        $expired_count++;
    }
}

// Step 4: Get eligible user IDs from workers
$assignable_users = []; // key = lowercase name
$user_ids = [];

$next_workday = getNextWorkday($conn);

$res = $conn->query("
   SELECT DISTINCT u.id, u.name
FROM workers w
JOIN user u ON w.uid = u.id
LEFT JOIN plan p ON p.uid = u.id AND p.datum = '{$next_workday}'
LEFT JOIN cellvalue c ON p.valid = c.id
WHERE u.gueltigab <= CURDATE()
  AND u.gueltigbis >= CURDATE()
  AND (p.id IS NULL OR c.abwesend = 0)
ORDER BY u.name;
");

while ($row = $res->fetch_assoc()) {
    $user_ids[] = $row['id'];
    $assignable_users[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'lower_name' => mb_strtolower($row['name'])
    ];
}
$user_count = count($user_ids);
/*
$res = $conn->query("
    SELECT u.id, u.name
    FROM workers w
    JOIN user u ON w.uid = u.id
    WHERE u.gueltigab <= CURDATE() AND u.gueltigbis >= CURDATE()
    ORDER BY u.name
");
while ($row = $res->fetch_assoc()) {
    $user_ids[] = $row['id'];
    $assignable_users[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'lower_name' => mb_strtolower($row['name'])
    ];
}

$user_count = count($user_ids);
*/
// Step 4.1: Determine last used user_id
$lastUserId = null;
$res = $conn->query("SELECT user_id FROM clients WHERE user_id IS NOT NULL ORDER BY id DESC LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    $lastUserId = $row['user_id'];
}

// Step 4.2: Calculate starting index for round robin
$user_index = 0;
if ($lastUserId !== null) {
    $pos = array_search($lastUserId, $user_ids);
    if ($pos !== false) {
        $user_index = ($pos + 1) % $user_count;
    }
}

// Step 5: Insert new or reactivate existing clients
foreach ($newClients as $key => $data) {
    if (isset($existing[$key])) {
        // Only reactivate if status is 'expired' and 'entlassen' is empty
        if ($existing[$key]['status'] === 'expired' && $data['status']=== "active") {
            $stmt = $conn->prepare("UPDATE clients SET status = 'active' WHERE id = ?");
            $stmt->bind_param('i', $existing[$key]['id']);
            $stmt->execute();
            $reactivated_count++;
        }
        continue;
    }
    $assigned_user_id = null;

    // Check if Zuständig column is present and non-empty
    if ($data["zuständig"] !== null) {
        $zuständig_input = mb_strtolower(trim($data["zuständig"] ?? ''));
        if ($zuständig_input !== '') {
            foreach ($assignable_users as $user) {
                if (mb_strpos($user['lower_name'], $zuständig_input) !== false) {
                    $assigned_user_id = $user['id'];
                    break;
                }
            }
        }
    }

    // If no match, use round-robin
    if (!$assigned_user_id) {
        $assigned_user_id = $user_ids[$user_index];
        $user_index = ($user_index + 1) % $user_count;
    }

    $stmt = $conn->prepare("
        INSERT INTO clients (name, vorname, geschlecht, geburtsdatum, aufnahmedatum, status, user_id)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        'ssssssi',
        $data['name'],
        $data['vorname'],
        $data['geschlecht'],
        $data['geburtsdatum'],
        $data['aufnahmedatum'],
        $data['status'],
        $assigned_user_id
    );
    $stmt->execute();

    if ($data['status'] === 'expired') {
        $expired_count++;
    } else {
        $inserted_count++;
    }
}

// Final response
echo json_encode([
    'success' => true,
    'inserted_count' => $inserted_count,
    'reactivated_count' => $reactivated_count,
    'expired_count' => $expired_count
]);
?>