<?php
// Supportty DB setup: installs SB core + SaaS schemas on first boot
// Called from Docker CMD before Apache. Safe to re-run (skips if tables exist).

$host     = getenv('MYSQLHOST')     ?: getenv('MYSQL_HOST')     ?: 'localhost';
$user     = getenv('MYSQLUSER')     ?: getenv('MYSQL_USER')     ?: '';
$password = getenv('MYSQLPASSWORD') ?: getenv('MYSQL_PASSWORD') ?: '';
$dbname   = getenv('MYSQLDATABASE') ?: getenv('MYSQL_DATABASE') ?: '';
$port     = (int)(getenv('MYSQLPORT') ?: getenv('MYSQL_PORT') ?: 3306);
$envato   = getenv('ENVATO_PURCHASE_CODE') ?: '';
$url      = getenv('CLOUD_URL') ?: (getenv('RAILWAY_PUBLIC_DOMAIN') ? 'https://' . getenv('RAILWAY_PUBLIC_DOMAIN') : '');

if (!$host || !$user || !$dbname) { echo "[setup] Missing DB env vars.\n"; exit(0); }

$conn = new mysqli($host, $user, $password, $dbname, $port ?: null);
if ($conn->connect_error) { echo "[setup] Connect error: " . $conn->connect_error . "\n"; exit(1); }
$conn->set_charset('utf8mb4');

function install_schema($conn, $sql, $label) {
     if (empty($sql) || strpos($sql, 'CREATE TABLE') === false) {
              echo "[setup] $label: no CREATE TABLE found in response.\n";
              return;
     }
     $ok = 0; $err = 0;
     foreach (explode(';', $sql) as $q) {
              $q = trim($q);
              if (strpos($q, 'CREATE TABLE') !== false) {
                           $conn->query($q) ? $ok++ : $err++;
              }
     }
     echo "[setup] $label: $ok tables created" . ($err ? ", $err errors" : "") . ".\n";
}

if (!$envato) { echo "[setup] No ENVATO_PURCHASE_CODE, skipping.\n"; $conn->close(); exit(0); }

// 1. Support Board core tables (sb_settings, sb_users, etc.)
$r = $conn->query("SHOW TABLES LIKE 'sb_settings'");
if ($r && $r->num_rows > 0) {
     echo "[setup] SB core schema already installed.\n";
} else {
     echo "[setup] Installing SB core schema...\n";
     $sql = @file_get_contents('https://board.support/synch/updates.php?db=' . urlencode($envato) . '&domain=' . urlencode($url));
     install_schema($conn, $sql, 'SB core');
}

// 2. SaaS account tables (settings, agents, etc.)
$r = $conn->query("SHOW TABLES LIKE 'settings'");
if ($r && $r->num_rows > 0) {
     echo "[setup] SaaS schema already installed.\n";
} else {
     echo "[setup] Installing SaaS schema...\n";
     $sql = @file_get_contents('https://board.support/synch/saas.php');
     install_schema($conn, $sql, 'SaaS');
}

$conn->close();
echo "[setup] Done.\n";
