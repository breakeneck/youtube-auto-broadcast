<?php
/**
 * ISKCON Lutsk — smoke-тест транслюційного пайплайну.
 *
 * Запуск з хоста:      bash backend/tests/smoke.sh
 * всередині контейнера: php /var/www/cast.iskcon.lutsk.ua/backend/tests/smoke.php [--offline]
 *
 * Усі перевірки READ-ONLY: нічого не стартують і не зупиняють,
 * НИКОЛИ не виводять секрети (stream key, паролі, токени).
 *
 * Exit 0 — OK (можливі WARN), exit 1 — є FAIL.
 */

$OFFLINE = in_array('--offline', $argv, true);

$ROOT = dirname(__DIR__); // backend/
require $ROOT . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable($ROOT);
$dotenv->load();
foreach (['YOUTUBE_AUTH_FILE', 'SHEETS_CREDENTIALS'] as $key) {
    if (isset($_ENV[$key]) && !str_starts_with($_ENV[$key], '/')) {
        $_ENV[$key] = $ROOT . '/' . $_ENV[$key];
    }
}

$GLOBALS['fail'] = 0;
$GLOBALS['warn'] = 0;
function ok(string $m): void  { echo "  [OK]   $m\n"; }
function bad(string $m): void { $GLOBALS['fail']++; echo "  [FAIL] $m\n"; }
function wrn(string $m): void { $GLOBALS['warn']++; echo "  [WARN] $m\n"; }
function sec(string $t): void { echo "\n== $t ==\n"; }

// ---------------------------------------------------------------- 1. PHP lint
sec('PHP lint');
$lintFiles = array_merge(
    [$ROOT . '/server.php', $ROOT . '/index.php'],
    glob($ROOT . '/src/yuri/*.php') ?: []
);
foreach ($lintFiles as $f) {
    $rc = 0;
    $out = [];
    exec('php -l ' . escapeshellarg($f) . ' 2>&1', $out, $rc);
    $name = basename($f);
    if ($rc === 0) {
        ok("lint $name");
    } else {
        bad("lint $name: " . trim($out[0] ?? 'unknown error'));
    }
}

// ---------------------------------------------------------------- 2. Конфіг
sec('Конфіг (.env / файли)');
$required = [
    'YOUTUBE_AUTH_FILE', 'YOUTUBE_STREAM_ID', 'YOUTUBE_PRIVACY',
    'OBS_HOST', 'OBS_PORT', 'OBS_USERNAME', 'OBS_PASSWORD',
    'TG_API_TOKEN', 'TG_CHAT_ID',
    'SHEETS_CREDENTIALS', 'SPREADSHEET_ID', 'SHEET_ID',
];
$missing = [];
foreach ($required as $key) {
    if (empty($_ENV[$key])) {
        $missing[] = $key;
    }
}
if ($missing) {
    bad('у .env немає ключів: ' . implode(', ', $missing));
} else {
    ok('усі ' . count($required) . ' обов\'язкові ключі .env присутні');
}
foreach (['YOUTUBE_AUTH_FILE', 'SHEETS_CREDENTIALS'] as $key) {
    $p = $_ENV[$key] ?? '';
    if ($p && file_exists($p)) {
        ok("$key файл існує");
        if ($key === 'YOUTUBE_AUTH_FILE') {
            json_decode(file_get_contents($p));
            if (json_last_error() === JSON_ERROR_NONE) {
                ok('auth.json валідний JSON');
            } else {
                bad('auth.json НЕ є валідним JSON');
            }
        }
    } else {
        bad("$key файл не знайдено: $p");
    }
}

// ---------------------------------------------------------------- 3. Стан
sec('Стан (state.json)');
$stateFile = $ROOT . '/data/state.json';
$state = null;
if (file_exists($stateFile)) {
    $state = (array)json_decode(file_get_contents($stateFile));
    ok('state.json читається');
    $id = $state['id'] ?? null;
    if ($id) {
        wrn("зараз іде ефір (id=$id) — активний ffmpeg-cast це нормально");
    } else {
        ok('ефір не іде (id=null)');
    }
    $starting = (int)($state['starting'] ?? 0);
    if ($starting && time() - $starting > 600) {
        wrn('starting-лок старше 10 хв — хтось вмер посеред старту; видаліть "starting" зі state.json');
    }
} else {
    bad('state.json не знайдено');
}

