<?php
/**
 ***********************************************************************************************
 * Device Login API - Authenticates a user and returns an API key if the device is approved
 *
 * Uses the TBL_BL_DEVICES table (adm_bl_devices) defined in ConfigTables.php.
 * The table must be installed via the residents plugin installation page.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
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
$hashedPassword = $row['usr_password'];

//Check whether the user is allowed to log in
validateUserLogin($userId, $password);

// Device info is required to match the correct device record.
if (!is_array($device)
    || empty($device['deviceId'])
    || empty($device['platform'])
    || empty($device['brand'])
    || empty($device['model'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Device information is required.']);
        exit;
}

$platform = (string) $device['platform'];
$brand = (string) $device['brand'];
$model = (string) $device['model'];
$deviceId = (string) $device['deviceId'];

// Check device approval status
$deviceStmt = $gDb->queryPrepared(
    'SELECT bde_id, bde_is_active, bde_api_key FROM ' . TBL_BL_DEVICES . ' WHERE bde_usr_id = ? AND bde_device_id = ? ORDER BY bde_timestamp_create DESC LIMIT 1',
    [$userId, $deviceId],
    false
);
if ($deviceStmt === false) {
    admidioApiError('Database error', 500);
}
$deviceRow = $deviceStmt->fetch();

if (!$deviceRow) {
    // Auto-create a pending device request on first login attempt.
    $inserted = $gDb->queryPrepared(
    'INSERT INTO ' . TBL_BL_DEVICES . ' (bde_device_id, bde_usr_id, bde_is_active, bde_platform, bde_brand, bde_model, bde_timestamp_create) VALUES (?, ?, 0, ?, ?, ?, NOW())',
    [$deviceId, $userId, $platform, $brand, $model],
    false
    );
    if ($inserted === false) {
        admidioApiError('Database error', 500);
    }
    $requestId = (int) $gDb->lastInsertId();

    echo json_encode([
    'status' => 'pending',
    'message' => 'Device request submitted. Ask admin to approve to login.',
    'device_id' => $requestId,
    ]);
    exit;
}

if (!$deviceRow['bde_is_active']) {
    echo json_encode([
    'status' => 'pending',
    'message' => 'Device request is not approved yet.',
    'device_id' => (int) $deviceRow['bde_id'],
    ]);
    exit;
}

// Generate API key if not already set
$apiKey = (string) ($deviceRow['bde_api_key'] ?? '');
if ($apiKey === '') {
    // Safety net: in case an old device was approved without an API key.
    $apiKey = bin2hex(random_bytes(20));
    $updated = $gDb->queryPrepared(
    'UPDATE ' . TBL_BL_DEVICES . ' SET bde_api_key = ?, bde_timestamp_change = NOW() WHERE bde_id = ?',
    [$apiKey, $deviceRow['bde_id']],
    false
    );
    if ($updated === false) {
        admidioApiError('Database error', 500);
    }
}

$user = new User($gDb, $gProfileFields);
$user->readDataById($userId);
$user->updateLoginData();

$userData = [
    'id'         => $userId,
    'login'      => $user->getValue('usr_login_name'),
    'first_name' => $user->getValue('FIRST_NAME'),
    'last_name'  => $user->getValue('LAST_NAME'),
    'email'      => $user->getValue('EMAIL'),
    'api_key'    => $apiKey,
];

echo json_encode([
    'status' => 'approved',
    'user'   => $userData,
    'apiKey' => $apiKey,
]);
