<?php

/*
 * ==========================================================
 * CLOUD CONFIGURATION FILE
 * ==========================================================
 *
 * All values are read from environment variables. Set them in
 * your deployment platform (Railway → Variables) — never commit
 * real values to this file. The original Envato template lives
 * at script/config_.php.
 *
 */

// Super admin
define('SUPER_EMAIL',    getenv('SUPER_EMAIL')    ?: '');
define('SUPER_PASSWORD', getenv('SUPER_PASSWORD') ?: '');

// Pusher (real-time)
define('CLOUD_PUSHER_ID',      (int) (getenv('CLOUD_PUSHER_ID') ?: 0));
define('CLOUD_PUSHER_KEY',     getenv('CLOUD_PUSHER_KEY')     ?: '');
define('CLOUD_PUSHER_SECRET',  getenv('CLOUD_PUSHER_SECRET')  ?: '');
define('CLOUD_PUSHER_CLUSTER', getenv('CLOUD_PUSHER_CLUSTER') ?: '');

// Twilio (SMS, optional)
define('CLOUD_TWILIO_SID',    getenv('CLOUD_TWILIO_SID')    ?: '');
define('CLOUD_TWILIO_TOKEN',  getenv('CLOUD_TWILIO_TOKEN')  ?: '');
define('CLOUD_TWILIO_SENDER', getenv('CLOUD_TWILIO_SENDER') ?: '');

// SMTP
define('CLOUD_SMTP_HOST',        getenv('CLOUD_SMTP_HOST')        ?: '');
define('CLOUD_SMTP_USERNAME',    getenv('CLOUD_SMTP_USERNAME')    ?: '');
define('CLOUD_SMTP_PASSWORD',    getenv('CLOUD_SMTP_PASSWORD')    ?: '');
define('CLOUD_SMTP_PORT',        (int) (getenv('CLOUD_SMTP_PORT') ?: 587));
define('CLOUD_SMTP_SENDER',      getenv('CLOUD_SMTP_SENDER')      ?: '');
define('CLOUD_SMTP_SENDER_NAME', getenv('CLOUD_SMTP_SENDER_NAME') ?: '');

// Cloud database (falls back to Railway's MySQL plugin vars)
define('CLOUD_DB_NAME',     getenv('CLOUD_DB_NAME')     ?: getenv('MYSQLDATABASE') ?: getenv('MYSQL_DATABASE') ?: '');
define('CLOUD_DB_USER',     getenv('CLOUD_DB_USER')     ?: getenv('MYSQLUSER')     ?: getenv('MYSQL_USER')     ?: '');
define('CLOUD_DB_PASSWORD', getenv('CLOUD_DB_PASSWORD') ?: getenv('MYSQLPASSWORD') ?: getenv('MYSQL_PASSWORD') ?: '');
define('CLOUD_DB_HOST',     getenv('CLOUD_DB_HOST')     ?: getenv('MYSQLHOST')     ?: getenv('MYSQL_HOST')     ?: 'localhost');

// SB core DB (same MySQL — required by script/include/functions.php)
define('SB_DB_HOST',     getenv('MYSQLHOST')     ?: 'localhost');
define('SB_DB_USER',     getenv('MYSQLUSER')     ?: '');
define('SB_DB_PASSWORD', getenv('MYSQLPASSWORD') ?: '');
define('SB_DB_NAME',     getenv('MYSQLDATABASE') ?: '');
define('SB_DB_PORT',     (int)(getenv('MYSQLPORT') ?: 3306));
define('SB_URL',         getenv('CLOUD_URL')     ?: '');

// Support Board core DB connection — used by sb_db_connect() in script/include/functions.php.
// Same MySQL as CLOUD_DB_* on Railway; kept as separate constants because the SB core
// references them by these specific names.
define('SB_DB_NAME',     getenv('SB_DB_NAME')     ?: CLOUD_DB_NAME);
define('SB_DB_USER',     getenv('SB_DB_USER')     ?: CLOUD_DB_USER);
define('SB_DB_PASSWORD', getenv('SB_DB_PASSWORD') ?: CLOUD_DB_PASSWORD);
define('SB_DB_HOST',     getenv('SB_DB_HOST')     ?: CLOUD_DB_HOST);
define('SB_DB_PORT',     (int) (getenv('SB_DB_PORT') ?: getenv('MYSQLPORT') ?: getenv('MYSQL_PORT') ?: 3306));