// ---------------------------------------------------------------- 4. Cron
sec('Cron (check-schedule)');
$cronLog = $ROOT . '/data/cron.cast.log';
if (file_exists($cronLog)) {
    $age = time() - filemtime($cronLog);
    if ($age <= 180) {
        ok("cron.cast.log свіжий ($age с тому) — cron живий");
    } else {
        bad("cron.cast.log не оновлювався $age с (> 3 хв) — cron ліг? Перевірити: crontab -l на хості");
    }
} else {
    bad('cron.cast.log не знайдено');
}

// ---------------------------------------------------------------- 5. YouTube
if (!$OFFLINE) {
    sec('YouTube API');
    try {
        $yt = new \App\Youtube($_ENV['YOUTUBE_AUTH_FILE']);
        $status = $yt->getStreamStatus($_ENV['YOUTUBE_STREAM_ID']);
        if (in_array($status, ['inactive', 'active', 'error'], true)) {
            ok("стрім знайдено, статус: $status");
        } else {
            bad("стрім $_ENV[YOUTUBE_STREAM_ID] не знайдено (unknown) — перевірте YOUTUBE_STREAM_ID");
        }
        if (!empty($state['id'])) {
            $lc = $yt->getBroadcastLifeCycle($state['id']);
            ok("поточний ефір lifeCycle=$lc");
        }
    } catch (\Throwable $e) {
        bad('YouTube API недоступний (token міг протухнути): ' . mb_substr($e->getMessage(), 0, 160));
    }
} else {
    sec('YouTube API (пропущено: --offline)');
}

// ---------------------------------------------------------------- 6. Sheets
if (!$OFFLINE) {
    sec('Google Sheets API');
    try {
        $rows = \App\GoogleSheet::getRowsAfterToday(1);
        ok('розклад читається, рядків на завтра: ' . count($rows));
    } catch (\Throwable $e) {
        bad('Sheets API недоступний: ' . mb_substr($e->getMessage(), 0, 160));
    }
} else {
    sec('Google Sheets API (пропущено: --offline)');
}

