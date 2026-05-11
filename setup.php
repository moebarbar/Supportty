<?php
// Supportty DB setup: installs SB core + SaaS schemas on first boot
// Called from entrypoint.sh before Apache. Safe to re-run (skips tables if exist).

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

// MySQL 8+/9+ defaults break Support Board queries (ANSI_QUOTES treats double-quoted
// strings as identifiers; ONLY_FULL_GROUP_BY rejects partial GROUP BY; STRICT_TRANS_TABLES
// rejects legacy datetime values). Clear sql_mode globally so all connections — including
// the per-tenant ones created during signup — inherit the relaxed mode.
if (@$conn->query("SET GLOBAL sql_mode = ''")) {
    echo "[setup] Cleared GLOBAL sql_mode.\n";
} else {
    echo "[setup] Could not SET GLOBAL sql_mode (need SUPER priv): {$conn->error}\n";
}

// One-shot reset: set SUPPORTTY_RESET=1 in Railway env vars to wipe ALL signup state
// (every tenant DB, every tenant MySQL user, the SaaS users table, every config_*.php
// file). After the reset runs once, set SUPPORTTY_RESET=0 (or delete the var) so the
// next deploy doesn't wipe again.
if (getenv('SUPPORTTY_RESET') === '1') {
    echo "[setup] SUPPORTTY_RESET=1 — wiping all signup state...\n";
    $r = $conn->query("SHOW DATABASES LIKE 'sb\\_%'");
    $dropped = 0;
    if ($r) {
        while ($row = $r->fetch_array()) {
            $db = $row[0];
            if (preg_match('/^sb_\d+$/', $db)) {
                $conn->query("DROP DATABASE `$db`");
                @$conn->query("DROP USER IF EXISTS '$db'@'%'");
                @$conn->query("DROP USER IF EXISTS '$db'@'localhost'");
                $dropped++;
            }
        }
    }
    echo "[setup]   dropped $dropped tenant DBs (and their users).\n";
    if (@$conn->query("DELETE FROM users")) {
        echo "[setup]   cleared SaaS users table.\n";
    }
    $configs = glob('/var/www/html/script/config/config_*.php') ?: [];
    foreach ($configs as $cfg) { @unlink($cfg); }
    echo "[setup]   deleted " . count($configs) . " per-tenant config files.\n";
    echo "[setup] Reset complete. Remove SUPPORTTY_RESET env var to stop wiping on future deploys.\n";
}

function run_sql_file($conn, $file, $label) {
             if (!file_exists($file)) { echo "[setup] $label: SQL file not found: $file\n"; return; }
             $raw = file_get_contents($file);
             // Remove comment lines (lines starting with -- or #)
    $lines = explode("\n", $raw);
             $clean_lines = array_filter($lines, function($l) {
                              $t = ltrim($l);
                              return !(strpos($t, '--') === 0 || strpos($t, '#') === 0);
             });
             $sql = implode("\n", $clean_lines);
             $ok = 0; $err = 0;
             foreach (explode(';', $sql) as $q) {
                              $q = trim($q);
                              if (empty($q)) { continue; }
                              if (preg_match('/^(CREATE|SET|ALTER|INSERT)/i', $q)) {
                                                   if ($conn->query($q) !== false) {
                                                                            $ok++;
                                                   } elseif ($conn->errno !== 1050) {
                                                                            echo "[setup] $label error ({$conn->errno}): {$conn->error}\n";
                                                                            $err++;
                                                   }
                              }
             }
             echo "[setup] $label: $ok statements OK" . ($err ? ", $err errors" : "") . ".\n";
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
                                                   if (strpos($q, 'CREATE TABLE') !== false) { $conn->query($q) ? $ok++ : $err++; }
                              }
                              echo "[setup] SB core: $ok tables created" . ($err ? ", $err errors" : "") . ".\n";
             } else {
                              echo "[setup] SB core: no CREATE TABLE found in response.\n";
             }
}

// 2. SaaS account tables: settings, users, users_data, agents, slack, messenger, whatsapp
// Installed from bundled saas_schema.sql - no external endpoint needed.
$r = $conn->query("SHOW TABLES LIKE 'settings'");
if ($r && $r->num_rows > 0) {
             echo "[setup] SaaS schema already installed.\n";
} else {
             echo "[setup] Installing SaaS schema from saas_schema.sql...\n";
             run_sql_file($conn, __DIR__ . '/saas_schema.sql', 'SaaS');
}

$conn->close();
echo "[setup] Done.\n";
