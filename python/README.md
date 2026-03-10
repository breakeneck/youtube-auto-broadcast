# YouTube Auto Broadcast - Django Application

Система автоматизації YouTube трансляцій для храму ІСККОН Луцьк.

## Можливості

- 🎥 **Керування трансляціями**: Створення та керування YouTube прямими ефірами
- 📅 **Розклад**: Планування трансляцій з автоматичним запуском
- 👥 **Користувачі**: Рольова система доступу (адмін, мовець, глядач)
- 📚 **Шастри**: Інтеграція з базою даних шлок (БГ, ШБ, ЧЧ)
- 📱 **Telegram**: Сповіщення про трансляції та розклад
- 🌐 **Мультиязичність**: Підтримка української, англійської, російської мов

## Вимоги

- Python 3.10+
- PostgreSQL 14+
- OBS Studio (на віддаленому сервері)

## Встановлення

### 1. Клонування та налаштування

```bash
cd python
python -m venv venv
source venv/bin/activate  # Linux/Mac
# або
.\venv\Scripts\activate  # Windows

pip install -r requirements.txt
```

### 2. Налаштування бази даних

Створіть базу даних для трансляцій:

```sql
CREATE DATABASE broadcast;
```

База даних shastra_parser вже повинна існувати з шлоками.

### 3. Налаштування змінних середовища

Скопіюйте `.env.example` в `.env` та налаштуйте:

```bash
cp .env.example .env
```

Основні змінні:
- `DJANGO_SECRET_KEY` - секретний ключ Django
- `YOUTUBE_AUTH_FILE` - шлях до файлу облікових даних YouTube API
- `TG_API_TOKEN` - токен Telegram бота
- `OBS_HOST`, `OBS_PORT`, `OBS_USERNAME`, `OBS_PASSWORD` - дані для SSH підключення до OBS

### 4. Міграції та початкові дані

```bash
python manage.py migrate
python manage.py setup_initial_data
python manage.py createsuperuser
```

### 5. Запуск

```bash
# Розробка
python manage.py runserver

# Продакшн
python manage.py collectstatic
gunicorn broadcast_project.wsgi:application
```

### 6. Планувальник (cron)

Додайте в crontab для перевірки кожну хвилину:

```cron
* * * * * cd /path/to/python && /path/to/venv/bin/python manage.py run_scheduler >> /var/log/broadcast/scheduler.log 2>&1
```

## Структура проекту

```
python/
├── broadcast_project/     # Налаштування Django
│   ├── settings.py
│   ├── urls.py
│   └── wsgi.py
├── apps/
│   ├── core/              # Базові утиліти
│   ├── users/             # Користувачі та ролі
│   ├── broadcasts/        # Трансляції
│   ├── schedule/          # Розклад
│   └── integrations/      # Зовнішні сервіси
├── templates/             # HTML шаблони
├── locale/                # Файли перекладів
├── static/                # Статичні файли
├── logs/                  # Логи
└── manage.py
```

## Ролі користувачів

- **Адміністратор**: Повний доступ до всіх функцій
- **Мовець (Broadcaster)**: Може запускати/зупиняти трансляції
- **Глядач (Viewer)**: Тільки перегляд

## API

### Статус трансляції
```
GET /api/status/
```

### Майбутній розклад
```
GET /api/upcoming/
```

## Розробка

### Створення перекладів

```bash
python manage.py makemessages -l uk
python manage.py makemessages -l ru
python manage.py compilemessages
```

### Тести

```bash
python manage.py test
```

## Ліцензія

ISCКОН Луцьк © 2026
