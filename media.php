<?php

// Media files (photos, videos, PDFs) attached to projects.
// Files are saved in the "uploads" folder, the list is kept in the
// project_media table.

require_once __DIR__ . "/db.php";

const MEDIA_MAX_FILES = 10;
const MEDIA_MAX_BYTES = 25 * 1024 * 1024; // 25 MB per file

// mime type => [file extension, kind]
function media_allowed(): array
{
    return [
        "image/jpeg"      => ["jpg",  "image"],
        "image/png"       => ["png",  "image"],
        "image/gif"       => ["gif",  "image"],
        "image/webp"      => ["webp", "image"],
        "video/mp4"       => ["mp4",  "video"],
        "video/webm"      => ["webm", "video"],
        "application/pdf" => ["pdf",  "file"],
        "application/zip" => ["zip",  "archive"],
        "application/x-zip-compressed" => ["zip", "archive"],
    ];
}

function media_ensure_table(): void
{
    global $conn;

    static $done = false;

    if ($done) {
        return;
    }

    $done = true;

    try {

        $conn->query(
            "CREATE TABLE IF NOT EXISTS project_media (
                id INT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                file_type VARCHAR(10) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX (project_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

    } catch (Throwable $e) {
        // the portfolio still works without media
    }
}

function media_dir(): string
{
    $dir = __DIR__ . "/uploads";

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // uploaded files may be shown, but never run as scripts
    $htaccess = $dir . "/.htaccess";

    if (!file_exists($htaccess)) {
        file_put_contents(
            $htaccess,
            "<FilesMatch \"\\.(php|phtml|php[0-9]|phar|pl|py|cgi)$\">\n    Require all denied\n</FilesMatch>\n"
        );
    }

    return $dir;
}

// Address of an uploaded file, relative to the site root
function media_url(string $fileName): string
{
    return "uploads/" . rawurlencode($fileName);
}

// Turns PHP's $_FILES["media"] into a simple list
function media_normalize($input): array
{
    $files = [];

    if (!is_array($input) || !isset($input["name"])) {
        return $files;
    }

    if (!is_array($input["name"])) {
        $input = array_map(fn($value) => [$value], $input);
    }

    foreach ($input["name"] as $i => $name) {

        $error = $input["error"][$i] ?? UPLOAD_ERR_NO_FILE;

        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $files[] = [
            "name"     => (string) $name,
            "tmp_name" => (string) ($input["tmp_name"][$i] ?? ""),
            "error"    => $error,
            "size"     => (int) ($input["size"][$i] ?? 0),
        ];
    }

    return $files;
}

// Returns "" when everything is fine, otherwise an error message
function media_validate($input): string
{
    $files = media_normalize($input);

    if (count($files) === 0) {
        return "";
    }

    if (count($files) > MEDIA_MAX_FILES) {
        return "You can add up to " . MEDIA_MAX_FILES . " files at a time.";
    }

    if (!function_exists("finfo_open")) {
        return "Uploads need the PHP fileinfo extension. Enable extension=fileinfo in php.ini.";
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $allowed = media_allowed();

    foreach ($files as $file) {

        $name = $file["name"];

        if (
            $file["error"] === UPLOAD_ERR_INI_SIZE ||
            $file["error"] === UPLOAD_ERR_FORM_SIZE ||
            $file["size"] > MEDIA_MAX_BYTES
        ) {
            return "\"" . $name . "\" is too large (max 25 MB per file).";
        }

        if ($file["error"] !== UPLOAD_ERR_OK) {
            return "\"" . $name . "\" could not be uploaded. Please try again.";
        }

        $mime = finfo_file($finfo, $file["tmp_name"]);

        if (!isset($allowed[$mime])) {
            return "\"" . $name . "\" is not a supported file. Use JPG, PNG, GIF, WEBP, MP4, WEBM, PDF or ZIP.";
        }
    }

    return "";
}

// Saves the files (call media_validate first). Returns how many were saved.
function media_save(int $projectId, $input): int
{
    global $conn;

    media_ensure_table();

    $files = media_normalize($input);

    if (count($files) === 0) {
        return 0;
    }

    $dir = media_dir();
    $allowed = media_allowed();
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $saved = 0;

    foreach ($files as $file) {

        if ($file["error"] !== UPLOAD_ERR_OK) {
            continue;
        }

        $mime = finfo_file($finfo, $file["tmp_name"]);

        if (!isset($allowed[$mime])) {
            continue;
        }

        [$extension, $kind] = $allowed[$mime];

        $storedName = bin2hex(random_bytes(16)) . "." . $extension;

        if (!move_uploaded_file($file["tmp_name"], $dir . "/" . $storedName)) {
            continue;
        }

        $original = mb_substr(basename($file["name"]), 0, 255);

        $stmt = $conn->prepare(
            "INSERT INTO project_media (project_id, file_name, original_name, file_type)
             VALUES (?, ?, ?, ?)"
        );

        if ($stmt) {
            $stmt->bind_param("isss", $projectId, $storedName, $original, $kind);
            $stmt->execute();
            $stmt->close();
            $saved++;
        }
    }

    return $saved;
}

// All media, grouped by project: [project_id => [row, row, ...]]
function media_load_all(): array
{
    global $conn;

    media_ensure_table();

    $grouped = [];

    try {

        $result = $conn->query("SELECT * FROM project_media ORDER BY id ASC");

        while ($result && $row = $result->fetch_assoc()) {
            $grouped[(int) $row["project_id"]][] = $row;
        }

    } catch (Throwable $e) {
        // show no media
    }

    return $grouped;
}

// Removes the files and rows of a project that is being deleted
function media_delete_for_project(int $projectId): void
{
    global $conn;

    media_ensure_table();

    try {

        $dir = media_dir();

        $stmt = $conn->prepare("SELECT file_name FROM project_media WHERE project_id = ?");
        $stmt->bind_param("i", $projectId);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {

            $path = $dir . "/" . basename($row["file_name"]);

            if (is_file($path)) {
                unlink($path);
            }
        }

        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM project_media WHERE project_id = ?");
        $stmt->bind_param("i", $projectId);
        $stmt->execute();
        $stmt->close();

    } catch (Throwable $e) {
        // nothing to clean up
    }
}

// The media of one project (used on the edit page)
function media_load_for_project(int $projectId): array
{
    global $conn;

    media_ensure_table();

    $rows = [];

    try {

        $stmt = $conn->prepare(
            "SELECT id, file_name, original_name, file_type
             FROM project_media WHERE project_id = ? ORDER BY id ASC"
        );

        $stmt->bind_param("i", $projectId);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        $stmt->close();

    } catch (Throwable $e) {
        // show no media
    }

    return $rows;
}

// Removes chosen files (by project_media id) from one project
function media_delete_items(int $projectId, array $ids): void
{
    global $conn;

    media_ensure_table();

    try {

        $dir = media_dir();

        foreach ($ids as $id) {

            $id = (int) $id;

            if ($id <= 0) {
                continue;
            }

            $stmt = $conn->prepare(
                "SELECT file_name FROM project_media WHERE id = ? AND project_id = ?"
            );
            $stmt->bind_param("ii", $id, $projectId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$row) {
                continue;
            }

            $path = $dir . "/" . basename($row["file_name"]);

            if (is_file($path)) {
                unlink($path);
            }

            $stmt = $conn->prepare(
                "DELETE FROM project_media WHERE id = ? AND project_id = ?"
            );
            $stmt->bind_param("ii", $id, $projectId);
            $stmt->execute();
            $stmt->close();
        }

    } catch (Throwable $e) {
        // nothing to clean up
    }
}
