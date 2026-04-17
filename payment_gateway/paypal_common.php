<?php
/**
 ***********************************************************************************************
 * PayPal common logic - Shared functions for web and mobile
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/paypal_config.php');

/**
 * Get PayPal OAuth2 Access Token
 */
function paypal_get_access_token(): string
{
    $tokenRequest = paypal_make_request(
        rtrim(PAYPAL_API_URL, '/') . '/v1/oauth2/token',
        'POST',
        ['Authorization: Basic ' . base64_encode(PAYPAL_CLIENT_ID . ':' . PAYPAL_CLIENT_SECRET)],
        ['grant_type' => 'client_credentials']
    );

    $tokenResponse = json_decode($tokenRequest['body'], true);
    if (empty($tokenResponse['access_token'])) {
        file_put_contents(__DIR__ . '/paypal_error.log', "PayPal Auth Error: Code " . $tokenRequest['code'] . ", Body: " . $tokenRequest['body'] . "\n", FILE_APPEND);
    }
    return $tokenResponse['access_token'] ?? '';
}

/**
 * Create a PayPal Order
 */
function paypal_create_order(string $accessToken, array $orderData): array
{
    return paypal_make_request(
        rtrim(PAYPAL_API_URL, '/') . '/v2/checkout/orders',
        'POST',
        [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ],
        json_encode($orderData)
    );
}

/**
 * Capture a PayPal Order
 */
function paypal_capture_order(string $accessToken, string $orderId): array
{
    return paypal_make_request(
        rtrim(PAYPAL_API_URL, '/') . '/v2/checkout/orders/' . $orderId . '/capture',
        'POST',
        [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]
    );
}

/**
 * Common currency mapper
 */
function paypal_map_currency(string $currency): string
{
    $currencyMap = [
        '$' => 'USD',
        '€' => 'EUR',
        '£' => 'GBP',
        '₹' => 'INR',
        '¥' => 'JPY'
    ];
    $code = $currencyMap[$currency] ?? $currency;
    return (strlen($code) === 3) ? $code : 'USD';
}

/**
 * Sanitize name for PayPal
 */
function paypal_sanitize_name(string $name): string
{
    return preg_replace('/[^a-zA-Z]/', '', $name);
}

/**
 * Common PayPal transaction initiation
 * Used by both web and mobile to start payment
 * Mirrors initCcavenueTransaction() from ccavenue_common.php
 *
 * @param array  $invoiceIds Array of invoice IDs to pay
 * @param int    $userId     User ID making the payment
 * @param string $source     'web' or 'mobile' - determines redirect URLs
 * @return array Payment data or error array
 */
