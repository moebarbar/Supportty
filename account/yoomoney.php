<?php

/*
 * ==========================================================
 * YOOMONEY.PHP
 * ==========================================================
 *
 * Process YooMoney payments. © 2017-2026 board.support. All rights reserved.
 *
 */

header('Content-Type: application/json');
$raw = file_get_contents('php://input');
$response = json_decode($raw, true); 

if ($response && isset($response['event']) && $response['event'] == 'payment.succeeded') {
    require('functions.php');
    $response_id = sb_isset($response, ['object', 'id']);
    if ($response_id) {
        $response = yoomoney_curl('payments/' . $response_id, false, 'GET');
        $error = false;
        if ($response && sb_isset($response, 'status') == 'succeeded') {
            $metadata = sb_isset($response, 'metadata');
            $cloud_user_id = sb_isset($metadata, 'sb_user_id');
            $amount = sb_isset($response, 'amount');
            if ($cloud_user_id && $amount) {
                if (isset($metadata['membership_id'])) {
                    $membership = membership_get($metadata['membership_id']);
                    if ($membership) {
                        membership_update($membership['id'], $membership['period'], $cloud_user_id, $response['id'], sb_isset($metadata, 'referral'));
                        cloud_add_to_payment_history($cloud_user_id, $amount['value'], 'membership', $response['id']);
                    } else {
                        $error = 'Membership not found:';
                    }
                } else if (isset($metadata['credits'])) {
                    membership_set_purchased_credits($amount['value'], $amount['currency'], $cloud_user_id, $response['id']);
                } else if (isset($metadata['white_label'])) {
                    if ($amount['value'] == super_get_white_label() && strtolower($amount['currency']) == strtolower(YOOMONEY_CURRENCY)) {
                        membership_save_white_label($cloud_user_id);
                        cloud_add_to_payment_history($cloud_user_id, $amount['value'], 'white-label', $response['id']);
                    }
                }
            }
        } else {
            $error = 'YooMoney error:';
        }
        if ($error) {
            sb_cloud_debug($error);
            sb_cloud_debug($response);
        }
    }
}

?>