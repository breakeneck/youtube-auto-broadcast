#!/usr/bin/env bash
# Смоук-тест пайплайну трансляцій ISKCON Lutsk.
# Запуск з хоста: bash backend/tests/smoke.sh [--offline]
# --offline — пропустити зовнішні виклики (YouTube/Sheets/SSH), лише локальні перевірки.
set -euo pipefail
docker exec cast_backend php /var/www/cast.iskcon.lutsk.ua/backend/tests/smoke.php "$@"