function initPaypalTransaction(array $invoiceIds, int $userId, string $source = 'web'): array
{
    global $gDb, $gCurrentOrgId, $gCurrentOrganization, $gCurrentUser, $pgConf;

    // Validate config
    if (
        empty(PAYPAL_CLIENT_ID) ||
        empty(PAYPAL_CLIENT_SECRET) ||
        empty(PAYPAL_API_URL)
    ) {
        return ['error' => 'PayPal is not configured. Please contact administrator.', 'error_code' => 'RE_PG_NOT_CONFIGURED'];
    }

    if (empty($invoiceIds)) {
        return ['error' => 'No invoices selected', 'error_code' => 'RE_PG_NO_INVOICES'];
    }

    // Mark OLD initiated payments as TIMEOUT first
    residentsCheckPaymentTimeouts();

    // Check for recent initiated payments (timeout from config or default 15 mins)
    $timeoutMins = isset($pgConf['timeout']) && (int)$pgConf['timeout'] > 0 ? (int)$pgConf['timeout'] : ((int)PAYPAL_TIMEOUT > 0 ? (int)PAYPAL_TIMEOUT : 15);
    $timeoutTime = date('Y-m-d H:i:s', strtotime('-' . $timeoutMins . ' minutes'));

    // Check if ANY of the selected invoices have a recent pending payment (status = IT only)
    $initiatedInvoices = [];
    foreach ($invoiceIds as $invId) {
        $invId = (int)$invId;
        $checkRecentSql = 'SELECT COUNT(*)
            FROM ' . TBL_RE_TRANS_ITEMS . ' i
            JOIN ' . TBL_RE_TRANS . ' p ON p.rtr_id = i.rti_pg_payment_id
            WHERE i.rti_inv_id = ? AND p.rtr_status = ? AND p.rtr_timestamp_create >= ?';
        $checkRecentStmt = $gDb->queryPrepared($checkRecentSql, [$invId, 'IT', $timeoutTime], false);
        if ($checkRecentStmt !== false && $checkRecentStmt->fetchColumn() > 0) {
            $invNumStmt = $gDb->queryPrepared('SELECT riv_number FROM ' . TBL_RE_INVOICES . ' WHERE riv_id = ?', [$invId], false);
            $invNum = $invNumStmt !== false ? $invNumStmt->fetchColumn() : $invId;
            $initiatedInvoices[] = $invNum ?: $invId;
        }
    }

    if (!empty($initiatedInvoices)) {
        return [
            'error' => 'A payment was already initiated for invoice(s) ' . implode(', ', $initiatedInvoices) . '. Please wait ' . $timeoutMins . ' minutes before trying again.',
            'error_code' => 'RE_PG_PAYMENT_INITIATED',
            'invoices' => implode(', ', $initiatedInvoices),
            'timeout' => $timeoutMins
        ];
    }

    // Check if ANY of the selected invoices already have a successful payment (status = SU)
    $alreadyPaidInvoices = [];
    foreach ($invoiceIds as $invId) {
        $invId = (int)$invId;
        $checkPaidSql = 'SELECT COUNT(*)
            FROM ' . TBL_RE_TRANS_ITEMS . ' i
            JOIN ' . TBL_RE_TRANS . ' p ON p.rtr_id = i.rti_pg_payment_id
            WHERE i.rti_inv_id = ? AND p.rtr_status = ?';
        $checkPaidStmt = $gDb->queryPrepared($checkPaidSql, [$invId, 'SU'], false);
        if ($checkPaidStmt !== false && $checkPaidStmt->fetchColumn() > 0) {
            $invNumStmt = $gDb->queryPrepared('SELECT riv_number FROM ' . TBL_RE_INVOICES . ' WHERE riv_id = ?', [$invId], false);
            $invNum = $invNumStmt !== false ? $invNumStmt->fetchColumn() : $invId;
            $alreadyPaidInvoices[] = $invNum ?: $invId;
        }
    }

    if (!empty($alreadyPaidInvoices)) {
        return [
            'error' => 'Invoice(s) ' . implode(', ', $alreadyPaidInvoices) . ' already have a successful payment. Cannot initiate another payment.',
            'error_code' => 'RE_PG_ALREADY_PAID',
            'invoices' => implode(', ', $alreadyPaidInvoices)
        ];
    }

    // Validate invoices & calculate total
    $totalAmount = 0.0;
    $currency = '';
    $ownerId = 0;

    foreach ($invoiceIds as $invId) {
        $invId = (int)$invId;

        $stmt = $gDb->queryPrepared(
            'SELECT * FROM ' . TBL_RE_INVOICES . ' WHERE riv_id = ? AND riv_org_id = ?',
            [$invId, $gCurrentOrgId],
            false
        );
        if ($stmt === false) {
            return ['error' => 'Database error', 'error_code' => 'RE_PG_DATABASE_ERROR'];
        }
        $invoice = $stmt->fetch();

        if (!$invoice) {
            return ['error' => 'Invalid invoice', 'error_code' => 'RE_PG_INVALID_INVOICE'];
        }

        if ((int)$invoice['riv_is_paid'] === 1) {
            return ['error' => 'Invoice already paid', 'error_code' => 'RE_PG_INVOICE_PAID'];
        }

        if ($ownerId === 0) {
            $ownerId = (int)$invoice['riv_usr_id'];
        } elseif ($ownerId !== (int)$invoice['riv_usr_id']) {
            return ['error' => 'Invoices must belong to same user', 'error_code' => 'RE_PG_INVOICES_SAME_USER'];
        }

        if ($ownerId !== $userId) {
            return ['error' => 'Unauthorized invoice access', 'error_code' => 'RE_PG_UNAUTHORIZED'];
        }

        $totals = residentsGetInvoiceTotals($invId);
        $totalAmount += (float)$totals['amount'];
        $currency = $totals['currency'];
    }

    if ($totalAmount <= 0) {
        return ['error' => 'Invalid payment amount', 'error_code' => 'RE_PG_INVALID_AMOUNT'];
    }

    // Determine redirect URLs based on source
    $baseUrl = ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/payment_gateway/';
    $apiBaseUrl = ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/api/payment/';

    if ($source === 'mobile' && !empty($_POST['mobile_base_url'])) {
        $apiBaseUrl = rtrim($_POST['mobile_base_url'], '/') . '/adm_plugins/residents/api/payment/';
    }

    if ($source === 'mobile') {
        $returnUrl = $apiBaseUrl . 'paypal_response.php';
        $cancelUrl = $apiBaseUrl . 'paypal_cancel.php';
    } else {
        $returnUrl = !empty($pgConf['redirect_url']) ? $pgConf['redirect_url'] : $baseUrl . 'paypal_response.php';
        $cancelUrl = !empty($pgConf['cancel_url']) ? $pgConf['cancel_url'] : $baseUrl . 'paypal_cancel.php';
    }

    // Insert payment record (status = IT for initiated)
    if ($gDb->queryPrepared(
        'INSERT INTO ' . TBL_RE_TRANS . ' (
            rtr_pg_id,
            rtr_status,
            rtr_amount,
            rtr_currency,
            rtr_usr_id,
            rtr_org_id,
            rtr_pg_pay_method,
            rtr_usr_id_create,
            rtr_usr_id_change,
            rtr_timestamp_create,
            rtr_timestamp_change
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            null,
            'IT',
            number_format($totalAmount, 2, '.', ''),
            $currency,
            $ownerId,
            $gCurrentOrgId,
            'PayPal',
            $ownerId,
            $ownerId,
            DATETIME_NOW,
            DATETIME_NOW
        ],
        false
    ) === false) {
        return ['error' => 'Failed to create initiated payment', 'error_code' => 'RE_PG_CREATE_FAILED'];
    }

    $paymentId = (int)$gDb->lastInsertId();

    // Insert payment items
    foreach ($invoiceIds as $invId) {
        $invTotals = residentsGetInvoiceTotals($invId);

        if ($gDb->queryPrepared(
            'INSERT INTO ' . TBL_RE_TRANS_ITEMS . ' (
                rti_pg_payment_id,
                rti_inv_id,
                rti_amount,
                rti_currency,
                rti_usr_id,
                rti_org_id,
                rti_usr_id_create,
                rti_usr_id_change,
                rti_timestamp_create,
                rti_timestamp_change
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $paymentId,
                $invId,
                number_format((float)$invTotals['amount'], 2, '.', ''),
                $currency,
                $ownerId,
                $gCurrentOrgId,
                $ownerId,
                $ownerId,
                DATETIME_NOW,
                DATETIME_NOW
            ],
            false
        ) === false) {
            return ['error' => 'Failed to create initiated payment items', 'error_code' => 'RE_PG_ITEMS_FAILED'];
        }
    }

    // Get PayPal Access Token
    $accessToken = paypal_get_access_token();
    if (empty($accessToken)) {
        return ['error' => 'PayPal authentication failed', 'error_code' => 'RE_PG_AUTH_FAILED'];
    }

    // Build PayPal Order
    $addr = residentsGetUserAddress($ownerId);
    $currencyCode = paypal_map_currency($currency);
    $firstName = paypal_sanitize_name(is_object($gCurrentUser) ? $gCurrentUser->getValue('FIRST_NAME') : 'First');
    $lastName = paypal_sanitize_name(is_object($gCurrentUser) ? $gCurrentUser->getValue('LAST_NAME') : 'Last');
    if (empty($firstName)) $firstName = 'First';
    if (empty($lastName)) $lastName = 'Last';

    $orderData = [
        'intent' => 'CAPTURE',
        'payment_source' => [
            'paypal' => [
                'experience_context' => [
                    'brand_name' => substr((is_object($gCurrentOrganization) ? $gCurrentOrganization->getValue('org_longname') : 'Admidio'), 0, 127),
                    'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                    'landing_page' => 'NO_PREFERENCE',
                    'user_action' => 'PAY_NOW',
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl
                ],
                'email_address' => filter_var($addr['email'] ?? '', FILTER_VALIDATE_EMAIL) ? $addr['email'] : null,
                'name' => ['given_name' => $firstName, 'surname' => $lastName]
            ]
        ],
        'purchase_units' => [[
            'custom_id' => (string)$paymentId,
            'amount' => ['currency_code' => $currencyCode, 'value' => number_format($totalAmount, 2, '.', '')]
        ]]
    ];

    $orderData['payment_source']['paypal'] = array_filter($orderData['payment_source']['paypal']);

    $orderRequest = paypal_create_order($accessToken, $orderData);
    $orderResponse = json_decode($orderRequest['body'], true);

    if ($orderRequest['code'] === 200 || $orderRequest['code'] === 201) {
        $approvalUrl = '';
        foreach ($orderResponse['links'] as $link) {
            if ($link['rel'] === 'payer-action' || $link['rel'] === 'approve') {
                $approvalUrl = $link['href'];
                break;
            }
        }

        // Update payment record with PayPal Order ID
        $gDb->queryPrepared(
            'UPDATE ' . TBL_RE_TRANS . ' SET rtr_pg_id = ?, rtr_pg_request = ?, rtr_timestamp_change = ? WHERE rtr_id = ?',
            [$orderResponse['id'], json_encode($orderData), DATETIME_NOW, $paymentId],
            false
        );

        return [
            'success' => true,
            'payment_id' => $paymentId,
            'amount' => $totalAmount,
            'currency' => $currency,
            'approval_url' => $approvalUrl,
            'paypal_order_id' => $orderResponse['id']
        ];
    }

    // Order creation failed
    $errorMsg = 'PayPal Order Creation Failed';
    if (!empty($orderResponse['message'])) {
        $errorMsg .= ': ' . $orderResponse['message'];
    }
    if (!empty($orderResponse['details'])) {
        foreach ($orderResponse['details'] as $detail) {
            $errorMsg .= ' - ' . ($detail['description'] ?? $detail['issue'] ?? '');
        }
    }
    error_log('PayPal Order Error: Code ' . $orderRequest['code'] . ' Body: ' . $orderRequest['body']);
    return ['error' => $errorMsg, 'error_code' => 'RE_PG_ORDER_FAILED'];
}

