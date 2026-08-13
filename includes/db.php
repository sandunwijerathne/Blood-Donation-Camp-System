<?php
/**
 * Database Connection
 * 
 * PDO singleton with error handling and UTF-8 support.
 */

require_once __DIR__ . '/../config.php';

function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '" . DB_CHARSET . "'"
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (APP_DEBUG) {
                die('Database Connection Error: ' . $e->getMessage());
            } else {
                die('Database connection failed. Please contact the administrator.');
            }
        }
    }

    return $pdo;
}
