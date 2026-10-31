<?php

require_once __DIR__ . "/../session.php";
require_once __DIR__ . "/../theme.php";
require_once __DIR__ . "/../media.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $technologies = trim($_POST["technologies"] ?? "");
    $project_link = trim($_POST["project_link"] ?? "");

    // "localhost/myapp" -> "http://localhost/myapp"
    if ($project_link !== "" && !preg_match('#^https?://#i', $project_link)) {
        $project_link = "http://" . $project_link;
    }

    // photos / videos / PDFs added with the + button
    if (empty($_POST) && !empty($_SERVER["CONTENT_LENGTH"])) {
        $mediaError = "Your files are too big to upload at once. Try fewer or smaller files.";
    } else {
        $mediaError = media_validate($_FILES["media"] ?? null);
    }

    if ($mediaError !== "") {

        $error = $mediaError;

    } elseif ($title === "" || $description === "") {

        $error = "Title and description are required.";

    } elseif (
        $project_link !== "" &&
        !filter_var($project_link, FILTER_VALIDATE_URL)
    ) {

        $error = "Please enter a valid project link.";

    } else {

        $stmt = $conn->prepare(
            "INSERT INTO projects
            (title, description, technologies, project_link)
            VALUES (?, ?, ?, ?)"
        );

        if ($stmt) {

            $stmt->bind_param(
                "ssss",
                $title,
                $description,
                $technologies,
                $project_link
            );

            if ($stmt->execute()) {

                $newProjectId = (int) $conn->insert_id;

                media_save($newProjectId, $_FILES["media"] ?? null);

                header("Location: dashboard.php");
                exit;

            } else {

                $error = "Unable to add the project.";
            }

            $stmt->close();

        } else {

            $error = "Database error. Please try again.";
        }
    }
}

// Projects that are already on the portfolio
$existingProjects = [];

$existingResult = $conn->query(
    "SELECT title, technologies, project_link
     FROM projects
     ORDER BY created_at DESC"
);

if ($existingResult) {
    while ($row = $existingResult->fetch_assoc()) {
        $existingProjects[] = $row;
    }
}

?>

<!DOCTYPE html>
<html lang="en"<?= theme_html_attrs() ?>>

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Add Project</title>

    <link rel="stylesheet" href="../style.css?v=<?= filemtime(__DIR__ . '/../style.css') ?>">

    <?= theme_style_tag() ?>

</head>

<body>

<header class="admin-navbar">

    <h2>
        Add Project
    </h2>

    <a href="dashboard.php">
        Dashboard
    </a>

</header>


<main class="form-container">

    <h1>
        Add New Project
    </h1>


    <?php if ($error !== ""): ?>

        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <form method="POST" enctype="multipart/form-data">

        <label>
            Project Title
        </label>

        <input
            type="text"
            name="title"
            placeholder="Project title"
            required
        >


        <label>
            Description
        </label>

        <textarea
            name="description"
            rows="6"
            placeholder="Project description"
            required
        ></textarea>


        <label>
            Technologies
        </label>

        <input
            type="text"
            name="technologies"
            placeholder="HTML, CSS, PHP"
        >


        <label>
            Project Link
        </label>

        <div class="input-with-plus">

            <input
                type="text"
                name="project_link"
                id="project_link"
                placeholder="http://localhost/my-project or https://example.com"
                autocomplete="off"
            >

            <button
                type="button"
                class="input-plus"
                id="media_add"
                title="Add photos, videos or PDFs"
                aria-label="Add photos, videos or PDFs"
            >+</button>

        </div>

        <input
            type="file"
            name="media[]"
            id="media_input"
            accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,application/pdf"
            multiple
            hidden
        >

        <small class="form-hint">
            Optional. If you add a link, a live preview of it is shown on your portfolio.
            Use the + button to attach photos, videos or PDFs (up to 10 files, 25 MB each).
        </small>

        <div class="media-previews" id="media_previews"></div>


        <label>
            Front Page Preview
        </label>

        <div
            class="project-preview project-preview-live link-preview"
            id="link-preview"
        ></div>

        <div class="project-media" id="media_front"></div>

        <small class="form-hint">
            Some websites don't allow being shown inside another page, so their preview may stay blank.
            Projects on localhost always work.
        </small>


        <button
            type="submit"
            class="btn"
        >
            Add Project
        </button>

    </form>

</main>


<section class="existing-projects">

    <h2>
        Projects Already Added
    </h2>

    <?php if (count($existingProjects) > 0): ?>

        <div class="existing-grid">

            <?php foreach ($existingProjects as $project): ?>

                <div class="existing-card">

                    <?php if (!empty($project["project_link"])): ?>

                        <div class="project-preview project-preview-live">
                            <iframe
                                src="<?= htmlspecialchars($project["project_link"]) ?>"
                                title="Preview of <?= htmlspecialchars($project["title"]) ?>"
                                loading="lazy"
                                tabindex="-1"
                                scrolling="no"
                            ></iframe>
                        </div>

                    <?php else: ?>

                        <div class="project-preview preview-empty">
                            No link added
                        </div>

                    <?php endif; ?>

                    <h3>
                        <?= htmlspecialchars($project["title"]) ?>
                    </h3>

                    <?php if (!empty($project["technologies"])): ?>

                        <span class="tag">
                            <?= htmlspecialchars($project["technologies"]) ?>
                        </span>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <p class="existing-empty">
            No projects added yet. Your first one will appear here.
        </p>

    <?php endif; ?>

</section>


