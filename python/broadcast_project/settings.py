"""
Django settings for broadcast_project project.

Configuration for YouTube Auto Broadcasting Application with:
- Two PostgreSQL databases (broadcast and shastra)
- User authentication with long sessions
- Multi-language support (en, uk, ru)
- OBS and YouTube integration
"""

import os
from pathlib import Path
from datetime import timedelta

# Build paths inside the project like this: BASE_DIR / 'subdir'.
BASE_DIR = Path(__file__).resolve().parent.parent

# Load environment variables from .env file
from dotenv import load_dotenv
load_dotenv(BASE_DIR / '.env')


# Quick-start development settings - unsuitable for production
# See https://docs.djangoproject.com/en/4.2/howto/deployment/checklist/

# SECURITY WARNING: keep the secret key used in production secret!
SECRET_KEY = os.getenv('DJANGO_SECRET_KEY', 'django-insecure-change-me-in-production')

# SECURITY WARNING: don't run with debug turned on in production!
DEBUG = os.getenv('DJANGO_DEBUG', 'True').lower() in ('true', '1', 'yes')

ALLOWED_HOSTS = os.getenv('DJANGO_ALLOWED_HOSTS', 'localhost,127.0.0.1').split(',')


# Application definition
INSTALLED_APPS = [
    # Jazzmin admin theme (must be before django.contrib.admin)
    'jazzmin',
    
    # Django built-in apps
    'django.contrib.admin',
    'django.contrib.auth',
    'django.contrib.contenttypes',
    'django.contrib.sessions',
    'django.contrib.messages',
    'django.contrib.staticfiles',
    
    # Third-party apps
    'django.contrib.sites',  # Required for django-allauth
    'allauth',
    'allauth.account',
    'allauth.socialaccount',
    'crispy_forms',
    'crispy_bootstrap5',
    'django_apscheduler',
    'modeltranslation',
    'rosetta',
    
    # Local apps
    'apps.core',
    'apps.users',
    'apps.broadcasts',
    'apps.schedule',
    'apps.integrations',
]

MIDDLEWARE = [
    'django.middleware.security.SecurityMiddleware',
    'django.contrib.sessions.middleware.SessionMiddleware',
    'django.middleware.locale.LocaleMiddleware',  # Must be before CommonMiddleware
    'django.middleware.common.CommonMiddleware',
    'django.middleware.csrf.CsrfViewMiddleware',
    'django.contrib.auth.middleware.AuthenticationMiddleware',
    'django.contrib.messages.middleware.MessageMiddleware',
    'django.middleware.clickjacking.XFrameOptionsMiddleware',
    'allauth.account.middleware.AccountMiddleware',
]

ROOT_URLCONF = 'broadcast_project.urls'

TEMPLATES = [
    {
        'BACKEND': 'django.template.backends.django.DjangoTemplates',
        'DIRS': [BASE_DIR / 'templates'],
        'APP_DIRS': True,
        'OPTIONS': {
            'context_processors': [
                'django.template.context_processors.debug',
                'django.template.context_processors.request',
                'django.contrib.auth.context_processors.auth',
                'django.contrib.messages.context_processors.messages',
            ],
        },
    },
]

WSGI_APPLICATION = 'broadcast_project.wsgi.application'


# Database configuration
# Two databases: broadcast (default) and shastra (for shlokas)

DATABASES = {
    'default': {
        'ENGINE': 'django.db.backends.postgresql',
        'NAME': os.getenv('BROADCAST_DB_NAME', 'broadcast'),
        'USER': os.getenv('BROADCAST_DB_USER', 'postgres'),
        'PASSWORD': os.getenv('BROADCAST_DB_PASSWORD', 'postgres'),
        'HOST': os.getenv('BROADCAST_DB_HOST', 'localhost'),
        'PORT': os.getenv('BROADCAST_DB_PORT', '5432'),
    },
    'shastra': {
        'ENGINE': 'django.db.backends.postgresql',
        'NAME': os.getenv('SHASTRA_DB_NAME', 'shastra_parser'),
        'USER': os.getenv('SHASTRA_DB_USER', 'postgres'),
        'PASSWORD': os.getenv('SHASTRA_DB_PASSWORD', 'postgres'),
        'HOST': os.getenv('SHASTRA_DB_HOST', 'localhost'),
        'PORT': os.getenv('SHASTRA_DB_PORT', '5432'),
    }
}

