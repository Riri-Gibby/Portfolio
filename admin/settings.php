<?php

require_once __DIR__ . "/../session.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../theme.php";

$id = (int) $_SESSION["admin_id"];
$error = "";
$success = "";

// Current account
$q = $conn->prepare("SELECT username, password FROM admins WHERE id = ?");
$q->bind_param("i", $id);
$q->execute();
$admin = $q->get_result()->fetch_assoc();
$q->close();

$username = $admin["username"] ?? ($_SESSION["admin_username"] ?? "");

// Current appearance (the same values the whole site uses)
$theme  = theme_load();
$color  = $theme["color"];
$color2 = $theme["color2"];
$mode   = $theme["mode"];
$font   = $theme["font"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $current  = $_POST["current_password"] ?? "";
    $new      = $_POST["new_password"] ?? "";
    $confirm  = $_POST["confirm_password"] ?? "";
    $color    = strtolower(trim($_POST["theme_color"] ?? ""));
    $color2   = strtolower(trim($_POST["theme_color2"] ?? ""));
    $mode     = $_POST["theme_mode"] ?? "";
    $font     = $_POST["font_family"] ?? "";

    if ($username === "" || mb_strlen($username) > 50) {

        $error = "Username is required and must be 50 characters or fewer.";

    } elseif (
        !theme_is_hex($color) ||
        ($color2 !== "" && !theme_is_hex($color2)) ||
        !array_key_exists($mode, theme_modes()) ||
        !array_key_exists($font, theme_fonts())
    ) {

        $error = "Choose a valid color, mode and font.";

    } elseif (
        $new !== "" &&
        !(password_verify($current, $admin["password"]) ||
          hash_equals($admin["password"], $current))
    ) {

        $error = "Your current password is incorrect.";

    } elseif ($new !== "" && strlen($new) < 8) {

        $error = "New password must be at least 8 characters.";

    } elseif ($new !== "" && $new !== $confirm) {

        $error = "The new passwords do not match.";

    } else {

        $q = $conn->prepare("SELECT id FROM admins WHERE username = ? AND id <> ?");
        $q->bind_param("si", $username, $id);
        $q->execute();
        $taken = $q->get_result()->num_rows > 0;
        $q->close();

        if ($taken) {

            $error = "That username is already in use.";

        } else {

            if ($new !== "") {
                $hashed = password_hash($new, PASSWORD_DEFAULT);
                $q = $conn->prepare("UPDATE admins SET username = ?, password = ? WHERE id = ?");
                $q->bind_param("ssi", $username, $hashed, $id);
            } else {
                $q = $conn->prepare("UPDATE admins SET username = ? WHERE id = ?");
                $q->bind_param("si", $username, $id);
            }

            if ($q->execute()) {

                $q->close();

                $q = $conn->prepare(
                    "INSERT INTO admin_settings (admin_id, theme_color, theme_color2, theme_mode, font_family)
                     VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        theme_color = VALUES(theme_color),
                        theme_color2 = VALUES(theme_color2),
                        theme_mode = VALUES(theme_mode),
                        font_family = VALUES(font_family)"
                );
                $q->bind_param("issss", $id, $color, $color2, $mode, $font);
                $q->execute();
                $q->close();

                // show the saved theme on this very page
                theme_load(true);

                $_SESSION["admin_username"] = $username;
                $success = "Settings saved successfully.";

            } else {

                $q->close();
                $error = "Unable to save settings.";
            }
        }
    }
}

$presets   = theme_presets();
$gradients = theme_gradients();
$gradColor2 = $color2 !== "" ? $color2 : "#a78bfa";