<script>
(function () {

    var input = document.getElementById("project_link");
    var box = document.getElementById("link-preview");
    var timer = null;
    var currentUrl = "";

    // "localhost/myapp" -> "http://localhost/myapp", or "" if not a usable link yet
    function normalize(value) {

        value = value.trim();

        if (value === "") {
            return "";
        }

        if (!/^https?:\/\//i.test(value)) {
            value = "http://" + value;
        }

        try {

            var host = new URL(value).hostname;

            if (host === "localhost" || host.indexOf(".") !== -1) {
                return value;
            }

        } catch (e) {}

        return "";
    }

    function showMessage(text) {

        box.innerHTML = "";

        var msg = document.createElement("div");
        msg.className = "preview-empty";
        msg.textContent = text;

        box.appendChild(msg);
        currentUrl = "";
    }

    function hasMedia() {
        return document.querySelectorAll("#media_front > *").length > 0;
    }

    function update() {

        var url = normalize(input.value);

        // files are shown in their own preview, so the link box is only
        // needed when there is a link, or when there is nothing else to show
        if (url === "") {

            if (hasMedia()) {
                box.innerHTML = "";
                box.style.display = "none";
                currentUrl = "";
            } else {
                box.style.display = "";
                showMessage("Type a project link or add a file to preview it here.");
            }

            return;
        }

        box.style.display = "";

        if (url === currentUrl) {
            return;
        }

        box.innerHTML = "";

        var frame = document.createElement("iframe");
        frame.src = url;
        frame.title = "Live preview of the project link";
        frame.tabIndex = -1;
        frame.setAttribute("scrolling", "no");

        box.appendChild(frame);
        currentUrl = url;
    }

    input.addEventListener("input", function () {
        clearTimeout(timer);
        timer = setTimeout(update, 600);
    });

    // runs when files are added or removed
    document.addEventListener("mediachange", update);

    update();

})();
</script>

<script>
(function () {

    var MAX_FILES = 10;
    var MAX_BYTES = 25 * 1024 * 1024;

    var input = document.getElementById("media_input");
    var addButton = document.getElementById("media_add");
    var list = document.getElementById("media_previews");

    // the files chosen so far (the + button can be used several times)
    var chosen = new DataTransfer();

    function sameFile(a, b) {
        return a.name === b.name && a.size === b.size && a.lastModified === b.lastModified;
    }

    function removeAt(index) {

        var next = new DataTransfer();

        Array.prototype.forEach.call(chosen.files, function (file, i) {
            if (i !== index) {
                next.items.add(file);
            }
        });

        chosen = next;
        input.files = chosen.files;
        render();
    }

    function render() {

        list.innerHTML = "";

        Array.prototype.forEach.call(chosen.files, function (file, index) {

            var item = document.createElement("div");
            item.className = "media-preview";

            var thumb;

            if (file.type.indexOf("image/") === 0) {

                thumb = document.createElement("img");
                thumb.src = URL.createObjectURL(file);
                thumb.alt = file.name;

            } else if (file.type.indexOf("video/") === 0) {

                thumb = document.createElement("video");
                thumb.src = URL.createObjectURL(file);
                thumb.muted = true;
                thumb.preload = "metadata";

            } else {

                thumb = document.createElement("div");
                thumb.className = "media-doc";
                thumb.textContent = "PDF";
            }

            var name = document.createElement("span");
            name.className = "media-name";
            name.textContent = file.name;

            var remove = document.createElement("button");
            remove.type = "button";
            remove.className = "media-remove";
            remove.setAttribute("aria-label", "Remove " + file.name);
            remove.textContent = "\u00d7";
            remove.addEventListener("click", function () {
                removeAt(index);
            });

            item.appendChild(thumb);
            item.appendChild(name);
            item.appendChild(remove);
            list.appendChild(item);
        });

        renderFront();
    }

    // larger preview of the chosen files, shown the way the portfolio shows them
    var front = document.getElementById("media_front");
    var frontUrls = [];

    function renderFront() {

        frontUrls.forEach(function (u) { URL.revokeObjectURL(u); });
        frontUrls = [];

        Array.prototype.slice.call(front.querySelectorAll("[data-new]")).forEach(function (el) {
            el.remove();
        });

        Array.prototype.forEach.call(chosen.files, function (file) {

            var url = URL.createObjectURL(file);
            frontUrls.push(url);

            var box = document.createElement("div");
            box.setAttribute("data-new", "1");

            if (file.type.indexOf("image/") === 0) {

                box.className = "media-item";

                var img = document.createElement("img");
                img.src = url;
                img.alt = file.name;
                box.appendChild(img);

            } else if (file.type.indexOf("video/") === 0) {

                box.className = "media-item media-video";

                var video = document.createElement("video");
                video.src = url;
                video.controls = true;
                video.preload = "metadata";
                box.appendChild(video);

            } else {

                box.className = "media-item media-pdf";

                var frame = document.createElement("iframe");
                frame.src = url + "#toolbar=0&navpanes=0&view=FitH";
                frame.title = file.name;
                box.appendChild(frame);
            }

            front.appendChild(box);
        });

        document.dispatchEvent(new Event("mediachange"));
    }

    addButton.addEventListener("click", function () {
        input.click();
    });

    input.addEventListener("change", function () {

        var problem = "";

        Array.prototype.forEach.call(input.files, function (file) {

            var duplicate = Array.prototype.some.call(chosen.files, function (c) {
                return sameFile(c, file);
            });

            if (duplicate) {
                return;
            }

            if (file.size > MAX_BYTES) {
                problem = file.name + " is larger than 25 MB, so it was skipped.";
            } else if (chosen.files.length >= MAX_FILES) {
                problem = "You can add up to " + MAX_FILES + " files at a time.";
            } else {
                chosen.items.add(file);
            }
        });

        input.files = chosen.files;
        render();

        if (problem !== "") {
            alert(problem);
        }
    });

})();
</script>

</body>
</html>