<?php

// Site-wide theme. Every page loads this file and prints theme_style_tag()
// right after style.css, so one color choice recolors the whole site:
// portfolio, login page and admin area.

require_once __DIR__ . "/db.php";

const THEME_DEFAULT_COLOR = "#38bdf8";
const THEME_DEFAULT_FONT  = "Poppins";
const THEME_DEFAULT_MODE  = "light";

function theme_presets(): array
{
    return [
        "#38bdf8" => "Sky Blue",
        "#22c55e" => "Green",
        "#14b8a6" => "Teal",
        "#a78bfa" => "Purple",
        "#f472b6" => "Pink",
        "#ef4444" => "Red",
        "#f97316" => "Orange",
    ];
}

// Two-color gradients: [name, first color, second color]
function theme_gradients(): array
{
    return [
        ["Sunset",   "#f97316", "#ec4899"],
        ["Ocean",    "#38bdf8", "#14b8a6"],
        ["Twilight", "#38bdf8", "#a78bfa"],
        ["Aurora",   "#22c55e", "#8b5cf6"],
        ["Berry",    "#8b5cf6", "#ec4899"],
        ["Fire",     "#ef4444", "#f97316"],
    ];
}

function theme_modes(): array
{
    return ["light" => "Light", "dark" => "Dark"];
}

function theme_fonts(): array
{
    return [
        "Poppins"     => "Poppins, sans-serif",
        "Arial"       => "Arial, sans-serif",
        "Georgia"     => "Georgia, serif",
        "Roboto Mono" => "'Roboto Mono', monospace",
        "Verdana"     => "Verdana, sans-serif",
        "Tahoma"      => "Tahoma, sans-serif",
    ];
}

function theme_is_hex(string $value): bool
{
    return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $value);
}

// "#22c55e" -> 142 (the hue, 0-360)
function theme_hue(string $hex): int
{
    $r = hexdec(substr($hex, 1, 2)) / 255;
    $g = hexdec(substr($hex, 3, 2)) / 255;
    $b = hexdec(substr($hex, 5, 2)) / 255;

    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $d = $max - $min;

    if ($d == 0) {
        return 199;
    }

    if ($max == $r) {
        $h = fmod(($g - $b) / $d, 6);
    } elseif ($max == $g) {
        $h = ($b - $r) / $d + 2;
    } else {
        $h = ($r - $g) / $d + 4;
    }

    $h *= 60;

    if ($h < 0) {
        $h += 360;
    }

    return (int) round($h);
}

