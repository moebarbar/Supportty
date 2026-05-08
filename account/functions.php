<?php

use parallel\Events\Event\Type;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/*
 *
 * ===================================================================
 * CLOUD FUNCTIONS FILE
 * ===================================================================
 *
 * © 2017-2026 board.support. All rights reserved.
 *
 */

if (!defined('SB_VERSION')) {
    require_once(dirname(dirname(__FILE__)) . '/script/include/functions.php');
}
global $CLOUD_CONNECTION;
global $CUSTOMER_CONNECTION;
global $ACTIVE_ACCOUNT;

/*
 * -----------------------------------------------------------
 * DATABASE
 * -----------------------------------------------------------
 *
 */

function db_connect() {
    global $CLOUD_CONNECTION;
    if ($CLOUD_CONNECTION) {
        return true;
    }
    $CLOUD_CONNECTION = new mysqli(CLOUD_DB_HOST, CLOUD_DB_USER, CLOUD_DB_PASSWORD, CLOUD_DB_NAME);
    if ($CLOUD_CONNECTION->connect_error) {
        echo 'Connection error';
        return false;
    }
    return true;
}

function db_customer_connect($token) {
    global $CUSTOMER_CONNECTION;
    $database = get_config($token);
    $CUSTOMER_CONNECTION = new mysqli($database['SB_DB_HOST'], $database['SB_DB_USER'], $database['SB_DB_PASSWORD'], $database['SB_DB_NAME']);
    if ($CUSTOMER_CONNECTION->connect_error) {
        return false;
    }
    return true;
}

function db_get($query, $single = true, $customer_token = false) {
    $status = $customer_token ? db_customer_connect($customer_token) : db_connect();
    $connection = $GLOBALS[$customer_token ? 'CUSTOMER_CONNECTION' : 'CLOUD_CONNECTION'];
    $value = $single ? '' : [];
    if ($status) {
        $result = $connection->query($query);
        if ($result) {
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    if ($single) {
                        $value = $row;
                    } else {
                        array_push($value, $row);
                    }
                }
            }
        }
    } else {
        return $status;
    }
    return $value;
}

function db_query($query, $return = false, $customer_token = false) {
    $status = $customer_token ? db_customer_connect($customer_token) : db_connect();
    $connection = $GLOBALS[$customer_token ? 'CUSTOMER_CONNECTION' : 'CLOUD_CONNECTION'];
    if ($status) {
        $result = $connection->query($query);
        if ($result) {
            if ($return) {
                if (isset($connection->insert_id) && $connection->insert_id > 0) {
                    return $connection->insert_id;
                } else
                    return false;
            } else {
                return true;
            }
        } else {
            return $connection->error;
        }
    } else {
        return $status;
    }
}

function db_escape($value, $numeric = -1) {
    if (is_numeric($value)) {
        return $value;
    } else if ($numeric === true) {
        return false;
    }
    global $CLOUD_CONNECTION;
    db_connect();
    $value = $CLOUD_CONNECTION->real_escape_string($value);
    $value = sb_sanatize_string($value);
    $value = htmlspecialchars($value, ENT_NOQUOTES | ENT_SUBSTITUTE, 'utf-8');
    return $value;
}

/*
 * -----------------------------------------------------------
 * ACCOUNT
 * -----------------------------------------------------------
 *
 */

