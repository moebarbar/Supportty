<?php
/**
 * One-time database setup script.
  * Called from Docker CMD before Apache starts.
   * Downloads and installs the Support Board schema using the Envato purchase code.
    * Safe to run repeatedly -- only installs if tables are missing.
     */

     define('SB_PATH', '/var/www/html/script');

     // Read DB credentials from env vars (Railway MySQL plugin injects these)
     $host     = getenv('MYSQLHOST')     ?: getenv('MYSQL_HOST')     ?: 'localhost';
     $user     = getenv('MYSQLUSER')     ?: getenv('MYSQL_USER')     ?: '';
     $password = getenv('MYSQLPASSWORD') ?: getenv('MYSQL_PASSWORD') ?: '';
     $dbname   = getenv('MYSQLDATABASE') ?: getenv('MYSQL_DATABASE') ?: '';
     $port     = (int)(getenv('MYSQLPORT') ?: getenv('MYSQL_PORT') ?: 3306);
     $envato   = getenv('ENVATO_PURCHASE_CODE') ?: '';
     $url      = getenv('CLOUD_URL') ?: (getenv('RAILWAY_PUBLIC_DOMAIN') ? 'https://' . getenv('RAILWAY_PUBLIC_DOMAIN') : '');

     if (!$host || !$user || !$dbname) {
         echo "[setup.php] Missing DB env vars, skipping setup.\n";
             exit(0);
             }

             // Connect
             $conn = new mysqli($host, $user, $password, $dbname, $port ?: null);
             if ($conn->connect_error) {
                 echo "[setup.php] DB connect error: " . $conn->connect_error . "\n";
                     exit(1);
                     }
                     $conn->set_charset('utf8mb4');

                     // Check if already installed
                     $result = $conn->query("SHOW TABLES LIKE 'sb_settings'");
                     if ($result && $result->num_rows > 0) {
                         echo "[setup.php] Database already initialised (sb_settings exists). Skipping.\n";
                             $conn->close();
                                 exit(0);
                                 }

                                 echo "[setup.php] sb_settings table not found. Fetching schema...\n";

                                 if (!$envato) {
                                     echo "[setup.php] ENVATO_PURCHASE_CODE not set. Cannot download schema. Skipping.\n";
                                         $conn->close();
                                             exit(0);
                                             }

                                             // Fetch the SQL schema from board.support (same URL used by sb_installation_db)
                                             $schema_url = 'https://board.support/synch/updates.php?db=' . urlencode($envato) . '&domain=' . urlencode($url);
                                             echo "[setup.php] Fetching schema from board.support...\n";
                                             $sql_database = file_get_contents($schema_url);

                                             if (empty($sql_database) || strpos($sql_database, 'CREATE TABLE') === false) {
                                                 echo "[setup.php] Failed to fetch schema or invalid response: " . substr($sql_database, 0, 200) . "\n";
                                                     $conn->close();
                                                         exit(1);
                                                         }

                                                         echo "[setup.php] Schema fetched. Running CREATE TABLE statements...\n";
                                                         $statements = explode(';', $sql_database);
                                                         $errors = 0;
                                                         foreach ($statements as $query) {
                                                             $query = trim($query);
                                                                 if (strpos($query, 'CREATE TABLE') !== false) {
                                                                         if ($conn->query($query) !== true) {
                                                                                     echo "[setup.php] Error: " . $conn->error . "\n";
                                                                                                 $errors++;
                                                                                                         }
                                                                                                             }
                                                                                                             }
                                                                                                             
                                                                                                             if ($errors === 0) {
                                                                                                                 echo "[setup.php] Schema installed successfully.\n";
                                                                                                                 } else {
                                                                                                                     echo "[setup.php] Schema installed with $errors error(s).\n";
                                                                                                                     }
                                                                                                                     
                                                                                                                     $conn->close();
                                                                                                                     exit(0);
