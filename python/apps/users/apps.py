"""
Users application configuration.
"""
from django.apps import AppConfig
from django.utils.translation import gettext_lazy as _


class UsersConfig(AppConfig):
    """Configuration for the users application."""
    
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'apps.users'
    verbose_name = _('Users')
    
    def ready(self):
        """Import signal handlers when app is ready."""
        try:
            import apps.users.signals  # noqa
        except ImportError:
            pass
