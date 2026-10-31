<?php

// Shared session setup. Every page that uses sessions loads this file
// instead of calling session_start() directly, so the login lasts until
// the admin presses Logout (or 30 days pass).

$sessionLifetime = 60 * 60 * 24 * 30; // 30 days

// Keep sessions in the project's own folder so other apps on the same
// server can't clean them up early.
$sessionPath = __DIR__ . "/sessions";

if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0700, true);
}

if (is_dir($sessionPath) && is_writable($sessionPath)) {
    session_save_path($sessionPath);
}

ini_set("session.gc_maxlifetime", (string) $sessionLifetime);

session_set_cookie_params([
    "lifetime" => $sessionLifetime,
    "path"     => "/",
    "httponly" => true,
    "samesite" => "Lax",
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