?>
<!DOCTYPE html>
<html lang="en"<?= theme_html_attrs() ?>>

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Settings</title>

    <link rel="stylesheet" href="../style.css?v=<?= filemtime(__DIR__ . '/../style.css') ?>">

    <?= theme_style_tag() ?>

    <style>
        .settings-panel {
            max-width: 760px;
            margin: 45px auto;
            padding: clamp(22px, 4vw, 42px);
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: 0 12px 40px var(--shadow);
        }

        .settings-panel h1 { margin-bottom: 8px; color: var(--white); }
        .settings-panel h2 { margin: 18px 0 6px; color: var(--white); }
        .settings-help, .settings-note { color: var(--gray); font-size: 13px; }
        .settings-help { margin-bottom: 24px; }
        .settings-panel hr { border: 0; border-top: 1px solid var(--border); margin: 22px 0 6px; }

        .settings-form { display: grid; gap: 10px; }

        .settings-form label {
            font-weight: 600;
            font-size: 13px;
            margin-top: 8px;
            color: var(--crimson-dark);
        }

        .settings-form input[type="text"],
        .settings-form input[type="password"],
        .settings-form select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: var(--sky-pale);
            color: var(--white);
            font: inherit;
        }

        .swatches {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            margin: 6px 0;
        }

        .swatch {
            width: 42px;
            height: 42px;
            padding: 0;
            border-radius: 50%;
            border: 3px solid var(--card);
            box-shadow: 0 0 0 1px var(--border), 0 4px 12px var(--shadow);
            transition: .2s;
        }

        .swatch:hover { transform: scale(1.1); }

        .swatch.active {
            box-shadow: 0 0 0 3px var(--crimson-dark), 0 4px 12px var(--shadow);
        }

        .swatch-title {
            width: 100%;
            font-size: 12px;
            color: var(--gray);
            margin-top: 6px;
        }

        .mode-options {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin: 6px 0;
        }

        .mode-option {
            flex: 1;
            min-width: 140px;
            padding: 14px 16px;
            border-radius: 12px;
            border: 2px solid var(--border);
            background: var(--sky-pale);
            color: var(--white);
            font: inherit;
            font-size: 13px;
            font-weight: 600;
            text-align: center;
            transition: .2s;
        }

        .mode-option:hover { transform: translateY(-2px); }

        .mode-option.active {
            border-color: var(--crimson-dark);
            box-shadow: 0 0 0 3px var(--shadow);
        }

        .custom-color {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: var(--gray);
        }

        .custom-color input[type="color"] {
            width: 42px;
            height: 42px;
            padding: 0;
            border: 0;
            border-radius: 50%;
            background: none;
            cursor: pointer;
        }

        .settings-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .settings-message { padding: 12px 15px; border-radius: 9px; margin: 15px 0; }
        .settings-message.error { background: #fff1f2; color: #be123c; }
        .settings-message.success { background: #ecfdf5; color: #047857; }
    </style>

</head>

<body>

<header class="admin-navbar">

    <h2>Settings</h2>

    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="../index.php">View Portfolio</a>
    </div>

</header>


<main class="settings-panel">

    <h1>Account &amp; Appearance</h1>

    <p class="settings-help">
        Update your login details and choose how your whole portfolio looks.
    </p>

    <?php if ($error !== ""): ?>
        <div class="settings-message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success !== ""): ?>
        <div class="settings-message success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>


    <form method="POST" class="settings-form" autocomplete="off">

        <h2>Light or dark</h2>

        <p class="settings-note">
            Choose a light or a dark look for the whole site.
        </p>

        <input type="hidden" name="theme_mode" id="theme_mode" value="<?= htmlspecialchars($mode) ?>">

        <div class="mode-options" id="mode-options">
            <?php foreach (theme_modes() as $key => $label): ?>
                <button
                    type="button"
                    class="mode-option<?= $mode === $key ? " active" : "" ?>"
                    data-mode="<?= $key ?>"
                >
                    <?= $key === "dark" ? "&#9790;" : "&#9728;" ?> <?= htmlspecialchars($label) ?>
                </button>
            <?php endforeach; ?>
        </div>


        <h2>Theme color</h2>

        <p class="settings-note">
            Pick a solid color or a gradient and the entire site changes to match: portfolio, login page and admin area.
            You can preview it here before saving.
        </p>

        <input type="hidden" name="theme_color" id="theme_color" value="<?= htmlspecialchars($color) ?>">
        <input type="hidden" name="theme_color2" id="theme_color2" value="<?= htmlspecialchars($color2) ?>">

        <div class="swatches" id="solid-swatches">

            <div class="swatch-title">Solid colors</div>

            <?php foreach ($presets as $hex => $name): ?>
                <button
                    type="button"
                    class="swatch"
                    data-color="<?= $hex ?>"
                    title="<?= htmlspecialchars($name) ?>"
                    aria-label="<?= htmlspecialchars($name) ?>"
                    style="background: <?= $hex ?>;"
                ></button>
            <?php endforeach; ?>

            <label class="custom-color">
                <input type="color" id="custom_color" value="<?= htmlspecialchars($color) ?>">
                Custom
            </label>

        </div>

        <div class="swatches" id="gradient-swatches">

            <div class="swatch-title">Gradients</div>

            <?php foreach ($gradients as [$name, $c1, $c2]): ?>
                <button
                    type="button"
                    class="swatch"
                    data-c1="<?= $c1 ?>"
                    data-c2="<?= $c2 ?>"
                    title="<?= htmlspecialchars($name) ?>"
                    aria-label="<?= htmlspecialchars($name) ?> gradient"
                    style="background: linear-gradient(135deg, <?= $c1 ?>, <?= $c2 ?>);"
                ></button>
            <?php endforeach; ?>

            <label class="custom-color">
                <input type="color" id="grad_c1" value="<?= htmlspecialchars($color) ?>">
                <input type="color" id="grad_c2" value="<?= htmlspecialchars($gradColor2) ?>">
                Custom gradient
            </label>

        </div>


        <label for="font_family">Font family</label>

        <select id="font_family" name="font_family">
            <?php foreach (array_keys(theme_fonts()) as $f): ?>
                <option value="<?= htmlspecialchars($f) ?>" <?= $font === $f ? "selected" : "" ?>>
                    <?= htmlspecialchars($f) ?>
                </option>
            <?php endforeach; ?>
        </select>


        <hr>

        <h2>Username</h2>

        <label for="username">Username</label>

        <input id="username" type="text" name="username" maxlength="50" value="<?= htmlspecialchars($username) ?>" required>


        <hr>

        <h2>Change password</h2>

        <p class="settings-note">
            Leave the password fields empty to keep your current password. New passwords need at least 8 characters.
        </p>

        <label for="current_password">Current password (required to change password)</label>
        <input id="current_password" name="current_password" type="password" autocomplete="current-password">

        <label for="new_password">New password</label>
        <input id="new_password" name="new_password" type="password" minlength="8" autocomplete="new-password">

        <label for="confirm_password">Confirm new password</label>
        <input id="confirm_password" name="confirm_password" type="password" minlength="8" autocomplete="new-password">


        <div class="settings-actions">
            <button class="btn" type="submit">Save Settings</button>
            <a class="btn" href="dashboard.php">Cancel</a>
        </div>

    </form>

</main>


<script>
(function () {

    var root = document.documentElement;

    var fieldColor = document.getElementById("theme_color");
    var fieldColor2 = document.getElementById("theme_color2");
    var fieldMode = document.getElementById("theme_mode");

    var customSolid = document.getElementById("custom_color");
    var gradPick1 = document.getElementById("grad_c1");
    var gradPick2 = document.getElementById("grad_c2");

    var solidSwatches = document.querySelectorAll("#solid-swatches .swatch");
    var gradSwatches = document.querySelectorAll("#gradient-swatches .swatch");
    var modeButtons = document.querySelectorAll(".mode-option");

    // "#22c55e" -> hue 0-360
    function hexToHue(hex) {

        var r = parseInt(hex.substr(1, 2), 16) / 255;
        var g = parseInt(hex.substr(3, 2), 16) / 255;
        var b = parseInt(hex.substr(5, 2), 16) / 255;

        var max = Math.max(r, g, b);
        var min = Math.min(r, g, b);
        var d = max - min;

        if (d === 0) {
            return 199;
        }

        var h;

        if (max === r) {
            h = ((g - b) / d) % 6;
        } else if (max === g) {
            h = (b - r) / d + 2;
        } else {
            h = (r - g) / d + 4;
        }

        h = Math.round(h * 60);

        return h < 0 ? h + 360 : h;
    }

    // live preview + remember the choice for the Save button
    function setColors(c1, c2) {

        fieldColor.value = c1;
        fieldColor2.value = c2;

        root.style.setProperty("--h", hexToHue(c1));
        root.style.setProperty("--h2", hexToHue(c2 !== "" ? c2 : c1));

        if (c2 !== "") {
            root.setAttribute("data-grad", "1");
        } else {
            root.removeAttribute("data-grad");
        }

        customSolid.value = c1;
        gradPick1.value = c1;

        if (c2 !== "") {
            gradPick2.value = c2;
        }

        solidSwatches.forEach(function (s) {
            s.classList.toggle("active", c2 === "" && s.getAttribute("data-color") === c1);
        });

        gradSwatches.forEach(function (s) {
            s.classList.toggle(
                "active",
                s.getAttribute("data-c1") === c1 && s.getAttribute("data-c2") === c2
            );
        });
    }

    function setMode(mode) {

        fieldMode.value = mode;

        root.style.setProperty("--f", mode === "dark" ? 1 : 0);
        root.style.colorScheme = mode === "dark" ? "dark" : "light";

        modeButtons.forEach(function (b) {
            b.classList.toggle("active", b.getAttribute("data-mode") === mode);
        });
    }

    solidSwatches.forEach(function (s) {
        s.addEventListener("click", function () {
            setColors(s.getAttribute("data-color"), "");
        });
    });

    gradSwatches.forEach(function (s) {
        s.addEventListener("click", function () {
            setColors(s.getAttribute("data-c1"), s.getAttribute("data-c2"));
        });
    });

    customSolid.addEventListener("input", function () {
        setColors(customSolid.value.toLowerCase(), "");
    });

    function customGradient() {
        setColors(gradPick1.value.toLowerCase(), gradPick2.value.toLowerCase());
    }

    gradPick1.addEventListener("input", customGradient);
    gradPick2.addEventListener("input", customGradient);

    modeButtons.forEach(function (b) {
        b.addEventListener("click", function () {
            setMode(b.getAttribute("data-mode"));
        });
    });

    // start from what is saved
    setColors(fieldColor.value, fieldColor2.value);
    setMode(fieldMode.value);

})();
</script>

</body>

</html>
