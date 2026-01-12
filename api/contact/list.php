<?php
/**
 ***********************************************************************************************
 * API endpoint to return a list of contacts with profile information
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../../../system/common.php');
require_once(__DIR__ . '/../../common_function.php');
header('Content-Type: application/json; charset=utf-8');

$currentUser = validateApiKey();
$picPath = THEME_PATH. '/images/no_profile_pic.png';

function encodeProfileImage(string $binary): array
{
    if ($binary === '') {
        return [
            'profile' => null,
            'profile_mime' => null,
            'profile_has_image' => false,
        ];
    }

    $mime = 'image/jpeg';
    $finfo = function_exists('finfo_open') ? @finfo_open(FILEINFO_MIME_TYPE) : false;
    if ($finfo) {
        $detected = @finfo_buffer($finfo, $binary);
        if (is_string($detected) && $detected !== '') {
            $mime = $detected;
    }
        @finfo_close($finfo);
    }

    return [
    'profile' => base64_encode($binary),
    'profile_mime' => $mime,
    'profile_has_image' => true,
    ];
}

$today = date('Y-m-d');

$sqlUsers = 'SELECT DISTINCT u.usr_id, u.usr_login_name
    FROM ' . TBL_USERS . ' u
    INNER JOIN ' . TBL_MEMBERS . ' m ON m.mem_usr_id = u.usr_id
        AND m.mem_begin <= ?
        AND m.mem_end > ?
    INNER JOIN ' . TBL_ROLES . ' r ON r.rol_id = m.mem_rol_id
    INNER JOIN ' . TBL_CATEGORIES . ' c ON c.cat_id = r.rol_cat_id
    WHERE u.usr_valid = true
        AND (c.cat_org_id = ? OR c.cat_org_id IS NULL)';

$users = $gDb->queryPrepared($sqlUsers, array($today, $today, (int) $gCurrentOrgId), false);
if ($users === false) {
    admidioApiError('Database error', 500);
}
$contacts = [];

while ($row = $users->fetch()) {
    $user = new User($gDb, $gProfileFields);
    $user->readDataById($row['usr_id']);
    if (!isMemberOfOrganization($user)) {
        continue;
    }
    $profileBinary = '';
    if ((int) $gSettingsManager->get('profile_photo_storage') === 0) {
        $usr_photo = $user->getValue('usr_photo');
        if (!empty($usr_photo)) {
            $profileBinary = $usr_photo;
    }
    }
    else {
        $file = ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $user->getValue('usr_id') . '.jpg';
        if (is_file($file)) {
            $profileBinary = (string) @file_get_contents($file);
    }
    }

    $encodedProfile = encodeProfileImage($profileBinary);
    $contacts[] = [
    'id'            => $row['usr_id'],
    'login'         => $user->getValue('usr_login_name'),
    'first_name'    => $user->getValue('FIRST_NAME'),
    'last_name'     => $user->getValue('LAST_NAME'),
    'email'         => $user->getValue('EMAIL'),
    'gender'        => $user->getValue('GENDER'),
    'street'        => $user->getValue('STREET'),
    'post_code'     => $user->getValue('POSTCODE'),
    'city'          => $user->getValue('CITY'),
    'country'       => $user->getValue('COUNTRY'),
    'phone'         => $user->getValue('PHONE'),
    'mobile'        => $user->getValue('MOBILE'),
    'birthday'      => $user->getValue('BIRTHDAY'),
    'website'       => $user->getValue('WEBSITE'),
    'profile'           => $encodedProfile['profile'],
    'profile_mime'      => $encodedProfile['profile_mime'],
    'profile_has_image' => $encodedProfile['profile_has_image']
    ];
}

echo json_encode([ 'contacts' => $contacts ]);
