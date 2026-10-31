<?php

require_once __DIR__ . "/../session.php";
require_once __DIR__ . "/../theme.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../db.php";

$result = $conn->query(
    "SELECT * FROM messages
     ORDER BY created_at DESC"
);

?>

<!DOCTYPE html>
<html lang="en"<?= theme_html_attrs() ?>>

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Messages</title>

    <link rel="stylesheet" href="../style.css">

    <?= theme_style_tag() ?>

</head>

<body>

<header class="admin-navbar">

    <h2>
        Visitor Messages
    </h2>

    <a href="dashboard.php">
        Dashboard
    </a>

</header>


<main class="admin-content">

    <h1>
        Messages
    </h1>

    <p class="dashboard-subtitle">
        Messages submitted by visitors.
    </p>


    <div class="messages">

        <?php if (
            $result &&
            $result->num_rows > 0
        ): ?>

            <?php while (
                $message = $result->fetch_assoc()
            ): ?>

                <div class="message-card">

                    <div class="message-header">

                        <div>

                            <h3>
                                <?= htmlspecialchars(
                                    $message["name"]
                                ) ?>
                            </h3>

                            <p>
                                <?= htmlspecialchars(
                                    $message["email"]
                                ) ?>
                            </p>

                        </div>

                        <span>
                            <?= htmlspecialchars(
                                $message["created_at"]
                            ) ?>
                        </span>

                    </div>


                    <p class="message-text">

                        <?= nl2br(
                            htmlspecialchars(
                                $message["message"]
                            )
                        ) ?>

                    </p>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="message-card">

                <p>
                    No messages have been submitted yet.
                </p>

            </div>

        <?php endif; ?>

    </div>

</main>

</body>
</html>