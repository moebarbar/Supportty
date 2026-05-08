<?php

/*
 * ==========================================================
 * INITIAL CONFIGURATION FILE
 * ==========================================================
 *
 * Cloud configuration file for Support Board
 *
 */

define('SUPER_EMAIL', 'AAA');
define('SUPER_PASSWORD', 'AAA');

define('CLOUD_PUSHER_ID', 0000);
define('CLOUD_PUSHER_KEY', 'AAA');
define('CLOUD_PUSHER_SECRET', 'AAA');
define('CLOUD_PUSHER_CLUSTER', 'AAA');

define('CLOUD_TWILIO_SID', '');
define('CLOUD_TWILIO_TOKEN', '');
define('CLOUD_TWILIO_SENDER', '');

define('CLOUD_SMTP_HOST', 'AAA');
define('CLOUD_SMTP_USERNAME', 'AAA');
define('CLOUD_SMTP_PASSWORD', 'AAA');
define('CLOUD_SMTP_PORT', 587);
define('CLOUD_SMTP_SENDER', 'AAA');
define('CLOUD_SMTP_SENDER_NAME', 'AAA');

define('CLOUD_DB_NAME', 'AAA');
define('CLOUD_DB_USER', 'AAA');
define('CLOUD_DB_PASSWORD', 'AAA');
define('CLOUD_DB_HOST', 'localhost');
define('CLOUD_URL', 'https://DOMAIN');

define('SB_CLOUD', true);
define('SB_CLOUD_KEY', 'AAA');
define('SB_CLOUD_PATH', '/var/www/vhosts/DOMAIN/httpdocs');
define('SB_CLOUD_BRAND_LOGO', 'https://DOMAIN/custom/logo.svg');
define('SB_CLOUD_BRAND_LOGO_LINK', 'https://DOMAIN');
define('SB_CLOUD_BRAND_ICON', 'https://DOMAIN/custom/icon.svg');
define('SB_CLOUD_BRAND_ICON_PNG', 'https://DOMAIN/custom/icon.png');
define('SB_CLOUD_BRAND_NAME', 'BBB');
define('SB_CLOUD_MANIFEST_URL', 'https://DOMAIN/manifest.json');
define('SB_CLOUD_MEMBERSHIP_TYPE', 'messages-agents'); //messages, users, agents

define('PAYMENT_PROVIDER', 'stripe'); //rapyd, verifone, yoomoney, razorpay, manual

define('STRIPE_SECRET_KEY', 'XXX');
define('STRIPE_PRODUCT_ID', 'XXX');
define('STRIPE_CURRENCY', 'usd');

define('ONESIGNAL_APP_ID', 'AAA');
define('ONESIGNAL_API_KEY', 'AAA');

define('ENVATO_PURCHASE_CODE' ,'AAA');

define('OPEN_EXCHANGE_RATE_APP_ID', 'AAA');

//define('GOOGLE_CLIENT_ID', '');
//define('GOOGLE_CLIENT_SECRET', '');
//define('GOOGLE_REFRESH_TOKEN', '');

//define('GOOGLE_LOGIN_CLIENT_ID', '');
//define('GOOGLE_LOGIN_CLIENT_SECRET', '');

//define('WHATSAPP_APP_ID', '');
//define('WHATSAPP_APP_SECRET', '');
//define('WHATSAPP_CONFIGURATION_ID', '');
//define('WHATSAPP_APP_TOKEN', '');
//define('WHATSAPP_VERIFY_TOKEN', '');

//define('MESSENGER_APP_ID', '');
//define('MESSENGER_APP_SECRET', '');
//define('MESSENGER_CONFIGURATION_ID', '');
//define('MESSENGER_VERIFY_TOKEN', '');
//define('MESSENGER_APP_TOKEN', '');

//define('OPEN_AI_KEY', '');

//define('RAZORPAY_KEY_ID', '');
//define('RAZORPAY_KEY_SECRET', '');
//define('RAZORPAY_CURRENCY', '');

//define('RAPYD_ACCESS_KEY', '');
//define('RAPYD_SECRET_KEY', '');
//define('RAPYD_TEST_MODE', );
//define('RAPYD_CURRENCY', '');
//define('RAPYD_COUNTRY', '');

//define('VERIFONE_SECRET_WORD', '');
//define('VERIFONE_SECRET_KEY', '');
//define('VERIFONE_MERCHANT_ID', '');
//define('VERIFONE_CURRENCY', '');

//define('YOOMONEY_SHOP_ID', '');
//define('YOOMONEY_KEY_SECRET', '');
//define('YOOMONEY_CURRENCY', '');

//define('PAYMENT_MANUAL_LINK', '');
//define('PAYMENT_MANUAL_CURRENCY', '');

//define('SHOPIFY_CLIENT_ID', '');
//define('SHOPIFY_CLIENT_SECRET', '');
//define('SHOPIFY_APP_ID', '');
//define('SHOPIFY_PLANS', [['100 messages', ''], ['5000 messages', 'SB_PLAN_ID'], ['50000 messages', 'SB_PLAN_ID'], ['100000 messages', 'SB_PLAN_ID']]);

