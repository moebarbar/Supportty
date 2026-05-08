<?php
// Supportty DB setup: installs SB core + SaaS schemas on first boot
// Called from entrypoint.sh before Apache. Safe to re-run (skips if tables exist).

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

function run_sql_file($conn, $file, $label) {
         if (!file_exists($file)) {
                      echo "[setup] $label: SQL file not found: $file\n";
                      return;
         }
         $sql = file_get_contents($file);
         // Split on semicolons but skip comments and blank lines
    $ok = 0; $err = 0; $skip = 0;
         foreach (explode(';', $sql) as $q) {
                      $q = trim($q);
                      // Skip empty statements and pure comment blocks
             if (empty($q) || preg_match('/^(--|#|\/\*)/', $q)) { $skip++; continue; }
                      // Only run CREATE TABLE / SET / ALTER statements
             if (preg_match('/^(CREATE|SET|ALTER|INSERT)/i', $q)) {
                              if ($conn->query($q) !== false) {
                                                   $ok++;
                              } else {
                                                   // Duplicate table is fine (1050), report others
                                  if ($conn->errno !== 1050) { $err++; echo "[setup] $label SQL error ({$conn->errno}): {$conn->error}\n"; }
                                  else { $skip++; }
                              }
             }
         }
         echo "[setup] $label: $ok statements executed" . ($err ? ", $err errors" : "") . ".\n";
}

if (!$envato) { echo "[setup] No ENVATO_PURCHASE_CODE, skipping SB core.\n"; }

// 1. Support Board core tables (sb_settings, sb_users, etc.)
$r = $conn->query("SHOW TABLES LIKE 'sb_settings'");
if ($r && $r->num_rows > 0) {
         echo "[setup] SB core schema already installed.\n";
} elseif ($envato) {
         echo "[setup] Installing SB core schema...\n";
         $sql = @file_get_contents('https://board.support/synch/updates.php?db=' . urlencode($envato) . '&domain=' . urlencode($url));
         if ($sql && strpos($sql, 'CREATE TABLE') !== false) {
                      $ok = 0; $err = 0;
                      foreach (explode(';', $sql) as $q) {
                                       $q = trim($q);
                                       if (strpos($q, 'CREATE TABLE') !== false) {
                                                            $conn->query($q) ? $ok++ : $err++;
                                       }
                      }
                      echo "[setup] SB core: $ok tables created" . ($err ? ", $err errors" : "") . ".\n";
         } else {
                      echo "[setup] SB core: no CREATE TABLE found in response.\n";
         }
}

// 2. SaaS account tables (settings, users, users_data, agents, etc.)
//    Installed from the bundled saas_schema.sql file (no external endpoint needed).
$r = $conn->query("SHOW TABLES LIKE 'settings'");
if ($r && $r->num_rows > 0) {
         echo "[setup] SaaS schema already installed.\n";
} else {
         echo "[setup] Installing SaaS schema from saas_schema.sql...\n";
         $schema_file = __DIR__ . '/saas_schema.sql';
         run_sql_file($conn, $schema_file, 'SaaS');
}

$conn->close();
echo "[setup] Done.\n";
