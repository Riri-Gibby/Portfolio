<?php

require_once __DIR__ . "/../session.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../db.php";
require_once __DIR__ . "/../media.php";

$id = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if ($id) {

    // remove the project's uploaded photos, videos and PDFs too
    media_delete_for_project($id);

    $stmt = $conn->prepare(
        "DELETE FROM projects WHERE id = ?"
    );

    if ($stmt) {

        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
}

header("Location: dashboard.php");

exit;

?>