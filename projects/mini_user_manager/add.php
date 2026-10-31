<?php
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    include "db.php";

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $username = $_POST["username"];
        $email = $_POST["email"];

        $stmt = $conn->prepare(
            "INSERT INTO user (username, email) VALUES (?, ?)"
        );

        $stmt->bind_param("ss", $username, $email);

        $stmt->execute();
        $stmt->close();
    }
    header("Location: index.php");
    exit();
?>