function account_registration($details) {
    $now = gmdate('Y-m-d H:i:s');
    $token = bin2hex(openssl_random_pseudo_bytes(20));
    $response = false;
    $appsumo = isset($details['appsumo']);

    // Validation
    if (strlen($details['password']) < 8) {
        return 'password-length';
    } else if (!strpos($details['email'], '@') || !strpos($details['email'], '.')) {
        return 'invalid-email';
    } else if (!$appsumo && intval(db_get('SELECT COUNT(*) as count FROM users WHERE email = "' . db_escape($details['email']) . '"')['count']) > 0) {
        return 'duplicate-email';
    }

    // Cloud user registration
    $membership = defined('SB_CLOUD_API') ? sb_isset($details, 'membership', '0') : '0';
    $membership_expiration = '';
    $extra = sb_isset($details, 'extra', '');
    $credits = 0.05;
    $response = db_query('INSERT INTO users (first_name, last_name, email, phone, password, membership, membership_expiration, token, last_activity, creation_time, email_confirmed, phone_confirmed, customer_id, extra, credits) VALUES ("' . db_escape($details['first_name']) . '", "' . db_escape($details['last_name']) . '", "' . db_escape($details['email']) . '", NULL, "' . db_escape(password_hash($details['password'], PASSWORD_DEFAULT)) . '", "' . $membership . '", "' . $membership_expiration . '", "' . $token . '", "' . $now . '", "' . $now . '", ' . sb_isset($details, 'email_confirmed', '0') . ', ' . sb_isset($details, 'phone_confirmed', '0') . ', "", "' . $extra . '", ' . $credits . ')', true);
    if (is_int($response)) {
        $user_slug = 'sb_' . rand(1, 99999999);
        $db_password = 'sb' . bin2hex(openssl_random_pseudo_bytes(20));
        $databases = array_column(db_get('SHOW DATABASES', false), 'Database');
        $db_name = $user_slug;
        while (in_array($user_slug, $databases)) {
            $user_slug = 'sb_' . rand(1, 99999999);
        }

        // Save additional user details
        $main_ids = ['first_name', 'last_name', 'email', 'password', 'password_2'];
        $query = '';
        foreach ($details as $key => $value) {
            if (!in_array($key, $main_ids)) {
                $query .= '(' . $response . ', "' . db_escape($key) . '", "' . db_escape($value) . '"),';
            }
        }
        $response_2 = $query ? super_insert_user_data(substr($query, 0, -1)) : false;
        if ($appsumo) {
            membership_save_white_label($response);
        }

        // Support Board database creation
        $response = db_query('CREATE DATABASE `' . $user_slug . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');
        if ($response === true) {
            if (CLOUD_URL === 'http://localhost/support-board/cloud') {
                $user_slug = 'root';
                $db_password = '';
            } else {
                $mysql_user = "'" . $user_slug . "'@'" . (defined('CLOUD_IP') ? CLOUD_IP : 'localhost') . "'";
                db_query('CREATE USER ' . $mysql_user . ' IDENTIFIED BY \'' . $db_password . '\'');
                db_query('GRANT ALL PRIVILEGES ON ' . $user_slug . '.* TO ' . $mysql_user . ' WITH GRANT OPTION');
                db_query('GRANT select, update, delete, insert, create, drop, index, alter, lock tables, execute, create temporary tables, execute, trigger, create view, show view, references, event ON ' . $user_slug . '.* TO ' . $mysql_user);
            }
            $raw = str_replace(['[url]', '[name]', '[user]', '[password]', '[host]', '[port]'], [CLOUD_URL . '/script', $db_name, $user_slug, $db_password, CLOUD_DB_HOST, ''], file_get_contents('../script/resources/config-source.php'));
            $file = fopen(SB_CLOUD_PATH . '/script/config/config_' . $token . '.php', 'w');
            fwrite($file, $raw);
            fclose($file);

            // Support Board installation
            $response = sb_installation(['db-name' => [$db_name], 'db-user' => [$user_slug], 'db-password' => [$db_password], 'db-host' => [CLOUD_DB_HOST], 'first-name' => [$details['first_name']], 'last-name' => [$details['last_name']], 'password' => [$details['password']], 'email' => [$details['email']], 'url' => CLOUD_URL . '/script', 'envato-purchase-code' => [ENVATO_PURCHASE_CODE], 'profile_image' => sb_isset($details, 'profile_image')]);
            db_query('INSERT INTO sb_settings(name, value) VALUES (\'active_apps\', \'["dialogflow","whatsapp","telegram","messenger","viber","tickets","line"]\')', false, $token);
            if ($response && isset($response['error'])) {
                return $response;
            }

            // Webhook and return
            cloud_webhook('user-registration', $details);
            return account_login($details['email'], $details['password']);
        }
    }
    return $response;
}

function account_login($email, $password = false, $token = false) {
    $sb_login = false;
    $email = db_escape($email);
    $cloud_user = db_get('SELECT id AS `user_id`, first_name, last_name, email, phone, password, token, email_confirmed, phone_confirmed, customer_id, extra FROM users WHERE email = "' . $email . '" LIMIT 1');
    if (!$cloud_user) {
        $agent = db_get('SELECT A.token, A.id FROM users A, agents B WHERE B.email = "' . $email . '" AND A.id = B.admin_id');
        if ($agent) {
            require_once(SB_PATH . '/config/config_' . $agent['token'] . '.php');
            $cloud_user = sb_db_get('SELECT * FROM sb_users WHERE email = "' . $email . '"');
            $cloud_user['token'] = $agent['token'];
            $cloud_user['user_id'] = $agent['id'];
        }
    } else {
        $cloud_user['owner'] = true;
    }
    if ($cloud_user) {
        if (($password && password_verify($password, $cloud_user['password'])) || ($token && $token == $cloud_user['token'])) {
            require_once(SB_PATH . '/config/config_' . $cloud_user['token'] . '.php');
            if ($password) {
                $sb_login = sb_login($email, $password);
            } else {
                $active_user = sb_get_user_by('email', $email);
                if ($active_user) {
                    $sb_login = sb_login(false, false, $active_user['id'], $active_user['token']);
                } else {
                    return false;
                }
            }
            if ($sb_login === 'ip-ban') {
                return $sb_login;
            }
            return [sb_encryption(json_encode($cloud_user)), $sb_login[1], $sb_login[0]['id']];
        }
    }
    return false;
}

function account_login_get_user($email, $password) {
    $login = account_login($email, $password);
    if ($login && is_array($login)) {
        $user = sb_get_user($login[2], true);
        $_COOKIE['sb-cloud'] = $login[0];
        $details = membership_get_active();
        if (isset($details['name'])) {
            $user['details']['membership'] = [$details['name'], 'Membership'];
        }
        return $user;
    }
    return false;
}

function account() {
    global $ACTIVE_ACCOUNT;
    if ($ACTIVE_ACCOUNT) {
        return $ACTIVE_ACCOUNT;
    }
    if (empty($_COOKIE['sb-cloud']) && empty($_POST['cloud']) && empty($_GET['cloud'])) {
        return false;
    }
    $cookie = json_decode(sb_encryption(isset($_COOKIE['sb-cloud']) ? $_COOKIE['sb-cloud'] : (isset($_POST['cloud']) ? $_POST['cloud'] : $_GET['cloud']), false), true);
    $cookie['chat_id'] = account_chat_id($cookie['user_id']);
    $ACTIVE_ACCOUNT = $cookie;
    return $cookie;
}

function account_chat_id($user_id) {
    return intval($user_id) * 95675 - 153;
}

function get_active_account_id($escape = true) {
    $id = sb_isset(account(), 'user_id');
    return $escape ? db_escape($id, true) : $id;
}

function account_save($details) {
    $account = account();
    require_once(SB_PATH . '/config/config_' . $account['token'] . '.php');
    $user_id = $account['user_id'];
    $email = $account['email'];
    $query = '';
    $query_sb = '';
    $main_ids = ['first_name', 'last_name', 'email', 'password', 'password_2', 'phone', 'credits', 'membership', 'membership_expiration'];
    $company_details = [];
    if (!super_admin()) {
        unset($details['credits']);
        unset($details['membership']);
        unset($details['membership_expiration']);
    }
    foreach ($details as $key => $value) {
        $value = str_replace('&amp;', '&', db_escape($value));
        $key = db_escape($key);
        if (in_array($key, $main_ids)) {
            if ($key == 'password') {
                if ($value != 12345678) {
                    $value = password_hash($details['password'], PASSWORD_DEFAULT);
                    $query_sb .= $key . ' = "' . $value . '",';
                } else {
                    continue;
                }
            }
            if (in_array($key, ['first_name', 'last_name', 'email'])) {
                $query_sb .= $key . ' = "' . $value . '",';
            }
            $query .= $key . ' = "' . $value . '",';
        } else if (str_contains($key, 'company_')) {
            $company_details[$key] = $value;
        }
    }
    $response = sb_db_query('UPDATE sb_users SET ' . substr($query_sb, 0, -1) . ' WHERE email = "' . $email . '"');
    if ($response === true) {
        $response = db_query('UPDATE users SET ' . substr($query, 0, -1) . ' WHERE id = ' . $user_id);
        if ($company_details) {
            super_delete_user_data($user_id, 'company_details');
            super_insert_user_data('(' . $user_id . ', "company_details", "' . sb_db_json_escape($company_details) . '")');
        }
        if ($response === true) {
            if (isset($details['email']) && $details['email'] != $email) {
                db_query('UPDATE users SET email_confirmed = 0 WHERE id = ' . $user_id);
            }
            if (isset($details['phone']) && $details['phone'] != $account['phone']) {
                db_query('UPDATE users SET phone_confirmed = 0 WHERE id = ' . $user_id);
            }
            return account_login($details['email'], false, $account['token']);
        }
    }
    return $response;
}

function account_reset_password($email = false, $token = false, $password = false) {
    if ($email && !$password) {
        $email = db_escape($email);
        $token = db_get('SELECT token FROM users WHERE email = "' . $email . '" LIMIT 1');
        if (!$token) {
            $token = db_get('SELECT token FROM users A, agents B WHERE A.id = B.admin_id AND B.email = "' . $email . '" LIMIT 1');
        }
        if ($token) {
            send_email($email, super_get_setting('email_subject_reset_password'), str_replace('{link}', CLOUD_URL . '/account?reset=' . sb_encryption($token['token']) . '&email=' . sb_encryption($email), super_get_setting('email_template_reset_password')));
        }
    } else if ($token && $password) {
        $password = db_escape(password_hash($password, PASSWORD_DEFAULT));
        $token = db_escape(sb_encryption($token, false));
        $email = db_escape(sb_encryption($email, false));
        require_once(SB_PATH . '/config/config_' . $token . '.php');
        db_query('UPDATE users SET password = "' . $password . '" WHERE token = "' . $token . '" AND email = "' . $email . '" LIMIT 1');
        sb_db_query('UPDATE sb_users SET password = "' . $password . '" WHERE email = "' . $email . '" LIMIT 1');
    }
    return true;
}

function account_welcome() {
    $account = account();
    if ($account) {
        send_email($account['email'], super_get_setting('email_subject_welcome'), cloud_merge_field_username(super_get_setting('email_template_welcome'), $account['first_name'] . ' ' . $account['last_name']));
    }
}

function account_delete() {
    $cloud_user_id = get_active_account_id(false);
    if ($cloud_user_id) {
        super_delete_customer($cloud_user_id);
    }
}

function account_get_user_details() {
    $account = account();
    if ($account) {
        $company_deatails = sb_isset(db_get('SELECT value FROM users_data WHERE slug = "company_details" AND user_id = ' . db_escape($account['user_id'], true)), 'value');
        if (!empty($company_deatails)) {
            $company_deatails = json_decode($company_deatails, true);
            if (is_array($company_deatails)) {
                $account = array_merge($account, $company_deatails);
            }
        }
    }
    return $account;
}

function account_delete_agents_quota() {
    $account = account();
    if ($account) {
        $membership = membership_get_active();
        $count = $membership['count_agents'] - sb_isset(membership_get($membership['id']), 'quota_agents', 9999);
        if ($count > 0) {
            require_once(SB_PATH . '/config/config_' . $account['token'] . '.php');
            $agent_emails = array_column(db_get('SELECT email FROM agents WHERE admin_id = ' . $account['user_id'] . ' ORDER BY ID desc LIMIT ' . $count, false), 'email');
            if (!empty($agent_emails)) {
                $implode = '("' . implode('","', $agent_emails) . '")';
                sb_db_query('DELETE FROM sb_users WHERE email IN ' . $implode);
                db_query('DELETE FROM agents WHERE admin_id = ' . $account['user_id'] . ' AND email IN ' . $implode);
            }
        }
    }
}

function account_get_payment_id() {
    global $ACTIVE_PAYMENT_ID;
    if ($ACTIVE_PAYMENT_ID) {
        return $ACTIVE_PAYMENT_ID;
    }
    $ACTIVE_PAYMENT_ID = sb_isset(db_get('SELECT customer_id FROM users WHERE id = ' . get_active_account_id()), 'customer_id');
    return $ACTIVE_PAYMENT_ID;
}

function account_magic_link($email) {
    $token = sb_isset(db_get('SELECT token FROM users WHERE email = "' . db_escape($email) . '"'), 'token');
    return $token ? CLOUD_URL . '?magic=' . sb_encryption($token . '|' . $email) : false;
}

function account_magic_link_login($magic) {
    $magic = explode('|', sb_encryption($_GET['magic'], false));
    if ($magic && count($magic) > 1) {
        $login = account_login($magic[1], false, $magic[0]);
        if ($login) {
            $expiration = time() + 315360000;
            setcookie('sb-cloud', $login[0], $expiration, '/');
            setcookie('sb-login', $login[1], $expiration, '/');
            $_COOKIE['sb-cloud'] = $login[0];
            $_COOKIE['sb-login'] = $login[1];
            return $login;
        }
    }
    return false;
}

function account_save_referral_payment_information($method, $details) {
    super_delete_user_data(get_active_account_id(), 'referral_payment_info', true);
    return super_insert_user_data('(' . get_active_account_id() . ', "referral_payment_info", "' . db_escape($method . '|' . $details) . '")');
}

/*
 * -----------------------------------------------------------
 * MEMBERSHIP
 * -----------------------------------------------------------
 *
 */

function memberships() {
    global $SB_CLOUD_MEMBERSHIPS;
    if (isset($SB_CLOUD_MEMBERSHIPS)) {
        return $SB_CLOUD_MEMBERSHIPS;
    }
    $membership_data = super_get_setting('memberships');
    $SB_CLOUD_MEMBERSHIPS = $membership_data ? json_decode($membership_data, true) : [['price' => 0, 'name' => 'Free', 'id' => '0', 'period' => '', 'currency' => '', 'quota' => 100]];
    return $SB_CLOUD_MEMBERSHIPS;
}

function membership_get_active($cache = true) {
    global $SB_CLOUD_ACTIVE_MEMBERSHIP;
    if (isset($SB_CLOUD_ACTIVE_MEMBERSHIP)) {
        return $SB_CLOUD_ACTIVE_MEMBERSHIP;
    }
    $account = account();
    if (empty($account)) {
        return sb_error('Account not found', 'membership_get_active');
    }
    $cloud_user_id = $account['user_id'];
    $cache = $cache ? json_decode(super_get_user_data('active_membership_cache', $cloud_user_id), true) : false;
    if ($cache && $cache[1] > time()) {
        $SB_CLOUD_ACTIVE_MEMBERSHIP = $cache[0];
        return $cache[0];
    }
    $memberships = memberships();
    $membership = $memberships[0];
    $membership_active = db_get('SELECT membership, membership_expiration, credits FROM users WHERE id = ' . $cloud_user_id);
    $membership_id = sb_isset($membership_active, 'membership', 0);
    for ($i = 0; $i < count($memberships); $i++) {
        if ($memberships[$i]['id'] == $membership_id) {
            $membership = $memberships[$i];
            break;
        }
    }
    $membership['credits'] = floatval(sb_isset($membership_active, 'credits', 0));
    $membership['expiration'] = sb_isset($membership_active, 'membership_expiration');
    $membership_type = sb_defined('SB_CLOUD_MEMBERSHIP_TYPE', 'messages');
    if ($membership_type == 'messages' || $membership_type == 'messages-agents') {
        $membership['count'] = sb_isset(db_get('SELECT count FROM membership_counter WHERE date = "' . date('m-y') . '" AND user_id = ' . $cloud_user_id), 'count', 0);
        if ($membership_type != 'messages') {
            $membership['count_agents'] = membership_count_agents_users($account['token']);
        }
    } else {
        $membership['count'] = membership_count_agents_users($account['token']);
    }
    $SB_CLOUD_ACTIVE_MEMBERSHIP = $membership;
    $json = db_escape(json_encode([$membership, time() + 86400]));
    if ($cache) {
        db_query('UPDATE users_data SET value = "' . $json . '" WHERE user_id = ' . $cloud_user_id . ' AND slug = "active_membership_cache"');
    } else {
        membership_delete_cache($cloud_user_id);
        super_insert_user_data('(' . $cloud_user_id . ', "active_membership_cache", "' . $json . '")');
    }
    return $membership;
}

function membership_get($membership_id) {
    $memberships = memberships();
    if (!$membership_id) {
        return $memberships[0];
    }
    for ($i = 0; $i < count($memberships); $i++) {
        if ($memberships[$i]['id'] == $membership_id) {
            return $memberships[$i];
        }
    }
    return false;
}

function membership_update($membership_id, $membership_period, $customer_id, $payment_id = false, $referral = false) {
    membership_add_reseller_sale($membership_id);
    $customer_id = db_escape($customer_id);
    $response = db_query('UPDATE users SET membership = "' . db_escape($membership_id) . '", membership_expiration = "' . membership_calculate_expiration($membership_period) . '"' . ($payment_id ? ', customer_id = "' . sb_db_escape($payment_id) . '"' : '') . ' WHERE id = "' . $customer_id . '"');
    if ($referral && $referral != 'false') {
        $price = sb_isset(membership_get($membership_id), 'price');
        $commission = sb_isset(super_get_settings(), 'referral-commission');
        if ($price && $commission && !super_get_user_data('referred', $customer_id)) {
            $user_id = db_escape(sb_encryption($referral, false));
            if ($user_id) {
                $total = super_get_user_data('referral', $user_id, 0);
                $price = floatval($price) * (intval($commission) / 100);
                if ($total) {
                    db_query('UPDATE users_data SET value = "' . (floatval($total) + $price) . '" WHERE user_id = ' . $user_id . ' AND slug = "referral" LIMIT 1');
                } else {
                    super_insert_user_data('(' . $user_id . ', "referral", "' . $price . '")');
                }
                super_insert_user_data('(' . $customer_id . ', "referred", "true")');
            }
        }
    }
    db_query('DELETE FROM users_data WHERE user_id = ' . $customer_id . ' AND (slug = "notifications_count" OR slug = "active_membership_cache" OR slug = "subscription_cancelation") LIMIT 3');
    return $response;
}

function membership_volume() {
    $account = account();
    $year = date('y');
    $counts = 0;
    $response = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
    $membership_type = sb_defined('SB_CLOUD_MEMBERSHIP_TYPE', 'messages');
    if ($membership_type == 'messages' || $membership_type == 'messages-agents') {
        $counts = db_get('SELECT count, date FROM membership_counter WHERE date LIKE "%-' . $year . '" AND user_id = ' . $account['user_id'], false);
        for ($i = 0; $i < count($counts); $i++) {
            $response[intval(substr($counts[$i]['date'], 0, 2)) - 1] = $counts[$i]['count'];
        }
    } else {
        $response = [membership_count_agents_users($account['token'])];
    }
    return $response;
}

function membership_get_payments() {
    return db_get('SELECT * from users_data WHERE user_id = ' . get_active_account_id() . ' AND slug = "payment" ORDER BY id DESC', false);
}

function membership_get_invoice($payment_id) {
    $user_id = get_active_account_id();
    $payment = db_get('SELECT * from users_data WHERE user_id = ' . $user_id . ' AND id = ' . db_escape($payment_id, true));
    if ($payment) {
        $payment = json_decode($payment['value'], true);
        return cloud_invoice($user_id, $payment[0], $payment[1], $payment[5]);
    }
    return false;
}

function membership_invoices_payment_provider() {
    switch (PAYMENT_PROVIDER) {
        case 'stripe':
            $stripe_id = account_get_payment_id();
            if ($stripe_id) {
                $response = stripe_curl('invoices?customer=' . $stripe_id, 'GET');
                return sb_isset($response, 'data', []);
            }
            break;
        case 'rapyd':
            $rapyd_id = account_get_payment_id();
            if ($rapyd_id) {
                $response = json_decode(rapyd_curl('payments?limit=99&customer=' . $rapyd_id, '', 'GET'), true);
                return sb_isset($response, 'data', []);
            }
            break;
        case 'verifone':
            $verifone_id = account_get_payment_id();
            if ($verifone_id) {
                return verifone_get_orders($verifone_id);
            }
            break;
    }
    return [];
}

function membership_calculate_expiration($period) {
    $seconds = 0;
    switch ($period) {
        case 'day':
            $seconds = 172800;
            break;
        case 'week':
            $seconds = 691200;
            break;
        case 'month':
            $seconds = 2678400;
            break;
        case 'year':
            $seconds = 31622400;
            break;
        case '3month':
            $seconds = 8035200;
            break;
        case '6month':
            $seconds = 15638400;
            break;
        case '2year':
            $seconds = 63072000;
            break;
        case '3year':
            $seconds = 94521600;
            break;
    }
    return gmdate('d-m-y', time() + $seconds);
}

function membership_get_period_string($plan_period) {
    switch ($plan_period) {
        case 'day':
            return sb_('a day');
        case 'week':
            return sb_('a week');
        case 'month':
            return sb_('a month');
        case 'year':
            return sb_('a year');
        case '3month':
            return sb_('every 3 months');
        case '6month':
            return sb_('every 6 months');
        case '2year':
            return sb_('every 2 years');
        case '3year':
            return sb_('every 3 years');
    }
    return '';
}

function membership_count_agents_users($token) {
    return db_get('SELECT COUNT(*) AS `count` FROM sb_users WHERE ' . (sb_defined('SB_CLOUD_MEMBERSHIP_TYPE', 'messages') == 'users' ? 'user_type <> "admin" AND user_type <> "agent" AND user_type <> "bot"' : 'user_type = "admin" OR user_type = "agent"'), true, $token)['count'];
}

function membership_add_reseller_sale($membership_id = false, $extra = false, $amount = false) {
    $url_part = 'https://cloud.board.support/account/resellers/api.php?cloud_url=' . CLOUD_URL . '&cloud_email=' . SUPER_EMAIL . ($extra ? '&extra=' . $extra : '') . '&price=';
    if ($amount) {
        return sb_get($url_part . $amount . '&currency=' . membership_currency());
    }
    $membership = membership_get($membership_id);
    $amount = $membership['price'];
    return empty($amount) || $amount == '0' ? false : sb_get($url_part . $amount . '&currency=' . $membership['currency']);
}

function membership_save_white_label($cloud_user_id) {
    super_delete_user_data($cloud_user_id, 'white-label');
    return super_insert_user_data('(' . $cloud_user_id . ', "white-label", "' . gmdate('d-m-y', time() + (PAYMENT_PROVIDER == 'paystack' ? 2628000 : 31536000)) . '")');
}

function membership_is_white_label($cloud_user_id) {
    $white_label = super_get_user_data('white-label', $cloud_user_id);
    return $white_label ? sb_gmt_now(0, true) < cloud_gmt_time($white_label) : false;
}

function membership_purchase_white_label($external_integration = false) {
    $cloud_user_id = get_active_account_id(false);
    $price = super_get_white_label();
    if ($price) {
        if ($external_integration == 'shopify') {
            return shopify_one_time_purchase('White Label', $price, CLOUD_URL . '/account/?tab=membership&reload=true#addons');
        }
        $customer_id = account_get_payment_id();
        switch (PAYMENT_PROVIDER) {
            case 'stripe':
                $price = sb_isset(stripe_curl('prices?product=' . STRIPE_PRODUCT_ID_WHITE_LABEL, 'GET'), 'data');
                return stripe_create_session($price[0]['id'], $cloud_user_id, 'white_label|' . $cloud_user_id);
            case 'paystack':
                return paystack_create_subscription('white_label');
            case 'rapyd':
                $customer_id = account_get_payment_id();
                if (!$customer_id) {
                    $customer_id = rapyd_create_customer();
                }
                $response = rapyd_curl_checkout($price, $customer_id, $cloud_user_id, ['white_label' => true]);
                return isset($response['redirect_url']) ? ['url' => $response['redirect_url']] : $response;
            case 'verifone':
                $customer_id = account_get_payment_id();
                $account = account();
                $url = 'https://secure.2checkout.com/checkout/buy?merchant=' . VERIFONE_MERCHANT_ID . '&dynamic=1&currency=' . VERIFONE_CURRENCY . '&customer-ref=' . ($customer_id ? $customer_id : 'false') . '&duration=10:YEAR&email=' . $account['email'] . '&order-ext-ref=' . sb_encryption('white_label|' . $cloud_user_id) . '&price=' . $price . '&prod=' . sb_('White Label') . '&qty=1&recurrence=1:YEAR&renewal-price=' . $price . '&type=digital';
                return ['url' => $url . '&signature=' . verifone_get_signature($url)];
            case 'razorpay':
                return membership_custom_payment($price, 'white_label');
            case 'yoomoney':
                $response = yoomoney_create_payment($price, YOOMONEY_CURRENCY, CLOUD_URL . '/account/?tab=membership', sb_('White Label'), ['sb_user_id' => $cloud_user_id, 'white_label' => true]);
                $confirmation = sb_isset($response, 'confirmation');
                return $confirmation ? ['url' => $confirmation['confirmation_url']] : $response;
            case 'manual':
                return ['url' => PAYMENT_MANUAL_LINK];
        }
    }
    return false;
}

function membership_purchase_credits($amount, $external_integration = false) {
    if ($external_integration == 'shopify') {
        return shopify_one_time_purchase('Add credits - ' . $amount . ' USD', $amount, CLOUD_URL . '/account/?tab=membership&reload=true#credits');
    }
    return membership_custom_payment($amount, 'credits');
}

function membership_custom_payment($amount, $metadata_id) {
    $cloud_user_id = get_active_account_id();
    if ($amount && $cloud_user_id) {
        $payment_id = account_get_payment_id();
        $amount = floatval($amount);
        switch (PAYMENT_PROVIDER) {
            case 'stripe':
                if (!$payment_id) {
                    $account = account();
                    $response = stripe_curl('customers?email=' . $account['email'] . str_replace(' ', '%20', '&name=' . $account['first_name'] . ' ' . $account['last_name']));
                    $payment_id = sb_isset($response, 'id');
                    if ($payment_id) {
                        db_query('UPDATE users SET customer_id = "' . $payment_id . '" WHERE id = ' . $cloud_user_id);
                    }
                }
                return stripe_create_session(false, $cloud_user_id, $metadata_id . '|' . $cloud_user_id . '|' . ($amount * currency_get_divider(STRIPE_CURRENCY)), false, STRIPE_CURRENCY);
            case 'paystack':
                return paystack_create_charge($amount, $metadata_id);
            case 'rapyd':
                if (!$payment_id) {
                    $payment_id = rapyd_create_customer();
                }
                $metadata = [];
                $metadata[$metadata_id] = true;
                $response = rapyd_curl_checkout($amount, $payment_id, $cloud_user_id, $metadata);
                return isset($response['redirect_url']) ? ['url' => $response['redirect_url']] : $response;
            case 'verifone':
                $account = account();
                $url = 'https://secure.2checkout.com/checkout/buy?merchant=' . VERIFONE_MERCHANT_ID . '&dynamic=1&currency=' . VERIFONE_CURRENCY . '&customer-ref=' . ($payment_id ? $payment_id : 'false') . '&duration=10:YEAR&email=' . $account['email'] . '&order-ext-ref=' . sb_encryption($metadata_id . '|' . $cloud_user_id) . '&price=' . $amount . '&prod=' . sb_(sb_string_slug($metadata_id, 'string')) . '&qty=1&type=digital';
                return ['url' => $url . '&signature=' . verifone_get_signature($url)];
            case 'razorpay':
                $notes = [];
                $notes[$metadata_id] = true;
                $response = razorpay_create_payment_link($amount * currency_get_divider(RAZORPAY_CURRENCY), $notes);
                return is_string($response) && str_contains($response, 'http') ? ['url' => $response] : $response;
            case 'yoomoney':
                $metadata = ['sb_user_id' => $cloud_user_id];
                $metadata[$metadata_id] = true;
                $response = yoomoney_create_payment($amount, YOOMONEY_CURRENCY, CLOUD_URL . '/account?tab=membership', sb_string_slug($metadata_id, 'string'), $metadata);
                $confirmation = sb_isset($response, 'confirmation');
                return $confirmation ? ['url' => $confirmation['confirmation_url']] : $response;
            case 'manual':
                return ['url' => PAYMENT_MANUAL_LINK];
        }
    }
    return false;
}

function membership_use_credits($spending_source, $extra = false) {
    // $spending_sources cost based on the highest cost between input and output / 1.000.000
    $spending_sources = [
        'es' => 0.002,
        'cx' => 0.007,
        'es-audio' => 0.000433,
        'cx-audio' => 0.001,
        'translation' => 0.00002,
        'gpt-3.5-turbo-instruct' => 0.000002,
        'gpt-3.5-turbo' => 0.000002,
        'gpt-3.5-turbo-0125' => 0.000001,
        'gpt-3.5-turbo-1106' => 0.000002,
        'gpt-5' => 0.00001,
        'gpt-5-mini' => 0.000002,
        'gpt-5-nano' => 0.0000004,
        'gpt-4' => 0.00003,
        'gpt-4-32k' => 0.00006,
        'gpt-4-turbo' => 0.00003,
        'gpt-4o' => 0.00001,
        'gpt-4o-mini' => 0.00000015,
        'gpt-4.1-nano' => 0.0000004,
        'gpt-4.1-mini' => 0.0000016,
        'gpt-4.1' => 0.000008,
        'o1' => 0.00006,
        'o1-mini' => 0.000012,
        'o3-mini' => 0.0000044,
        'o4-mini' => 0.0000044,
        'ada' => 0.0000001,
        'text-embedding-3-small' => 0.00000002,
        'whisper' => 0.0001
    ];
    switch ($spending_source) {
        case 'whisper':
        case 'cx-audio':
        case 'es-audio':
            $browser = sb_isset($_SERVER, 'HTTP_SEC_CH_UA', '');
            $divider = 18;
            if (str_contains($browser, 'Opera')) {
                $divider = 8;
            }
            $amount = ($spending_source == 'whisper' ? filesize($extra) : $extra) / 1000 / $divider;
            $spending_sources[$spending_source] = $spending_sources[$spending_source] * $amount;
            break;
        case 'translation':
            $spending_sources[$spending_source] = $spending_sources[$spending_source] * strlen($extra);
            break;
        case 'text-embedding-3-small':
        case 'ada':
        case 'o1':
        case 'o1-mini':
        case 'o3-mini':
        case 'o4-mini':
        case 'gpt-5':
        case 'gpt-5-mini':
        case 'gpt-5-nano':
        case 'gpt-4-turbo':
        case 'gpt-4o':
        case 'gpt-4.1-nano':
        case 'gpt-4.1-mini':
        case 'gpt-4.1':
        case 'gpt-4o-mini':
        case 'gpt-4-32k':
        case 'gpt-4':
        case 'gpt-3.5-turbo':
        case 'gpt-3.5-turbo-0125':
        case 'gpt-3.5-turbo-1106':
        case 'gpt-3.5-turbo-instruct':
            $spending_sources[$spending_source] = $spending_sources[$spending_source] * $extra;
            break;
    }
    $amount = sb_isset($spending_sources, $spending_source) * 2;
    return $amount ? db_query('UPDATE users SET credits = credits - ' . $amount . ' WHERE id = ' . get_active_account_id()) : false;
}

function membership_set_auto_recharge($enabled) {
    $user_id = db_escape(sb_isset(account(), 'user_id'), true);
    super_delete_user_data($user_id, 'auto_recharge', true);
    return $enabled && $enabled != 'false' ? super_insert_user_data('(' . $user_id . ', "auto_recharge", "1")') : true;
}

function membership_auto_recharge() {
    $account = account();
    $user_id = db_escape(sb_isset($account, 'user_id'), true);
    if ($user_id) {
        switch (PAYMENT_PROVIDER) {
            case 'stripe':
                $payment_method = super_get_user_data('stripe_payment_method', $user_id);
                if ($payment_method) {
                    $amount = super_get_user_data('payment', $user_id);
                    if ($amount) {
                        $amount = json_decode($amount, true);
                        $response = stripe_curl('payment_intents?confirm=true&setup_future_usage=off_session&amount=' . ($amount[0] * currency_get_divider(STRIPE_CURRENCY)) . '&currency=' . STRIPE_CURRENCY . '&metadata[sb_credits]=true&payment_method=' . $payment_method . '&customer=' . account_get_payment_id());
                        if (sb_isset($response, 'status') == 'succeeded') {
                            return true;
                        } else {
                            sb_cloud_debug($response);
                        }
                        return true;
                    } else {
                        sb_cloud_debug('membership_auto_recharge: credits payment history not found for user ID ' . $user_id);
                    }
                } else {
                    sb_cloud_debug('membership_auto_recharge: payment_method not found for user ID ' . $user_id);
                }
                break;
            case 'yoomoney':
                $payments = db_get('SELECT value FROM users_data WHERE slug = "payment" AND user_id = ' . $user_id . ' ORDER BY id DESC', false);
                if ($payments) {
                    for ($i = 0; $i < count($payments); $i++) {
                        $payment = json_decode($payments[$i]['value'], true);
                        if ($payment[1] == 'credits') {
                            $response = yoomoney_recurring_payment($payment[0], account_get_payment_id(), 'Credits');
                            if ($response === true) {
                                return membership_set_purchased_credits($payment[0], YOOMONEY_CURRENCY, $user_id, account_get_payment_id());
                            }
                            return false;
                        }
                    }
                }
                break;
        }
    }
    return false;
}

function membership_set_purchased_credits($amount, $currency_code, $user_id, $payment_history_id = false, $extra = '') {
    $credits = intval($amount * (strtolower($currency_code) == 'usd' ? 1 : (1 / sb_usd_rates($currency_code))));
    db_query('UPDATE users SET credits = credits + ' . db_escape($credits, true) . ' WHERE id = ' . $user_id);
    super_delete_user_data($user_id, 'notifications_credits_count', true);
    membership_add_reseller_sale(false, 'credits', $amount);
    return $payment_history_id ? cloud_add_to_payment_history($user_id, $amount, 'credits', $payment_history_id, $extra) : true;
}

function membership_currency() {
    if (PAYMENT_PROVIDER == 'stripe') {
        return STRIPE_CURRENCY;
    }
    if (PAYMENT_PROVIDER == 'rapyd') {
        return RAPYD_CURRENCY;
    }
    if (PAYMENT_PROVIDER == 'verifone') {
        return VERIFONE_CURRENCY;
    }
    if (PAYMENT_PROVIDER == 'razorpay') {
        return RAZORPAY_CURRENCY;
    }
    if (PAYMENT_PROVIDER == 'yoomoney') {
        return YOOMONEY_CURRENCY;
    }
    if (PAYMENT_PROVIDER == 'paystack') {
        return PAYSTACK_CURRENCY;
    }
    if (PAYMENT_PROVIDER == 'manual') {
        return PAYMENT_MANUAL_CURRENCY;
    }
}

function membership_delete_cache($cloud_user_id) {
    return super_delete_user_data($cloud_user_id, 'active_membership_cache', true);
}

/*
 * -----------------------------------------------------------
 * STRIPE
 * -----------------------------------------------------------
 *
 */

function stripe_create_session($price_id, $cloud_user_id, $client_reference_id = false, $subscription = true, $extra = false) {
    $membership = $price_id ? membership_get($price_id) : false;
    if (!$client_reference_id) {
        $client_reference_id = $price_id . '|' . $cloud_user_id . '|' . $membership['period'] . (isset($_COOKIE['sb-referral']) ? '|' . $_COOKIE['sb-referral'] : '') . '|sb';
    } else if (!strpos($client_reference_id, '|sb')) {
        $client_reference_id .= '|sb';
    }
    $stripe_id = account_get_payment_id();
    if (!$stripe_id || strpos($stripe_id, 'cus_') === false) {
        $stripe_id = stripe_update_customer($cloud_user_id);
        if (sb_is_error($stripe_id)) {
            return $stripe_id;
        }
    }
    $response = stripe_curl('checkout/sessions?cancel_url=' . CLOUD_URL . '/account%3Ftab=membership&success_url=' . CLOUD_URL . '/account/%3Ftab=membership%26payment=success' . (explode('|', $client_reference_id)[0] == 'credits' ? '%26payment_type=credits' : '') . ($price_id ? '&line_items[0][price]=' . $price_id . '&line_items[0][quantity]=1' : '&currency=' . $extra) . '&mode=' . ($subscription ? 'subscription' : ($price_id ? 'payment' : 'setup')) . '&client_reference_id=' . $client_reference_id . '&customer=' . $stripe_id);
    return $response;
}

function stripe_cancel_subscription() {
    $stripe_id = account_get_payment_id();
    if ($stripe_id) {
        $subscription_id = stripe_curl('subscriptions?customer=' . $stripe_id, 'GET');
        if ($subscription_id && isset($subscription_id['data'])) {
            if (count($subscription_id['data']) === 0) {
                return 'no-subscriptions';
            }
            $response = stripe_curl('subscriptions/' . $subscription_id['data'][0]['id'], 'DELETE');
            if (sb_isset($response, 'status') == 'canceled') {
                super_insert_user_data('(' . get_active_account_id() . ', "subscription_cancelation", "' . sb_gmt_now() . '")');
            }
            return $response;
        }
    }
    return false;
}

function stripe_curl($url_part, $type = 'POST', $post_fields = '') {
    $response = sb_curl('https://api.stripe.com/v1/' . $url_part, $post_fields, ['Authorization: Basic ' . base64_encode(STRIPE_SECRET_KEY)], $type);
    return $type == 'POST' || $type == 'PATCH' ? $response : json_decode($response, true);
}

function stripe_get_price($price_amount, $product_id, $currency_code) {
    $price_amount = intval($price_amount);
    $prices = stripe_curl('prices?product=' . $product_id . '&limit=100&type=one_time', 'GET');
    $currency_code = strtolower($currency_code);
    if (!isset($prices['data'])) {
        return $prices;
    }
    $prices = $prices['data'];
    for ($i = 0; $i < count($prices); $i++) {
        if ($price_amount == $prices[$i]['unit_amount'] && $prices[$i]['currency'] == $currency_code) {
            return $prices[$i];
        }
    }
    return stripe_curl('prices?unit_amount=' . $price_amount . '&currency=' . $currency_code . '&product=' . $product_id);
}

function stripe_update_customer($cloud_user_id, $stripe_id = false, $is_tax_id = true) {
    $company_details = json_decode(super_get_user_data('company_details', $cloud_user_id, '[]'), true);
    $email = account()['email'];
    $post_fields = [
        'email' => $email,
        'name' => $company_details['company_name'] ?? '',
        'address[line1]' => $company_details['company_address'] ?? '',
        'address[line2]' => $company_details['company_address2'] ?? '',
        'address[city]' => $company_details['company_city'] ?? '',
        'address[postal_code]' => $company_details['company_postal_code'] ?? '',
        'address[country]' => $company_details['company_country'] ?? ''
    ];
    if ($is_tax_id && !empty($company_details['company_tax_id']) && !empty($company_details['company_country'])) {
        $post_fields['tax_id_data[0][type]'] = $company_details['company_country'] === 'gb' ? 'gb_vat' : 'eu_vat';
        $post_fields['tax_id_data[0][value]'] = $company_details['company_tax_id'];
    }
    $response = stripe_curl('customers' . ($stripe_id ? '/' . $stripe_id : ''), 'POST', $post_fields);
    $error = sb_isset($response, 'error');
    if ($error) {
        return $error['code'] == 'tax_id_invalid' && $is_tax_id ? stripe_update_customer($cloud_user_id, $stripe_id, false) : sb_error('stripe-error', 'stripe_update_customer', $error);
    }
    return sb_isset($response, 'id', $response);
}

/*
 * -----------------------------------------------------------
 * RAZORPAY
 * -----------------------------------------------------------
 *
 */

function razorpay_curl($url_part, $body = [], $type = 'POST') {
    $response = sb_curl('https://api.razorpay.com/v1/' . $url_part, json_encode($body, JSON_INVALID_UTF8_IGNORE, JSON_UNESCAPED_UNICODE), ['Content-Type: application/json', 'Authorization: Basic ' . base64_encode(RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET)], $type);
    return $type == 'POST' ? $response : json_decode($response, true);
}

function razorpay_get_plans($plan_id = false) {
    $plans = razorpay_curl('plans' . ($plan_id ? '/' . $plan_id : ''), '', 'GET');
    return $plan_id ? $plans : sb_isset($plans, 'items', []);
}

function razorpay_create_subscription($plan_id, $cloud_user_id) {
    $membership = $plan_id ? membership_get($plan_id) : false;
    $response = razorpay_curl('subscriptions', ['plan_id' => $plan_id, 'total_count' => $membership['period'] == 'month' ? 1200 : 100, 'quantity' => 1, 'notes' => ['customer_id' => $cloud_user_id, 'referral' => sb_isset($_COOKIE, 'sb-referral', ''), 'period' => $membership['period']]]);
    return isset($response['short_url']) ? ['url' => $response['short_url']] : $response['short_url'];
}

function razorpay_create_payment_link($amount, $notes = []) {
    $notes['customer_id'] = get_active_account_id();
    $response = razorpay_curl('payment_links', ['amount' => $amount, 'callback_url' => CLOUD_URL . '/account?tab=membership', 'callback_method' => 'get', 'notes' => $notes]);
    return sb_isset($response, 'short_url', $response);
}

function razorpay_cancel_subscription($cloud_user_id = false) {
    $payment_id = sb_isset(db_get('SELECT customer_id FROM users WHERE id = ' . db_escape($cloud_user_id ? $cloud_user_id : get_active_account_id(), true)), 'customer_id');
    if ($payment_id) {
        $response = razorpay_curl('subscriptions/' . $payment_id . '/cancel');
        if (sb_isset($response, 'status') == 'canceled') {
            super_insert_user_data('(' . get_active_account_id() . ', "subscription_cancelation", "' . sb_gmt_now() . '")');
        }
        return $response;
    }
    return true;
}

/*
 * -----------------------------------------------------------
 * RAPYD
 * -----------------------------------------------------------
 *
 */

function rapyd_curl($url_path, $raw = '', $type = 'POST', $default = false) {
    $salt = mt_rand(10000000, 99999999);
    $timestamp = time();
    if (!is_string($raw)) {
        $raw = json_encode($raw, JSON_INVALID_UTF8_IGNORE, JSON_UNESCAPED_UNICODE);
    }
    $signature = base64_encode(hash_hmac('sha256', strtolower($type) . '/v1/' . $url_path . $salt . $timestamp . RAPYD_ACCESS_KEY . RAPYD_SECRET_KEY . $raw, RAPYD_SECRET_KEY));
    $header = ['Content-Type: application/json', 'access_key: ' . RAPYD_ACCESS_KEY, 'salt: ' . $salt, 'timestamp: ' . $timestamp, 'signature: ' . $signature];
    $response = sb_curl('https://' . (RAPYD_TEST_MODE ? 'sandboxapi' : 'api') . '.rapyd.net/v1/' . $url_path, $raw, $header, $type);
    return isset($response['status']) && sb_isset($response['status'], 'status') == 'SUCCESS' ? $response['data'] : $response;
}

function rapyd_curl_checkout($amount, $customer_id, $cloud_user_id, $metadata = []) {
    $metadata = array_merge($metadata, ['cloud_user_id' => $cloud_user_id, 'rapyd_secret_key' => RAPYD_SECRET_KEY, 'referral' => sb_isset($_COOKIE, 'sb-referral')]);
    return rapyd_curl('checkout', ['amount' => $amount, 'country' => RAPYD_COUNTRY, 'currency' => RAPYD_CURRENCY, 'customer' => $customer_id, 'custom_elements' => ['billing_address_collect' => true], 'metadata' => $metadata]);
}

function rapyd_create_customer() {
    $account = account();
    $customer_id = rapyd_curl('customers', ['name' => $account['first_name'] . ' ' . $account['last_name'], 'email' => $account['email']]);
    if (isset($customer_id['id'])) {
        $customer_id = $customer_id['id'];
        db_query('UPDATE users SET customer_id = "' . sb_db_escape($customer_id) . '" WHERE id = ' . db_escape($account['user_id'], true));
    }
    return $customer_id;
}

function rapyd_create_checkout($membership_id, $cloud_user_id) {
    $customer_id = account_get_payment_id();
    $membership = membership_get($membership_id);
    if (!$customer_id) {
        $customer_id = rapyd_create_customer();
    }
    $response = rapyd_curl_checkout($membership['price'], $customer_id, $cloud_user_id, ['membership_id' => $membership_id, 'membership_period' => $membership['period']]);
    return isset($response['redirect_url']) ? ['url' => $response['redirect_url']] : $response;
}

/*
 * -----------------------------------------------------------
 * 2CHECKOUT VERIFONE
 * -----------------------------------------------------------
 *
 */

function verifone_create_checkout($membership_id, $cloud_user_id) {
    $customer_id = account_get_payment_id();
    $membership = membership_get($membership_id);
    $period = $membership['period'];
    $duration = '';
    $account = account();
    switch ($period) {
        case 'day':
            $period = '1:DAY';
            $duration = '3650:DAY';
            break;
        case 'week':
            $period = '1:WEEK';
            $duration = '480:WEEK';
            break;
        case 'month':
            $period = '1:MONTH';
            $duration = '120:MONTH';
            break;
        case '3month':
            $period = '3:MONTH';
            $duration = '120:MONTH';
            break;
        case '6month':
            $period = '6:MONTH';
            $duration = '120:MONTH';
            break;
        case 'year':
            $period = '1:YEAR';
            $duration = '10:YEAR';
            break;
        case '2year':
            $period = '2:YEAR';
            $duration = '10:YEAR';
            break;
        case '3year':
            $period = '3:YEAR';
            $duration = '10:YEAR';
            break;
    }
    $url = 'https://secure.2checkout.com/checkout/buy?merchant=' . VERIFONE_MERCHANT_ID . '&dynamic=1&currency=' . VERIFONE_CURRENCY . '&customer-ref=' . ($customer_id ? $customer_id : 'false') . '&duration=' . $duration . '&email=' . $account['email'] . '&order-ext-ref=' . sb_encryption($cloud_user_id . '|' . $membership_id . '|' . $membership['period'] . (isset($_COOKIE['sb-referral']) ? '|' . $_COOKIE['sb-referral'] : '')) . '&price=' . $membership['price'] . '&prod=' . $membership['name'] . '&qty=1&recurrence=' . $period . '&renewal-price=' . $membership['price'] . '&type=digital';
    return ['url' => $url . '&signature=' . verifone_get_signature($url)];
}

function verifone_get_signature($url) {
    parse_str(substr($url, strpos($url, '?') + 1), $values);
    $serialized = '';
    foreach ($values as $key => $value) {
        if (!in_array($key, ['merchant', 'dynamic', 'email'])) {
            $serialized .= mb_strlen($value) . $value;
        }
    }
    return hash_hmac('sha256', $serialized, VERIFONE_SECRET_WORD);
}

function verifone_curl($url_part, $type = 'POST') {
    $date = gmdate('Y-m-d H:i:s');
    $string = strlen(VERIFONE_MERCHANT_ID) . VERIFONE_MERCHANT_ID . strlen($date) . $date;
    $hash = hash_hmac('md5', $string, VERIFONE_SECRET_KEY);
    $response = sb_curl('https://api.2checkout.com/rest/6.0/' . $url_part, '', ['Content-Type: application/json', 'Accept: application/json', 'X-Avangate-Authentication: code="' . VERIFONE_MERCHANT_ID . '" date="' . $date . '" hash="' . $hash . '"'], $type);
    return is_string($response) ? json_decode($response, true) : $response;
}

function verifone_cancel_subscription() {
    $verifone_id = account_get_payment_id();
    if ($verifone_id) {
        $subscriptions = sb_isset(verifone_curl('subscriptions?CustomerEmail=' . $verifone_id . '&SubscriptionEnabled=true&Limit=99', 'GET'), 'Items', []);
        if ($subscriptions) {
            for ($i = 0; $i < count($subscriptions); $i++) {
                verifone_curl('subscriptions/' . $subscriptions[$i]['SubscriptionReference'], 'DELETE');
            }
            super_insert_user_data('(' . get_active_account_id() . ', "subscription_cancelation", "' . sb_gmt_now() . '")');
            return ['status' => 'canceled'];
        } else {
            return 'no-subscriptions';
        }
    }
    return false;
}

function verifone_get_orders($customer_id) {
    $page = 1;
    $customer_orders = [];
    while ($page) {
        $response = verifone_curl('orders?Limit=99', 'GET');
        $orders = sb_isset($response, 'Items', []);
        $pagination = sb_isset($response, 'Pagination');
        for ($i = 0; $i < count($orders); $i++) {
            $order = $orders[$i];
            if (isset($order['BillingDetails']) && $order['BillingDetails']['Email'] == $customer_id) {
                array_push($customer_orders, $order);
            }
        }
        $page = $pagination && ($pagination['Page'] * $pagination['Limit'] < $pagination['Count']) ? $pagination['Page'] + 1 : false;
    }
    return $customer_orders;
}

/*
 * -----------------------------------------------------------
 * YOOMONEY
 * -----------------------------------------------------------
 *
 */

function yoomoney_curl($url_part, $body = false, $type = 'POST') {
    $response = sb_curl('https://api.yookassa.ru/v3/' . $url_part, $body ? json_encode($body, JSON_INVALID_UTF8_IGNORE, JSON_UNESCAPED_UNICODE) : '', ['Authorization: Basic ' . base64_encode(YOOMONEY_SHOP_ID . ':' . YOOMONEY_KEY_SECRET), 'Idempotence-Key: ' . rand(99999, 9999999), 'Content-Type: application/json', 'Accept: application/json'], $type);
    return $type == 'POST' ? $response : json_decode($response, true);
}

function yoomoney_create_payment($amount, $currency_code, $return_url, $description = false, $metadata = false, $recurring = true) {
    $account = account();
    if (!$account) {
        return sb_error('account-not-found', 'yoomoney_create_payment');
    }
    $query = ['amount' => ['value' => number_format($amount, 2, '.', ''), 'currency' => strtoupper($currency_code)], 'capture' => true, 'confirmation' => ['type' => 'redirect', 'return_url' => $return_url]];
    if ($description) {
        $query['description'] = $description;
    }
    if ($recurring) {
        $query['save_payment_method'] = 'true';
    }
    if ($metadata) {
        $query['metadata'] = $metadata;
    }
    $query['receipt'] = [
        'customer' => [
            'full_name' => $account['first_name'] . ' ' . $account['last_name'],
            'email' => $account['email']
        ],
        'items' => [
            [
                'description' => SB_CLOUD_BRAND_NAME . ' - ' . $description,
                'quantity' => '1.00',
                'amount' => [
                    'value' => number_format($amount, 2, '.', ''),
                    'currency' => strtoupper($currency_code),
                ],
                'payment_mode' => 'full_payment',
                'payment_subject' => 'payment',
                'vat_code' => 1
            ]
        ]
    ];
    return yoomoney_curl('payments', $query);
}

function yoomoney_create_subscription($price_id) {
    $membership = membership_get($price_id);
    if ($membership) {
        $response = yoomoney_create_payment($membership['price'], YOOMONEY_CURRENCY, CLOUD_URL . '/account?tab=membership', $membership['name'], ['sb_user_id' => get_active_account_id(), 'membership_id' => $price_id, 'referral' => sb_isset($_COOKIE, 'sb-referral')]);
        $confirmation = sb_isset($response, 'confirmation');
        return $confirmation ? ['url' => $confirmation['confirmation_url']] : $response;
    }
    return false;
}

function yoomoney_cancel_subscription() {
    $payment_id = account_get_payment_id();
    if ($payment_id) {
        db_query('UPDATE users SET customer_id = "" WHERE id = ' . get_active_account_id());
        super_insert_user_data('(' . get_active_account_id() . ', "subscription_cancelation", "' . sb_gmt_now() . '")');
        return ['status' => 'canceled'];
    }
    return false;
}

function yoomoney_recurring_payment($amount, $payment_id, $description = '') {
    $query = ['amount' => ['value' => $amount, 'currency' => strtoupper(YOOMONEY_CURRENCY)], 'capture' => true, 'payment_method_id' => $payment_id, 'description' => $description];
    $response = yoomoney_curl('payments', $query);
    return sb_isset($response, 'status') == 'succeeded' ? true : $response;
}

function yoomoney_cron() {
    $users = db_get('SELECT id, membership, membership_expiration, customer_id FROM users WHERE membership <> "free" AND membership <> "0" AND customer_id <> ""', false);
    $white_labels = array_column(db_get('SELECT user_id, value FROM users_data WHERE slug = "white-label"', false), 'value', 'user_id');
    $now_plus_24 = sb_gmt_now(-86400, true);
    for ($i = 0; $i < count($users); $i++) {
        try {
            $payment_id = $users[$i]['customer_id'];
            if ($payment_id) {
                $user_id = $users[$i]['id'];
                $membership = membership_get($users[$i]['membership']);
                $reset_query = 'UPDATE users SET customer_id = "" WHERE id = ' . $user_id;
                if ($membership) {
                    if ($now_plus_24 > cloud_gmt_time($users[$i]['membership_expiration'])) {
                        $response = yoomoney_recurring_payment($membership['price'], $payment_id, $membership['name']);
                        if ($response === true) {
                            membership_update($membership['id'], $membership['period'], $user_id, $payment_id);
                            cloud_add_to_payment_history($user_id, $membership['price'], 'Membership', $payment_id);
                        } else {
                            db_query($reset_query);
                        }
                    }
                }
                $white_label = sb_isset($white_labels, $user_id);
                if ($white_label && $now_plus_24 > cloud_gmt_time($white_label)) {
                    $amount = super_get_white_label();
                    $response = yoomoney_recurring_payment($amount, $payment_id, 'White Label');
                    if ($response === true) {
                        membership_save_white_label($user_id);
                        cloud_add_to_payment_history($user_id, $amount, 'White Label', $payment_id);
                    } else {
                        db_query($reset_query);
                    }
                }
            }
        } catch (Exception $e) {
        }
    }
}

/*
 * -----------------------------------------------------------
 * PAYSTACK
 * -----------------------------------------------------------
 *
 */

function paystack_curl($url_part, $body = false, $type = 'POST') {
    $response = sb_curl('https://api.paystack.co/' . $url_part, $body ? json_encode($body, JSON_INVALID_UTF8_IGNORE, JSON_UNESCAPED_UNICODE) : '', ['Authorization: Bearer ' . PAYSTACK_SECRET_KEY, 'Content-Type: application/json', 'Accept: application/json'], $type);
    return $type == 'POST' ? $response : json_decode($response, true);
}

function paystack_create_subscription($price_id) {
    $membership = $price_id == 'white_label' ? ['name' => sb_('White Label'), 'price' => super_get_white_label(), 'period' => 'monthly'] : membership_get($price_id);
    if ($membership) {
        $plan = paystack_get_plan($membership['name'], $membership['price'], $membership['period']);
        if (sb_is_error($plan)) {
            return $plan;
        }
        $response = paystack_create_transaction($membership['price'], $price_id);
        $url = sb_isset($response, 'authorization_url');
        return $url ? ['url' => $url] : $response;
    }
    return false;
}

function paystack_create_transaction($amount, $price_id) {
    $account = account();
    if ($account) {
        $response = paystack_curl('transaction/initialize', ['email' => $account['email'], 'first_name' => $account['email'], 'last_name' => $account['last_name'], 'amount' => $amount * 100, 'currency' => strtoupper(PAYSTACK_CURRENCY), 'metadata' => ['sb_user_id' => get_active_account_id(), 'membership_id' => $price_id, 'referral' => sb_isset($_COOKIE, 'sb-referral')], 'callback_url' => CLOUD_URL . '/account?tab=membership']);
        return sb_is_error($response) ? $response : $response['data'];
    }
    return false;
}

function paystack_create_plan($name, $amount, $interval) {
    $response = paystack_curl('plan', ['name' => $name, 'interval' => $interval == 'month' ? 'monthly' : $interval, 'amount' => $amount * 100, 'currency' => strtoupper(PAYSTACK_CURRENCY)]);
    return $response && !empty($response['status']) ? $response['data'] : sb_error('plan-creation-failed', 'paystack_create_plan', $response);
}

function paystack_get_plan($name, $amount, $interval, $is_create = true) {
    $plans = sb_isset(paystack_curl('plan', '', 'GET'), 'data', []);
    $amount_ = $amount * 100;
    foreach ($plans as $plan) {
        if ($plan['name'] == $name && $amount_ == $plan['amount']) {
            return $plan;
        }
    }
    return $is_create ? paystack_create_plan($name, $amount, $interval) : false;
}

function paystack_create_charge($amount, $price_id) {
    $account = account();
    if ($account) {
        $authorization_code = account_get_payment_id();
        if (!$authorization_code) {
            $response = paystack_create_transaction($amount, $price_id);
            $url = sb_isset($response, 'authorization_url');
            return $url ? ['url' => $url] : $response;
        }
        $response = paystack_curl('transaction/charge_authorization', ['authorization_code' => $authorization_code, 'email' => $account['email'], 'amount' => $amount * 100, 'currency' => strtoupper(PAYSTACK_CURRENCY), 'metadata' => ['sb_user_id' => get_active_account_id(), 'membership_id' => $price_id, 'referral' => sb_isset($_COOKIE, 'sb-referral')], 'callback_url' => CLOUD_URL . '/account?tab=membership']);
        if ($response && !empty($response['status'])) {
            return true;
        } else {
            $response = paystack_create_transaction($amount, $price_id);
            $url = sb_isset($response, 'authorization_url');
            return $url ? ['url' => $url] : $response;
        }
    }
    return false;
}


/*
 * -----------------------------------------------------------
 * SUPER ADMIN
 * -----------------------------------------------------------
 *
 */

function super_admin() {
    global $SUPER_ACTIVE_ACCOUNT;
    if ($SUPER_ACTIVE_ACCOUNT) {
        return $SUPER_ACTIVE_ACCOUNT;
    }
    if (empty($_COOKIE['sb-super'])) {
        return false;
    }
    $cookie = sb_encryption($_COOKIE['sb-super'], false);
    if ($cookie != SUPER_EMAIL) {
        return false;
    }
    $SUPER_ACTIVE_ACCOUNT = $cookie;
    return true;
}

function super_login($email, $password) {
    return password_verify($password, SUPER_PASSWORD) && $email == SUPER_EMAIL ? sb_encryption($email) : false;
}

function super_get_customers($price_id = false) {
    return db_get('SELECT * FROM users' . ($price_id ? (' WHERE membership = ' . $price_id) : '') . ' ORDER BY creation_time ASC', false);
}

function super_get_customer($customer_id) {
    $customer_id = db_escape($customer_id);
    $stripe = PAYMENT_PROVIDER == 'stripe';
    $verifone = !$stripe && PAYMENT_PROVIDER == 'verifone';
    $cloud_settings = super_get_settings();

    // Customer details
    $customer = db_get('SELECT * FROM users WHERE id = ' . $customer_id);
    require_once(SB_PATH . '/config/config_' . $customer['token'] . '.php');
    $customer['password'] = '********';
    $customer['extra_fields'] = db_get('SELECT slug, value FROM users_data WHERE user_id = ' . $customer_id, false);
    $customer['database'] = SB_DB_NAME;
    if (empty($customer['phone'])) {
        $customer['phone'] = '';
    }
    for ($i = 1; $i < 5; $i++) {
        $name = sb_string_slug(sb_isset($cloud_settings, 'registration-field-' . $i));
        if ($name && !isset($customer['extra_fields'][$name])) {
            array_push($customer['extra_fields'], ['slug' => sb_string_slug($name), 'value' => '']);
        }
    }

    // Sales
    $customer['invoices'] = [];
    $customer['lifetime_value'] = 0;
    if (!empty($customer['customer_id'])) {
        $invoices = $verifone ? verifone_get_orders($customer['customer_id']) : sb_isset($stripe ? stripe_curl('invoices?customer=' . $customer['customer_id'], 'GET') : json_decode(rapyd_curl('payments?limit=99&customer=' . $customer['customer_id'], '', 'GET'), true), 'data');
        $lifetime_value = 0;
        if ($invoices) {
            $currency = '';
            for ($i = 0; $i < count($invoices); $i++) {
                $invoice = $invoices[$i];
                $lifetime_value += $invoice[$stripe ? 'amount_paid' : ($verifone ? 'NetPrice' : 'amount')];
                $currency = $invoice[$stripe ? 'currency' : ($verifone ? 'Currency' : 'currency_code')];
            }
            $customer['lifetime_value'] = strtoupper($currency) . ' ' . ($stripe ? ($lifetime_value / currency_get_divider($currency)) : $lifetime_value);
            $customer['invoices'] = $invoices;
        }
    }

    // Statistics
    if (in_array(sb_defined('SB_CLOUD_MEMBERSHIP_TYPE', 'messages'), ['messages', 'messages-agents'])) {
        $monthly_volume = db_get('SELECT count, date FROM membership_counter WHERE user_id = ' . $customer_id, false);
        $customer['monthly_volume'] = $monthly_volume;
    } else {
        $customer['monthly_volume'] = '';
    }
    $customer['count_users'] = sb_db_get('SELECT COUNT(*) AS `count` FROM sb_users WHERE user_type <> "admin" AND user_type <> "agent" AND user_type <> "bot"')['count'];
    $customer['count_agents'] = sb_db_get('SELECT COUNT(*) AS `count` FROM sb_users WHERE user_type = "admin" OR user_type = "agent"')['count'];
    return $customer;
}

function super_save_customer($customer_id, $details, $extra_details = false) {
    $query = '';
    $customer_id = db_escape($customer_id);
    $cloud_user = db_get('SELECT * FROM users WHERE id = ' . $customer_id);
    if ($details['email'] != $cloud_user['email']) {
        require_once(SB_PATH . '/config/config_' . $cloud_user['token'] . '.php');
        sb_db_query('UPDATE sb_users SET email = "' . db_escape($details['email']) . '" WHERE email = "' . $cloud_user['email'] . '"');
    }
    if ($details['membership'] != $cloud_user['membership']) {
        if ($details['membership'] == 'manual_membership_renewal') {
            $details['membership'] = $cloud_user['membership'];
        }
        $details['membership_expiration'] = membership_calculate_expiration(membership_get($details['membership'])['period']);
        membership_add_reseller_sale($details['membership']);
    } else {
        unset($details['membership_expiration']);
    }
    $credits = sb_isset($details, 'credits');
    if ($credits != $cloud_user['credits']) {
        $new_credits = $credits - $cloud_user['credits'];
        if ($new_credits > 0) {
            membership_set_purchased_credits($new_credits, membership_currency(), $customer_id);
        }
    }
    if ((!empty($details['phone']) || !empty($details['email'])) && db_get('SELECT id FROM users WHERE id <> ' . $customer_id . ' AND (' . (empty($details['phone']) ? '' : 'phone = "' . $details['phone'] . '"') . (empty($details['phone']) ? '' : ' OR ') . (empty($details['email']) ? '' : 'email = "' . $details['email'] . '"') . ')')) {
        return 'duplicate-phone-or-email';
    }
    foreach ($details as $key => $value) {
        $value = db_escape($value);
        if ($key == 'password') {
            if ($value != '********') {
                require_once(SB_PATH . '/config/config_' . $cloud_user['token'] . '.php');
                $password = password_hash($value, PASSWORD_DEFAULT);
                sb_db_query('UPDATE sb_users SET password = "' . $password . '" WHERE email = "' . $cloud_user['email'] . '"');
                $query .= 'password = "' . $password . '",';
            }
        } else {
            $query .= $key . ' = ' . ($value == 'null' || ($key == 'phone' && !$value) ? 'NULL' : ('"' . $value . '"')) . ',';
        }
    }
    $query_users_data = '';
    if ($extra_details) {
        foreach ($extra_details as $key => $value) {
            $value = trim($value);
            if ($key == 'white-label' || $key == 'white_label') {
                if ($value == 'activate' || $value == 'renew') {
                    $amount = super_get_white_label();
                    if ($amount) {
                        $query_users_data .= '(' . $customer_id . ', "white-label", "' . gmdate('d-m-y', time() + 31536000) . '"),';
                        membership_add_reseller_sale(false, 'white-label', $amount);
                    }
                } else {
                    $query_users_data .= '(' . $customer_id . ', "white-label", "' . super_get_user_data('white-label', $customer_id, '') . '"),';
                }
            } else if (!empty($value)) {
                $query_users_data .= '(' . $customer_id . ', "' . $key . '", "' . $value . '"),';
            }
        }
    }
    if ($query_users_data) {
        super_delete_user_data($customer_id);
        $response_2 = $query_users_data ? super_insert_user_data(substr($query_users_data, 0, -1)) : false;
    }
    membership_delete_cache($customer_id);
    return db_query('UPDATE users SET ' . substr($query, 0, -1) . ' WHERE id = ' . $customer_id);
}

function super_get_active_customers() {
    $active_customers = [];
    $now_less_5_days = sb_gmt_now(432000);

    // Customers with active membership and older than 1 month
    $customers = db_get('SELECT token, email, first_name, last_name, membership FROM users WHERE membership <> "free" AND membership <> "0" AND creation_time < "' . sb_gmt_now(2592000) . '"', false);
    for ($i = 0; $i < count($customers); $i++) {

        // Customers with at least 1 message or 1 user in the last 5 days
        if (db_get('SELECT id FROM sb_messages WHERE creation_time > "' . $now_less_5_days . '"', false, $customers[$i]['token']) || db_get('SELECT id FROM sb_users WHERE creation_time > "' . $now_less_5_days . '"', false, $customers[$i]['token'])) {
            array_push($active_customers, $customers[$i]);
        }
    }
    return $active_customers;
}

function super_delete_customer($customer_id) {
    $customer_id = db_escape($customer_id);
    $token = sb_isset(db_get('SELECT token FROM users WHERE id = "' . $customer_id . '"'), 'token');
    if ($token) {
        $path = SB_PATH . '/config/config_' . $token . '.php';
        if (file_exists($path)) {
            require_once($path);
        }

        // Delete attachments
        $attachments = db_get('SELECT * FROM sb_messages WHERE attachments <> ""', false, $token);
        if (!empty($attachments)) {
            for ($i = 0; $i < count($attachments); $i++) {
                $attachments_2 = json_decode($attachments[$i]['attachments'], true);
                for ($j = 0; $j < count($attachments_2); $j++) {
                    sb_file_delete($attachments_2[$j][1]);
                }
            }
        }

        // Delete user profile images
        $images = db_get('SELECT profile_image FROM sb_users WHERE profile_image <> ""', false, $token);
        if (!empty($images)) {
            for ($i = 0; $i < count($images); $i++) {
                $image = $images[$i]['profile_image'];
                if (!strpos($image, 'user.')) {
                    sb_file_delete($image);
                }
            }
        }

        // Delete setting images
        $settings = json_decode(sb_isset(db_get('SELECT value FROM sb_settings WHERE name = "settings" LIMIT 1', true, $token), 'value', '[]'), true);
        $setting_keys = ['bot-image', 'brand-img', 'header-img', 'chat-icon'];
        for ($i = 0; $i < count($setting_keys); $i++) {
            $image = sb_isset($settings, $setting_keys[$i]);
            if ($image && $image[0]) {
                sb_file_delete($image[0]);
            }
        }

        // Delete department images
        $departments = sb_isset($settings, 'departments', [[]])[0];
        if (is_array($departments)) {
            for ($i = 0; $i < count($departments); $i++) {
                $image = $departments[$i]['department-image'];
                if ($image) {
                    sb_file_delete($image);
                }
            }
        }

        // Delete flow attachments
        $flows = json_decode(sb_isset(db_get('SELECT value FROM sb_settings WHERE name = "open-ai-flows" LIMIT 1', true, $token), 'value', '[]'), true);
        foreach ($flows as $flow) {
            $steps = sb_isset($flow, 'steps', []);
            foreach ($steps as $step) {
                foreach ($step as $block_cnt) {
                    foreach ($block_cnt as $block) {
                        if (!empty($block['attachments'])) {
                            foreach ($block['attachments'] as $attachment) {
                                sb_file_delete($attachment);
                            }
                        }
                    }
                }
            }
        }

        // Delete article attachments
        if (!empty(db_get('SHOW TABLES LIKE "sb_articles"'))) {
            $articles = db_get('SELECT editor_js FROM sb_articles ', false, $token);
            foreach ($articles as $article) {
                preg_match_all('/"file":\{"url":"(.*?)"\}/', $article['editor_js'], $matches);
                foreach ($matches[1] as $url) {
                    sb_file_delete($url);
                }
            }
        }

        // Delete embeddings
        $path_embeddings = SB_PATH . '/uploads/embeddings/' . $customer_id;
        if (file_exists($path_embeddings)) {
            array_map('unlink', glob($path_embeddings . '/*.*'));
            rmdir($path_embeddings);
        }

        // Delete entries from main database
        db_query('DELETE FROM agents WHERE admin_id = ' . $customer_id);
        super_delete_user_data($customer_id);
        db_query('DELETE FROM users WHERE id = ' . $customer_id);
        db_query('DELETE FROM membership_counter WHERE user_id = ' . $customer_id);
        db_query('DELETE FROM messenger WHERE token = "' . $token . '"');
        db_query('DELETE FROM whatsapp WHERE token = "' . $token . '"');

        sb_cloud_save_settings(false, $customer_id);

        // Delete config file
        if (file_exists($path)) {
            unlink($path);
        }

        // Delete database
        db_query('DROP DATABASE ' . SB_DB_NAME);
        db_query('DROP USER \'' . SB_DB_USER . '\'@\'' . (defined('CLOUD_IP') ? CLOUD_IP : 'localhost') . '\'');
        return true;
    }
    return false;
}

function super_save_emails($settings) {
    foreach ($settings as $key => $value) {
        $value = str_replace('"', '\"', $value);
        db_query('INSERT INTO settings(name, value) VALUES ("' . db_escape($key) . '", "' . $value . '") ON DUPLICATE KEY UPDATE value = "' . $value . '"');
    }
    return true;
}

function super_get_emails() {
    $rows = db_get('SELECT name, value FROM settings', false);
    $emails = [];
    for ($i = 0; $i < count($rows); $i++) {
        $name = $rows[$i]['name'];
        if (str_contains($name, 'email') || $name == 'template_verification_code_phone') {
            $emails[$name] = $rows[$i]['value'];
        }
    }
    return $emails;
}

function super_get_user_data($slug, $cloud_user_id, $default = false) {
    return $cloud_user_id ? sb_isset(db_get('SELECT value FROM users_data WHERE slug = "' . $slug . '" AND user_id = ' . db_escape($cloud_user_id, true) . ' LIMIT 1'), 'value', $default) : false;
}

function super_insert_user_data($values) {
    return db_query('INSERT INTO users_data(user_id, slug, value) VALUES ' . $values);
}

function super_delete_user_data($cloud_user_id = false, $slug = false, $limit = false) {
    return empty($cloud_user_id) && empty($slug) ? false : db_query('DELETE FROM users_data WHERE ' . ($cloud_user_id ? 'user_id = ' . db_escape($cloud_user_id, true) : '') . ($slug ? ($cloud_user_id ? ' AND ' : '') . 'slug = "' . db_escape($slug) . '"' : '') . ($limit ? ' LIMIT 1' : ''));
}

function super_get_setting($name, $default = false) {
    return sb_isset(db_get('SELECT value FROM settings WHERE name = "' . $name . '"'), 'value', $default);
}

function super_get_settings() {
    global $CLOUD_SETTINGS;
    if ($CLOUD_SETTINGS) {
        return $CLOUD_SETTINGS;
    }
    $value = super_get_setting('user-settings');
    if ($value) {
        $CLOUD_SETTINGS = json_decode($value, true);
    }
    return $CLOUD_SETTINGS;
}

function super_save_setting($name, $value) {
    $value = db_escape(is_string($value) ? $value : json_encode($value, JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_UNICODE));
    return db_query('INSERT INTO settings(name, value) VALUES ("' . db_escape($name) . '", "' . $value . '") ON DUPLICATE KEY UPDATE value = "' . $value . '"');
}

function super_save_settings($settings) {
    $settings = str_replace(['&lt;', '&gt;'], ['<', '>'], db_escape(json_encode($settings, JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_UNICODE)));
    return db_query('INSERT INTO settings(name, value) VALUES ("user-settings", "' . $settings . '") ON DUPLICATE KEY UPDATE value = "' . $settings . '"');
}

function super_membership_plans() {
    $free_plan = memberships()[0];
    $membership_type_ma = sb_defined('SB_CLOUD_MEMBERSHIP_TYPE', 'messages') == 'messages-agents';
    $code = '<div data-id="free" data-price="' . $free_plan['price'] . '" data-period="" data-currency="" class="free-plan"><div class="sb-input"><h5>Name</h5><input type="text" class="name" value="' . $free_plan['name'] . '" placeholder="Insert plan name..." /><h5>Quota</h5><input type="number" class="quota" value="' . $free_plan['quota'] . '" placeholder="0" />' . ($membership_type_ma ? '<h5>Quota agents</h5><input type="number" class="quota-agents" placeholder="0" value="' . sb_isset($free_plan, 'quota_agents', '') . '" />' : '');
    if (PAYMENT_PROVIDER == 'stripe') {
        $code .= '</div></div>';
        $prices = sb_isset(stripe_curl('prices?limit=99&active=true&product=' . STRIPE_PRODUCT_ID, 'GET'), 'data', []);
        for ($i = 0; $i < count($prices); $i++) {
            $period = sb_isset($prices[$i]['recurring'], 'interval');
            if ($period && sb_isset($prices[$i]['recurring'], 'interval_count') == 1) {
                $membership = membership_get($prices[$i]['id']);
                $price = $prices[$i]['unit_amount'] / currency_get_divider($prices[$i]['currency']);
                $quota_agents = $membership_type_ma ? '<h5>Quota agents</h5><input type="number" class="quota-agents" placeholder="0" value="' . sb_isset($membership, 'quota_agents', '') . '" />' : '';
                if ($prices[$i]['recurring']['interval_count'] > 1) {
                    $period = $prices[$i]['recurring']['interval_count'] . $period;
                }
                $code .= '<div data-id="' . $prices[$i]['id'] . '" data-price="' . $price . '" data-period="' . $period . '" data-currency="' . $prices[$i]['currency'] . '"><h3>' . strtoupper($prices[$i]['currency']) . ' ' . $price . '<span> / ' . $period . '</span></h3><div class="sb-input"><h5>Name</h5><input type="text" class="name" value="' . sb_isset($membership, 'name', '') . '" placeholder="Insert plan name..." /><h5>Quota</h5><input type="number" class="quota" placeholder="0" value="' . sb_isset($membership, 'quota', '') . '" />' . $quota_agents . '</div></div>';
            }
        }
    } else if (PAYMENT_PROVIDER == 'razorpay') {
        $code .= '</div></div>';
        $plans = razorpay_get_plans();
        for ($i = 0; $i < count($plans); $i++) {
            $period = str_replace(['monthly', 'yearly'], ['month', 'year'], sb_isset($plans[$i], 'period'));
            if ($period && ($period == 'month' || $period == 'year') && $plans[$i]['interval'] == 1) {
                $membership = membership_get($plans[$i]['id']);
                $price = $plans[$i]['item']['amount'] / 100;
                $quota_agents = $membership_type_ma ? '<h5>Quota agents</h5><input type="number" class="quota-agents" placeholder="0" value="' . sb_isset($membership, 'quota_agents', '') . '" />' : '';
                $code .= '<div data-id="' . $plans[$i]['id'] . '" data-price="' . $price . '" data-period="' . $period . '" data-currency="' . strtolower($plans[$i]['item']['currency']) . '"><h3>' . strtoupper($plans[$i]['item']['currency']) . ' ' . $price . '<span> / ' . $period . '</span></h3><div class="sb-input"><h5>Name</h5><input type="text" class="name" value="' . sb_isset($membership, 'name', '') . '" placeholder="Insert plan name..." /><h5>Quota</h5><input type="number" class="quota" placeholder="0" value="' . sb_isset($membership, 'quota', '') . '" />' . $quota_agents . '</div></div>';
            }
        }
    } else {
        $code .= '<h5>Price</h5><input type="number" disabled class="price" value="0" /><h5>Period</h5><select class="period" disabled><option value=""></option></select></div></div>';
        $memberships = memberships();
        for ($i = 1; $i < count($memberships); $i++) {
            $membership = $memberships[$i];
            $quota_agents = $membership_type_ma ? '<h5>Quota agents</h5><input type="number" class="quota-agents" placeholder="0" value="' . sb_isset($membership, 'quota_agents', '') . '" />' : '';
            $code .= '<div data-id="' . $membership['id'] . '" data-price="' . $membership['price'] . '" data-period="' . $membership['period'] . '" data-currency="' . $membership['currency'] . '"><div class="sb-input"><h5>Name</h5><input class="name" type="text" value="' . sb_isset($membership, 'name', '') . '" placeholder="Insert plan name..." /><h5>Quota</h5><input type="number" class="quota" placeholder="Monthly messages..." value="' . sb_isset($membership, 'quota', '') . '" />' . $quota_agents . '<h5>Price</h5><input type="number" class="price" value="' . sb_isset($membership, 'price', '') . '" /><h5>Period</h5><select class="period"><option value="month">Monthly</option><option value="year">Yearly</option></select></div><i class="sb-icon-close"></i></div>';
        }
    }
    $code = '<div id="membership-plans">' . $code . '</div>' . (in_array(PAYMENT_PROVIDER, ['stripe', 'razorpay']) ? '' : '<a id="add-membership" class="sb-btn" href="#">Add membership</a>') . '<a id="save-membership-plans" class="sb-btn sb-btn-black" href="#">Save membership plans</a>';

    // White label
    if (PAYMENT_PROVIDER != 'stripe') {
        $code .= '<div class="super-white-label"><h2>White label option</h2><p>Set the annual price for the white label option that removes your logo from the your customers\' chat widget. Leave it empty to disable the option.</p><div class="sb-input"><h5>Price</h5><input type="number" value="' . super_get_white_label() . '" /></div><a id="save-white-label" class="sb-btn sb-btn-black" href="#">Save white label price</a></div>';
    }

    return $code;
}

function super_save_membership_plans($plans) {
    $count = count($plans);
    $currency_code = membership_currency();
    $minimum_price = sb_usd_get_amount(5, $currency_code);
    $max_quotas = cloud_get_max_quotas();
    $count_minimum_quota = count($max_quotas) - 1;
    $plans_final = [];
    for ($i = 1; $i < $count; $i++) {
        if ($plans[$i]['price'] < $minimum_price) {
            $plans[$i]['price'] = $minimum_price;
        }
    }
    for ($i = 0; $i < $count; $i++) {
        $plan = $plans[$i];
        if ($plan['quota'] == -1) {
            continue;
        }
        $price = $plan['price'];
        $period = empty($plan['period']) ? 'month' : str_replace(['monthly', 'yearly'], ['month', 'year'], $plan['period']);
        if ($period != 'month' && $period != 'year') {
            continue;
        }
        $messages_agents = SB_CLOUD_MEMBERSHIP_TYPE == 'messages-agents';
        for ($y = 0; $y < $count_minimum_quota; $y++) {
            if ($price >= sb_usd_get_amount($max_quotas[$y]['price'][$period], $currency_code) && $price < sb_usd_get_amount($max_quotas[$y + 1]['price'][$period], $currency_code)) {
                $max_quota = $max_quotas[$y]['quota'][$period][$messages_agents ? 'messages' : SB_CLOUD_MEMBERSHIP_TYPE];
                if ($plan['quota'] > $max_quota) {
                    $plan['quota'] = $max_quota;
                } else if (empty($plan['quota'])) {
                    $plan['quota'] = 1;
                }
                if ($messages_agents) {
                    $max_quota = $max_quotas[$y]['quota'][$period]['agents'];
                    if ($plan['quota_agents'] > $max_quota) {
                        $plan['quota_agents'] = $max_quota;
                    }
                }
            }
        }
        if (isset($plan['quota_agents']) && empty($plan['quota_agents'])) {
            $plan['quota_agents'] = 1;
        }
        array_push($plans_final, $plan);
    }
    $plans_final = db_escape(json_encode($plans_final, JSON_INVALID_UTF8_IGNORE, JSON_UNESCAPED_UNICODE));
    if (defined('STRIPE_PRODUCT_ID_WHITE_LABEL')) {
        $price = sb_isset(stripe_curl('prices?product=' . STRIPE_PRODUCT_ID_WHITE_LABEL, 'GET'), 'data');
        if ($price) {
            super_save_white_label($price[0]['unit_amount'] / currency_get_divider($price[0]['currency']));
        }
    }
    super_delete_user_data(false, 'active_membership_cache');
    return empty($plans_final) ? 'error' : db_query('INSERT INTO settings(name, value) VALUES ("memberships", "' . $plans_final . '") ON DUPLICATE KEY UPDATE value = "' . $plans_final . '"');
}

function super_save_white_label($price) {
    $min_amount = sb_usd_get_amount(50, membership_currency());
    if ($price < $min_amount) {
        return ' The minimum amount is ' . $min_amount . ' ' . strtoupper(membership_currency()) . '.';
    }
    return db_query('INSERT INTO settings(name, value) VALUES ("white-label", "' . $price . '") ON DUPLICATE KEY UPDATE value = "' . $price . '"');
}

function super_get_white_label() {
    return super_get_setting('white-label', '');
}

function super_get_affiliates() {
    return db_get('SELECT A.id, A.first_name, A.last_name, A.email, B.value FROM users A, users_data B WHERE A.id = B.user_id AND B.slug = "referral"', false);
}

function super_get_affiliate_details($affiliate_id) {
    return explode('|', sb_isset(db_get('SELECT value FROM users_data WHERE user_id = "' . db_escape($affiliate_id, true) . '" AND slug = "referral_payment_info"'), 'value', ''));
}

function super_reset_affiliate($affiliate_id) {
    return super_delete_user_data($affiliate_id, 'referral', true);
}

function super_admin_config() {
    if (!isset($_COOKIE['SACL_' . 'VGC' . 'KMENS']) || !password_verify('ODO2' . 'KMENS', $_COOKIE['SACL_' . 'VGC' . 'KMENS'])) {
        require_once(SB_PATH . '/config.php');
        $ec = sb_defined('ENVA' . 'TO_PUR' . 'CHASE' . '_CO' . 'DE');
        $m = 'Env' . 'ato purc' . 'hase c' . 'ode inv' . 'alid or mi' . 'ss' . 'ing.';
        if ($ec) {
            $response = sb_get('ht' . 'tps://bo' . 'ard.supp' . 'ort/syn' . 'ch/verif' . 'ication.php?verific' . 'ation&cl' . 'oud=true&code=' . $ec . '&domain=' . CLOUD_URL);
            if ($response == 'veri' . 'ficat' . 'ion-success') {
                setcookie('SACL_' . 'VGC' . 'KMENS', password_hash('ODO2' . 'KMENS', PASSWORD_DEFAULT), time() + 2592000, '/');
            } else {
                die($m);
            }
        } else {
            die($m);
        }
    }
}

function super_update_saas() {
    if (!defined('ENVATO_PURCHASE_CODE')) {
        return 'missing-purchase-code';
    }
    $download_link = file_get_contents('https://board.support/synch/updates.php?saas_download=true&saas=' . trim(ENVATO_PURCHASE_CODE));
    if (empty($download_link)) {
        return false;
    }
    if (in_array($download_link, ['invalid-envato-purchase-code', '199-usd-envato-purchase-code-not-allowed', 'purchase-code-limit-exceeded', 'banned', 'missing-arguments'])) {
        return str_replace('-', ' ', ucfirst($download_link));
    }
    $is_no_apps = strpos($download_link, 'saas-no-apps') === 0;
    if ($is_no_apps) {
        $download_link = str_replace('saas-no-apps', '', $download_link);
    }
    $zip = sb_download('https://board.support/synch/temp/' . $download_link);
    if ($zip) {
        $file_path = SB_CLOUD_PATH . '/' . bin2hex(openssl_random_pseudo_bytes(20)) . '.zip';
        file_put_contents($file_path, $zip);
        if (file_exists($file_path)) {
            $zip = new ZipArchive;
            if ($zip->open($file_path) === true) {
                $zip->extractTo(SB_CLOUD_PATH);
                $zip->close();
                unlink($file_path);
                return $is_no_apps ? 'success-no-apps-' . ENVATO_PURCHASE_CODE : true;
            }
            unlink($file_path);
            return 'zip-error';
        }
        unlink($file_path);
        return 'file-not-found';
    }
    return 'download-error';
}

/*
 * -----------------------------------------------------------
 * SHOPIFY
 * -----------------------------------------------------------
 *
 */

function shopify_curl($url_part, $post_fields = '', $header = [], $method = 'POST', $cloud_user_id = false) {
    global $shopify_token;
    $cloud_user_id = $cloud_user_id ? $cloud_user_id : get_active_account_id();
    if ($cloud_user_id) {
        $shopify_token = $shopify_token ? $shopify_token : super_get_user_data('shopify_token', $cloud_user_id);
        $shopify_shop = shopify_get_shop_name($cloud_user_id);
        if ($shopify_shop) {
            $response = sb_curl('https://' . $shopify_shop . '/admin/' . $url_part, $post_fields, $shopify_token ? array_merge($header, ['X-Shopify-Access-Token: ' . $shopify_token]) : $header, $method);
            return $method == 'GET' ? json_decode($response, true) : $response;
        }
        sb_error('Shopify shop not found', 'shopify_curl');
    } else {
        sb_error('Cloud user ID not found', 'shopify_curl');
    }
    return false;
}

function shopify_graphql($body) {
    $response = shopify_curl('api/2024-10/graphql.json', is_string($body) ? $body : json_encode($body), ['Content-Type: application/json']);
    return sb_isset($response, 'data', $response);
}

function shopify_get_shop_name($cloud_user_id = false) {
    global $shopify_shop;
    if ($shopify_shop) {
        return $shopify_shop;
    }
    $shopify_shop = super_get_user_data('shopify_shop', $cloud_user_id ? $cloud_user_id : get_active_account_id());
    return $shopify_shop;
}

function shopify_one_time_purchase($name, $amount, $url) {
    $response = shopify_graphql([
        'query' => 'mutation AppPurchaseOneTimeCreate($test: Boolean!, $name: String!, $price: MoneyInput!, $returnUrl: URL!) { appPurchaseOneTimeCreate(test: $test, name: $name, returnUrl: $returnUrl, price: $price) { userErrors { field message } appPurchaseOneTime { createdAt id } confirmationUrl } }',
        'variables' => [
            'test' => false,
            'name' => $name,
            'returnUrl' => $url,
            'price' => [
                'amount' => $amount,
                'currencyCode' => 'USD'
            ]
        ]
    ]);
    $url = sb_isset($response, ['appPurchaseOneTimeCreate', 'confirmationUrl']);
    return $url ? ['url' => $url] : ['error' => $response];
}

function shopify_subscription_purchase($name, $amount, $url, $is_monthly = true) {
    $response = shopify_graphql([
        'query' => 'mutation AppSubscriptionCreate($test: Boolean!, $name: String!, $lineItems: [AppSubscriptionLineItemInput!]!, $returnUrl: URL!) { appSubscriptionCreate(test: $test, name: $name, returnUrl: $returnUrl, lineItems: $lineItems) { userErrors { field message } appSubscription { id } confirmationUrl }}',
        'variables' => [
            'test' => false,
            'name' => $name,
            'returnUrl' => $url,
            'lineItems' => [
                ['plan' => ['appRecurringPricingDetails' => ['price' => ['amount' => $amount, 'currencyCode' => 'USD'], 'interval' => $is_monthly ? 'EVERY_30_DAYS' : 'ANNUAL']]]
            ]
        ]
    ]);
    $url = sb_isset($response, ['appSubscriptionCreate', 'confirmationUrl']);
    return $url ? ['url' => $url] : ['error' => $response];
}

function shopify_cancel_subscription() {
    $response = shopify_curl('api/2024-10/recurring_application_charges.json', '', [], 'GET');
    $subscription_id = sb_isset($response, 'recurring_application_charges');
    if (!empty($subscription_id)) {
        $response = shopify_curl('api/2024-10/recurring_application_charges/' . $subscription_id[0]['id'] . '.json', '', [], 'DELETE');
        if (empty($response)) {
            super_insert_user_data('(' . get_active_account_id() . ', "subscription_cancelation", "' . sb_gmt_now() . '")');
            return ['status' => 'canceled'];
        }
        return json_decode($response);
    }
    return $response;
}

function shopify_subscription($price_id) {
    $memberships = memberships();
    for ($i = 0; $i < count($memberships); $i++) {
        if ($memberships[$i]['id'] == $price_id) {
            return shopify_subscription_purchase($memberships[$i]['name'], $memberships[$i]['price'], CLOUD_URL . '/account/?tab=membership&reload=true', $memberships[$i]['period'] != 'year');
        }
    }
    return false;
}

function shopify_get_customer($shopify_id) {
    $response = shopify_curl('api/2023-10/customers/' . $shopify_id . '.json', '', [], 'GET');
    $customer = sb_isset($response, 'customer');
    if ($customer) {
        return $customer;
    }
    sb_error('shopify-get-customer-error', 'shopify_get_customer', $response);
    return false;
}

function shopify_get_orders($shopify_id) {
    $response = shopify_curl('api/2023-10/orders.json?customer_id=' . $shopify_id . '&status=any', '', [], 'GET');
    $orders = sb_isset($response, 'orders');
    if ($orders) {
        return $orders;
    }
    sb_error('shopify-get-orders-error', 'shopify_get_orders', $response);
    return [];
}

function shopify_get_products($product_ids = false, $key = false, $collection = false, $search = false, $pagination = false, $variant = false) {
    $query_part = 'id title description handle variants(first: 250) { edges { node { price selectedOptions { name value } } } } images(first: 1) { edges { node { src } } }';
    if ($product_ids) {
        $product_ids = strpos($product_ids, ',') === false ? [$product_ids] : explode(',', $product_ids);
        $product_ids = implode(',', array_map(fn($id) => '\"gid://shopify/Product/' . $id . '\"', $product_ids));
        $query = '{ "query": "query { nodes(ids: [' . $product_ids . ']) { ... on Product { ' . $query_part . ' } } }" }';
    } else if ($search) {
        $search_string = '';
        $title = is_string($search) ? $search : sb_isset($search, 'title');
        if ($title) {
            $search_string = '((title:*' . $title . '*) OR (product_type:*' . $title . '*))';
        }
        if (!is_string($search)) {
            foreach ($search as $key_ => $value) {
                if ($value && $key_ != 'title') {
                    $search_string .= ' ' . $key_ . ':*' . $value . '*';
                }
            }
        }
        $query = '{ "query": "query { products(first: ' . ($variant ? '250' : '30') . ', query: \"' . trim($search_string) . '\") { edges { node { ' . $query_part . ' } } } }" }';
    } else if ($collection) {
        if (!is_numeric($collection)) {
            $collections = shopify_get_data('collections');
            for ($i = 0; $i < count($collections); $i++) {
                if ($collections[$i]['name'] == $collection) {
                    $collection = $collections[$i]['id'];
                    break;
                }
            }
        }
        $query = '{ "query": "query { collection(id: \"gid://shopify/Collection/' . $collection . '\") { title products(first: 30) { edges { node { ' . $query_part . ' } } pageInfo { hasNextPage endCursor } } } }" }';
    } else {
        $query = '{ "query": "query { products(first: 30' . ($pagination ? ', after: \"' . $pagination . '\"' : '') . ') { edges { node { ' . $query_part . ' } } pageInfo { hasNextPage endCursor } } }" }';
    }
    $data = shopify_graphql($query);
    if (isset($data['errors'])) {
        sb_error('shopify-get-products-error', 'shopify_get_products', $data);
        return false;
    }
    if ($product_ids) {
        $products = sb_isset($data, 'nodes');
    } else {
        $products = sb_isset($collection ? sb_isset($data, 'collection') : $data, 'products');
        $next = sb_isset($products, ['pageInfo', 'endCursor']);
        $products = $products ? array_column(sb_isset($products, 'edges'), 'node') : [];
    }
    $products_ = [];
    if ($products) {
        for ($i = 0; $i < count($products); $i++) {
            $image = sb_isset($products[$i], ['images', 'edges']);
            if (!empty($image)) {
                $products[$i]['image'] = $image[0]['node']['src'];
            }
            $product_variants = array_column($products[$i]['variants']['edges'], 'node');
            $products[$i]['variants'] = $product_variants;
            $products[$i]['price'] = $products[$i]['variants'][0]['price'];
            unset($products[$i]['images']);
            if ($variant) {
                $variant[1] = strtolower(str_replace(' ', '', $variant[1]));
                for ($j = 0; $j < count($product_variants); $j++) {
                    $options = $product_variants[$j]['selectedOptions'];
                    for ($y = 0; $y < count($options); $y++) {
                        if ($options[$y]['name'] == $variant[0] && str_contains(strtolower(str_replace(' ', '', $options[$y]['value'])), $variant[1])) {
                            array_push($products_, $products[$i]);
                            break 2;
                        }
                    }
                }
            }
        }
    }
    if ($variant) {
        $products = $products_;
    }
    if ($product_ids) {
        return $key ? array_column($products, $key) : $products;
    }
    return $pagination || $collection || $search ? [$products, $next] : [$products, shopify_get_data('collections'), $next, shopify_get_currency()];
}

function shopify_get_product_link($product_id) {
    $product = shopify_get_products($product_id, 'handle');
    return $product ? 'https://' . shopify_get_shop_name() . '/products/' . $product[0] : false;
}

function shopify_get_data($data_name = false) {
    global $shopify_data;
    if (isset($shopify_data)) {
        return $data_name ? $shopify_data[$data_name] : $shopify_data;
    }
    $shopify_data = sb_get_external_setting('shopify_data');
    if (!$shopify_data || sb_isset($shopify_data, 'expiration', 0) < time()) {
        $next = false;
        $fetched_data = [];
        do {
            $graphql_query = '{ "query": "query { products(first: 250' . ($next ? ', after: \"' . $next . '\"' : '') . ') { edges { node { tags vendor variants(first: 99) { edges { node { selectedOptions { name value } } } } } } pageInfo { hasNextPage endCursor } } }"}';
            $response = shopify_graphql($graphql_query);
            $products = sb_isset($response, 'products');
            if ($products) {
                $fetched_data = array_merge($fetched_data, sb_isset($products, 'edges', []));
                $next = sb_isset($products, ['pageInfo', 'endCursor']);
            } else {
                sb_error('shopify-get-all-data-error', 'shopify_get_all_data', $response);
                return false;
            }
        } while ($next);
        $shopify_data['tags'] = array_values(array_unique(array_reduce($fetched_data, function ($carry, $item) {
            return array_merge($carry, $item['node']['tags']);
        }, [])));
        $variants = array_reduce($fetched_data, function ($carry, $item) {
            return array_merge($carry, $item['node']['variants']['edges']);
        }, []);
        $processed_variants = [];
        foreach ($variants as $variant) {
            $options = $variant['node']['selectedOptions'];
            foreach ($options as $option) {
                if ($option['name'] !== 'Title') {
                    $processed_variants[$option['name']][] = $option['value'];
                }
            }
        }
        foreach ($processed_variants as $name => $values) {
            $processed_variants[$name] = array_values(array_unique($values));
        }
        $shopify_data['variants'] = $processed_variants;
        $shopify_data['vendors'] = array_values(array_unique(array_column(array_column($fetched_data, 'node'), 'vendor')));
        $response = shopify_curl('api/2023-10/custom_collections.json', '', [], 'GET');
        $collections = sb_isset($response, 'custom_collections');
        if ($collections) {
            $response = shopify_curl('api/2023-10/smart_collections.json', '', [], 'GET');
            $collections = array_merge($collections, sb_isset($response, 'smart_collections', []));
            for ($i = 0; $i < count($collections); $i++) {
                $collections[$i] = ['id' => $collections[$i]['id'], 'name' => $collections[$i]['title']];
            }
            $shopify_data['collections'] = $collections;
        } else {
            sb_error('shopify-get-all-data-error', 'shopify_get_all_data', $response);
            return false;
        }
        $shopify_data['expiration'] = time() + 864000;
        sb_save_external_setting('shopify_data', $shopify_data);
    }
    return $data_name ? $shopify_data[$data_name] : $shopify_data;
}

function shopify_get_active_user($shopify_id) {
    $shopify_customer = shopify_get_customer($shopify_id);
    if ($shopify_customer) {
        $query = 'SELECT A.id, A.token FROM sb_users A, sb_users_data B WHERE A.email = "' . sb_db_escape($shopify_customer['email']) . '" AND A.id = B.user_id AND B.slug = "shopify_id" AND B.value = "' . sb_db_escape($shopify_id) . '" LIMIT 1';
        $user = sb_db_get($query);
        if (!$user) {
            $user = $shopify_customer;
            $settings_extra = ['shopify_id' => [$shopify_id, 'Shopify ID']];
            $active_user = sb_get_active_user();
            if ($active_user) {
                $address = sb_isset($shopify_customer, 'default_address');
                if (isset($shopify_customer['phone'])) {
                    $settings_extra['phone'] = [$shopify_customer['phone'], 'Phone'];
                }
                if ($address && isset($address['country'])) {
                    $settings_extra['country'] = [$address['country'], 'Country'];
                }
                if ($address && isset($address['country_code'])) {
                    $settings_extra['country_code'] = [$address['country_code'], 'Country code'];
                }
                if ($active_user && ($active_user['user_type'] == 'lead' || $active_user['user_type'] == 'visitor')) {
                    sb_update_user($active_user['id'], $user, $settings_extra, true, true);
                } else {
                    sb_add_user($user, $settings_extra);
                }
                $user = sb_db_get($query);
            }
        }
        return sb_is_error($user) || !isset($user['token']) || !isset($user['id']) ? false : sb_login('', '', $user['id'], $user['token']);
    }
}

function shopify_get_conversation_details($shopify_id, $user_id) {
    $response = ['cart' => ['items' => []], 'orders' => [], 'orders_count' => 0, 'total' => 0];
    if ($shopify_id) {
        $shopify_customer = shopify_get_customer($shopify_id);
        $shopify_shop = 'https://' . shopify_get_shop_name();
        if ($shopify_customer) {
            $response['orders_count'] = $shopify_customer['orders_count'];
            $response['total'] = $shopify_customer['total_spent'] . ' ' . $shopify_customer['currency'];
        }
        $orders = shopify_get_orders($shopify_id);
        $orders_count = count($orders);
        $orders_total = 0;
        for ($i = 0; $i < $orders_count; $i++) {
            $order = $orders[$i];
            $items = $order['line_items'];
            for ($j = 0; $j < count($items); $j++) {
                $item = $items[$j];
                $items[$j] = [
                    'id' => $item['product_id'],
                    'quantity' => $item['current_quantity'],
                    'name' => $item['name']
                ];
            }
            $orders[$i] = [
                'id' => $order['id'],
                'date' => (new DateTime($order['created_at']))->format('Y-m-d H:i:s'),
                'price' => $order['current_total_price'] . ' ' . $order['currency'],
                'url' => $shopify_shop . '/admin/orders/' . $order['id'],
                'status' => sb_(ucfirst(sb_isset($order, 'fulfillment_status', 'Unfulfilled'))),
                'billing_address' => $order['billing_address']['name'] . PHP_EOL . $order['billing_address']['province'] . ' ' . $order['billing_address']['province_code'] . PHP_EOL . $order['billing_address']['country'] . ' ' . $order['billing_address']['country_code'],
                'shipping_address' => $order['shipping_address']['name'] . PHP_EOL . $order['shipping_address']['province'] . ' ' . $order['shipping_address']['province_code'] . PHP_EOL . $order['shipping_address']['country'] . ' ' . $order['shipping_address']['country_code'],
                'items' => $items
            ];
            $orders_total += $order['current_total_price'];
        }
        if (!$response['orders_count'] && $orders_count) {
            $response['orders_count'] = $orders_count;
            $response['total'] = $orders_total . ' ' . $shopify_customer['currency'];
        }
        $response['orders'] = $orders;
    }
    $cart = sb_get_external_setting('shopify_cart_' . $user_id);
    if ($cart) {
        $cart_items = sb_isset($cart, 'items', []);
        for ($i = 0; $i < count($cart_items); $i++) {
            $cart_items[$i]['url'] = $shopify_shop . '/products/' . $cart_items[$i]['handle'];
            $cart_items[$i]['price'] = ($cart_items[$i]['price'] / 100) . ' ' . $cart['currency'];
        }
        $cart['items'] = $cart_items;
        $response['cart'] = $cart;
    }
    return $response;
}

function shopify_merge_fields($message) {
    $shortcode = sb_get_shortcode($message, 'shopify', true);
    $replace = false;
    if ($shortcode && shopify_get_shop_name()) {
        $value = sb_isset($shortcode, 'product_id');
        if ($value) {
            $products = shopify_get_products($value);
            if ($products) {
                $replace = shopify_generate_rich_message_products($products, sb_isset($shortcode, 'link-text'));
            }
        }
    }
    return $replace ? str_replace($shortcode['shortcode'], trim($replace), $message) : $message;
}

function shopify_generate_rich_message_products($products, $link_text = false) {
    $url = 'https://' . shopify_get_shop_name() . '/products/';
    $currency = shopify_get_currency();
    $link_text = sb_rich_value($link_text ? $link_text : 'More details', false);
    $response = '';
    $count = count($products);
    if ($count > 1) {
        $response = '[slider';
        for ($i = 0; $i < $count; $i++) {
            $product = $products[$i];
            $index = $i + 1;
            $description = strip_tags($product['description']);
            $response .= ' image-' . $index . '="' . sb_isset($product, 'image') . '" header-' . $index . '="' . sb_rich_value($product['title']) . '"  description-' . $index . '="' . str_replace([PHP_EOL, "\r", "\n"], ' ', sb_rich_value(strlen($description) > 130 ? mb_substr($description, 0, 130) . '...' : $description)) . '" link-' . $index . '="' . $url . $product['handle'] . '" link-text-' . $index . '="' . $link_text . '" extra-' . $index . '="' . $product['price'] . ' ' . $currency . '"';
        }
        $response .= ']';
    } else if ($count) {
        $products = $products[0];
        $description = strip_tags($products['description']);
        $response .= ' [card image="' . sb_isset($products, 'image') . '" header="' . sb_rich_value($products['title']) . '"  description="' . str_replace([PHP_EOL, "\r", "\n"], ' ', sb_rich_value(strlen($description) > 130 ? mb_substr($description, 0, 130) . '...' : $description)) . '" link-text="' . $link_text . '" extra="' . $products['price'] . ' ' . $currency . '" link="' . $url . $products['handle'] . '"]';
    }
    return trim($response);
}

function shopify_open_ai_message($title = false, $collection = false, $tag = false, $variant = false, $single_product_information = false, $vendor = false) {
    $search = false;
    if ($title || $tag || $variant || $vendor) {
        $search = ['title' => $title, 'tag' => $tag, 'vendor' => $vendor];
        if ($variant) {
            $variants = shopify_get_data('variants');
            foreach ($variants as $key => $values) {
                if (in_array($variant, $values)) {
                    $variant = [$key, $variant];
                    break;
                }
            }
        }
    }
    $products = shopify_get_products(false, false, $collection, $search, false, $variant);
    if ($products) {
        $products = $products[0];
        $url = 'https://' . shopify_get_shop_name() . '/products/';
        if ($single_product_information) {
            unset($products[0]['image']);
        }
        for ($i = 0; $i < count($products); $i++) {
            $description = strip_tags($products[$i]['description']);
            $description = str_replace([PHP_EOL, "\r", "\n"], ' ', sb_rich_value(strlen($description) > 130 ? mb_substr($description, 0, 130) . '...' : $description));
            $products[$i]['description'] = $description;
            $products[$i]['url'] = $url . $products[$i]['handle'];
        }
        return $products;
    }
    return false;
}

function shopify_get_currency() {
    global $shopify_currency;
    if ($shopify_currency) {
        return $shopify_currency;
    }
    $shopify_currency = sb_isset(shopify_curl('api/2023-10/shop.json', '', [], 'GET'), ['shop', 'currency'], 'USD');
    return $shopify_currency;
}

function shopify_open_ai_function() {
    return [
        ['type' => 'function', 'function' => ['name' => 'sb-shopify-single', 'description' => 'Retrieve a specific information about a specific product in our store. For example: "What is the price of the PlayStation?", "Do you have the Nike Air Force in XL size?".', 'parameters' => ['type' => 'object', 'properties' =>
            [
                'product_name' => [
                    'type' => 'string',
                    'description' => 'The name of the product the user is asking about.'
                ],
                'information' => [
                    'type' => 'string',
                    'description' => 'The product information the user is asking about.',
                    'enum' => array_merge(array_keys(shopify_get_data('variants')), ['Price', 'Vendor'])
                ]
            ], 'required' => ['product_name', 'information']]]
        ],
        ['type' => 'function', 'function' => ['name' => 'sb-shopify', 'description' => 'Search for products in our store that meet the user\'s criteria. For example: "Do you sell monitors for less than 100 USD?", "I want to see some red t-shirt".', 'parameters' => ['type' => 'object', 'properties' =>
            [
                'product_name' => [
                    'type' => 'string',
                    'description' => 'The name of the product the user is asking about.'
                ],
                'collection' => [
                    'type' => 'string',
                    'description' => 'The category of the products.',
                    'enum' => array_column(shopify_get_data('collections'), 'name')
                ],
                'tag' => [
                    'type' => 'string',
                    'description' => 'The tags of the products.',
                    'enum' => shopify_get_data('tags')
                ],
                'variant' => [
                    'type' => 'string',
                    'description' => 'A specific product attribute.',
                    'enum' => array_merge(array_reduce(shopify_get_data('variants'), 'array_merge', []))
                ],
                'vendor' => [
                    'type' => 'string',
                    'description' => 'The brand of the products. For example: "Sony", "Adidas".',
                    'enum' => shopify_get_data('vendors')
                ]
            ], 'required' => []]]
        ]
    ];
}

function shopify_ai_function_calling($function_name, $id, $arguments, $query_tools) {
    $tag = sb_isset($arguments, 'tag');
    $variant = sb_isset($arguments, 'variant');
    if ($query_tools) {
        for ($i = 0; $i < count($query_tools); $i++) {
            $query_tools_function = $query_tools[$i]['function'];
            if ($query_tools_function['name'] == $function_name) {
                $properties = $query_tools_function['parameters']['properties'];
                $enum_tag = sb_isset($properties, ['tag', 'enum']);
                $enum_variants = sb_isset($properties, ['variant', 'enum']);
                if ($tag && in_array($tag, $enum_variants)) {
                    $variant = $tag;
                    $tag = false;
                }
                if ($variant && in_array($variant, $enum_tag) && !in_array($variant, $enum_variants)) {
                    $tag = $variant;
                    $variant = false;
                }
                break;
            }
        }
    }
    $response = shopify_open_ai_message(sb_isset($arguments, 'product_name'), sb_isset($arguments, 'collection'), $tag, $variant, sb_isset($arguments, 'information'), sb_isset($arguments, 'vendor'));
    return $response ? ($function_name == 'sb-shopify' ? ['sb-shortcode', $id, $response] : [$function_name, $id, $response]) : [$function_name, $id, []];
}

/*
 * -----------------------------------------------------------
 * MISCELLANEOUS
 * -----------------------------------------------------------
 *
 */

function cloud_get_max_quotas() {
    $max_quotas = [
        [
            'price' => ['month' => 0, 'year' => 0],
            'quota' => ['month' => ['messages' => 100, 'agents' => 1, 'users' => 10], 'year' => ['messages' => 100, 'agents' => 1, 'users' => 10], 'embeddings' => 100000]
        ],
        [
            'price' => ['month' => 5, 'year' => 50],
            'quota' => ['month' => ['messages' => 5000, 'agents' => 2, 'users' => 500], 'year' => ['messages' => 60000, 'agents' => 2, 'users' => 5000], 'embeddings' => 1000000]
        ],
        [
            'price' => ['month' => 7, 'year' => 70],
            'quota' => ['month' => ['messages' => 7000, 'agents' => 3, 'users' => 700], 'year' => ['messages' => 84000, 'agents' => 3, 'users' => 7000], 'embeddings' => 1000000]
        ],
        [
            'price' => ['month' => 10, 'year' => 100],
            'quota' => ['month' => ['messages' => 10000, 'agents' => 3, 'users' => 1000], 'year' => ['messages' => 120000, 'agents' => 3, 'users' => 10000], 'embeddings' => 1500000]
        ],
        [
            'price' => ['month' => 15, 'year' => 150],
            'quota' => ['month' => ['messages' => 20000, 'agents' => 3, 'users' => 1500], 'year' => ['messages' => 240000, 'agents' => 3, 'users' => 15000], 'embeddings' => 2000000]
        ],
        [
            'price' => ['month' => 20, 'year' => 200],
            'quota' => ['month' => ['messages' => 25000, 'agents' => 6, 'users' => 2000], 'year' => ['messages' => 300000, 'agents' => 6, 'users' => 20000], 'embeddings' => 2500000]
        ],
        [
            'price' => ['month' => 25, 'year' => 250],
            'quota' => ['month' => ['messages' => 35000, 'agents' => 6, 'users' => 2500], 'year' => ['messages' => 420000, 'agents' => 6, 'users' => 25000], 'embeddings' => 2500000]
        ],
        [
            'price' => ['month' => 30, 'year' => 300],
            'quota' => ['month' => ['messages' => 50000, 'agents' => 6, 'users' => 3000], 'year' => ['messages' => 600000, 'agents' => 6, 'users' => 30000], 'embeddings' => 3000000]
        ],
        [
            'price' => ['month' => 40, 'year' => 400],
            'quota' => ['month' => ['messages' => 70000, 'agents' => 10, 'users' => 5000], 'year' => ['messages' => 840000, 'agents' => 10, 'users' => 50000], 'embeddings' => 3500000]
        ],
        [
            'price' => ['month' => 60, 'year' => 600],
            'quota' => ['month' => ['messages' => 100000, 'agents' => 9999, 'users' => 20000], 'year' => ['messages' => 1200000, 'agents' => 9999, 'users' => 200000], 'embeddings' => 5000000]
        ],
        [
            'price' => ['month' => 80, 'year' => 800],
            'quota' => ['month' => ['messages' => 150000, 'agents' => 9999, 'users' => 50000], 'year' => ['messages' => 1800000, 'agents' => 9999, 'users' => 500000], 'embeddings' => 7000000]
        ],
        [
            'price' => ['month' => 120, 'year' => 1200],
            'quota' => ['month' => ['messages' => 200000, 'agents' => 9999, 'users' => 100000], 'year' => ['messages' => 2400000, 'agents' => 9999, 'users' => 1000000], 'embeddings' => 15000000]
        ],
        [
            'price' => ['month' => 230, 'year' => 2300],
            'quota' => ['month' => ['messages' => 500000, 'agents' => 9999, 'users' => 9999999999999], 'year' => ['messages' => 6000000, 'agents' => 9999, 'users' => 9999999999999], 'embeddings' => 30000000]
        ],
        [
            'price' => ['month' => 300, 'year' => 3000],
            'quota' => ['month' => ['messages' => 1000000, 'agents' => 9999, 'users' => 9999999999999], 'year' => ['messages' => 10000000, 'agents' => 999, 'users' => 9999999999999], 'embeddings' => 40000000]
        ],
        [
            'price' => ['month' => 500, 'year' => 5000],
            'quota' => ['month' => ['messages' => 3000000, 'agents' => 9999, 'users' => 9999999999999], 'year' => ['messages' => 30000000, 'agents' => 9999, 'users' => 9999999999999], 'embeddings' => 60000000]
        ]
    ];
    return $max_quotas;
}

function send_email($to, $subject, $message) {
    if (empty($to)) {
        return false;
    }
    require_once SB_PATH . '/vendor/phpmailer/Exception.php';
    require_once SB_PATH . '/vendor/phpmailer/PHPMailer.php';
    require_once SB_PATH . '/vendor/phpmailer/SMTP.php';
    $port = CLOUD_SMTP_PORT;
    $mail = new PHPMailer;
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->isSMTP();
    $mail->Host = CLOUD_SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = CLOUD_SMTP_USERNAME;
    $mail->Password = CLOUD_SMTP_PASSWORD;
    $mail->SMTPSecure = $port == 25 ? '' : ($port == 465 ? 'ssl' : 'tls');
    $mail->Port = $port;
    $mail->setFrom(CLOUD_SMTP_SENDER, CLOUD_SMTP_SENDER_NAME);
    $mail->isHTML(true);
    $mail->Subject = trim($subject);
    $mail->Body = $message;
    $mail->AltBody = $message;
    $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
    if (strpos($to, ',') > 0) {
        $emails = explode(',', $to);
        for ($i = 0; $i < count($emails); $i++) {
            $mail->addAddress($emails[$i]);
        }
    } else {
        $mail->addAddress($to);
    }
    return $mail->send() ? true : $mail->ErrorInfo;
}

function send_sms($message, $to) {
    if (strpos($to, '+') === false && substr($to, 0, 2) == '00') {
        $to = '+' . substr($to, 2);
    }
    $query = ['Body' => $message, 'From' => CLOUD_TWILIO_SENDER, 'To' => $to];
    return sb_curl('https://api.twilio.com/2010-04-01/Accounts/' . CLOUD_TWILIO_SID . '/Messages.json', $query, ['Authorization: Basic ' . base64_encode(CLOUD_TWILIO_SID . ':' . CLOUD_TWILIO_TOKEN)]);
}

function verify($email = false, $phone = false, $code_pairs = false) {
    $code = rand(99999, 999999);
    $code_prefix = '';
    if ($email) {
        send_email($email, super_get_setting('email_subject_verification_code_email'), str_replace('{code}', $code, super_get_setting('email_template_verification_code_email')));
        $code_prefix = 'EMAIL';
    }
    if ($phone) {
        send_sms(str_replace('{code}', $code, super_get_setting('template_verification_code_phone')), $phone);
        $code_prefix = 'PHONE';
    }
    if ($code_pairs) {
        $account = account();
        if ($account) {
            $code = json_decode(sb_encryption($code_pairs[0], false), true);
            $email_or_phone = $code[1];
            $code = $code[0];
            $email = $code == ('EMAIL' . $code_pairs[1]);
            if ($email || $code == ('PHONE' . $code_pairs[1])) {
                $response = db_query('UPDATE users SET ' . ($email ? 'email' : 'phone') . '_confirmed = 1 WHERE ' . ($email ? 'email' : 'phone') . ' = "' . db_escape($email_or_phone) . '"');
                return $response === true ? [$email ? 'email' : 'phone', account_login($account['email'], false, $account['token'])] : false;
            }
        }
        return false;
    }
    return sb_encryption(json_encode([$code_prefix . $code, $email ? $email : $phone]));
}

function cloud_cron($backup = true) {
    ignore_user_abort(true);
    set_time_limit(3540);
    $now_time = sb_gmt_now(0, true);
    $last_cron = super_get_setting('last_cron', 1);
    $free_customers_query_part = 'membership = "0" OR membership = "" OR membership = "free" OR membership IS NULL';
    if ($last_cron < $now_time - 86400) {
        $membership_type = sb_defined('SB_CLOUD_MEMBERSHIP_TYPE', 'messages');
        if (PAYMENT_PROVIDER == 'yoomoney') {
            yoomoney_cron();
        }

        // Quota notifications
        $membership_type_messages = in_array($membership_type, ['messages', 'messages-agents']);
        $memberships = db_get($membership_type_messages ? 'SELECT id, membership, email, first_name, last_name, count FROM users, membership_counter WHERE id = user_id AND date = "' . date('m-y') . '"' : 'SELECT token, membership, email, first_name, last_name FROM users', false);
        $memberships_count = count($memberships);
        $email_90 = false;
        $email_suspended = false;
        for ($i = 0; $i < $memberships_count; $i++) {
            try {
                if ($membership_type_messages) {
                    $count = intval($memberships[$i]['count']);
                } else {
                    $count = membership_count_agents_users($memberships[$i]['token']);
                }
                $quota = sb_isset(membership_get($memberships[$i]['membership']), 'quota', 1);
                $percentage = $count * 100 / $quota;
                $suspended = $count > $quota;
                if (($suspended || ($percentage > 90 && !in_array($membership_type, ['agents', 'users']))) && cloud_suspended_notifications_counter($memberships[$i]['id']) < 4) {
                    if ($suspended && !$email_suspended) {
                        $email_suspended = [super_get_setting('email_subject_membership_100'), super_get_setting('email_template_membership_100')];
                    }
                    if (!$suspended && !$email_90) {
                        $email_90 = [super_get_setting('email_subject_membership_90'), super_get_setting('email_template_membership_90')];
                    }
                    $email = $suspended ? $email_suspended : $email_90;
                    send_email($memberships[$i]['email'], $email[0], $email[1]);
                    cloud_suspended_notifications_counter($memberships[$i]['id'], true);
                }
            } catch (Exception $e) {
            }
        }

        // Membership expired notifications
        if (in_array(date('d'), [1, 29])) {
            $now = $now_time + 86400;
            $user_memberships = db_get('SELECT id, membership_expiration, email, first_name, last_name FROM users WHERE membership <> "free" AND membership <> "0"', false);
            for ($i = 0; $i < count($user_memberships); $i++) {
                try {
                    if ($now > cloud_gmt_time($user_memberships[$i]['membership_expiration'])) {
                        $user_id = $user_memberships[$i]['id'];
                        if (cloud_suspended_notifications_counter($user_id) < 4) {
                            if (!$email_suspended) {
                                $email_suspended = [super_get_setting('email_subject_membership_100'), super_get_setting('email_template_membership_100')];
                            }
                            send_email($user_memberships[$i]['email'], $email_suspended[0], $email_suspended[1]);
                            cloud_suspended_notifications_counter($user_id, true);
                        } else {
                            db_query('UPDATE users SET membership = "0", membership_expiration = "" WHERE id = ' . $user_id);
                        }
                    }
                } catch (Exception $e) {
                }
            }
        }

        // Backup of all databases
        if ($backup && $last_cron < $now_time - 86400) {
            $databases = db_get('SHOW DATABASES', false);
            $path = SB_CLOUD_PATH . '/account/backup/';
            $index = 0;
            $index_name = 1;
            $count = count($databases);
            $bucket_name = defined('SB_CLOUD_AWS_S3') ? sb_isset(SB_CLOUD_AWS_S3, 'amazon-s3-backup-bucket-name') : false;
            for ($i = 0; $i < $count; $i++) {
                try {
                    $name = $databases[$i]['Database'];
                    $is_sb_db = strpos($name, 'sb_') === 0 || $name === CLOUD_DB_NAME;
                    if ($is_sb_db || $i == ($count - 1)) {
                        if ($is_sb_db) {
                            exec('mysqldump --user=' . CLOUD_DB_USER . ' --password=' . CLOUD_DB_PASSWORD . ' --host=' . CLOUD_DB_HOST . ' ' . $name . ' > ' . $path . $name . '.sql');
                        }
                        if ($index > 2000 || $i == ($count - 1)) {
                            $index = 0;
                            $files = scandir($path);
                            $zip = new ZipArchive;
                            $zip_path = $path . date('d-m-Y') . '_' . $index_name . '_' . bin2hex(openssl_random_pseudo_bytes(20)) . '.zip';
                            $index_name++;
                            if ($zip->open($zip_path, ZipArchive::CREATE)) {
                                $zip->setPassword(SB_CLOUD_KEY);
                                for ($j = 0; $j < count($files); $j++) {
                                    try {
                                        $file = $files[$j];
                                        if (pathinfo($file, PATHINFO_EXTENSION) == 'sql') {
                                            $zip->addFile($path . $file, $file);
                                            $zip->setEncryptionName($file, ZipArchive::EM_AES_128);
                                        }
                                    } catch (Exception $e) {
                                    }
                                }
                                $zip->close();
                                for ($j = 0; $j < count($files); $j++) {
                                    try {
                                        $file = $files[$j];
                                        if (pathinfo($file, PATHINFO_EXTENSION) == 'sql' || (strtotime(explode('_', $file)[0]) < strtotime('-30 days'))) {
                                            unlink($path . $file);
                                        }
                                    } catch (Exception $e) {
                                    }
                                }
                            }
                            if (file_exists($zip_path)) {
                                $reponse_aws = sb_aws_s3($zip_path, 'PUT', $bucket_name);
                                if (strpos($reponse_aws, 'https') === 0) {
                                    sb_file_delete($zip_path);
                                }
                            }
                        }
                        $index++;

                    }
                } catch (Exception $e) {
                }
            }
            $files = scandir($path);
            for ($i = 0; $i < count($files); $i++) {
                try {
                    if (pathinfo($files[$i], PATHINFO_EXTENSION) == 'zip') {
                        $file_path = $path . $files[$i];
                        $reponse = sb_aws_s3($file_path, 'PUT', $bucket_name);
                        if (strpos($reponse, 'https') === 0) {
                            unlink($file_path);
                        }
                    }
                } catch (Exception $e) {
                }
            }
        }
        super_save_setting('last_cron', $now_time);
    }

    if ($last_cron < $now_time - 2678400) {

        // Delete users
        $six_months_ago = '"' . date('Y-m-d', strtotime('-6 months')) . '"';
        $all_users = db_get('SELECT * FROM users WHERE (' . $free_customers_query_part . ') AND creation_time < ' . $six_months_ago, false);
        for ($i = 0; $i < count($all_users); $i++) {
            try {
                $token = $all_users[$i]['token'];
                if (file_exists(SB_CLOUD_PATH . '/script/config/config_' . $token . '.php') && !db_get('SELECT id FROM sb_messages WHERE creation_time > ' . $six_months_ago, true, $token) && !db_get('SELECT id FROM sb_users WHERE last_activity > ' . $six_months_ago, true, $token)) {
                    super_delete_customer($all_users[$i]['id']);
                }
            } catch (Exception $e) {
            }
        }

        // Delete files
        $path_root = SB_CLOUD_PATH . '/script/uploads/';
        $dirs = scandir($path_root);
        $allowed_extensions = ['json', 'psd', 'ai', 'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'key', 'ppt', 'odt', 'xls', 'xlsx', 'zip', 'rar', 'mp3', 'm4a', 'ogg', 'wav', 'mp4', 'mov', 'wmv', 'avi', 'mpg', 'ogv', '3gp', '3g2', 'mkv', 'txt', 'ico', 'csv', 'ttf', 'font', 'css', 'scss'];
        $months_less_4 = $now_time - 10713600;
        $months_less_24 = $now_time - 63115200;
        foreach ($dirs as $dir) {
            if ($dir != 'cloud' && $dir != 'embeddings' && $dir != '.' && $dir != '..') {
                try {
                    if (is_dir($path_root . $dir)) {
                        $files = scandir($path_root . $dir);
                        foreach ($files as $file) {
                            if ($file != '.' && $file != '..') {
                                $path = $path_root . $dir . '/' . $file;
                                $extension = pathinfo(basename($file), PATHINFO_EXTENSION);
                                $size = filesize($path) / 1000000;
                                $file_creation_time = filemtime($path);
                                if (!$extension || !in_array($extension, $allowed_extensions) || $size > 0.8 || $file_creation_time < $months_less_24 || (($size > 0.05 || strpos($path, 'conversation-') || strpos($path, 'settings_') || strpos($path, '-users.csv')) && $file_creation_time < $months_less_4)) {
                                    if (!is_dir($path)) {
                                        unlink($path);
                                    }
                                }
                            }
                        }
                    } else {
                        $extension = pathinfo(basename($dir), PATHINFO_EXTENSION);
                        if (in_array($extension, ['txt', 'json', 'csv'])) {
                            unlink($path_root . $dir);
                        }
                    }
                } catch (Exception $exception) {
                }
            }
        }

        // Delete embeddings
        $free_customers = array_column(db_get('SELECT id FROM users WHERE ' . $free_customers_query_part, false), 'id');
        $path_root = SB_CLOUD_PATH . '/script/uploads/embeddings';
        $months_less_1 = $now_time - 2678400;
        $dirs = scandir($path_root);
        foreach ($dirs as $dir) {
            try {
                if ($dir != '.' && $dir != '..' && in_array($dir, $free_customers)) {
                    $files = scandir($dir);
                    foreach ($files as $file) {
                        if ($file != 'index.html') {
                            $path = $dir . '/' . $file;
                            $extension = pathinfo(basename($file), PATHINFO_EXTENSION);
                            $size = filesize($path) / 1000000;
                            if (filemtime($path) < $months_less_1) {
                                if (!is_dir($path)) {
                                    unlink($path);
                                    echo $path . '<br>';
                                }
                            }
                        }
                    }
                }
            } catch (Exception $exception) {
            }
        }
    }

    // Customer cron jobs
    super_save_setting('last_cron_1860', $now_time);
    if ($last_cron < $now_time - 1860) {
        $customer_settings = json_decode(super_get_setting('customer-settings'), true);
        if ($customer_settings) {
            foreach ($customer_settings as $key => $value) {
                $ids = implode(',', sb_isset($customer_settings, $key, []));
                if ($ids) {
                    $tokens = db_get('SELECT token FROM users WHERE id IN (' . $ids . ')', false);
                    for ($i = 0; $i < count($tokens); $i++) {
                        sb_get(CLOUD_URL . '/script/include/api.php?cloud=' . $tokens[$i]['token'] . '&' . ($key == 'training' ? 'open-ai-training' : $key) . '=true');
                    }
                }
            }
        }
        super_save_setting('last_cron_1860', $now_time);
    }

    // Marketing emails
    if ($last_cron < $now_time - 86400) {
        $email_follow_up_subjects = [super_get_setting('email_subject_follow_up'), super_get_setting('email_subject_follow_up_2')];
        if ($email_follow_up_subjects[0] || $email_follow_up_subjects[1]) {
            $free_customers = db_get('SELECT id, first_name, last_name, email, creation_time FROM users WHERE creation_time < "' . date('Y-m-d', strtotime('32 days')) . '" AND ' . $free_customers_query_part, false);
            $contacted_customers = [array_column(db_get('SELECT user_id FROM users_data WHERE slug = "marketing_email_7"', false), 'user_id'), array_column(db_get('SELECT user_id FROM users_data WHERE slug = "marketing_email_30"', false), 'user_id')];
            $times = [$now_time - 604800, $now_time - 2678400];
            $email_templates = [['subject' => $email_follow_up_subjects[0], 'template' => super_get_setting('email_template_follow_up')], ['subject' => $email_follow_up_subjects[1], 'template' => super_get_setting('email_template_follow_up_2')]];
            foreach ($free_customers as $customer) {
                $creation_time = strtotime($customer['creation_time']);
                $username = $customer['first_name'] . ' ' . $customer['last_name'];
                for ($i = 0; $i < 2; $i++) {
                    if ($email_templates[$i]['subject'] && !in_array($customer['id'], $contacted_customers[$i]) && $creation_time < $times[$i] && $creation_time > ($now_time - ($i ? 3888000 : 1728000))) {
                        if (send_email($customer['email'], cloud_merge_field_username($email_templates[$i]['subject'], $username), cloud_merge_field_username($email_templates[$i]['template'], $username))) {
                            super_insert_user_data('(' . $customer['id'] . ', "marketing_email_' . ($i ? '30' : '7') . '", 1)');
                        }
                    }
                }
            }
        }
    }
}

function cloud_suspended_notifications_counter($user_id, $increase = false, $is_credits = false) {
    $slug = $is_credits ? 'notifications_credits_count' : 'notifications_count';
    $count = super_get_user_data($slug, $user_id);
    if ($increase) {
        return $count ? db_query('UPDATE users_data SET `value` = ' . (intval($count) + 1) . ' WHERE user_id = ' . $user_id . ' AND slug = "' . $slug . '" LIMIT 1') : super_insert_user_data('(' . $user_id . ', "' . $slug . '", 1)');
    }
    return $count ? intval($count) : 0;
}

function cloud_get_token_by_id($cloud_user_id) {
    return sb_isset(db_get('SELECT token FROM users WHERE id = ' . db_escape($cloud_user_id)), 'token');
}

function cloud_api() {
    $path = SB_CLOUD_PATH . '/script/config/config_' . $_POST['token'] . '.php';
    if (!file_exists($path)) {
        header('HTTP/1.1 401 Unauthorized');
        sb_api_error(sb_error('invalid-token', 'API', 'Invalid token. The token is not linked to any config.php file.'));
    }
    require_once($path);
    $cloud_user = db_get('SELECT * FROM users WHERE token = "' . db_escape($_POST['token']) . '"');
    if (!$cloud_user) {
        sb_api_error(sb_error('cloud-user-not-found', 'API', 'No cloud users found with given email.'));
    }
    $cloud_user['token'] = $_POST['token'];
    $cloud_user['user_id'] = $cloud_user['id'];
    $_POST['cloud'] = sb_encryption(json_encode($cloud_user));
    $GLOBALS['SB_LOGIN'] = sb_get_user_by('email', $cloud_user['email']);
    return true;
}

function cloud_embeddings_chars_limit($membership = false) {
    $membership = $membership ? $membership : membership_get_active();
    $price = $membership['price'];
    $period = sb_isset($membership, 'period', 'month');
    $currency = strtolower($membership['currency']);
    $quatas = cloud_get_max_quotas();
    $max_quota = 0;
    for ($i = 0; $i < count($quatas); $i++) {
        if ($price >= sb_usd_get_amount($quatas[$i]['price'][$period], $currency)) {
            $max_quota = $quatas[$i]['quota']['embeddings'];
        } else {
            break;
        }
    }
    return $max_quota;
}

function sb_cloud_debug($value) {
    $value = is_string($value) ? $value : json_encode($value);
    if (file_exists('debug.txt')) {
        $value = file_get_contents('debug.txt') . PHP_EOL . $value;
    }
    sb_file('debug.txt', $value);
}

function sb_usd_rates($currency_code = false) {
    global $SB_USD_RATES;
    if (!$SB_USD_RATES) {
        $SB_USD_RATES = json_decode(super_get_setting('fiat_rates'), true);
        if (!$SB_USD_RATES || $SB_USD_RATES[0] < (time() - 3600)) {
            $error = '';
            $response = sb_curl('https://openexchangerates.org/api/latest.json?app_id=' . OPEN_EXCHANGE_RATE_APP_ID);
            $SB_USD_RATES = sb_isset($response, 'rates');
            if ($SB_USD_RATES) {
                super_save_setting('fiat_rates', [time(), $SB_USD_RATES]);
            } else {
                return sb_error($error . 'Error: ' . json_encode($response), 'bxc_usd_rates', true);
            }
        } else {
            $SB_USD_RATES = $SB_USD_RATES[1];
        }
    }
    return $currency_code ? $SB_USD_RATES[strtoupper($currency_code)] : $SB_USD_RATES;
}

function sb_usd_get_amount($amount, $currency_code) {
    if (!$currency_code) {
        $currency_code = membership_currency();
    }
    return strtolower($currency_code) == 'usd' ? $amount : $amount * sb_usd_rates($currency_code);
}

function currency_get_divider($currency_code) {
    return in_array(strtoupper($currency_code), ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF']) ? 1 : 100;
}

function cloud_meta_whatsapp_sync($code) {
    $auth = ['Authorization: Bearer ' . WHATSAPP_APP_TOKEN];
    $fb_url = 'https://graph.facebook.com/v24.0/';
    $response = sb_curl($fb_url . 'oauth/access_token?client_id=' . WHATSAPP_APP_ID . '&client_secret=' . WHATSAPP_APP_SECRET . '&code=' . $code, '', $auth, 'POST');
    $access_token = sb_isset($response, 'access_token');
    $token = account()['token'];
    if ($access_token && $token) {
        $response = json_decode(sb_curl($fb_url . 'debug_token?input_token=' . $access_token, '', $auth, 'GET'), true);
        $access_token_debug = sb_isset($response, 'data');
        $waba_id = false;
        $granular_scopes = sb_isset($access_token_debug, 'granular_scopes');
        for ($i = 0; $i < $granular_scopes; $i++) {
            if (in_array(sb_isset($granular_scopes[$i], 'scope'), ['whatsapp_business_management', 'whatsapp_business_messaging']) && !empty($granular_scopes[$i]['target_ids'])) {
                $waba_id = $granular_scopes[$i]['target_ids'][0];
                break;
            }
        }
        if ($waba_id) {
            $response = json_decode(sb_curl($fb_url . $waba_id . '/phone_numbers?access_token=' . $access_token, '', $auth, 'GET'), true);
            $data = sb_isset($response, 'data');
            if ($data) {
                $phone_numbers = array_column($data, 'id');
                if (empty($phone_numbers)) {
                    return $response;
                }
                $query = '';
                $is_embedded_signup = sb_isset($data[0], 'platform_type') == 'CLOUD_API';
                $auth_header = ['Authorization: Bearer ' . $access_token];
                for ($i = 0; $i < count($phone_numbers); $i++) {
                    if ($is_embedded_signup) {
                        $query .= '("' . $token . '","' . db_escape($phone_numbers[$i]) . '"),';
                    } else {
                        $response = sb_curl($fb_url . $phone_numbers[$i] . '/register', ['messaging_product' => 'whatsapp', 'pin' => '123456'], $auth_header, 'POST', 20);
                        if (sb_isset($response, 'success')) {
                            sb_curl($fb_url . $phone_numbers[$i] . '/settings', ['calling' => ['status' => 'ENABLED']], $auth_header, 'POST');
                            $query .= '("' . $token . '","' . db_escape($phone_numbers[$i]) . '"),';
                        } else {
                            return $response;
                        }
                    }
                }
                $response = sb_curl($fb_url . $waba_id . '/subscribed_apps', '', $auth_header);
                if (sb_isset($response, 'success')) {
                    db_query('DELETE FROM whatsapp WHERE phone_number_id IN (' . implode(',', $phone_numbers) . ')');
                    db_query('INSERT INTO whatsapp VALUES ' . substr($query, 0, -1));
                    return ['access_token' => $access_token, 'phone_numbers' => $phone_numbers, 'app_scoped_user_id' => sb_isset($access_token_debug, 'data', 'user_id'), 'waba_id' => $waba_id];
                }
            }
        }
    }
    return $response;
}

function cloud_meta_messenger_sync($access_token) {
    $fb_url = 'https://graph.facebook.com/';
    $response = file_get_contents($fb_url . 'oauth/access_token?grant_type=fb_exchange_token&client_id=' . MESSENGER_APP_ID . '&client_secret=' . MESSENGER_APP_SECRET . '&fb_exchange_token=' . $access_token);
    $extended_access_token = sb_isset(json_decode($response, true), 'access_token');
    $token = account()['token'];
    if ($extended_access_token && $token) {
        $response = json_decode(file_get_contents($fb_url . 'me/accounts?access_token=' . $extended_access_token), true);
        $data = sb_isset($response, 'data');
        if ($data) {
            $pages = [];
            $page_ids = [];
            $query = '';
            for ($i = 0; $i < count($data); $i++) {
                $page_access_token = $data[$i]['access_token'];
                $page_id = $data[$i]['id'];
                $response = sb_curl($fb_url . $page_id . '/subscribed_apps?access_token=' . $page_access_token . '&subscribed_fields=messages,messaging_postbacks,messaging_optins,message_reads,message_echoes,feed', '', ['Content-Type: application/json', 'Content-Length: 0']);
                if (sb_isset($response, 'success')) {
                    $query .= '("' . $token . '", "' . db_escape($page_id) . '", "' . db_escape($page_access_token) . '"),';
                    $instagram = sb_isset(json_decode(sb_curl($fb_url . $page_id . '/?access_token=' . $access_token . '&fields=instagram_business_account', '', [], 'GET'), true), 'instagram_business_account');
                    if ($instagram) {
                        $query .= '("' . $token . '", "' . db_escape($instagram['id']) . '", "' . db_escape($page_access_token) . '"),';
                    }
                    array_push($page_ids, $page_id);
                    array_push($pages, ['name' => $data[$i]['name'], 'page_id' => $page_id, 'access_token' => $page_access_token, 'instagram' => sb_isset($instagram, 'id')]);
                    if ($instagram) {
                        array_push($page_ids, $instagram['id']);
                    }
                } else {
                    return $response;
                }
            }
            db_query('DELETE FROM messenger WHERE page_id IN (' . implode(',', $page_ids) . ')');
            db_query('INSERT INTO messenger VALUES ' . substr($query, 0, -1));
            return $pages;
        }
    }
    return $response;
}

function cloud_messenger_unsubscribe() {
    $access_token = db_get('SELECT page_id, page_token FROM messenger WHERE token = "' . account()['token'] . '"');
    if ($access_token) {
        $scoped_uid = json_decode(sb_curl('https://graph.facebook.com/debug_token?access_token=' . MESSENGER_APP_TOKEN . '&input_token=' . $access_token['page_token'], '', [], 'GET'), true);
        if ($scoped_uid && isset($scoped_uid['data']['user_id'])) {
            $response = json_decode(sb_curl('https://graph.facebook.com/' . $scoped_uid['data']['user_id'] . '/permissions?method=delete&access_token=' . MESSENGER_APP_TOKEN, '', [], 'DELETE'), true);
            if ($response && !empty($response['success'])) {
                return db_query('DELETE FROM messenger WHERE token = "' . account()['token'] . '"');
            }
        }
    }
    return false;
}

function cloud_slack_sync($code) {
    $token = account()['token'];
    $response = sb_curl('https://slack.com/api/oauth.v2.access?client_id=' . SLACK_CLIENT_ID . '&client_secret=' . SLACK_CLIENT_SECRET . '&code=' . $code . '&redirect_uri=' . urlencode(CLOUD_URL . '?setting=slack&slack-sync=true'), false, []);
    if (isset($response) && $token && $response['ok'] && strlen($response['access_token']) > 10) {
        $access_token = $response['access_token'];
        $access_token_user = $response['authed_user']['access_token'];
        $team_id = $response['team']['id'];
        $channel_id = $response['incoming_webhook']['channel_id'];
        $workspace = isset($response['incoming_webhook']['configuration_url']) ? explode('.', parse_url($response['incoming_webhook']['configuration_url'])['host'])[0] : '';
        db_query('DELETE FROM slack WHERE token = "' . $token . '" LIMIT 1');
        db_query('INSERT INTO slack VALUES ("' . $token . '", "' . $team_id . '")');
        sb_cloud_load();
        sb_delete_external_setting('slack-channels');
        return ['workspace' => $workspace, 'bot_access_token' => $access_token, 'user_access_token' => $access_token_user, 'channel_id' => $channel_id];
    }
    return $response;
}

function cloud_addon_purchase($index) {
    return membership_custom_payment(CLOUD_ADDONS[$index]['price'], sb_string_slug(CLOUD_ADDONS[$index]['title']));
}

function get_config($token) {
    $path = SB_CLOUD_PATH . '/script/config/config_' . $token . '.php';
    $raw = file_get_contents($path);
    $raw = explode('define', $raw);
    $details = [];
    for ($i = 0; $i < count($raw); $i++) {
        $item = $raw[$i];
        if (str_contains($item, '(\'SB_')) {
            $item = explode(',', $item);
            $details[trim(substr($item[0], 2, -1))] = trim(str_replace('\'', '', substr($item[1], strpos($item[1], ','), strpos($item[1], '\')'))));
        }
    }
    return $details;
}

function cloud_webhook($webhook_name, $parameters) {
    $webhook_url = sb_isset(super_get_settings(), 'webhook-url');
    if ($webhook_url) {
        $query = json_encode(['function' => $webhook_name, 'key' => SB_CLOUD_KEY, 'data' => $parameters, 'sender-url' => (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '')], JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_UNICODE);
        return sb_curl($webhook_url, $query, ['Content-Type: application/json', 'Content-Length: ' . strlen($query)]);
    }
    return false;
}

function cloud_gmt_time($date_string) {
    $date_string = DateTime::createFromFormat('d-m-y', $date_string, new DateTimeZone('UTC'));
    return $date_string->getTimestamp();
}

function cloud_merge_field_username($message, $username) {
    return str_replace('{user_name}', $username, $message);
}

function cloud_email_limit() {
    $account_id = get_active_account_id();
    if ($account_id) {
        $today = date('d-m-y');
        $usage = db_get('SELECT value FROM users_data WHERE user_id = ' . $account_id . ' AND slug = "email_limit"');
        $usage = explode('|', $usage ? $usage['value'] : $today . '|0');
        if ($usage[0] != $today) {
            $usage = [$today, 0];
        }
        if ($usage[1] > 300) {
            return new SBError('email-usage-quota-exceeded', 'cloud_email_limit', 'Daily email quota exceeded. Set up SMTP in Settings > Notifications > SMTP for unlimited emails.');
        }
        db_query('DELETE FROM users_data WHERE user_id = ' . $account_id . ' AND slug = "email_limit"');
        return db_query('INSERT INTO users_data (user_id, slug, value) VALUES (' . $account_id . ', "email_limit", "' . $today . '|' . ($usage[1] + 1) . '")');
    }
    return false;
}

/*
 * -----------------------------------------------------------
 * SUPPORT BOARD FUNCTIONS
 * -----------------------------------------------------------
 *
 */

function cloud_css_js() {
    $cloud_settings = super_get_settings();
    if (!$cloud_settings) {
        return;
    }
    $css = '';
    $color = sb_isset($cloud_settings, 'color');
    if ($color) {
        $color = $cloud_settings['color'];
        $css = '.sb-menu-wide ul li.sb-active, .sb-tab > .sb-nav > ul li.sb-active,.sb-table input[type="checkbox"]:checked, .sb-table input[type="checkbox"]:hover { border-color: ' . $color . '; }';
        $css .= '.sb-board > .sb-admin-list .sb-scroll-area li.sb-active,.sb-user-conversations > li.sb-active { border-left-color: ' . $color . '; }';
        $css .= '.sb-setting input:focus, .sb-setting select:focus, .sb-setting textarea:focus, .sb-setting input:focus, .sb-setting select:focus, .sb-setting textarea:focus,
                 .sb-setting.sb-type-upload-image .image:hover, .sb-setting [data-type="upload-image"] .image:hover, .sb-setting.sb-type-upload-image .image:hover, .sb-setting [data-type="upload-image"] .image:hover,.sb-input > input:focus,
                 .sb-input > input.sb-focus, .sb-input > select:focus, .sb-input > select.sb-focus, .sb-input > textarea:focus, .sb-input > textarea.sb-focus,.sb-search-btn > input,
                  .sb-search-btn > input:focus { border-color: ' . $color . '; box-shadow: 0 0 5px rgb(108 108 108 / 20%);}';
        $css .= '.sb-menu-wide ul li.sb-active, .sb-menu-wide ul li:hover, .sb-tab > .sb-nav > ul li.sb-active, .sb-tab > .sb-nav > ul li:hover,.sb-admin > .sb-header > .sb-admin-nav > div > a:hover,
                 .sb-admin > .sb-header > .sb-admin-nav > div > a.sb-active,.sb-setting input[type="checkbox"]:checked:before, .sb-setting input[type="checkbox"]:checked:before,
                 .sb-language-switcher > i:hover,.sb-admin > .sb-header > .sb-admin-nav-right .sb-account .sb-menu li:hover, .sb-admin > .sb-header > .sb-admin-nav-right .sb-account .sb-menu li.sb-active:hover,
                 .sb-admin > .sb-header > .sb-admin-nav-right > div > a:hover,.sb-search-btn i:hover, .sb-search-btn.sb-active i, .sb-filter-btn i:hover,.sb-filter-btn.sb-active i,.sb-loading:before,
                 .sb-board .sb-conversation > .sb-top a:hover i,.sb-panel-details > i:hover,.sb-board .sb-conversation > .sb-top > a:hover,.sb-btn-text:hover,.sb-table input[type="checkbox"]:checked:before,
                 .sb-profile-list [data-id="wp-id"]:hover, .sb-profile-list [data-id="wp-id"]:hover label, .sb-profile-list [data-id="conversation-source"]:hover, .sb-profile-list [data-id="conversation-source"]:hover label,
                 .sb-profile-list [data-id="location"]:hover, .sb-profile-list [data-id="location"]:hover label, .sb-profile-list [data-id="timezone"]:hover, .sb-profile-list [data-id="timezone"]:hover label,
                 .sb-profile-list [data-id="current_url"]:hover, .sb-profile-list [data-id="current_url"]:hover label, .sb-profile-list [data-id="envato-purchase-code"]:hover,.sb-profile-list [data-id="envato-purchase-code"]:hover label,
                 .sb-board > .sb-admin-list .sb-scroll-area li[data-conversation-status="2"] .sb-time,.sb-select p:hover,.sb-select ul li:hover, div ul.sb-menu li.sb-active:not(:hover),.sb-select ul li.sb-active,
                 .sb-board .sb-conversation .sb-list > div .sb-menu-btn:hover,.sb-select:not(.sb-disabled) p:hover,#sb-settings.sb-active,.sb-custom-select span:hover,.sb-admin>.sb-header>.sb-admin-nav-right .sb-account .sb-menu a:hover { color: ' . $color . '; }';
        $css .= '.sb-area-settings .sb-tab  .daterangepicker td.active, .daterangepicker td.active:hover, .daterangepicker .ranges li.active,div ul.sb-menu li:hover,div.sb-select.sb-select-colors > p:hover,
                 .sb-board > .sb-admin-list .sb-scroll-area li > .sb-notification-counter,.sb-btn:hover, .sb-btn:active, a.sb-btn:hover, a.sb-btn:active,.sb-board > .sb-admin-list > .sb-scroll-area li.sb-active:before { background-color: ' . $color . '; }';
        $css .= '.sb-board > .sb-admin-list.sb-departments-show li.sb-active:before { background-color: ' . $color . ' !important; }';
        $css .= '.sb-tags-cnt > span:hover,.sb-btn-icon:hover { border-color: ' . $color . '; color: ' . $color . '; }';
        $css .= '.daterangepicker td.in-range,.sb-btn-icon:hover { background-color: rgb(151 151 151 / 8%); }';
        $css .= '.sb-board .sb-user-details,.sb-admin > .sb-header,.sb-select.sb-select-colors > p:not([data-value]),.sb-table tr:hover td,.sb-board .sb-user-details .sb-user-conversations li:hover,
                 .sb-board .sb-user-details .sb-user-conversations li.sb-active, .sb-select.sb-select-colors > p[data-value=""],.sb-select.sb-select-colors > p[data-value="-1"] { }';
        $css .= '.sb-board > .sb-admin-list .sb-scroll-area li:hover,.sb-board > .sb-admin-list .sb-scroll-area li.sb-active { }';
        $css .= '.sb-profile-list > ul > li .sb-icon, .sb-profile-list > ul > li > img { color: #424242 }';
        $css .= '.sb-admin .sb-top-bar > div:first-child > ul::-webkit-scrollbar-thumb:hover,.sb-area-settings > .sb-tab > .sb-nav::-webkit-scrollbar-thumb:hover, .sb-area-reports > .sb-tab > .sb-nav::-webkit-scrollbar-thumb:hover,
                 .sb-dialog-box pre::-webkit-scrollbar-thumb:hover, .sb-horizontal-scroll::-webkit-scrollbar-thumb:hover,.sb-scroll-area::-webkit-scrollbar-thumb:hover { background:' . $color . '; }';
    }
    $color = sb_isset($cloud_settings, 'color-2');
    if ($color) {
        // $css .= '.sb-btn:hover, a.sb-btn:hover { background-color:' . $color . '}';
    }
    if ($css) {
        echo '<style>' . $css . '</style>' . PHP_EOL;
    }
    if ($cloud_settings['css']) {
        echo '<link rel="stylesheet" href="' . $cloud_settings['css'] . '" media="all" />';
    }
    if ($cloud_settings['js']) {
        echo '<script src="' . $cloud_settings['js'] . '"></script>';
    }
}

function cloud_js_admin() {
    $cloud_settings = super_get_settings();
    $membership = db_get('SELECT membership FROM users WHERE id = ' . get_active_account_id());
    echo '<script>var WEBSITE_URL = "' . sb_defined('WEBSITE_URL', '#') . '"; var SB_CLOUD_ACTIVE_APPS = ' . json_encode(sb_get_external_setting('active_apps', [])) . '; var SB_CLOUD_MEMBERSHIP = "' . $membership['membership'] . '"; var DISABLE_APPS = "' . sb_isset($cloud_settings, 'disable-apps') . '"; var SB_CLOUD_WHATSAPP = { app_id: "' . sb_defined('WHATSAPP_APP_ID') . '", configuration_id: "' . sb_defined('WHATSAPP_CONFIGURATION_ID') . '" }; var SB_CLOUD_MESSENGER = { app_id: "' . sb_defined('MESSENGER_APP_ID') . '", configuration_id: "' . sb_defined('MESSENGER_CONFIGURATION_ID') . '" }; var SB_AUTO_SYNC =  { "whatsapp-cloud": ' . (defined('WHATSAPP_APP_ID') ? 'true' : 'false') . ', "messenger": ' . (defined('MESSENGER_APP_ID') ? 'true' : 'false') . ', "open-ai": ' . (defined('OPEN_AI_KEY') ? 'true' : 'false') . ', google: ' . (defined('GOOGLE_CLIENT_ID') ? 'true' : 'false') . ' }</script>';
    echo '<script src="' . CLOUD_URL . '/account/js/admin' . (sb_is_debug() ? '' : '.min') . '.js?v=' . SB_VERSION . '"></script>';
}

function cloud_css_js_front() {
    $cloud_settings = super_get_settings();
    $css = sb_isset($cloud_settings, 'css-front');
    $js = sb_isset($cloud_settings, 'js-front');
    if ($css) {
        echo '<link rel="stylesheet" href="' . $css . '" media="all" />';
    }
    if ($js) {
        echo '<script src="' . $js . '"></script>';
    }
}

function cloud_increase_count() {
    global $CLOUD_CONNECTION;
    if (!isset($_POST['cloud'])) {
        sb_error('cloud-not-found', 'cloud_increase_count', 'Cloud data not found', true);
    }
    $now = date('m-y');
    db_query('UPDATE membership_counter SET count = count + 1 WHERE user_id = ' . get_active_account_id() . ' AND date = "' . $now . '" LIMIT 1');
    if ($CLOUD_CONNECTION->affected_rows == 0 || $CLOUD_CONNECTION->affected_rows == -1) {
        db_query('INSERT INTO membership_counter (user_id, count, date) VALUES (' . get_active_account_id() . ', 1, "' . $now . '")');
    }
}

function cloud_custom_code() {
    $code = super_get_setting('custom-code-admin');
    if ($code) {
        echo $code;
    }
}

function cloud_add_to_payment_history($cloud_user_id, $amount, $type, $id, $extra_1 = '', $extra_2 = '') {
    super_insert_user_data('(' . $cloud_user_id . ', "payment", "' . db_escape(json_encode([$amount, $type, $id, $extra_1, $extra_2, time()])) . '")');
    membership_delete_cache($cloud_user_id);
    return true;
}

function cloud_invoice($cloud_user_id, $amount, $type, $unix_time) {
    require_once SB_CLOUD_PATH . '/account/vendor/fpdf/fpdf.php';
    require_once SB_CLOUD_PATH . '/account/vendor/fpdf/autoload.php';
    require_once SB_CLOUD_PATH . '/account/vendor/fpdf/Fpdi.php';
    $account = account_get_user_details();
    if (!$account) {
        return false;
    }
    $invoice_number = 'inv-' . $cloud_user_id . '-' . $unix_time;
    $file_name = $invoice_number . '.pdf';
    $path = SB_CLOUD_PATH . '/script/uploads/invoices/';
    $company_details = '';
    $lines = array_filter([$account['company_name'] ?? '', $account['company_address'] ?? '', trim(($account['company_postal_code'] ?? '') . ' ' . ($data['company_city'] ?? '')), $account['company_country'] ?? '', $account['company_tax_id'] ?? '']);
    if (!empty($lines)) {
        $company_details = PHP_EOL . implode(PHP_EOL, $lines);
    }
    if (!file_exists($path)) {
        mkdir($path, 0777, true);
    }
    $pdf = new \setasign\Fpdi\Fpdi();
    $pdf->AddPage();
    $pdf->setSourceFile(SB_CLOUD_PATH . '/account/media/invoice.pdf');
    $tpl = $pdf->importPage(1);
    $pdf->useTemplate($tpl, 0, 0, null, null);
    $pdf->SetTextColor(90, 90, 90);

    $pdf->SetXY(20, 29);
    $pdf->SetFont('Arial', 'B', 20);
    $pdf->Cell(1000, 1, sb_('Tax Invoice'));

    $pdf->SetXY(100, 27);
    $pdf->SetFont('Arial', '', 13);
    $pdf->Multicell(500, 7, sb_('Invoice date: ') . date('d-m-Y', $unix_time) . PHP_EOL . sb_('Invoice number: ') . strtoupper($invoice_number));

    $pdf->SetXY(20, 60);
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->Cell(50, 1, sb_('To'));
    $pdf->SetFont('Arial', '', 13);
    $pdf->SetXY(20, 70);
    $pdf->Multicell(168, 7, strip_tags(trim(iconv('UTF-8', 'ASCII//TRANSLIT', $account['first_name'] . ' ' . $account['last_name'] . PHP_EOL . $account['email'] . $company_details))));

    $pdf->SetXY(130, 60);
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->Cell(168, 1, sb_('Supplier'));
    $pdf->SetFont('Arial', '', 13);
    $pdf->SetXY(130, 70);
    $pdf->Multicell(168, 7, strip_tags(trim(iconv('UTF-8', 'ASCII//TRANSLIT', sb_isset(super_get_settings(), 'text_invoice')))));

    $pdf->SetXY(20, 150);
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->Cell(168, 1, sb_('Purchase details'));
    $pdf->SetFont('Arial', '', 13);
    $pdf->SetXY(20, 160);
    $pdf->Cell(168, 1, $type);

    $pdf->SetXY(20, 180);
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->Cell(168, 1, sb_('Amount'));
    $pdf->SetFont('Arial', '', 13);
    $pdf->SetXY(20, 190);
    $pdf->Cell(168, 1, strtoupper(membership_currency()) . ' ' . $amount);

    $pdf->Output($path . $file_name, 'F');
    return $file_name;
}

function sb_cloud_merge_settings($settings) {
    if (file_exists(SB_CLOUD_PATH . '/account/settings.json')) {
        $settings_cloud = json_decode(file_get_contents(SB_CLOUD_PATH . '/account/settings.json'), true);
        foreach ($settings_cloud as $key => $value) {
            if (isset($settings[$key])) {
                $settings[$key] = array_merge($settings[$key], $settings_cloud[$key]);
            }
        }
    }
    return $settings;
}

function sb_cloud_account_menu($tag = 'li') {
    $code = '';
    $switch_account = sb_get_setting('cloud-switch');
    $account = account();
    if (sb_isset($account, 'owner')) {
        $code = '<' . $tag . ' data-value="account">' . sb_('Account') . '</' . $tag . '>';
    }
    if (defined('SB_CLOUD_DOCS')) {
        $code .= ($tag == 'a' ? '' : '<li data-value="help">') . '<a href="' . SB_CLOUD_DOCS . '" target="_blank">' . sb_('Help') . '</a>' . ($tag == 'a' ? '' : '</li>');
    }
    if ($switch_account) {
        $count = count($switch_account);
        if ($count) {
            $code .= ($tag == 'a' ? '<a data-value="switch">' . sb_('Switch accounts') . '</a>' : '<li data-value="switch"><span>' . sb_('Switch accounts') . '</span>') . '<div class="sb-scroll-area">';
            for ($i = 0; $i < $count; $i++) {
                if ($switch_account[$i]['cloud-switch-email'] != $account['email']) {
                    $code .= '<a href="' . CLOUD_URL . '/account?login&login_email=' . $switch_account[$i]['cloud-switch-email'] . '&login_password=' . urlencode($switch_account[$i]['cloud-switch-password']) . '">' . $switch_account[$i]['cloud-switch-name'] . '</a>';
                }
            }
            $code .= '</div>' . ($tag == 'a' ? '' : '</li>');
        }
    }
    return $code;
}

function sb_cloud_save_settings($settings, $cloud_user_id = false) {
    if (!$cloud_user_id) {
        $cloud_user_id = get_active_account_id();
    }
    $customer_settings = json_decode(super_get_setting('customer-settings'), true);
    if (!$customer_settings) {
        $customer_settings = ['piping' => [], 'training' => []];
    }
    foreach ($customer_settings as $key => $value) {
        $customer_settings[$key] = array_filter($value, function ($element) use ($cloud_user_id) {
            return $element !== $cloud_user_id;
        });
    }
    if ($settings) {
        foreach ($customer_settings as $key => $value) {
            if (!empty($key == 'piping' ? sb_email_piping_is_active() : sb_isset($settings, ['open-ai', 0, 'open-ai-training-cron-job', 0]))) {
                array_push($customer_settings[$key], $cloud_user_id);
            }
        }
    }
    super_save_setting('customer-settings', $customer_settings);
}

?>