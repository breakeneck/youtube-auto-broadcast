---
name: cast-smoke-test
description: Run read-only smoke tests for the ISKCON Lutsk YouTube broadcast pipeline (cast.iskcon.lutsk.ua). Use after ANY change to backend PHP, .env, the ffmpeg-cast systemd unit, cron, or scene/camera setup — and whenever the user asks to check/verify that broadcasts will work.
---

# ISKCON Lutsk — смоук-тест пайплайну трансляцій

Пайплайн: cron на хості щохвилини → `docker exec cast_backend php server.php check-schedule`
→ `Scenario::startObs($backend)` (SSH на 192.168.0.15) → пуш у дефолтний стрім YouTube
→ goLive → Telegram-нотифікація.

ДВА БЕКЕНДИ (вибір в UI «Бекенд трансляції», зберігається в `state.json:cast_backend`,
за замовчуванням `ffmpeg`):
- `obs` — `systemctl --user start obs-start` (OBS на Wayland, `--startstreaming`; сцени з поворотом
  камери і шумозаглушенням мікрофона; server/streamKey — в `~/.config/obs-studio/basic/profiles/Untitled/basic.ini`).
- `ffmpeg` — `systemctl --user start ffmpeg-cast` (fallback: ffmpeg з `/dev/video0`, без фільтрів,
  зображення перевернуте, шум не глушиться).
`Scenario::stopObs()` зупиняє ОБИДВА юніти (безпечно). Ручний старт/стоп в UI — тільки для
адміна (`/login`, креденшли `ADMIN_USER`/`ADMIN_PASSWORD_HASH` в `.env`).

## Коли запускати

Після БУДЬ-Яких змін у: `backend/src/**`, `backend/server.php`, `backend/index.php`,
`backend/.env`, `backend/data/state.json`, юніті `ffmpeg-cast.service` на OBS-машині,
cron-таблиці хоста, підключенні камери/мікрофона. Також — на будь-яке прохання
«перевір, чи все працює».

## Як запускати

З кореня проєкту:

```bash
bash backend/tests/smoke.sh            # повний тест (~10-20 с)
bash backend/tests/smoke.sh --offline  # без зовнішніх викликів (YouTube/Sheets/SSH)
```

Скрипт: `backend/tests/smoke.php` (виконується ВСЕРЕДИНІ контейнера `cast_backend`).
Він повністю read-only і сам нічого не виводить секретного. Exit 0 = OK, 1 = є FAIL.

## Інтерпретація результатів

Рівні: `[OK]` / `[WARN]` (не блокує) / `[FAIL]` (проблема).

| FAIL | Причина і лікування |
|---|---|
| lint | синтаксис PHP — `docker exec cast_backend php -l <файл>` |
| ключі .env / файли | `backend/.env`, `backend/data/auth.json`, credentials Sheets |
| cron.cast.log не свіжий | cron на ХОСТІ (`crontab -l`): рядка `docker exec -e TZ=Europe/Kiev cast_backend ... check-schedule` немає, або контейнер `cast_backend` не запущений (`docker ps`) |
| YouTube API недоступний | протух refresh-токен — переавторизувати `backend/data/auth.json` (Google OAuth) |
| стрім unknown | `YOUTUBE_STREAM_ID` у .env не існує в акаунті |
| SSH не вдався | OBS-машина вимкнена/мертва або змінились кредли в .env |
| юніт ffmpeg-cast.service НЕ знайдено | `~/.config/systemd/user/ffmpeg-cast.service` на 192.168.0.15 удалений/зіпсований |
| юніт obs-start.service НЕ знайдено (бекенд obs) | `~/.config/systemd/user/obs-start.service` (має містити QT_QPA_PLATFORM=wayland, WAYLAND_DISPLAY=wayland-0 — Xorg на машині НЕ boot-иться) |
| streamKey немає / не збігається в OBS-профілі (бекенд obs) | переписати з юніта ffmpeg-cast скриптом-одноденівкою (див. історію: obs_set_key.py) або вручну в налаштуваннях потоку OBS |
| OBS запущений без ефіру (бекенд ffmpeg) | тримає `/dev/video0` — `systemctl --user stop obs-start` (може зависнути — тоді `pkill -9 -x obs6`) |
| ефір іде (бекенд obs), а OBS не запущений | obs-start упав — `journalctl --user -u obs-start -n 30`; перечекати/перезапустити юніт |
| /dev/video0 НЕ існує | камера фізично від'єднана від OBS-машини |
| stream key НЕ збігається | ключ у юніті застарів (хтось Reset ключ у YouTube Studio) — оновити ExecStart у юніті, `systemctl --user daemon-reload` |

Нормальні WARN: активний ефір (йдде трансляція), ffmpeg-cast active без ефіру,
obs-start активний без ефіру (хтось відкрив OBS вручну), OBS запущений без ефіру,
старий `starting`-лок (видалити `"starting"` зі `backend/data/state.json`).

Норма, не лякайтесь: `ffmpeg-cast idle (стан 'failed' ...)` — ffmpeg після SIGTERM
виходить з кодом 255, systemd мітить failed; наступний `systemctl start` працює далі.

## Залізне (НЕ порушувати)

- НИКОЛИ не виводити у чат: stream key, `OBS_PASSWORD`, токени, вивід `systemctl cat ffmpeg-cast`
  і `ps` з ffmpeg-командою (там ключ у rtmps URL). Маскувати: `sed -E "s#live2?/[A-Za-z0-9-]+#<key>#g"`.
- НЕ запускати ОБИДВА пушери одночасно (ffmpeg-cast active + OBS запущений) — подвійний пуш
  у стрім. Перед стартом одного — зупинити інший.
- НЕ запускати OBS/ffmpeg-юніти «щоб допомогти» — старт тільки через UI (адмін) або cron.
- Ручні endpoints (`/start`, `/stop`, `/reset`, `/exitapps`, `/backend`) — вимагають сесію адміна;
  для перевірок з curl спочатку `POST /login` з креденшлами адміна (пароль не друкувати в чат).
- Смоук-тест нічого не стартує і не шле повідомлень у Telegram — не «допомогти» йому цим.
- ПОВНИЙ end-to-end тест (реальний ефір на 2 хв) — тільки за явної згоди користувача:
  стартнути обраний бекенд (`systemctl --user start obs-start` АБО `ffmpeg-cast`) на OBS-машині
  → дочекатись стрім `active` (~10-90 с; OBS може довше) → createBroadcast/goLive через
  `App\Scenario` → потім обов'язково `transition('complete')` і зупинка юніта. Не робити без запиту.

## Додаткова діагностика (коли тест червоний)

- Журнал cron: `backend/data/cron.cast.log` (час київський).
- Стан юніта без витоку ключа: `systemctl --user show ffmpeg-cast -p Result,ExecMainStatus,ActiveState,SubState`.
- Журнал ffmpeg (маскувати ключ!): `journalctl --user -u ffmpeg-cast -n 30 --no-pager | sed -E "s#live2?/[A-Za-z0-9-]+#<key>#g"`.
- Стан стріму з хоста: `docker exec cast_backend php backend/tests/smoke.php` (секція YouTube API).
- Ключові файли: `backend/server.php` (doStart/doStop/check-schedule + starting-лок),
  `backend/src/yuri/Scenario.php` (старти/стоп сценарію, вибір бекенда, пулінг active-стріму),
  `backend/src/yuri/Youtube.php` (API-обгортки), `backend/index.php` (UI + адмін-авторизація +
  POST /backend), `backend/data/state.json` (id/scheduled_row/starting/cast_backend).