// Cloud public URL (auto-derived from Railway domain if not set explicitly)
define('CLOUD_URL', getenv('CLOUD_URL') ?: (getenv('RAILWAY_PUBLIC_DOMAIN') ? 'https://' . getenv('RAILWAY_PUBLIC_DOMAIN') : ''));

// SaaS / cloud mode
define('SB_CLOUD',                 filter_var(getenv('SB_CLOUD') ?: 'true', FILTER_VALIDATE_BOOLEAN));
define('SB_CLOUD_KEY',             getenv('SB_CLOUD_KEY')             ?: '');
define('SB_CLOUD_PATH',            getenv('SB_CLOUD_PATH')            ?: '/var/www/html');
define('SB_CLOUD_BRAND_LOGO',      getenv('SB_CLOUD_BRAND_LOGO')      ?: (CLOUD_URL ? CLOUD_URL . '/account/media/logo.svg' : ''));
define('SB_CLOUD_BRAND_LOGO_LINK', getenv('SB_CLOUD_BRAND_LOGO_LINK') ?: CLOUD_URL);
define('SB_CLOUD_BRAND_ICON',      getenv('SB_CLOUD_BRAND_ICON')      ?: (CLOUD_URL ? CLOUD_URL . '/account/media/icon.svg' : ''));
define('SB_CLOUD_BRAND_ICON_PNG',  getenv('SB_CLOUD_BRAND_ICON_PNG')  ?: (CLOUD_URL ? CLOUD_URL . '/account/media/icon.png' : ''));
define('SB_CLOUD_BRAND_NAME',      getenv('SB_CLOUD_BRAND_NAME')      ?: 'Supportty');
define('SB_CLOUD_MANIFEST_URL',    getenv('SB_CLOUD_MANIFEST_URL')    ?: (CLOUD_URL ? CLOUD_URL . '/manifest.json' : ''));
define('SB_CLOUD_MEMBERSHIP_TYPE', getenv('SB_CLOUD_MEMBERSHIP_TYPE') ?: 'messages-agents');

// Payment provider
define('PAYMENT_PROVIDER', getenv('PAYMENT_PROVIDER') ?: 'stripe');

// Stripe
define('STRIPE_SECRET_KEY', getenv('STRIPE_SECRET_KEY') ?: '');
define('STRIPE_PRODUCT_ID', getenv('STRIPE_PRODUCT_ID') ?: '');
define('STRIPE_CURRENCY',   getenv('STRIPE_CURRENCY')   ?: 'usd');

// OneSignal
define('ONESIGNAL_APP_ID',  getenv('ONESIGNAL_APP_ID')  ?: '');
define('ONESIGNAL_API_KEY', getenv('ONESIGNAL_API_KEY') ?: '');

// Envato
define('ENVATO_PURCHASE_CODE', getenv('ENVATO_PURCHASE_CODE') ?: '');

// Open Exchange Rates
define('OPEN_EXCHANGE_RATE_APP_ID', getenv('OPEN_EXCHANGE_RATE_APP_ID') ?: '');

/*
 * ----------------------------------------------------------
 * OPTIONAL INTEGRATIONS
 * ----------------------------------------------------------
 *
 * Defined only when the env var is set, so `defined()` checks
 * elsewhere in the codebase keep their original meaning.
 *
 */

// Google
if (getenv('GOOGLE_CLIENT_ID'))           define('GOOGLE_CLIENT_ID',           getenv('GOOGLE_CLIENT_ID'));
if (getenv('GOOGLE_CLIENT_SECRET'))       define('GOOGLE_CLIENT_SECRET',       getenv('GOOGLE_CLIENT_SECRET'));
if (getenv('GOOGLE_REFRESH_TOKEN'))       define('GOOGLE_REFRESH_TOKEN',       getenv('GOOGLE_REFRESH_TOKEN'));
if (getenv('GOOGLE_LOGIN_CLIENT_ID'))     define('GOOGLE_LOGIN_CLIENT_ID',     getenv('GOOGLE_LOGIN_CLIENT_ID'));
if (getenv('GOOGLE_LOGIN_CLIENT_SECRET')) define('GOOGLE_LOGIN_CLIENT_SECRET', getenv('GOOGLE_LOGIN_CLIENT_SECRET'));

