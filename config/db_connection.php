<?php
require_once __DIR__ . '/env.php';

$host = app_env_required('PORTAL_DB_HOST');
$dbname = app_env_required('PORTAL_DB_NAME');
$username = app_env_required('PORTAL_DB_USER');
$password = app_env_required('PORTAL_DB_PASSWORD', true);

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Could not connect to the database: " . $e->getMessage());
}
?>
