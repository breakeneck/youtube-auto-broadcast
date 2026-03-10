"""
Schedule application configuration.
"""
from django.apps import AppConfig
from django.utils.translation import gettext_lazy as _


class ScheduleConfig(AppConfig):
    """Configuration for the schedule application."""
    
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'apps.schedule'
    verbose_name = _('Schedule')
    
    def ready(self):
        """Import signal handlers when app is ready."""
        try:
            import apps.schedule.signals  # noqa
        except ImportError:
            pass
