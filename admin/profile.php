<?php

require_once __DIR__ . "/../session.php";
require_once __DIR__ . "/../theme.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../db.php";

$admin_id = (int) $_SESSION["admin_id"];
$error = "";
$success = "";

social_ensure_columns();

// Load the current profile
$stmt = $conn->prepare(
    "SELECT display_name, headline, bio, facebook_url, instagram_url, discord_url, gmail_email
     FROM admins WHERE id = ?"
);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Same defaults the portfolio homepage uses until you save your own
$display_name = ($row["display_name"] ?? "") ?: "Gab";
$headline     = ($row["headline"] ?? "") ?: "Student";
$bio          = ($row["bio"] ?? "") ?: "Welcome to my portfolio. Here you can view my projects, skills, and contact information.";

$social = [];

foreach (social_networks() as $key => $network) {
    $social[$key] = (string) ($row[$network["column"]] ?? "");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $display_name = trim($_POST["display_name"] ?? "");
    $headline     = trim($_POST["headline"] ?? "");
    $bio          = trim($_POST["bio"] ?? "");

    // social links: empty is fine, otherwise it must be a valid link
    $socialError = "";

    foreach (social_networks() as $key => $network) {

        $typed = trim($_POST["social_" . $key] ?? "");
        $clean = social_normalize($typed, $network["type"]);

        if ($clean === null) {
            $socialError = $network["type"] === "email"
                ? "Please enter a valid " . $network["label"] . " address."
                : "Please enter a valid " . $network["label"] . " link.";
            $social[$key] = $typed;
        } else {
            $social[$key] = $clean;
        }
    }

    if ($display_name === "") {

        $error = "Please enter your name.";

    } elseif (
        mb_strlen($display_name) > 100 ||
        mb_strlen($headline) > 100
    ) {

        $error = "Name and title must be 100 characters or less.";

    } elseif (mb_strlen($bio) > 1000) {

        $error = "Your bio must be 1000 characters or less.";

    } elseif ($socialError !== "") {

        $error = $socialError;

    } else {

        $stmt = $conn->prepare(
            "UPDATE admins
             SET display_name = ?,
                 headline = ?,
                 bio = ?,
                 facebook_url = ?,
                 instagram_url = ?,
                 discord_url = ?,
                 gmail_email = ?
             WHERE id = ?"
        );

        if ($stmt) {

            $stmt->bind_param(
                "sssssssi",
                $display_name,
                $headline,
                $bio,
                $social["facebook"],
                $social["instagram"],
                $social["discord"],
                $social["gmail"],
                $admin_id
            );

            if ($stmt->execute()) {

                $success = "Profile saved.";

            } else {

                $error = "Unable to save your profile.";
            }

            $stmt->close();

        } else {

            $error = "Database error. Please try again.";
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

    <title>Edit Profile</title>

    <link rel="stylesheet" href="../style.css">

    <?= theme_style_tag() ?>

</head>

<body>

<header class="admin-navbar">

    <h2>
        Edit Profile
    </h2>

    <div>

        <a href="../index.php">
            View Portfolio
        </a>

        <a href="dashboard.php">
            Dashboard
        </a>

    </div>

</header>


<main class="form-container">

    <h1>
        Your Profile
    </h1>


    <?php if ($error !== ""): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success !== ""): ?>
        <div class="success">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>


    <form method="POST">

        <label>
            Name
        </label>

        <input
            type="text"
            name="display_name"
            value="<?= htmlspecialchars($display_name) ?>"
            maxlength="100"
            required
        >


        <label>
            Title / Role
        </label>

        <input
            type="text"
            name="headline"
            value="<?= htmlspecialchars($headline) ?>"
            placeholder="Student, Web Developer, ..."
            maxlength="100"
        >


        <label>
            About Me
        </label>

        <textarea
            name="bio"
            rows="6"
            maxlength="1000"
            placeholder="Write a short introduction about yourself"
        ><?= htmlspecialchars($bio) ?></textarea>


        <label>
            Social Links
        </label>

        <small class="form-hint">
            Optional. Each link or email you fill in shows up as an icon in the footer of your portfolio.
            Leave a box empty to hide that icon.
        </small>

        <?php foreach (social_networks() as $key => $network): ?>

            <label>
                <?= htmlspecialchars($network["label"]) ?>
            </label>

            <input
                type="text"
                name="social_<?= $key ?>"
                value="<?= htmlspecialchars($social[$key]) ?>"
                placeholder="<?= htmlspecialchars($network["placeholder"]) ?>"
                maxlength="255"
                autocomplete="off"
            >

        <?php endforeach; ?>


        <button type="submit" class="btn">
            Save Profile
        </button>

    </form>

</main>

</body>
</html>