// WhatsApp
if (getenv('WHATSAPP_APP_ID'))           define('WHATSAPP_APP_ID',           getenv('WHATSAPP_APP_ID'));
if (getenv('WHATSAPP_APP_SECRET'))       define('WHATSAPP_APP_SECRET',       getenv('WHATSAPP_APP_SECRET'));
if (getenv('WHATSAPP_CONFIGURATION_ID')) define('WHATSAPP_CONFIGURATION_ID', getenv('WHATSAPP_CONFIGURATION_ID'));
if (getenv('WHATSAPP_APP_TOKEN'))        define('WHATSAPP_APP_TOKEN',        getenv('WHATSAPP_APP_TOKEN'));
if (getenv('WHATSAPP_VERIFY_TOKEN'))     define('WHATSAPP_VERIFY_TOKEN',     getenv('WHATSAPP_VERIFY_TOKEN'));

// Messenger
if (getenv('MESSENGER_APP_ID'))           define('MESSENGER_APP_ID',           getenv('MESSENGER_APP_ID'));
if (getenv('MESSENGER_APP_SECRET'))       define('MESSENGER_APP_SECRET',       getenv('MESSENGER_APP_SECRET'));
if (getenv('MESSENGER_CONFIGURATION_ID')) define('MESSENGER_CONFIGURATION_ID', getenv('MESSENGER_CONFIGURATION_ID'));
if (getenv('MESSENGER_VERIFY_TOKEN'))     define('MESSENGER_VERIFY_TOKEN',     getenv('MESSENGER_VERIFY_TOKEN'));
if (getenv('MESSENGER_APP_TOKEN'))        define('MESSENGER_APP_TOKEN',        getenv('MESSENGER_APP_TOKEN'));

// OpenAI
if (getenv('OPEN_AI_KEY')) define('OPEN_AI_KEY', getenv('OPEN_AI_KEY'));

// Razorpay
if (getenv('RAZORPAY_KEY_ID'))     define('RAZORPAY_KEY_ID',     getenv('RAZORPAY_KEY_ID'));
if (getenv('RAZORPAY_KEY_SECRET')) define('RAZORPAY_KEY_SECRET', getenv('RAZORPAY_KEY_SECRET'));
if (getenv('RAZORPAY_CURRENCY'))   define('RAZORPAY_CURRENCY',   getenv('RAZORPAY_CURRENCY'));

// Rapyd
if (getenv('RAPYD_ACCESS_KEY'))          define('RAPYD_ACCESS_KEY', getenv('RAPYD_ACCESS_KEY'));
if (getenv('RAPYD_SECRET_KEY'))          define('RAPYD_SECRET_KEY', getenv('RAPYD_SECRET_KEY'));
if (getenv('RAPYD_TEST_MODE') !== false) define('RAPYD_TEST_MODE',  filter_var(getenv('RAPYD_TEST_MODE'), FILTER_VALIDATE_BOOLEAN));
if (getenv('RAPYD_CURRENCY'))            define('RAPYD_CURRENCY',   getenv('RAPYD_CURRENCY'));
if (getenv('RAPYD_COUNTRY'))             define('RAPYD_COUNTRY',    getenv('RAPYD_COUNTRY'));

// Verifone (2Checkout)
if (getenv('VERIFONE_SECRET_WORD')) define('VERIFONE_SECRET_WORD', getenv('VERIFONE_SECRET_WORD'));
if (getenv('VERIFONE_SECRET_KEY'))  define('VERIFONE_SECRET_KEY',  getenv('VERIFONE_SECRET_KEY'));
if (getenv('VERIFONE_MERCHANT_ID')) define('VERIFONE_MERCHANT_ID', getenv('VERIFONE_MERCHANT_ID'));
if (getenv('VERIFONE_CURRENCY'))    define('VERIFONE_CURRENCY',    getenv('VERIFONE_CURRENCY'));

