"""
Broadcasts application configuration.
"""
from django.apps import AppConfig
from django.utils.translation import gettext_lazy as _


class BroadcastsConfig(AppConfig):
    """Configuration for the broadcasts application."""
    
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'apps.broadcasts'
    verbose_name = _('Broadcasts')
    
    def ready(self):
        """Import signal handlers when app is ready."""
        try:
            import apps.broadcasts.signals  # noqa
        except ImportError:
            pass