# Database routers
DATABASE_ROUTERS = [
    'apps.core.routers.ShastraRouter',
]


# Password validation
AUTH_PASSWORD_VALIDATORS = [
    {
        'NAME': 'django.contrib.auth.password_validation.UserAttributeSimilarityValidator',
    },
    {
        'NAME': 'django.contrib.auth.password_validation.MinimumLengthValidator',
    },
    {
        'NAME': 'django.contrib.auth.password_validation.CommonPasswordValidator',
    },
    {
        'NAME': 'django.contrib.auth.password_validation.NumericPasswordValidator',
    },
]


# Internationalization
LANGUAGE_CODE = 'uk'  # Default language

LANGUAGES = (
    ('en', 'English'),
    ('uk', 'Українська'),
    ('ru', 'Русский'),
)

LOCALE_PATHS = [
    BASE_DIR / 'locale',
]

TIME_ZONE = 'Europe/Kiev'

USE_I18N = True

USE_L10N = True

USE_TZ = True


# Static files (CSS, JavaScript, Images)
STATIC_URL = '/static/'
STATIC_ROOT = BASE_DIR / 'staticfiles'
STATICFILES_DIRS = [
    BASE_DIR / 'static',
]

# Media files
MEDIA_URL = '/media/'
MEDIA_ROOT = BASE_DIR / 'media'


# Default primary key field type
DEFAULT_AUTO_FIELD = 'django.db.models.BigAutoField'


# Custom User Model
AUTH_USER_MODEL = 'users.User'


# Session settings - long sessions (30 days)
SESSION_COOKIE_AGE = int(os.getenv('SESSION_COOKIE_AGE', 2592000))  # 30 days
SESSION_SAVE_EVERY_REQUEST = True
SESSION_COOKIE_SECURE = not DEBUG  # Secure in production


# Django Allauth settings
SITE_ID = 1

AUTHENTICATION_BACKENDS = [
    'django.contrib.auth.backends.ModelBackend',
    'allauth.account.auth_backends.AuthenticationBackend',
]

ACCOUNT_LOGIN_ON_EMAIL_CONFIRMATION = True
ACCOUNT_LOGOUT_ON_GET = True
ACCOUNT_EMAIL_REQUIRED = True
ACCOUNT_USERNAME_REQUIRED = True
ACCOUNT_AUTHENTICATION_METHOD = 'username_email'
ACCOUNT_EMAIL_VERIFICATION = 'optional'  # For development, set to 'mandatory' in production

# Redirect URLs
LOGIN_REDIRECT_URL = '/'
LOGOUT_REDIRECT_URL = '/accounts/login/'


# Crispy forms
CRISPY_ALLOWED_TEMPLATE_PACKS = 'bootstrap5'
CRISPY_TEMPLATE_PACK = 'bootstrap5'