// Yoomoney
if (getenv('YOOMONEY_SHOP_ID'))    define('YOOMONEY_SHOP_ID',    getenv('YOOMONEY_SHOP_ID'));
if (getenv('YOOMONEY_KEY_SECRET')) define('YOOMONEY_KEY_SECRET', getenv('YOOMONEY_KEY_SECRET'));
if (getenv('YOOMONEY_CURRENCY'))   define('YOOMONEY_CURRENCY',   getenv('YOOMONEY_CURRENCY'));

// Manual payment
if (getenv('PAYMENT_MANUAL_LINK'))     define('PAYMENT_MANUAL_LINK',     getenv('PAYMENT_MANUAL_LINK'));
if (getenv('PAYMENT_MANUAL_CURRENCY')) define('PAYMENT_MANUAL_CURRENCY', getenv('PAYMENT_MANUAL_CURRENCY'));

// Shopify
if (getenv('SHOPIFY_CLIENT_ID'))     define('SHOPIFY_CLIENT_ID',     getenv('SHOPIFY_CLIENT_ID'));
if (getenv('SHOPIFY_CLIENT_SECRET')) define('SHOPIFY_CLIENT_SECRET', getenv('SHOPIFY_CLIENT_SECRET'));
if (getenv('SHOPIFY_APP_ID'))        define('SHOPIFY_APP_ID',        getenv('SHOPIFY_APP_ID'));
// SHOPIFY_PLANS is an array — set in code if needed:
// define('SHOPIFY_PLANS', [['100 messages', ''], ['5000 messages', 'SB_PLAN_ID'], ...]);

// Misc cloud options
if (getenv('STRIPE_PRODUCT_ID_WHITE_LABEL')) define('STRIPE_PRODUCT_ID_WHITE_LABEL', getenv('STRIPE_PRODUCT_ID_WHITE_LABEL'));
if (getenv('CLOUD_IP'))                       define('CLOUD_IP', getenv('CLOUD_IP'));
if (getenv('SB_CLOUD_DEFAULT_LANGUAGE_CODE')) define('SB_CLOUD_DEFAULT_LANGUAGE_CODE', getenv('SB_CLOUD_DEFAULT_LANGUAGE_CODE'));
if (getenv('SB_CLOUD_DEFAULT_RTL') !== false) define('SB_CLOUD_DEFAULT_RTL', filter_var(getenv('SB_CLOUD_DEFAULT_RTL'), FILTER_VALIDATE_BOOLEAN));
if (getenv('DIRECT_CHAT_URL')) define('DIRECT_CHAT_URL', getenv('DIRECT_CHAT_URL'));
if (getenv('WEBSITE_URL'))     define('WEBSITE_URL',     getenv('WEBSITE_URL'));
if (getenv('ARTICLES_URL'))    define('ARTICLES_URL',    getenv('ARTICLES_URL'));
if (getenv('SB_CLOUD_DOCS'))   define('SB_CLOUD_DOCS',   getenv('SB_CLOUD_DOCS'));
if (getenv('SUPER_BRANDING') !== false) define('SUPER_BRANDING', filter_var(getenv('SUPER_BRANDING'), FILTER_VALIDATE_BOOLEAN));

// AWS S3 (assembled from individual env vars)
if (getenv('AWS_S3_ACCESS_KEY')) {
    define('SB_CLOUD_AWS_S3', [
        'amazon-s3-access-key'         => getenv('AWS_S3_ACCESS_KEY')         ?: '',
        'amazon-s3-secret-access-key'  => getenv('AWS_S3_SECRET_ACCESS_KEY')  ?: '',
        'amazon-s3-bucket-name'        => getenv('AWS_S3_BUCKET_NAME')        ?: '',
        'amazon-s3-backup-bucket-name' => getenv('AWS_S3_BACKUP_BUCKET_NAME') ?: '',
        'amazon-s3-region'             => getenv('AWS_S3_REGION')             ?: '',
    ]);
}

// SB_CLOUD_EMAIL_BODY_AGENTS / SB_CLOUD_EMAIL_BODY_USERS / CLOUD_ADDONS
// are large HTML strings or arrays — set them directly in code if you
// need to override the defaults.
