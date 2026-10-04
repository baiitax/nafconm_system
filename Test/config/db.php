<?php
require_once __DIR__ . '/../../config/env.php';

define('DB_SERVER', app_env_required('TEST_DB_HOST'));
define('DB_USERNAME', app_env_required('TEST_DB_USER'));
define('DB_PASSWORD', app_env_required('TEST_DB_PASSWORD', true));
define('DB_NAME', app_env_required('TEST_DB_NAME'));
 
/* Attempt to connect to MySQL database */
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
 
// Check connection
if($conn->connect_error){
    die("ERROR: Could not connect. " . $conn->connect_error);
}
?>
