<?php

require_once __DIR__ . "/../session.php";
require_once __DIR__ . "/../theme.php";

if (!isset($_SESSION["admin_id"])) {

    header("Location: ../login.php");
    exit;
}

?>

<!DOCTYPE html>
<html lang="en"<?= theme_html_attrs() ?>>

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard</title>

    <link rel="stylesheet" href="../style.css">

    <?= theme_style_tag() ?>

</head>

<body>

<header class="admin-navbar">

    <h2>
        Admin Dashboard
    </h2>

    <div>

        <a href="../index.php">
            View Portfolio
        </a>

        <a href="../logout.php">
            Logout
        </a>

    </div>

</header>


<main class="dashboard">

    <h1>
        Welcome, <?= htmlspecialchars(
            $_SESSION["admin_username"]
        ) ?>
    </h1>

    <p class="dashboard-subtitle">
        Manage your portfolio website.
    </p>


    <div class="dashboard-grid">

        <a
            href="add_project.php"
            class="dashboard-card"
        >

            <div class="dashboard-icon">
                +
            </div>

            <h2>
                Add Project
            </h2>

            <p>
                Add a new project to your portfolio.
            </p>

        </a>


        <a
            href="messages.php"
            class="dashboard-card"
        >

            <div class="dashboard-icon">
                ✉
            </div>

            <h2>
                Messages
            </h2>

            <p>
                View messages from visitors.
            </p>

        </a>


        <a href="settings.php" class="dashboard-card">
            <div class="dashboard-icon">⚙</div>
            <h2>Settings</h2>
            <p>Change your username, password, theme color, and fonts.</p>
        </a>

        <a
            href="profile.php"
            class="dashboard-card"
        >

            <div class="dashboard-icon">
                👤
            </div>

            <h2>
                Edit Profile
            </h2>

            <p>
                Update your name, title, bio and picture.
            </p>

        </a>


        <a
            href="dashboard.php"
            class="dashboard-card"
        >

            <div class="dashboard-icon">
                📁
            </div>

            <h2>
                Manage Projects
            </h2>

            <p>
                Edit or delete existing projects.
            </p>

        </a>

    </div>


    <h2 class="admin-section-title">
        Projects
    </h2>


    <?php

    require_once "../db.php";

    $result = $conn->query(
        "SELECT * FROM projects
         ORDER BY created_at DESC"
    );

    ?>


    <div class="table-container">

        <table>

            <thead>

                <tr>
                    <th>Title</th>
                    <th>Technologies</th>
                    <th>Actions</th>
                </tr>

            </thead>

            <tbody>

            <?php if ($result && $result->num_rows > 0): ?>

                <?php while ($project =
                    $result->fetch_assoc()): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars(
                                $project["title"]
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $project["technologies"]
                            ) ?>
                        </td>

                        <td>

                            <a
                                href="edit.php?id=<?= $project["id"] ?>"
                                class="edit"
                            >
                                Edit
                            </a>

                            <a
                                href="delete_profile.php?id=<?= $project["id"] ?>"
                                class="delete"
                                onclick="return confirm(
                                    'Are you sure you want to delete this project?'
                                );"
                            >
                                Delete
                            </a>

                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>

                    <td colspan="3">
                        No projects found.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</main>

</body>
</html>