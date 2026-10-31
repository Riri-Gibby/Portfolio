<?php

require_once __DIR__ . "/../session.php";
require_once __DIR__ . "/../theme.php";
require_once __DIR__ . "/../media.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../db.php";

$id = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$id) {
    header("Location: dashboard.php");
    exit;
}


$stmt = $conn->prepare(
    "SELECT * FROM projects WHERE id = ?"
);

$stmt->bind_param("i", $id);

$stmt->execute();

$result = $stmt->get_result();

$project = $result->fetch_assoc();

$stmt->close();


if (!$project) {
    header("Location: dashboard.php");
    exit;
}


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
            "UPDATE projects
             SET title = ?,
                 description = ?,
                 technologies = ?,
                 project_link = ?
             WHERE id = ?"
        );

        if ($stmt) {

            $stmt->bind_param(
                "ssssi",
                $title,
                $description,
                $technologies,
                $project_link,
                $id
            );

            if ($stmt->execute()) {

                // files removed with the x button, then new files
                $removeIds = $_POST["remove_media"] ?? [];

                if (is_array($removeIds)) {
                    media_delete_items($id, $removeIds);
                }

                media_save($id, $_FILES["media"] ?? null);

                header("Location: dashboard.php");
                exit;

            } else {

                $error = "Unable to update the project.";
            }

            $stmt->close();

        } else {

            $error = "Database error. Please try again.";
        }
    }
}

// Files already attached to this project
$existingMedia = media_load_for_project($id);

?>

<!DOCTYPE html>
<html lang="en"<?= theme_html_attrs() ?>>

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Edit Project</title>

    <link rel="stylesheet" href="../style.css?v=<?= filemtime(__DIR__ . '/../style.css') ?>">

    <?= theme_style_tag() ?>

</head>

<body>

<header class="admin-navbar">

    <h2>
        Edit Project
    </h2>

    <a href="dashboard.php">
        Dashboard
    </a>

</header>


<main class="form-container">

    <h1>
        Edit Project
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
            value="<?= htmlspecialchars(
                $project["title"]
            ) ?>"
            required
        >


        <label>
            Description
        </label>

        <textarea
            name="description"
            rows="6"
            required
        ><?= htmlspecialchars(
            $project["description"]
        ) ?></textarea>


        <label>
            Technologies
        </label>

        <input
            type="text"
            name="technologies"
            value="<?= htmlspecialchars(
                $project["technologies"]
            ) ?>"
        >


        <label>
            Project Link
        </label>

        <div class="input-with-plus has-folder">

            <input
                type="text"
                name="project_link"
                id="project_link"
                placeholder="http://localhost/my-project or https://example.com"
                value="<?= htmlspecialchars(
                    $project["project_link"] ?? ""
                ) ?>"
                autocomplete="off"
            >

            <button
                type="button"
                class="input-plus"
                id="media_add"
                title="Add photos, videos, PDFs or a ZIP"
                aria-label="Add photos, videos or PDFs"
            >+</button>

            <button
                type="button"
                class="input-plus input-folder"
                id="folder_add"
                title="Add a whole folder"
                aria-label="Add a folder"
            ><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M10 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-8l-2-2z"/></svg></button>

        </div>

        <input
            type="file"
            name="media[]"
            id="media_input"
            accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,application/pdf,application/zip,.zip"
            multiple
            hidden
        >

        <input
            type="file"
            id="folder_input"
            webkitdirectory
            multiple
            hidden
        >

        <small class="form-hint">
            Optional. If you add a link, a live preview of it is shown on your portfolio.
            Use the + button to attach photos, videos, PDFs or a ZIP, or the folder button to add a folder: photos, videos and PDFs are added as they are, and code is packed into one ZIP download (up to 10 files, 25 MB each).
        </small>

        <?php if (count($existingMedia) > 0): ?>

            <small class="form-hint">
                Files already on this project. Press &times; to remove one when you update.
            </small>

            <div class="media-previews" id="media_existing">

                <?php foreach ($existingMedia as $media): ?>

                    <?php $mediaUrl = "../" . media_url($media["file_name"]); ?>

                    <div class="media-preview" data-id="<?= (int) $media["id"] ?>">

                        <?php if ($media["file_type"] === "image"): ?>

                            <img
                                src="<?= htmlspecialchars($mediaUrl) ?>"
                                alt="<?= htmlspecialchars($media["original_name"]) ?>"
                            >

                        <?php elseif ($media["file_type"] === "video"): ?>

                            <video
                                src="<?= htmlspecialchars($mediaUrl) ?>"
                                muted
                                preload="metadata"
                            ></video>

                        <?php elseif ($media["file_type"] === "archive"): ?>

                            <div class="media-doc">ZIP</div>

                        <?php else: ?>

                            <div class="media-doc">PDF</div>

                        <?php endif; ?>

                        <span class="media-name">
                            <?= htmlspecialchars($media["original_name"]) ?>
                        </span>

                        <button
                            type="button"
                            class="media-remove"
                            aria-label="Remove <?= htmlspecialchars($media["original_name"]) ?>"
                        >&times;</button>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

        <div class="media-previews" id="media_previews"></div>


        <label>
            Front Page Preview
        </label>

        <div
            class="project-preview project-preview-live link-preview"
            id="link-preview"
        ></div>

        <div class="project-media" id="media_front">

            <?php foreach ($existingMedia as $media): ?>

                <?php $mediaUrl = "../" . media_url($media["file_name"]); ?>

                <?php if ($media["file_type"] === "image"): ?>

                    <div class="media-item" data-id="<?= (int) $media["id"] ?>">
                        <img
                            src="<?= htmlspecialchars($mediaUrl) ?>"
                            alt="<?= htmlspecialchars($media["original_name"]) ?>"
                        >
                    </div>

                <?php elseif ($media["file_type"] === "video"): ?>

                    <div class="media-item media-video" data-id="<?= (int) $media["id"] ?>">
                        <video
                            src="<?= htmlspecialchars($mediaUrl) ?>"
                            controls
                            preload="metadata"
                        ></video>
                    </div>

                <?php elseif ($media["file_type"] === "archive"): ?>

                    <a
                        class="media-item media-zip"
                        data-id="<?= (int) $media["id"] ?>"
                        href="<?= htmlspecialchars($mediaUrl) ?>"
                        download="<?= htmlspecialchars($media["original_name"]) ?>"
                    >
                        <span><?= htmlspecialchars(preg_replace('/\.zip$/i', '', $media["original_name"])) ?></span>
                        <em>Download code</em>
                    </a>

                <?php else: ?>

                    <div class="media-item media-pdf" data-id="<?= (int) $media["id"] ?>">
                        <iframe
                            src="<?= htmlspecialchars($mediaUrl) ?>#toolbar=0&navpanes=0&view=FitH"
                            title="<?= htmlspecialchars($media["original_name"]) ?>"
                        ></iframe>
                    </div>

                <?php endif; ?>

            <?php endforeach; ?>

        </div>

        <small class="form-hint">
            Some websites don't allow being shown inside another page, so their preview may stay blank.
            Projects on localhost always work.
        </small>


        <button
            type="submit"
            class="btn"
        >
            Update Project
        </button>

    </form>

