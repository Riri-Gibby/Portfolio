<?php

require_once __DIR__ . "/session.php";
require_once __DIR__ . "/theme.php";
require_once __DIR__ . "/media.php";
require_once "db.php";

$projects = [];

$sql = "SELECT * FROM projects ORDER BY created_at DESC";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $projects[] = $row;
    }
}

// defaults, used until you save your own profile in the admin area
$profileName = "Gab";
$profileHeadline = "Student";
$profileBio = "Welcome to my portfolio. Here you can view my projects, skills, and contact information.";

$profileResult = $conn->query(
    "SELECT display_name, headline, bio
     FROM admins ORDER BY id ASC LIMIT 1"
);

if ($profileResult && $profileRow = $profileResult->fetch_assoc()) {

    if (!empty($profileRow["display_name"])) {
        $profileName = $profileRow["display_name"];
    }

    if (!empty($profileRow["headline"])) {
        $profileHeadline = $profileRow["headline"];
    }

    if (!empty($profileRow["bio"])) {
        $profileBio = $profileRow["bio"];
    }
}

// photos / videos / PDFs added to each project
$mediaByProject = media_load_all();

// social icons for the footer (only links that are filled in)
$socialLinks = social_load();

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $user_message = trim($_POST["message"] ?? "");

    if ($name === "" || $email === "" || $user_message === "") {

        $message = "Please complete all required fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";

    } else {

        $stmt = $conn->prepare(
            "INSERT INTO messages (name, email, message)
             VALUES (?, ?, ?)"
        );

        if ($stmt) {

            $stmt->bind_param(
                "sss",
                $name,
                $email,
                $user_message
            );

            if ($stmt->execute()) {
                $message = "Your message has been sent successfully.";
            } else {
                $message = "Unable to send your message. Please try again.";
            }

            $stmt->close();

        } else {
            $message = "Something went wrong. Please try again later.";
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

    <title>My Portfolio</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">

    <?= theme_style_tag() ?>

</head>

<body>

<header class="navbar">

    <div class="logo">
        Portfolio
    </div>

    <nav>
        <a href="#home">Home</a>
        <a href="#projects">Projects</a>
        <a href="#contact">Contact</a>
        <?php if (isset($_SESSION["admin_id"])): ?>
            <a href="admin/dashboard.php" class="admin-link">
                Dashboard
            </a>
        <?php else: ?>
            <a href="login.php" class="admin-link">
                Admin
            </a>
        <?php endif; ?>
    </nav>

</header>


<!-- PROFILE -->

<section class="hero" id="home">

    <div class="hero-content">

        <div class="profile-info">

            <p class="small-title">
                WELCOME TO MY PORTFOLIO
            </p>

            <h1>
                Hi, I'm <span><?= htmlspecialchars($profileName) ?></span>
            </h1>

            <h2>
                <?= htmlspecialchars($profileHeadline) ?>
            </h2>

            <p>
                <?= nl2br(htmlspecialchars($profileBio)) ?>
            </p>

            <a href="#projects" class="btn">
                View Projects
            </a>

        </div>

    </div>

</section>


<!-- PROJECTS -->

<section class="projects" id="projects">

    <div class="section-heading">

        <p>MY WORK</p>

        <h2>My Projects</h2>

    </div>


    <div class="project-grid">

        <?php if (count($projects) > 0): ?>

            <?php foreach ($projects as $project): ?>

                <div class="project-card">

                    <div class="project-content">

                        <?php if (isset($_SESSION["admin_id"])): ?>

                            <a
                                href="admin/edit.php?id=<?= (int) $project["id"] ?>"
                                class="edit-sign"
                                title="Edit this project"
                                aria-label="Edit <?= htmlspecialchars($project["title"]) ?>"
                            >
                                ✎
                            </a>

                        <?php endif; ?>

                        <?php if (!empty($project["project_link"])): ?>

                            <div class="project-preview project-preview-live">
                                <iframe
                                    src="<?= htmlspecialchars($project["project_link"]) ?>"
                                    title="Live preview of <?= htmlspecialchars($project["title"]) ?>"
                                    loading="lazy"
                                    tabindex="-1"
                                    scrolling="no"
                                ></iframe>
                            </div>

                        <?php endif; ?>


                        <?php if (!empty($mediaByProject[(int) $project["id"]])): ?>

                            <div class="project-media">

                                <?php foreach ($mediaByProject[(int) $project["id"]] as $media): ?>

                                    <?php $mediaUrl = media_url($media["file_name"]); ?>

                                    <?php if ($media["file_type"] === "image"): ?>

                                        <a
                                            class="media-item"
                                            href="<?= htmlspecialchars($mediaUrl) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <img
                                                src="<?= htmlspecialchars($mediaUrl) ?>"
                                                alt="<?= htmlspecialchars($media["original_name"]) ?>"
                                                loading="lazy"
                                            >
                                        </a>

                                    <?php elseif ($media["file_type"] === "video"): ?>

                                        <div class="media-item media-video">
                                            <video
                                                src="<?= htmlspecialchars($mediaUrl) ?>"
                                                controls
                                                preload="metadata"
                                            ></video>
                                        </div>

                                    <?php elseif ($media["file_type"] === "archive"): ?>

                                        <a
                                            class="media-item media-zip"
                                            href="<?= htmlspecialchars($mediaUrl) ?>"
                                            download="<?= htmlspecialchars($media["original_name"]) ?>"
                                        >
                                            <span><?= htmlspecialchars(preg_replace('/\.zip$/i', '', $media["original_name"])) ?></span>
                                            <em>Download code</em>
                                        </a>

                                    <?php else: ?>

                                        <div class="media-item media-pdf">
                                            <iframe
                                                src="<?= htmlspecialchars($mediaUrl) ?>#toolbar=0&navpanes=0&view=FitH"
                                                title="<?= htmlspecialchars($media["original_name"]) ?>"
                                                loading="lazy"
                                            ></iframe>
                                            <a
                                                href="<?= htmlspecialchars($mediaUrl) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                Open PDF ↗
                                            </a>
                                        </div>

                                    <?php endif; ?>

                                <?php endforeach; ?>

                            </div>

                        <?php endif; ?>


                        <h3>
                            <?= htmlspecialchars($project["title"]) ?>
                        </h3>

                        <p>
                            <?= nl2br(
                                htmlspecialchars(
                                    $project["description"]
                                )
                            ) ?>
                        </p>

                        <span class="tag">
                            <?= htmlspecialchars(
                                $project["technologies"]
                            ) ?>
                        </span>

                        <?php if (!empty($project["project_link"])): ?>

                            <a
                                href="<?= htmlspecialchars(
                                    $project["project_link"]
                                ) ?>"
                                target="_blank"
                                class="project-btn"
                            >
                                View Project
                            </a>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <p>
                No projects available yet.
            </p>

        <?php endif; ?>

    </div>

</section>


<!-- CONTACT -->

<section class="contact" id="contact">

    <div class="section-heading">

        <p>GET IN TOUCH</p>

        <h2>Contact Me</h2>

    </div>


    <?php if ($message !== ""): ?>

        <div class="alert">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>


    <form
        method="POST"
        class="contact-form"
    >

        <label>Name</label>

        <input
            type="text"
            name="name"
            placeholder="Enter your name"
            required
        >


        <label>Email</label>

        <input
            type="email"
            name="email"
            placeholder="Enter your email"
            required
        >


        <label>Message</label>

        <textarea
            name="message"
            rows="6"
            placeholder="Write your message..."
            required
        ></textarea>


        <button
            type="submit"
            class="btn"
        >
            Send Message
        </button>

    </form>

</section>


<footer>

    <h3>My Portfolio</h3>

    <p>
        Thank you for visiting my portfolio.
    </p>

    <?php if (count($socialLinks) > 0): ?>

        <div class="socials">

            <?php foreach (social_networks() as $key => $network): ?>

                <?php if (isset($socialLinks[$key])): ?>

                    <a
                        href="<?= htmlspecialchars(social_href($key, $socialLinks[$key])) ?>"
                        <?= $network["type"] === "link" ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                        title="<?= htmlspecialchars($network["label"]) ?>"
                        aria-label="<?= htmlspecialchars($network["label"]) ?>"
                    >
                        <i class="<?= $network["icon"] ?>"></i>
                    </a>

                <?php endif; ?>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</footer>

</body>
</html>