# Jazzmin admin theme settings
JAZZMIN_SETTINGS = {
    # title of the window
    "site_title": "Broadcast Admin",
    
    # Title on the brand, and the login screen (19 chars max)
    "site_header": "Broadcast",
    
    # square logo to use for your site, must be present in static files, used for favicon and brand on top left
    "site_logo": "favicon.ico",
    
    # Welcome text on the login screen
    "welcome_sign": "Welcome to Broadcast Admin",
    
    # Copyright on the footer
    "copyright": "ISKCON Lutsk",
    
    # The model admin to search from the search bar, search bar omitted if excluded
    "search_model": "users.User",
    
    # Field name on user model that contains avatar image
    "user_avatar": None,
    
    ############
    # Top Menu #
    ############
    
    # Links to put along the top menu
    "topmenu_links": [
        # Url that gets reversed (Permissions can be added)
        {"name": "Home", "url": "admin:index", "permissions": ["auth.view_user"]},
        # external url that opens in a new window
        {"name": "YouTube", "url": "https://youtube.com", "new_window": True},
        # model admin to link to (Permissions checked against model)
        {"model": "users.User"},
    ],
    
    #############
    # Side Menu #
    #############
    
    # Whether to display the side menu
    "show_sidebar": True,
    
    # Whether to aut expand the menu
    "navigation_expanded": True,
    
    # Hide these apps when generating side menu
    "hide_apps": [],
    
    # Hide these models when generating side menu
    "hide_models": [],
    
    # List of apps to base side menu ordering off of
    "order_with_respect_to": [
        "users",
        "broadcasts",
        "schedule",
        "integrations",
    ],
    
    # Custom links to append to app groups, keyed on app name
    "custom_links": {},
    
    # Custom icons for side menu, keyed on app name
    "icons": {
        "users": "fas fa-users",
        "broadcasts": "fas fa-video",
        "schedule": "fas fa-calendar-alt",
        "integrations": "fas fa-plug",
    },
    
    # Icons that are used when one is not manually specified
    "default_icon_parents": "fas fa-folder",
    "default_icon_children": "fas fa-circle",
    
    #############
    # UI Tweaks #
    #############
    
    # Relative paths to custom CSS/JS scripts (must be present in static files)
    "custom_css": None,
    "custom_js": None,
    
    # Whether to show the UI customizer on the sidebar
    "show_ui_builder": False,
    
    ###############
    # Change view #
    ###############
    
    # Render out the change view as a single form, or in tabs, current options are
    # - single
    # - horizontal_tabs (default)
    # - vertical_tabs
    # - collapsible
    # - carousel
    "changeform_format": "horizontal_tabs",
    
    # override change forms on a per modeladmin basis
    "changeform_format_overrides": {},
    
    # Add a language dropdown into the admin
    "language_chooser": True,
}


# APScheduler settings
APSCHEDULER_DATETIME_FORMAT = "N j, Y, f:s a"
APSCHEDULER_RUN_NOW_TIMEOUT = 25  # Seconds


# Application specific settings
YOUTUBE_PRIVACY = os.getenv('YOUTUBE_PRIVACY', 'public')
YOUTUBE_STREAM_ID = os.getenv('YOUTUBE_STREAM_ID', '')
YOUTUBE_AUTH_FILE = os.getenv('YOUTUBE_AUTH_FILE', '')

OBS_HOST = os.getenv('OBS_HOST', '')
OBS_PORT = int(os.getenv('OBS_PORT', '22'))
OBS_USERNAME = os.getenv('OBS_USERNAME', '')
OBS_PASSWORD = os.getenv('OBS_PASSWORD', '')

TG_API_TOKEN = os.getenv('TG_API_TOKEN', '')
TG_CHAT_ID = int(os.getenv('TG_CHAT_ID', '0'))
TG_SUBCHANNEL_ID = int(os.getenv('TG_SUBCHANNEL_ID', '0'))

MORNING_SB_START_TIME = os.getenv('MORNING_SB_START_TIME', '07:00')
MORNING_SB_END_TIME = os.getenv('MORNING_SB_END_TIME', '08:30')
DEFAULT_BROADCAST_DURATION_MINUTES = int(os.getenv('DEFAULT_BROADCAST_DURATION_MINUTES', '120'))


# Logging configuration
LOGGING = {
    'version': 1,
    'disable_existing_loggers': False,
    'formatters': {
        'verbose': {
            'format': '{levelname} {asctime} {module} {process:d} {thread:d} {message}',
            'style': '{',
        },
        'simple': {
            'format': '{levelname} {asctime} {message}',
            'style': '{',
        },
    },
    'handlers': {
        'console': {
            'class': 'logging.StreamHandler',
            'formatter': 'simple',
        },
        'file': {
            'class': 'logging.handlers.RotatingFileHandler',
            'filename': BASE_DIR / 'logs' / 'broadcast.log',
            'maxBytes': 1024 * 1024 * 5,  # 5 MB
            'backupCount': 10,
            'formatter': 'verbose',
        },
    },
    'loggers': {
        'django': {
            'handlers': ['console'],
            'level': os.getenv('DJANGO_LOG_LEVEL', 'INFO'),
        },
        'apps': {
            'handlers': ['console', 'file'],
            'level': 'DEBUG' if DEBUG else 'INFO',
        },
    },
}

# Create logs directory if it doesn't exist
(BASE_DIR / 'logs').mkdir(exist_ok=True)
