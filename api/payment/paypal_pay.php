<?php
/**
 * Mobile PayPal start API (HTML response for WebView)
 *
 * Mirrors ccavenue_pay.php - delegates to common renderPaypalForMobile()
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

require_once __DIR__ . '/../../../../system/common.php';
require_once __DIR__ . '/../../common_function.php';
require_once __DIR__ . '/../../payment_gateway/paypal_common.php';

global $gCurrentUser;

$gCurrentUser = validateApiKey();

$userId = (int)$gCurrentUser->getValue('usr_id');

// Read invoice IDs from POST body only
$invoiceIds = $_POST['invoice_ids'] ?? [];
$invoiceIds = array_map('intval', (array)$invoiceIds);

// Render payment form
renderPaypalForMobile($invoiceIds, $userId);
