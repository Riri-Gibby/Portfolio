<?php

require_once "db.php"; 

$id = $_GET["id"] ?? 0;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST["id"];
    $username = $_POST["username"];
    $email = $_POST["email"];

    $stmt = $conn->prepare(
        "UPDATE user
        SET username = ?, email = ?
        WHERE id = ?"
    );

    if (!$stmt) {
        die("Database Error (Update failed): " . $conn->error);
    }

    $stmt->bind_param("ssi", $username, $email, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: index.php");
    exit(); 
}


$stmt = $conn->prepare(
    "SELECT * FROM user
    WHERE id = ?"
);


if (!$stmt) {
    die("Database Error (Select failed): " . $conn->error);
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();


if (!$user) {
    die("User not found or invalid ID.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>Edit User</h1>
            <form method="POST" action="edit.php">
             
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($user['id']); ?>">
                
               
                <label>Username</label>
                <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>

               
                <label>Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>

             
                <button type="submit" class="btn add">
                    Update User
                </button>

              
                <a href="index.php" class="btn cancel">
                    Cancel
                </a>
            </form>
        </div>
    </div>
</body>
</html>
