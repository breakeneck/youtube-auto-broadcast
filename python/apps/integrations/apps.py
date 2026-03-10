"""
Integrations application configuration.
"""
from django.apps import AppConfig
from django.utils.translation import gettext_lazy as _


class IntegrationsConfig(AppConfig):
    """Configuration for the integrations application."""
    
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'apps.integrations'
    verbose_name = _('Integrations')
    
    def ready(self):
        """Import signal handlers when app is ready."""
        try:
            import apps.integrations.signals  # noqa
        except ImportError:
            pass