/**
 * Render PayPal payment for mobile WebView
 * Uses common initPaypalTransaction function
 * Mirrors renderCcavenueForMobile() from ccavenue_common.php
 */
function renderPaypalForMobile(array $invoiceIds, int $userId)
{
    // Use common transaction init with mobile source
    $result = initPaypalTransaction($invoiceIds, $userId, 'mobile');

    if (isset($result['error'])) {
        echo '<!DOCTYPE html>';
        echo '<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>';
        echo '<body style="font-family:-apple-system,BlinkMacSystemFont,sans-serif;text-align:center;padding:40px;background:#f8f9fa">';
        echo '<div style="background:#fff;padding:30px;border-radius:12px;max-width:320px;margin:auto;box-shadow:0 4px 20px rgba(0,0,0,0.1)">';
        echo '<div style="font-size:48px;margin-bottom:16px">⚠️</div>';
        echo '<h3 style="color:#dc3545;margin:0 0 12px">Payment Error</h3>';
        echo '<p style="color:#666;margin:0">' . htmlspecialchars($result['error']) . '</p>';
        echo '</div>';
        echo '<script>';
        echo 'if(window.ReactNativeWebView){window.ReactNativeWebView.postMessage(JSON.stringify({type:"PAYMENT_ERROR",success:false,data:' . json_encode($result) . '}));}';
        echo '</script>';
        echo '</body></html>';
        exit;
    }

    // Redirect to PayPal approval URL
    if (!empty($result['approval_url'])) {
        echo '<!DOCTYPE html>';
        echo '<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Redirecting…</title>';
        echo '<style>';
        echo 'body{margin:0;padding:60px 20px;text-align:center;background:linear-gradient(135deg,#0B1120 0%,#1a2942 100%);font-family:-apple-system,BlinkMacSystemFont,sans-serif;min-height:100vh;box-sizing:border-box}';
        echo '.loader{border:4px solid rgba(255,255,255,0.2);border-top:4px solid #349aaa;border-radius:50%;width:50px;height:50px;animation:spin 1s linear infinite;margin:0 auto 24px}';
        echo '@keyframes spin{to{transform:rotate(360deg)}}';
        echo 'h3{color:#fff;font-weight:600;margin:0 0 8px}';
        echo 'p{color:rgba(255,255,255,0.7);font-size:14px;margin:0}';
        echo '</style></head>';
        echo '<body>';
        echo '<div class="loader"></div>';
        echo '<h3>Connecting to PayPal</h3>';
        echo '<p>Please wait, do not close this window...</p>';
        echo '<script>window.location.href="' . htmlspecialchars($result['approval_url']) . '";</script>';
        echo '</body></html>';
        exit;
    }

    // If no approval URL was returned
    echo '<!DOCTYPE html>';
    echo '<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>';
    echo '<body style="font-family:-apple-system,BlinkMacSystemFont,sans-serif;text-align:center;padding:40px;background:#f8f9fa">';
    echo '<div style="background:#fff;padding:30px;border-radius:12px;max-width:320px;margin:auto;box-shadow:0 4px 20px rgba(0,0,0,0.1)">';
    echo '<div style="font-size:48px;margin-bottom:16px">⚠️</div>';
    echo '<h3 style="color:#dc3545;margin:0 0 12px">Payment Error</h3>';
    echo '<p style="color:#666;margin:0">Could not redirect to PayPal. Please try again.</p>';
    echo '</div></body></html>';
    exit;
}
