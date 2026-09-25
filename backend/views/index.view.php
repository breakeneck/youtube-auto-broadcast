<?php
/**
* @var \App\Row[] $lastRows
* @var \App\Row|null $currentScheduledRow
* @var bool $isAdmin
* @var string $castBackend
*/ ?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script
            src="https://code.jquery.com/jquery-3.7.1.slim.min.js"
            integrity="sha256-kmHvs0B+OpCW5GVHUNjv9rOmY0IvSIRcf7zGUDTDQM8="
            crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <title>ІССКОН Луцьк: пряма трансляція</title>

    <link rel="apple-touch-icon" sizes="180x180" href="/ico/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/ico/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/ico/favicon-16x16.png">
    <link rel="manifest" href="/ico/site.webmanifest">
    <link rel="mask-icon" href="/ico/safari-pinned-tab.svg" color="#5bbad5">
    <meta name="msapplication-TileColor" content="#da532c">
    <meta name="theme-color" content="#ffffff">

    <style>
        body { background: #f5f6f8; }
        .app-container { max-width: 720px; }

        .live-dot {
            width: 10px; height: 10px; border-radius: 50%;
            background: #dc3545; display: inline-block;
            animation: pulse 1.4s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%      { opacity: .35; transform: scale(.8); }
        }

        .row-scheduled-now {
            background: #fff8e6;
            border-left: 4px solid #ffc107 !important;
        }

        .schedule-meta { font-size: .82rem; color: #6c757d; }

        /* великі зручні кнопки на телефоні */
        @media (max-width: 575.98px) {
            .btn { padding-top: .5rem; padding-bottom: .5rem; }
            .schedule-actions .btn { min-width: 110px; }
        }
    </style>
</head>
<body>
<div class="app-container container px-3 py-4">

    <!-- Шапка -->
    <header class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h4 mb-0">
                <i class="bi bi-broadcast-pin text-primary"></i>
                ІССКОН Луцьк
            </h1>
            <div class="text-muted small">Пряма трансляція</div>
        </div>
        <div class="text-md-end">
            <div class="fw-semibold"><?= \App\Utils::getLocalTimeStr('now', 'EEEE')?></div>
            <div class="text-muted small"><?= \App\Utils::getLocalTimeStr('now', 'dd.MM.Y · HH:mm')?></div>
        </div>
    </header>

    <!-- Активна трансляція -->
    <?php if ($state->getAttr('id')):?>
        <div class="card border-danger mb-4 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="live-dot"></span>
                    <span class="fw-bold text-danger">Трансляція запущена</span>
                </div>
                <?php if ($currentScheduledRow):?>
                    <div class="text-muted mb-3"><?=htmlspecialchars((new \App\Decor($currentScheduledRow))->getTitle())?></div>
                <?php else:?>
                    <div class="mb-3"></div>
                <?php endif;?>
                <div class="d-grid d-sm-flex gap-2">
                    <a class="btn btn-outline-dark" target="_blank" rel="noopener"
                       href="https://www.youtube.com/watch?v=<?=$state->getAttr('id')?>">
                        <i class="bi bi-youtube"></i> Дивитись на YouTube
                    </a>
                    <?php if ($isAdmin):?>
                        <form method="post" action="/stop" class="flex-sm-grow-1">
                            <button type="submit" class="btn btn-danger w-100">
                                <span class="spinner-border spinner-border-sm visually-hidden" role="status" aria-hidden="true"></span>
                                <i class="bi bi-stop-circle"></i> Зупинити трансляцію
                            </button>
                        </form>
                    <?php endif;?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Розклад -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-calendar-week text-primary"></i> Розклад
        </div>
        <div class="list-group list-group-flush">
            <?php foreach ($lastRows as $row):?>
                <?php if (!$row->username && !$row->theme) continue ?>
                <?php
                $isScheduledNow = $row->isScheduledNow();
                $isCurrentlyRunning = $currentScheduledRow &&
                    $currentScheduledRow->dateFormatted() === $row->dateFormatted() &&
                    $currentScheduledRow->time === $row->time;
                ?>
                <div class="list-group-item <?=($isCurrentlyRunning || ($isScheduledNow && !$state->getAttr('id'))) ? 'row-scheduled-now' : ''?>">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="flex-grow-1" style="min-width: 0">
                            <div class="fw-semibold">
                                <?php if ($row->time):?>
                                    <i class="bi bi-clock text-muted"></i>
                                    <?=$row->dayOfWeek()?> <?=$row->time?>
                                <?php else:?>
                                    <i class="bi bi-calendar-day text-muted"></i>
                                    <?=$row->dateTableFormat()?>
                                <?php endif;?>
                                <?php if ($row->duration):?>
                                    <span class="badge text-bg-secondary ms-1"><?=$row->duration?> хв</span>
                                <?php endif;?>
                            </div>
                            <div class="schedule-meta text-truncate">
                                <?php if ($row->book && $row->verse):?>
                                    <a href="<?=(new \App\Decor($row))->getVedabaseUrl()?>" target="_blank" rel="noopener"
                                       class="text-decoration-none">
                                        <i class="bi bi-book"></i> <?=$row->book .' '. $row->verse?>
                                    </a>
                                    <?php if ($row->username || $row->theme):?><span class="mx-1">·</span><?php endif;?>
                                <?php endif;?>
                                <?php if ($row->username):?><?=htmlspecialchars($row->username)?><?php endif;?>
                                <?php if ($row->theme):?><?php if ($row->username):?> — <?php endif;?><?=htmlspecialchars($row->theme)?><?php endif;?>
                            </div>
                        </div>
                        <?php if ($isAdmin && $row->isValid()):?>
                            <div class="schedule-actions">
                                <?php if ($isCurrentlyRunning):?>
                                    <button type="button" class="btn btn-warning btn-sm" disabled>
                                        <i class="bi bi-broadcast"></i> Зараз
                                    </button>
                                <?php elseif ($isScheduledNow && !$state->getAttr('id')):?>
                                    <button type="button" class="go btn btn-success"
                                            data-username="<?=$row->username?>"
                                            data-verse="<?=$row->verse?>"
                                            data-book="<?=$row->book?>"
                                    >
                                        <span class="spinner-border spinner-border-sm visually-hidden" role="status" aria-hidden="true"></span>
                                        <i class="bi bi-play-fill"></i> Старт
                                    </button>
                                <?php elseif ($row->time && !$state->getAttr('id')):?>
                                    <?php /* час уже показаний у лівій колонці */ ?>
                                <?php elseif (!$state->getAttr('id')):?>
                                    <button type="button" class="go btn btn-outline-success btn-sm"
                                            data-username="<?=$row->username?>"
                                            data-verse="<?=$row->verse?>"
                                            data-book="<?=$row->book?>"
                                    >
                                        <i class="bi bi-play-fill"></i> Go
                                    </button>
                                <?php endif;?>
                            </div>
                        <?php endif ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Ручний запуск (лише адмін) -->
    <?php if ($isAdmin && !$state->getAttr('id')):?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-magic text-primary"></i> Запустити вручну
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-3 pb-3 border-bottom">
                    <i class="bi bi-camera-reels text-primary"></i>
                    <label for="castBackend" class="form-label mb-0 fw-semibold">Бекенд трансляції</label>
                    <select id="castBackend" class="form-select form-select-sm w-auto ms-auto">
                        <option value="obs" <?=($castBackend === 'obs') ? 'selected' : ''?>>OBS (основний)</option>
                        <option value="ffmpeg" <?=($castBackend === 'ffmpeg') ? 'selected' : ''?>>ffmpeg (fallback)</option>
                    </select>
                </div>
                <form id="form" method="post" action="/start">
                    <div class="mb-3">
                        <label for="title" class="form-label">Назва ефіру <span class="text-muted small">(якщо вказана — решту полів не треба)</span></label>
                        <input id="title" name="title" type="text" class="form-control" placeholder="Напр.: Харі-катха">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label for="book" class="form-label">Книга</label>
                            <select id="book" name="book" class="form-select">
                                <option>---</option>
                                <?php foreach (\App\Decor::booksDropDown() as $book):?>
                                    <option value="<?=$book?>"><?=$book?></option>
                                <?php endforeach;?>
                            </select>
                        </div>
                        <div class="col-4">
                            <label for="verse" class="form-label">Вірш</label>
                            <input id="verse" name="verse" class="form-control" placeholder="1.1">
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="username" class="form-label">Лектор</label>
                            <input id="username" name="username" class="form-control" placeholder="Ім'я">
                        </div>
                    </div>
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="skip_notification" value="1" id="skipNotification">
                            <label class="form-check-label" for="skipNotification">
                                Don't notify <span class="text-muted small">(анлістед, без анонсу в Telegram)</span>
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary px-4">
                            <span class="spinner-border spinner-border-sm visually-hidden" role="status" aria-hidden="true"></span>
                            <i class="bi bi-play-fill"></i> Запустити
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <footer class="text-center text-muted small pb-3">
        <i class="bi bi-geo-alt"></i> ISKCON Луцьк · час — локальний (Europe/Kiev)
        <?php if ($isAdmin):?>
            · <span class="badge text-bg-primary"><i class="bi bi-person-gear"></i> адмін</span>
            <a href="/logout" class="text-decoration-none">Вийти</a>
        <?php else:?>
            · <a href="/login" class="text-muted text-decoration-none"><i class="bi bi-lock-fill"></i> Вхід адміністратора</a>
        <?php endif;?>
    </footer>
</div>

<script type="text/javascript">
    $(document).on('click', '.go', function () {
        $('#username').val($(this).attr('data-username'));
        $('#verse').val($(this).attr('data-verse'));
        $('#book').val($(this).attr('data-book'));

        $('#form').submit();
    })
    $('form').submit(() => {
        $('button').prop('disabled', true);
        $('.spinner-border').removeClass('visually-hidden');
        return true;
    })

    function showToast(text, danger) {
        const el = document.getElementById('liveToast');
        el.querySelector('.toast-body').innerHTML =
            '<i class="bi ' + (danger ? 'bi-exclamation-triangle text-danger' : 'bi-check-circle text-success') + '"></i> ' + text;
        bootstrap.Toast.getOrCreateInstance(el, {delay: 2500}).show();
    }

    $('#castBackend').on('change', function () {
        const b = $(this).val();
        fetch('/backend', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'backend=' + encodeURIComponent(b)
        }).then(r => r.json()).then(d => {
            if (d.ok) {
                showToast('Збережено: бекенд ' + (d.backend === 'obs' ? 'OBS' : 'ffmpeg (fallback)'));
            } else {
                showToast('Не вдалось зберегти налаштування', true);
            }
        }).catch(() => showToast('Не вдалось зберегти налаштування', true));
    });
</script>

<div class="toast-container position-fixed bottom-0 start-50 translate-middle-x p-3">
    <div id="liveToast" class="toast align-items-center border-0" role="status">
        <div class="d-flex">
            <div class="toast-body"></div>
            <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>
</body>
</html>