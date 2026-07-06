<?php
/**
 ***********************************************************************************************
 * Device Logout API - Rotates the calling device's API key on sign-out.
 *
 * The device stays approved (rde_is_active is left unchanged) so the user can sign
 * back in with their password without waiting for admin re-approval, but the API
 * key that was held on the signed-out device is invalidated immediately. The next
 * successful password login returns the freshly rotated key.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../common_function.php');
header('Content-Type: application/json; charset=utf-8');

// Authenticate the caller with their current API key (also applies org context).
validateApiKey();

$currentKey = residentsExtractApiKey();
if ($currentKey === null) {
    // Should not happen: validateApiKey() already required a key.
    http_response_code(400);
    echo json_encode(['error' => 'Missing API key', 'code' => 'API_KEY_INVALID']);
    exit;
}

// Rotate the key for exactly this device row. rde_is_active is intentionally left
// untouched so the device remains approved for the next password login.
$newKey = bin2hex(random_bytes(20));
$updated = $gDb->queryPrepared(
    'UPDATE ' . TBL_RE_DEVICES . ' SET rde_api_key = ?, rde_timestamp_change = NOW() WHERE rde_api_key = ?',
    [$newKey, $currentKey],
    false
);
if ($updated === false) {
    admidioApiError('Database error', 500);
}

echo json_encode(['status' => 'ok']);