//define('SB_CLOUD_AWS_S3', ['amazon-s3-access-key' => '', 'amazon-s3-secret-access-key' => '', 'amazon-s3-bucket-name' => '', 'amazon-s3-backup-bucket-name' => '', 'amazon-s3-region' => '']);
//define('SB_CLOUD_DOCS', '');
//define('STRIPE_PRODUCT_ID_WHITE_LABEL', '');
//define('CLOUD_IP', '');
//define('SB_CLOUD_DEFAULT_LANGUAGE_CODE', 'zh');
//define('SB_CLOUD_DEFAULT_RTL', true);
//define('DIRECT_CHAT_URL', '');
//define('WEBSITE_URL', '');
//define('ARTICLES_URL', '');
//define('SUPER_BRANDING', true);
//define('SB_CLOUD_EMAIL_BODY_AGENTS', '<link href="https://fonts.googleapis.com/css?family=Roboto:400,500,900" rel="stylesheet" type="text/css"><div style="font-family:\'Roboto\',sans-serif;text-align:left;max-width:560px;margin:auto;"><div style="display:none>{message}</div><a href="https://board.support"><img style="width:200px;" src="https://board.support/media/logo.png" alt="logo"></a></div><div style="background:#FFF;padding:30px;border-radius:6px;border:1px solid rgb(218, 222, 223);margin:30px auto;max-width:500px"><p style="font-family:\'Roboto\',sans-serif;text-align:left;letter-spacing:.3px;font-size:15px;line-height:28px;color:#486d85;margin:0;">{message}{attachments}<table style="margin-top:30px;"><tr><td><img style="width:35px;border-radius:50%" src="{sender_profile_image}"></td><td><b style="font-size:13px;color:rgb(128,128,128);padding-left:5px;">{sender_name}</b></td></tr></table><a href="{conversation_link}" style="font-family:\'Roboto\',sans-serif;background-color: #009bfc;color: #FFF;font-size: 14px;line-height: 36px;letter-spacing: 0.3px;font-weight: 500;border-radius: 6px;text-decoration: none;height: 35px;display: inline-block;padding: 0 25px;margin-top: 30px;">Click here to reply</a></p></div><div style="color:#444444;font-size:12px;line-height:20px;padding:0;text-align:left;"><p style="font-family:\'Roboto\',sans-serif;font-size:12px; line-height:20px;color:#a0abb2;max-width:560px;margin: 30px auto">This email was sent to you by Support Board. By using our services, you agree to our <a href="https://board.support/privacy" target="_blank" style="color:#a0abb2;text-decoration:none;">Privacy Policy</a>.<br />&copy; Schiocco LTD. All rights reserved.</p></div></div>');
//define('SB_CLOUD_EMAIL_BODY_USERS', '<link href="https://fonts.googleapis.com/css?family=Roboto:400,500,900" rel="stylesheet" type="text/css"><div style="font-family:\'Roboto\',sans-serif;text-align:left;max-width:560px;margin:auto;"><div style="display:none>{message}</div><a href="https://board.support"><img style="width:200px;" src="https://board.support/media/logo.png" alt="logo"></a></div><div style="background:#FFF;padding:30px;border-radius:6px;border:1px solid rgb(218, 222, 223);margin:30px auto;max-width:500px"><p style="font-family:\'Roboto\',sans-serif;text-align:left;letter-spacing:.3px;font-size:15px;line-height:28px;color:#486d85;margin:0;">{message}{attachments}<table style="margin-top:30px;"><tr><td><img style="width:35px;border-radius:50%" src="{sender_profile_image}"></td><td><b style="font-size:13px;color:rgb(128,128,128);padding-left:5px;">{sender_name}</b> from Support Board</td></tr></table><a href="{conversation_link}" style="font-family:\'Roboto\',sans-serif;background-color: #009bfc;color: #FFF;font-size: 14px;line-height: 36px;letter-spacing: 0.3px;font-weight: 500;border-radius: 6px;text-decoration: none;height: 35px;display: inline-block;padding: 0 25px;margin-top: 30px;">Click here to reply</a></p></div><div style="color:#444444;font-size:12px;line-height:20px;padding:0;text-align:left;"><p style="font-family:\'Roboto\',sans-serif;font-size:12px; line-height:20px;color:#a0abb2;max-width:560px;margin: 30px auto">This email was sent to you by Support Board. By using our services, you agree to our <a href="https://board.support/privacy" target="_blank" style="color:#a0abb2;text-decoration:none;">Privacy Policy</a>.<br />&copy; Schiocco LTD. All rights reserved.</p></div></div>');
//define('CLOUD_ADDONS', [['title' => 'AAAA', 'description' => 'AAA', 'price' => 5.00], ['title' => 'BBBB', 'description' => 'AAA', 'price' => 9.15]]);

?>