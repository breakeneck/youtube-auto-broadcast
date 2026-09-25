<?php

date_default_timezone_set("Europe/Kiev");
require __DIR__ . "/vendor/autoload.php";
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
foreach (["YOUTUBE_AUTH_FILE", "SHEETS_CREDENTIALS"] as $key) {
    if (isset($_ENV[$key]) && !str_starts_with($_ENV[$key], "/")) {
        $_ENV[$key] = __DIR__ . "/" . $_ENV[$key];
    }
}

$client = new \Google\Client();
$client->setApplicationName('diag');
$client->setScopes(['https://www.googleapis.com/auth/spreadsheets.readonly']);
$client->setAuthConfig($_ENV['SHEETS_CREDENTIALS']);
$service = new \Google\Service\Sheets($client);

// Fetch everything (no filter)
$resp = $service->spreadsheets_values->get($_ENV['SPREADSHEET_ID'], $_ENV['SHEET_ID'] . "!A1:I50");
$raw = $resp->getValues();
echo "Total rows in SB!A1:I50: " . count($raw) . "\n\n";

// Show last few rows (closest to today's row the user added)
$count = count($raw);
$start = max(0, $count - 10);
for ($i = $start; $i < $count; $i++) {
    $r = $raw[$i];
    $date = $r[1] ?? "";
    $time = $r[2] ?? "";
    $duration = $r[3] ?? "";
    $user = $r[7] ?? "";
    $rowNum = $i + 1;
    echo "Row $rowNum: date='$date' time='$time' duration='$duration' user='$user'\n";
}

echo "\nCurrent local time: " . date("Y-m-d H:i:s") . "\n";
echo "Today (d.m.Y): " . date("d.m.Y") . "\n";