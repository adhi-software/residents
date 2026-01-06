<?php
require_once(__DIR__ . '/../../../../adm_program/system/common.php');
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

$sqlUsers = 'SELECT usr_id, usr_login_name, usr_photo FROM ' . TBL_USERS . ' WHERE usr_valid = true';

$users = $gDb->queryPrepared($sqlUsers, array(), false);
if ($users === false) {
  admidioApiError('Database error', 500);
}
$contacts = [];

while ($row = $users->fetch()) {
  $user = new User($gDb, $gProfileFields);
  $user->readDataById($row['usr_id']);
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