</main>


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

    // show the preview of the saved link right away
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
                thumb.textContent = isZip(file) ? "ZIP" : "PDF";
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

            } else if (isZip(file)) {

                box.className = "media-item media-zip";

                var zipLabel = document.createElement("span");
                zipLabel.textContent = file.name.replace(/\.zip$/i, "");

                var zipNote = document.createElement("em");
                zipNote.textContent = "Download code";

                box.appendChild(zipLabel);
                box.appendChild(zipNote);

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

    // ---- ZIP support: a code folder is packed into one .zip in the browser ----

    var ZIP_TYPES = ["application/zip", "application/x-zip-compressed"];

    function isZip(file) {
        return ZIP_TYPES.indexOf(file.type) !== -1 || /\.zip$/i.test(file.name);
    }

    var crcTable = (function () {

        var table = [], c, n, k;

        for (n = 0; n < 256; n++) {

            c = n;

            for (k = 0; k < 8; k++) {
                c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
            }

            table[n] = c >>> 0;
        }

        return table;
    })();

    function crc32(bytes) {

        var c = 0xFFFFFFFF;

        for (var i = 0; i < bytes.length; i++) {
            c = crcTable[(c ^ bytes[i]) & 0xFF] ^ (c >>> 8);
        }

        return (c ^ 0xFFFFFFFF) >>> 0;
    }

    // entries: [{ name: "folder/file.php", data: Uint8Array }]  (stored, not compressed)
    function buildZip(entries) {

        var encoder = new TextEncoder();
        var parts = [];
        var central = [];
        var offset = 0;
        var centralSize = 0;

        entries.forEach(function (entry) {

            var nameBytes = encoder.encode(entry.name);
            var crc = crc32(entry.data);
            var size = entry.data.length;

            var local = new Uint8Array(30 + nameBytes.length);
            var lv = new DataView(local.buffer);

            lv.setUint32(0, 0x04034b50, true);
            lv.setUint16(4, 20, true);
            lv.setUint16(6, 0x0800, true);
            lv.setUint16(8, 0, true);
            lv.setUint16(10, 0, true);
            lv.setUint16(12, 0x21, true);
            lv.setUint32(14, crc, true);
            lv.setUint32(18, size, true);
            lv.setUint32(22, size, true);
            lv.setUint16(26, nameBytes.length, true);
            lv.setUint16(28, 0, true);
            local.set(nameBytes, 30);

            var head = new Uint8Array(46 + nameBytes.length);
            var hv = new DataView(head.buffer);

            hv.setUint32(0, 0x02014b50, true);
            hv.setUint16(4, 20, true);
            hv.setUint16(6, 20, true);
            hv.setUint16(8, 0x0800, true);
            hv.setUint16(10, 0, true);
            hv.setUint16(12, 0, true);
            hv.setUint16(14, 0x21, true);
            hv.setUint32(16, crc, true);
            hv.setUint32(20, size, true);
            hv.setUint32(24, size, true);
            hv.setUint16(28, nameBytes.length, true);
            hv.setUint16(30, 0, true);
            hv.setUint16(32, 0, true);
            hv.setUint16(34, 0, true);
            hv.setUint16(36, 0, true);
            hv.setUint32(38, 0, true);
            hv.setUint32(42, offset, true);
            head.set(nameBytes, 46);

            parts.push(local, entry.data);
            central.push(head);

            offset += local.length + size;
            centralSize += head.length;
        });

        var end = new Uint8Array(22);
        var ev = new DataView(end.buffer);

        ev.setUint32(0, 0x06054b50, true);
        ev.setUint16(4, 0, true);
        ev.setUint16(6, 0, true);
        ev.setUint16(8, entries.length, true);
        ev.setUint16(10, entries.length, true);
        ev.setUint32(12, centralSize, true);
        ev.setUint32(16, offset, true);
        ev.setUint16(20, 0, true);

        return new Blob(parts.concat(central, [end]), { type: "application/zip" });
    }

    // packs a chosen folder into one File called "<folder name>.zip"
    function zipFolder(files) {

        var total = files.reduce(function (sum, f) { return sum + f.size; }, 0);

        if (total > MAX_BYTES) {
            alert("That folder is larger than 25 MB, so it can't be added as a ZIP.");
            return Promise.resolve(null);
        }

        return Promise.all(files.map(function (f) {
            return f.arrayBuffer().then(function (buffer) {
                return { name: f.webkitRelativePath || f.name, data: new Uint8Array(buffer) };
            });
        })).then(function (entries) {

            var root = (files[0].webkitRelativePath || "folder").split("/")[0] || "folder";

            return new File([buildZip(entries)], root + ".zip", {
                type: "application/zip",
                lastModified: Date.now()
            });
        });
    }

    var folderInput = document.getElementById("folder_input");
    var folderButton = document.getElementById("folder_add");

    var SUPPORTED = [
        "image/jpeg", "image/png", "image/gif", "image/webp",
        "video/mp4", "video/webm", "application/pdf"
    ].concat(ZIP_TYPES);

    // adds files to the list, skipping duplicates and anything too big or over the limit
    function addFiles(files) {

        var problem = "";

        Array.prototype.forEach.call(files, function (file) {

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
    }

    input.addEventListener("change", function () {
        addFiles(input.files);
    });

    folderButton.addEventListener("click", function () {
        folderInput.click();
    });

    // a chosen folder: photos/videos/PDFs are added as they are,
    // code or other files are packed into one ZIP download
    folderInput.addEventListener("change", function () {

        var all = Array.prototype.slice.call(folderInput.files);

        folderInput.value = "";

        var files = all.filter(function (f) {
            return !/(^|\/)(\.git|node_modules)\//.test(f.webkitRelativePath || f.name);
        });

        if (files.length === 0) {
            alert("That folder is empty.");
            return;
        }

        var media = files.filter(function (f) {
            return SUPPORTED.indexOf(f.type) !== -1;
        });

        var asZip = false;

        if (media.length === 0) {

            asZip = true;

        } else if (media.length < files.length) {

            asZip = confirm(
                "This folder has other files besides photos, videos and PDFs.\n\n" +
                "OK = add the whole folder as one ZIP download\n" +
                "Cancel = add only the photos, videos and PDFs"
            );
        }

        if (!asZip) {
            addFiles(media);
            return;
        }

        zipFolder(files).then(function (zipFile) {
            if (zipFile) {
                addFiles([zipFile]);
            }
        });
    });

})();
</script>

<script>
(function () {

    var existing = document.getElementById("media_existing");
    var form = document.querySelector("form");

    if (!existing) {
        return;
    }

    // marks a saved file for removal; it is deleted when the project is updated
    existing.addEventListener("click", function (event) {

        var button = event.target.closest(".media-remove");

        if (!button) {
            return;
        }

        var item = button.closest(".media-preview");

        var hidden = document.createElement("input");
        hidden.type = "hidden";
        hidden.name = "remove_media[]";
        hidden.value = item.getAttribute("data-id");

        form.appendChild(hidden);

        var big = document.querySelector('#media_front [data-id="' + item.getAttribute("data-id") + '"]');

        if (big) {
            big.remove();
        }

        document.dispatchEvent(new Event("mediachange"));

        item.remove();

        if (existing.children.length === 0) {
            existing.remove();
        }
    });

})();
</script>

</body>
</html>