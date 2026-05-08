<?php

/*
 * ==========================================================
 * GOOGLE.PHP
 * ==========================================================
 *
 * © 2017-2025 board.support. All rights reserved.
 *
 */

if (isset($_GET['code'])) {
    require('functions.php');
    $query = '{ code: "' . $_GET['code'] . '", grant_type: "authorization_code", client_id: "' . GOOGLE_LOGIN_CLIENT_ID . '", client_secret: "' . GOOGLE_LOGIN_CLIENT_SECRET . '", redirect_uri: "' . CLOUD_URL . '/account/google.php" }';
    $response = sb_curl('https://accounts.google.com/o/oauth2/token', $query, ['Content-Type: application/json', 'Content-Length: ' . strlen($query)]);
    if ($response && isset($response['access_token'])) {
        $json = sb_curl('https://www.googleapis.com/oauth2/v3/userinfo', '', ['Authorization: Bearer ' . $response['access_token']], 'GET');
        $response = json_decode($json, true);
        $email = sb_isset($response, 'email');
        if (empty($email)) {
            echo json_encode($response);
        } else {
            $query = 'SELECT token, email FROM users WHERE email = "' . $email . '" LIMIT 1';
            $cloud_user = db_get($query);
            if (!$cloud_user) {
                account_registration(['first_name' => $response['given_name'], 'last_name' => $response['family_name'], 'profile_image' => sb_download_file($response['picture'], basename(parse_url($response['picture'], PHP_URL_PATH)) . '.jpg'), 'email' => $response['email'], 'email_confirmed' => '1', 'password' => bin2hex(openssl_random_pseudo_bytes(10)), 'extra' => 'google-' . $response['sub']]);
                $cloud_user = db_get($query);
            }
            $login_data = account_login($cloud_user['email'], false, $cloud_user['token']);
            header('Location: ' . CLOUD_URL . '?auto_login=' . urlencode($login_data[0]) . '&sb=' . urlencode($login_data[1]));
        }
    } else {
        echo json_encode($response);
    }
}

?>