<?php
$servername = "mysql";
$username = "root";
$password = "password";

try {
    $conn = new mysqli($servername, $username, $password, "nora_flora");
    if ($conn->connect_error) {
        error_log($conn->connect_error);
        exit($conn->connect_error);
    }
} catch (Exception $e) {
    error_log($e);
    exit($e->getMessage());
}

return $conn;
