<?php
$host = 'localhost';
$dbname = 'famlii';
$username = 'root';
$password = '';
$mysqli = new mysqli($host, $username, $password, $dbname);
if ($mysqli->connect_error) { die('Connect Error: ' . $mysqli->connect_error); }
$mysqli->set_charset('utf8');

$result = $mysqli->query("SHOW COLUMNS FROM people2 LIKE 'full_name_en'");
if ($result->num_rows == 0) {
    if ($mysqli->query("ALTER TABLE people2 ADD COLUMN full_name_en VARCHAR(255) NULL DEFAULT NULL AFTER full_name")) {
        echo 'SUCCESS: Column full_name_en added successfully!';
    } else {
        echo 'ERROR: ' . $mysqli->error;
    }
} else {
    echo 'OK: Column full_name_en already exists.';
}
$mysqli->close();
echo ' Done.';
?>
