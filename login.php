<?php

require_once __DIR__ . "/session.php";
require_once __DIR__ . "/theme.php";

require_once "db.php";

if (isset($_SESSION["admin_id"])) {
    header("Location: admin/dashboard.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {

        $error = "Please enter your username and password.";

    } else {

        $stmt = $conn->prepare(
            "SELECT id, username, password
             FROM admins
             WHERE username = ?"
        );

        if ($stmt) {

            $stmt->bind_param("s", $username);

            $stmt->execute();

            $result = $stmt->get_result();

            $admin = $result->fetch_assoc();

            if ($admin && (password_verify($password, $admin["password"]) || hash_equals($admin["password"], $password))) {

                if (!password_get_info($admin["password"])["algo"]) {
                    $securePassword = password_hash($password, PASSWORD_DEFAULT);
                    $upgrade = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
                    $upgrade->bind_param("si", $securePassword, $admin["id"]);
                    $upgrade->execute();
                    $upgrade->close();
                }

                session_regenerate_id(true);

                $_SESSION["admin_id"] = $admin["id"];
                $_SESSION["admin_username"] =
                    $admin["username"];

                header("Location: admin/dashboard.php");
                exit;

            } else {

                $error = "Invalid username or password.";

            }

            $stmt->close();

        } else {

            $error = "Unable to process your request.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en"<?= theme_html_attrs() ?>>

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Admin Logins</title>

    <link rel="stylesheet" href="style.css">

    <?= theme_style_tag() ?>

</head>

<body class="login-page">

<div class="login-box">

    <h1>Admin Login</h1>

    <p>
        Login to manage your portfolio.
    </p>


    <?php if ($error !== ""): ?>

        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <form method="POST">

        <label>Username</label>

        <input
            type="text"
            name="username"
            placeholder="Username"
            required
        >


        <label>Password</label>

        <input
            type="password"
            name="password"
            placeholder="Password"
            required
        >


        <button
            type="submit"
            class="btn"
        >
            Login
        </button>

    </form>


    <a href="index.php" class="back-link">
        ← Back to Portfolio
    </a>

</div>

</body>
</html>