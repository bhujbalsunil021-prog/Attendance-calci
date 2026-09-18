<?php
/**
 * Database connection.
 * Change these four values if your MySQL setup is different.
 * (Default XAMPP: user "root", empty password.)
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'attendance_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Minimum attendance percentage the college requires.
define('MIN_ATTENDANCE', 75);

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('Cannot connect to the database. Start MySQL in XAMPP and import database/schema.sql. (' . $e->getMessage() . ')');
}
