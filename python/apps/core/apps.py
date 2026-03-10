"""
Core application configuration.
"""
from django.apps import AppConfig


class CoreConfig(AppConfig):
    """Configuration for the core application."""
    
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'apps.core'
    verbose_name = 'Core'
    
    def ready(self):
        """Import signal handlers when app is ready."""
        try:
            import apps.core.signals  # noqa
        except ImportError:
            pass
