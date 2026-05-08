<?php

/*
 * ==========================================================
 * PAYSTACK.PHP
 * ==========================================================
 *
 * Process Paystack payments. © 2017-2025 board.support. All rights reserved.
 *
 */
require('functions.php');
header('Content-Type: application/json');
$raw = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
if ($signature !== hash_hmac('sha512', $raw, PAYSTACK_SECRET_KEY)) {
    http_response_code(401);
    exit('Invalid signature');
}
$response = json_decode($raw, true);
if ($response['event'] === 'charge.success') {
    $data = $response['data'];
    $metadata = $data['metadata'];
    $membership_id = sb_isset($metadata, 'membership_id');
    $user_id = sb_isset($metadata, 'sb_user_id');
    if (!$user_id || !$membership_id) {
        sb_cloud_debug('Paystack: membership ID or user ID not found.');
        sb_cloud_debug($response);
        die();
    }
    if (defined('STRIPE_PRODUCT_ID_WHITE_LABEL') && $membership_id == 'white_label') {
        membership_save_white_label($user_id);
        cloud_add_to_payment_history($user_id, $data['amount'] / 100, 'white-label', $data['id']);
        membership_add_reseller_sale(false, 'white-label', $data['amount_paid']);
    } else if ($membership_id == 'credits') {
        membership_set_purchased_credits($data['amount'] / 100, $data['currency'], $user_id, $data['id']);
    } else {
        $authorization_code = $data['authorization']['authorization_code'] ?? null;
        if (!$authorization_code) {
            sb_cloud_debug('Paystack: authorization code not found.');
            sb_cloud_debug($response);
            die();
        }
        $membership = membership_get($membership_id);
        if (!$membership) {
            sb_cloud_debug('Paystack: membership not found.');
            sb_cloud_debug($response);
            die();
        }
        membership_update($metadata['membership_id'], $membership['period'], $user_id, $authorization_code, sb_isset($metadata, 'referral'));
        cloud_add_to_payment_history($user_id, $membership['amount'], 'membership', $data['id']);
        //$subscriptions = sb_isset(paystack_curl('subscription?perPage=50&page=1', 'GET'), 'data', []);
        //foreach ($subscriptions as $subscription) {
        //    if ($subscription['id'] != $data['id']) {
        //        paystack_curl('subscription/disable', ['code' => $subscription['subscription_code']]);
        //    }
        //}
    }
}

?>