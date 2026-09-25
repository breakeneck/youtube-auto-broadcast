<?php

require_once __DIR__ . "/vendor/autoload.php";

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Resolve relative file paths against the project root
foreach (["YOUTUBE_AUTH_FILE", "SHEETS_CREDENTIALS"] as $key) {
    if (isset($_ENV[$key]) && !str_starts_with($_ENV[$key], "/")) {
        $_ENV[$key] = __DIR__ . "/" . $_ENV[$key];
    }
}

$state = new \App\SimpleState();

app()->template->config("path", __DIR__ . "/views");

app()->config(["debug" => $_ENV["APP_DEBUG"]]);

// --- авторизація адміна (PHP-сессія, cookie HttpOnly + SameSite=Lax) ---
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

function is_admin(): bool
{
    return !empty($_SESSION['is_admin']);
}

function require_admin(): void
{
    if (!is_admin()) {
        http_response_code(403);
        exit('Forbidden: тільки для адміна');
    }
}

// Get current scheduled row if broadcast is running
$currentScheduledRow = null;
if ($state->getAttr("id") && $state->getAttr("scheduled_row")) {
    $scheduledData = $state->getAttr("scheduled_row");
    $currentScheduledRow = new \App\Row(...$scheduledData);
}

app()->get("/", function () use ($state, $currentScheduledRow) {
    $lastRows = \App\GoogleSheet::getRowsAfterToday();
    //    print_r($lastRows);die;

    echo app()->template->render("index", [
        "state" => $state,
        "lastRows" => $lastRows ?? [],
        "currentScheduledRow" => $currentScheduledRow,
        "isAdmin" => is_admin(),
        "castBackend" => $state->getAttr("cast_backend") ?: "ffmpeg",
    ]);
});

$renderLogin = function (?string $error = null) {
    echo app()->template->render("login", ["error" => $error]);
};

app()->get("/login", function () use ($renderLogin) {
    if (is_admin()) {
        app()->response()->redirect("/");
    }
    $renderLogin();
});

app()->post("/login", function () use ($renderLogin) {
    $login = (string)($_POST["login"] ?? "");
    $password = (string)($_POST["password"] ?? "");
    $hash = (string)($_ENV["ADMIN_PASSWORD_HASH"] ?? "");
    $adminUser = (string)($_ENV["ADMIN_USER"] ?? "");

    if ($adminUser !== "" && $hash !== "" && $login === $adminUser && password_verify($password, $hash)) {
        session_regenerate_id(true);
        $_SESSION["is_admin"] = true;
        app()->response()->redirect("/");
        return;
    }
    usleep(500000); // сповільнюємо брутфорс
    $renderLogin("Невірний логін або пароль");
});

app()->get("/logout", function () {
    $_SESSION = [];
    session_destroy();
    app()->response()->redirect("/");
});

// збереження вибраного бекенда трансляції (OBS / ffmpeg) — одразу з UI
app()->post("/backend", function () use ($state) {
    require_admin();
    $backend = (string)($_POST["backend"] ?? "");
    if (!in_array($backend, ["obs", "ffmpeg"], true)) {
        http_response_code(422);
        header("Content-Type: application/json");
        echo json_encode(["ok" => false]);
        return;
    }
    $state->setAttr("cast_backend", $backend);
    header("Content-Type: application/json");
    echo json_encode(["ok" => true, "backend" => $backend]);
});

app()->post("/start", function () use ($state) {
    require_admin();
    // старт: ~10 с на старт юніта + до 90 с на active стрім + API-виклики
    set_time_limit(180);
    if (!$state->getAttr("id") && !(($s = (int)$state->getAttr("starting")) && time() - $s < 600)) {
        $state->setAttr("starting", time());
        try {
            // «Don't notify»: анлістед-ефір без Telegram-анонсу
            $skipNotification = isset($_POST["skip_notification"]);

            $scenario = new App\Scenario();
            $scenario->startObs($state->getAttr("cast_backend") ?: "ffmpeg");
            $scenario->wait(10);

            $decor = new \App\Decor($_POST);
            $broadcastId = $scenario->startBroadcast(
                $decor->getTitle(),
                $decor->getDescription(),
                120,
                $skipNotification ? "unlisted" : $_ENV["YOUTUBE_PRIVACY"],
            );
            if (!$skipNotification) {
                $scenario->notify($broadcastId, $decor->getDescription());
            }

            $state->setAttr("id", $broadcastId);
        } finally {
            $state->setAttr("starting", null);
        }
    }

    app()->response()->redirect("/");
});

app()->post("/stop", function () use ($state) {
    require_admin();
    $broadcastId = $state->getAttr("id");

    $scenario = new App\Scenario();
    $scenario->finishBroadcast($broadcastId);
    $scenario->stopObs();

    $state->setAttr("id", null);
    $state->setAttr("scheduled_row", null);

    app()->response()->redirect("/");
});

app()->get("/reset", function () use ($state) {
    require_admin();
    $state->setAttr("id", null);
    $state->setAttr("scheduled_row", null);

    app()->response()->redirect("/");
});

app()->get("/exitapps", function () use ($state) {
    require_admin();
    $scenario = new App\Scenario();
    $scenario->stopObs();

    app()->response()->redirect("/");
});

app()->run();