// Saved theme:
// ["color" => "#38bdf8", "color2" => "" (or a hex for gradients), "mode" => "light", "font" => "Poppins"]
function theme_load(bool $refresh = false): array
{
    global $conn;

    // Cached so one page load only asks the database once
    static $cached = null;

    if ($cached !== null && !$refresh) {
        return $cached;
    }

    $theme = [
        "color"  => THEME_DEFAULT_COLOR,
        "color2" => "",
        "mode"   => THEME_DEFAULT_MODE,
        "font"   => THEME_DEFAULT_FONT,
    ];

    try {

        $conn->query(
            "CREATE TABLE IF NOT EXISTS admin_settings (
                admin_id INT NOT NULL PRIMARY KEY,
                theme_color VARCHAR(20) NOT NULL DEFAULT '#38bdf8',
                theme_color2 VARCHAR(20) NOT NULL DEFAULT '',
                theme_mode VARCHAR(10) NOT NULL DEFAULT 'light',
                font_family VARCHAR(40) NOT NULL DEFAULT 'Poppins'
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // Older installs: add the new columns once
        $columns = [];
        $cols = $conn->query("SHOW COLUMNS FROM admin_settings");

        while ($cols && $c = $cols->fetch_assoc()) {
            $columns[] = $c["Field"];
        }

        if (!in_array("theme_color2", $columns, true)) {
            $conn->query("ALTER TABLE admin_settings ADD theme_color2 VARCHAR(20) NOT NULL DEFAULT ''");
        }

        if (!in_array("theme_mode", $columns, true)) {
            $conn->query("ALTER TABLE admin_settings ADD theme_mode VARCHAR(10) NOT NULL DEFAULT 'light'");
        }

        $result = $conn->query(
            "SELECT theme_color, theme_color2, theme_mode, font_family
             FROM admin_settings
             ORDER BY admin_id ASC LIMIT 1"
        );

        if ($result && $row = $result->fetch_assoc()) {

            if (theme_is_hex($row["theme_color"])) {
                $theme["color"] = strtolower($row["theme_color"]);
            }

            if (theme_is_hex($row["theme_color2"])) {
                $theme["color2"] = strtolower($row["theme_color2"]);
            }

            if (array_key_exists($row["theme_mode"], theme_modes())) {
                $theme["mode"] = $row["theme_mode"];
            }

            if (array_key_exists($row["font_family"], theme_fonts())) {
                $theme["font"] = $row["font_family"];
            }
        }

    } catch (Throwable $e) {
        // fall back to the default theme
    }

    $cached = $theme;

    return $theme;
}

// Goes inside <head>, right after the style.css link
function theme_style_tag(): string
{
    $theme = theme_load();

    $hue  = theme_hue($theme["color"]);
    $hue2 = $theme["color2"] !== "" ? theme_hue($theme["color2"]) : $hue;
    $dark = $theme["mode"] === "dark";

    $css = ":root { --h: " . $hue . "; --h2: " . $hue2 . "; --f: " . ($dark ? 1 : 0)
         . "; color-scheme: " . ($dark ? "dark" : "light") . "; }";

    // Poppins is the original design, so only other fonts need an override.
    if ($theme["font"] !== THEME_DEFAULT_FONT) {
        $stack = theme_fonts()[$theme["font"]];
        $css .= " body, body *:not(iframe):not(i) { font-family: " . $stack . " !important; }";
    }

    return "<style id=\"theme-style\">" . $css . "</style>";
}

// Goes inside the <html ...> tag
function theme_html_attrs(): string
{
    $theme = theme_load();

    return $theme["color2"] !== "" ? " data-grad=\"1\"" : "";
}


// ---------------------------------------------------------------
// Social links (shown as icons in the portfolio footer)
// ---------------------------------------------------------------

function social_networks(): array
{
    return [
        "facebook" => [
            "label"       => "Facebook",
            "column"      => "facebook_url",
            "icon"        => "fa-brands fa-facebook-f",
            "placeholder" => "https://facebook.com/your-name",
            "type"        => "link",
        ],
        "instagram" => [
            "label"       => "Instagram",
            "column"      => "instagram_url",
            "icon"        => "fa-brands fa-instagram",
            "placeholder" => "https://instagram.com/your-name",
            "type"        => "link",
        ],
        "discord" => [
            "label"       => "Discord",
            "column"      => "discord_url",
            "icon"        => "fa-brands fa-discord",
            "placeholder" => "https://discord.gg/your-invite  or  https://discord.com/users/YOUR_ID",
            "type"        => "link",
        ],
        "gmail" => [
            "label"       => "Gmail",
            "column"      => "gmail_email",
            "icon"        => "fa-solid fa-envelope",
            "placeholder" => "yourname@gmail.com",
            "type"        => "email",
        ],
    ];
}

// Adds the social columns to the admins table the first time they are needed
function social_ensure_columns(): void
{
    global $conn;

    try {

        $existing = [];
        $cols = $conn->query("SHOW COLUMNS FROM admins");

        while ($cols && $c = $cols->fetch_assoc()) {
            $existing[] = $c["Field"];
        }

        foreach (social_networks() as $network) {
            if (!in_array($network["column"], $existing, true)) {
                $conn->query(
                    "ALTER TABLE admins ADD " . $network["column"] . " VARCHAR(255) DEFAULT NULL"
                );
            }
        }

    } catch (Throwable $e) {
        // the columns will simply stay empty
    }
}

// "" = empty, null = not valid, otherwise a clean http(s) link
// (or a clean email address when $type is "email")
function social_normalize(string $value, string $type = "link"): ?string
{
    $value = trim($value);

    if ($value === "") {
        return "";
    }

    if ($type === "email") {

        if (!filter_var($value, FILTER_VALIDATE_EMAIL) || mb_strlen($value) > 255) {
            return null;
        }

        return $value;
    }

    if (!preg_match('#^https?://#i', $value)) {
        $value = "https://" . $value;
    }

    if (!filter_var($value, FILTER_VALIDATE_URL) || mb_strlen($value) > 255) {
        return null;
    }

    return $value;
}

// What the icon opens: the link itself, or a new email to the address
function social_href(string $key, string $value): string
{
    $networks = social_networks();

    if (($networks[$key]["type"] ?? "link") === "email") {
        return "mailto:" . $value;
    }

    return $value;
}

// Links that are filled in: ["facebook" => "https://...", ...]
function social_load(): array
{
    global $conn;

    social_ensure_columns();

    $links = [];

    try {

        $result = $conn->query(
            "SELECT facebook_url, instagram_url, discord_url, gmail_email
             FROM admins ORDER BY id ASC LIMIT 1"
        );

        if ($result && $row = $result->fetch_assoc()) {

            foreach (social_networks() as $key => $network) {

                $clean = social_normalize((string) ($row[$network["column"]] ?? ""), $network["type"]);

                if ($clean !== null && $clean !== "") {
                    $links[$key] = $clean;
                }
            }
        }

    } catch (Throwable $e) {
        // show no icons
    }

    return $links;
}
