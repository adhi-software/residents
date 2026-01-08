<?php
/**
    * Device Registration API
    * 
    * This endpoint records a device request for admin approval. It does NOT issue an API key.
    * Uses the TBL_BL_DEVICES table (adm_bl_devices) defined in ConfigTables.php.
    * The table must be installed via the residents plugin installation page.
    */
require_once(__DIR__ . '/../../common_function.php');
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';
$device = $data['device'] ?? [];

if ($username === '' || $password === '') {
    echo json_encode(['error' => 'Username and password are required.']);
    exit;
}

// Check if the devices table exists (should be created via installation.php)
if (!tableExistsBILL(TBL_BL_DEVICES)) {
    echo json_encode(['error' => 'Devices table not found. Please run the residents plugin installation first.']);
    exit;
}

// Fetch user and validate password
$guser = $gDb->queryPrepared(
    'SELECT usr_id, usr_password FROM ' . TBL_USERS . ' WHERE usr_login_name = ? AND usr_valid = true',
    [$username],
    false
);
if ($guser === false) {
    admidioApiError('Database error', 500);
}
$row = $guser->fetch();

if (!$row) {
    echo json_encode(['error' => 'Invalid user name']);
    exit;
}

$userId = (int) $row['usr_id'];

//Check whether the user is allowed to log in
validateUserLogin($userId);

if (!is_array($device)
    || empty($device['deviceId'])
    || empty($device['platform'])
    || empty($device['brand'])
    || empty($device['model'])) {
        echo json_encode(['error' => 'Device information is required.']);
        exit;
}

$platform = (string) $device['platform'];
$brand = (string) $device['brand'];
$model = (string) $device['model'];
$deviceId = (string) $device['deviceId'];

// Check for existing device entry for this user
$existingStmt = $gDb->queryPrepared(
    'SELECT bde_id, bde_is_active FROM ' . TBL_BL_DEVICES . ' WHERE bde_usr_id = ? AND bde_device_id = ? ORDER BY bde_timestamp_create DESC LIMIT 1',
    [$userId, $deviceId],
    false
);
$existing = $existingStmt ? $existingStmt->fetch() : false;
if ($existingStmt === false) {
    admidioApiError('Database error', 500);
}

if ($existing) {
    // If device is already approved, do not change its status.
    if ((int) $existing['bde_is_active'] === 1) {
        echo json_encode([
            'status' => 'approved',
            'message' => 'Device is already approved.',
            'device_id' => (int) $existing['bde_id'],
        ]);
        exit;
    }

    // Pending device: refresh metadata but keep it pending.
    $updated = $gDb->queryPrepared(
    'UPDATE ' . TBL_BL_DEVICES . ' SET bde_platform = ?, bde_brand = ?, bde_model = ?, bde_timestamp_change = NOW() WHERE bde_id = ?',
    [$platform, $brand, $model, $existing['bde_id']],
    false
    );
    if ($updated === false) {
        admidioApiError('Database error', 500);
    }
    $requestId = (int) $existing['bde_id'];
} else {
    // Insert new device entry
    $inserted = $gDb->queryPrepared(
    'INSERT INTO ' . TBL_BL_DEVICES . ' (bde_device_id, bde_usr_id, bde_is_active, bde_platform, bde_brand, bde_model, bde_timestamp_create) VALUES (?, ?, 0, ?, ?, ?, NOW())',
    [$deviceId, $userId, $platform, $brand, $model],
    false
    );
    if ($inserted === false) {
        admidioApiError('Database error', 500);
    }
    $requestId = (int) $gDb->lastInsertId();
}

echo json_encode([
    'status' => 'pending',
    'message' => 'Device is registered. Ask admin to approve to login.',
    'device_id' => $requestId,
]);
