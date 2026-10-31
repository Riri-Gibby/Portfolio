<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

include "db.php";

$id = $_GET["id"] ?? 0;

try {
    $stmt = $conn->prepare(
        "DELETE FROM user WHERE id = ?"
    );

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    header("Location: index.php");
    exit();

} catch (mysqli_sql_exception $e) {
    echo "<h1>Database Error Encountered!</h1>";
    echo "<p><strong>Message:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . " on line " . $e->getLine() . "</p>";
    echo "<br><a href='index.php'>Go Back</a>";
    exit();
}
?>
