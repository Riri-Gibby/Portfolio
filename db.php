<?php

$host = "127.0.0.1";
$username = "root";
$password = "";
$database = "portfolio";

$conn = new mysqli(
    $host,
    $username,
    $password,
    $database
);

if ($conn->connect_error) {
    die("Unable to connect to the database. Please try again later.");
}

$conn->set_charset("utf8mb4");

?>