// ---------------------------------------------------------------- 7. OBS-машина
if (!$OFFLINE) {
    sec('OBS-машина (' . ($_ENV['OBS_HOST'] ?? '?') . ')');
    $ssh = null;
    try {
        $ssh = new phpseclib3\Net\SSH2($_ENV['OBS_HOST'], $_ENV['OBS_PORT']);
        if (!$ssh->login($_ENV['OBS_USERNAME'], $_ENV['OBS_PASSWORD'])) {
            bad('SSH-логін не вдався');
            $ssh = null;
        } else {
            ok('SSH-з\'єднання є');
        }
    } catch (\Throwable $e) {
        bad('SSH недоступний: ' . mb_substr($e->getMessage(), 0, 120));
        $ssh = null;
    }

    if ($ssh) {
        $exec = function (string $cmd) use ($ssh): string {
            return trim((string)$ssh->exec($cmd));
        };

        // юніт запускається на вимогу (systemctl start), тому is-enabled тут не очікується
        $unit = $exec('systemctl --user cat ffmpeg-cast 2>&1');
        if (str_contains($unit, 'ExecStart')) {
            ok('юніт ffmpeg-cast.service існує');
        } else {
            bad('юніт ffmpeg-cast.service НЕ знайдено — плановий ефір не стартує!');
        }

        $active = $exec('systemctl --user is-active ffmpeg-cast 2>&1');
        $broadcastRunning = !empty($state['id']);
        if ($active === 'inactive' && !$broadcastRunning) {
            ok('ffmpeg-cast зупинений (ефіру немає — так і має бути)');
        } elseif ($active === 'failed' && !$broadcastRunning) {
            // ffmpeg після SIGTERM виходить з кодом 255 -> systemd мітить 'failed'; наступний start це ігнорує
            ok("ffmpeg-cast idle (стан 'failed' після SIGTERM-зупинки — норма для ffmpeg)");
        } elseif ($active === 'active' && $broadcastRunning) {
            ok('ffmpeg-cast працює під час ефіру — ок');
        } elseif ($active === 'active') {
            wrn('ffmpeg-cast active, а ефіру в state немає — когось пушить у YouTube?');
        } else {
            wrn('ffmpeg-cast status: ' . mb_substr($active, 0, 40));
        }

        // OBS має бути зупинений — інакше тримає /dev/video0 і ffmpeg не стартує
        $obsProc = $exec('pgrep -x obs6 2>/dev/null; pgrep -x obs 2>/dev/null');
        if ($obsProc === '') {
            ok('OBS не запущений (/dev/video0 вільний)');
        } else {
            bad('OBS запущений і блокує /dev/video0 — ffmpeg-ефір не зможе стартувати!');
        }
        $obsStart = $exec('systemctl --user is-active obs-start 2>&1');
        if ($obsStart !== 'active') {
            ok('юніт obs-start не активний');
        } else {
            bad('obs-start активний — конфліктує з ffmpeg-cast');
        }

        $dev = $exec('[ -e /dev/video0 ] && echo yes || echo no');
        if ($dev === 'yes') {
            ok('/dev/video0 існує');
        } else {
            bad('/dev/video0 НЕ існує — камера від\'єднана?');
        }

        $ff = $exec('command -v ffmpeg >/dev/null && echo yes || echo no');
        if ($ff === 'yes') {
            ok('ffmpeg встановлений');
        } else {
            bad('ffmpeg не знайдено в PATH');
        }

        $vlc = $exec('systemctl --user is-active vlc 2>&1');
        if (in_array($vlc, ['inactive', 'unknown', 'failed'], true)) {
            ok('vlc.service зупинений');
        } else {
            wrn("vlc.service: $vlc (може конфліктувати за мікрофон)");
        }

        // Ключ у юніті має збігатися з поточним ключем стріму в YouTube
        if (preg_match('#rtmps://[^\s"\']+/(?:live2?|a)/([A-Za-z0-9\-]+)#', $unit, $m)) {
            $unitKey = $m[1];
            try {
                $auth = new \App\GoogleAuth($_ENV['YOUTUBE_AUTH_FILE']);
                $svc = new \Google_Service_YouTube($auth->getClient());
                $resp = $svc->liveStreams->listLiveStreams('cdn', ['id' => $_ENV['YOUTUBE_STREAM_ID']]);
                $item = $resp->getItems()[0] ?? null;
                $apiKey = $item ? $item->getCdn()->getIngestionInfo()->streamName : null;
                if ($apiKey === null) {
                    wrn('не вдалося отримати ключ стріму з API для порівняння');
                } elseif ($apiKey === $unitKey) {
                    ok('stream key у юніті збігається з YouTube (значення не виведено)');
                } else {
                    bad('ВАЖЛИВО: stream key у ffmpeg-cast.service НЕ збігається з YouTube — ключ міг бути скинутий! (значення не виведено)');
                }
            } catch (\Throwable $e) {
                wrn('не вдалося перевірити ключ через API: ' . mb_substr($e->getMessage(), 0, 120));
            }
        } else {
            bad('у ExecStart юніта ffmpeg-cast немає rtmps:// URL з ключем');
        }
    }
} else {
    sec('OBS-машина (пропущено: --offline)');
}

// ---------------------------------------------------------------- 8. Telegram
sec('Telegram');
if (!empty($_ENV['TG_API_TOKEN']) && !empty($_ENV['TG_CHAT_ID'])) {
    ok('Telegram налаштований (повідомлення навмисно не надсилаються)');
} else {
    bad('Telegram не налаштований');
}

// ---------------------------------------------------------------- Підсумок
echo "\n" . str_repeat('=', 46) . "\n";
if ($GLOBALS['fail'] === 0) {
    echo "РЕЗУЛЬТАТ: OK  (0 FAIL, {$GLOBALS['warn']} WARN)\n";
    exit(0);
}
echo "РЕЗУЛЬТАТ: FAIL  ({$GLOBALS['fail']} FAIL, {$GLOBALS['warn']} WARN)\n";
exit(1);