<?php

/*
 * ==========================================================
 * SLACK CLOUD POST FILE
 * ==========================================================
 *
 * Slack cloud post file to forward Slack messages to the right account. © 2017-2026 board.support. All rights reserved.
 *
 */

proc_nice(10);
header('Content-Type: application/json');
$raw = file_get_contents('php://input');
$response = json_decode($raw, true);

if (isset($response['challenge'])) {
    die(json_encode(['challenge' => $response['challenge']]));
}
if (isset($response['event']) && isset($response['team_id']) && $response['event']['subtype'] != 'bot_message') {
    require('functions.php');
    $token = db_get('SELECT token FROM slack WHERE team_id = "' . db_escape($response['team_id']) . '"');
    if ($token && isset($token['token'])) {
        $ch = curl_init(CLOUD_URL . '/script/apps/slack/post.php?cloud=' . $token['token']);
        if ($ch !== false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $raw);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
        }
    }
}
 
?>