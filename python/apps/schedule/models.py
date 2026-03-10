"""
Models for the schedule application.

Contains:
- ScheduleEntry: Individual scheduled broadcast entry
- ScheduleSettings: Global schedule settings (morning SB times, etc.)
- TelegramMessage: Telegram messages for schedule notifications
"""
from django.db import models
from django.conf import settings
from django.utils.translation import gettext_lazy as _

from apps.core.models import TimeStampedModel
from apps.broadcasts.models import Book, Lector


class ScheduleEntry(TimeStampedModel):
    """
    Schedule entry model - represents a scheduled broadcast.
    
    This is the main scheduling model that tracks what should be broadcast
    and when. It can be linked to an actual Broadcast when executed.
    """
    
    class DayOfWeek(models.IntegerChoices):
        """Day of week choices."""
        MONDAY = 0, _('Monday')
        TUESDAY = 1, _('Tuesday')
        WEDNESDAY = 2, _('Wednesday')
        THURSDAY = 3, _('Thursday')
        FRIDAY = 4, _('Friday')
        SATURDAY = 5, _('Saturday')
        SUNDAY = 6, _('Sunday')
    
    # Date and time
    date = models.DateField(
        _('Date'),
        help_text=_('Date of the scheduled broadcast')
    )
    
    start_time = models.TimeField(
        _('Start Time'),
        help_text=_('Scheduled start time')
    )
    
    end_time = models.TimeField(
        _('End Time'),
        blank=True,
        null=True,
        help_text=_('Scheduled end time (optional)')
    )
    
    # Shastra reference
    book = models.CharField(
        _('Book'),
        max_length=10,
        choices=Book.choices,
        blank=True,
        null=True,
        help_text=_('Book being discussed')
    )
    
    verse = models.CharField(
        _('Verse'),
        max_length=50,
        blank=True,
        null=True,
        help_text=_('Verse reference (e.g., "11.28.30" or "11.28.31-32")')
    )
    
    # Lector
    lector = models.ForeignKey(
        Lector,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='schedule_entries',
        verbose_name=_('Lector'),
        help_text=_('Person giving the lecture')
    )
    
    # Custom title (overrides auto-generated title)
    custom_title = models.CharField(
        _('Custom Title'),
        max_length=500,
        blank=True,
        null=True,
        help_text=_('Custom title (overrides auto-generated title)')
    )
    
    # Theme/topic
    theme = models.CharField(
        _('Theme'),
        max_length=255,
        blank=True,
        null=True,
        help_text=_('Theme or topic of the lecture')
    )
    
    # Link to actual broadcast
    broadcast = models.ForeignKey(
        'broadcasts.Broadcast',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='schedule_entries',
        verbose_name=_('Broadcast'),
        help_text=_('Actual broadcast created from this schedule entry')
    )
    
    # Status
    is_completed = models.BooleanField(
        _('Completed'),
        default=False,
        help_text=_('Whether this schedule entry has been processed')
    )
    
    is_skipped = models.BooleanField(
        _('Skipped'),
        default=False,
        help_text=_('Whether this schedule entry was skipped')
    )
    
    notes = models.TextField(
        _('Notes'),
        blank=True,
        default='',
        help_text=_('Additional notes')
    )
    
    class Meta:
        db_table = 'schedule_entries'
        verbose_name = _('Schedule Entry')
        verbose_name_plural = _('Schedule Entries')
        ordering = ['date', 'start_time']
        unique_together = ['date', 'start_time']
    
    def __str__(self):
        return f'{self.date} {self.start_time} - {self.get_title()}'
    
    @property
    def day_of_week(self) -> int:
        """Get day of week (0=Monday, 6=Sunday)."""
        return self.date.weekday()
    
    @property
    def day_of_week_display(self) -> str:
        """Get day of week display name."""
        return self.DayOfWeek(self.day_of_week).label
    
    def get_title(self) -> str:
        """Get the title for this schedule entry."""
        if self.custom_title:
            return self.custom_title
        
        parts = []
        
        if self.book and self.verse:
            book_display = dict(Book.choices).get(self.book, self.book)
            parts.append(f'{book_display} {self.verse}')
        
        if self.lector:
            parts.append(str(self.lector))
        
        if self.theme:
            parts.append(self.theme)
        
        return ' - '.join(parts) if parts else f'Broadcast {self.date}'
    
    def is_today(self) -> bool:
        """Check if this entry is for today."""
        from django.utils import timezone
        return self.date == timezone.now().date()
    
    def is_past(self) -> bool:
        """Check if this entry is in the past."""
        from django.utils import timezone
        return self.date < timezone.now().date()
    
    def is_future(self) -> bool:
        """Check if this entry is in the future."""
        from django.utils import timezone
        return self.date > timezone.now().date()


class ScheduleSettings(TimeStampedModel):
    """
    Singleton model for global schedule settings.
    
    Stores morning SB times and other configuration.
    """
    
    morning_sb_start_time = models.TimeField(
        _('Morning SB Start Time'),
        default='07:00',
        help_text=_('Default start time for morning Srimad-Bhagavatam class')
    )
    
    morning_sb_end_time = models.TimeField(
        _('Morning SB End Time'),
        default='08:30',
        help_text=_('Default end time for morning Srimad-Bhagavatam class')
    )
    
    default_broadcast_duration_minutes = models.PositiveIntegerField(
        _('Default Broadcast Duration (minutes)'),
        default=120,
        help_text=_('Default duration for broadcasts in minutes')
    )
    
    # Morning SB days (comma-separated day numbers, 0=Monday, 6=Sunday)
    morning_sb_days = models.CharField(
        _('Morning SB Days'),
        max_length=20,
        default='0,1,2,3,4',  # Mon-Fri
        help_text=_('Days when morning SB class is held (comma-separated: 0=Mon, 6=Sun)')
    )
    
    # Telegram settings
    telegram_chat_id = models.BigIntegerField(
        _('Telegram Chat ID'),
        null=True,
        blank=True,
        help_text=_('Main Telegram chat ID for notifications')
    )
    
    telegram_subchannel_id = models.BigIntegerField(
        _('Telegram Subchannel ID'),
        null=True,
        blank=True,
        help_text=_('Telegram subchannel ID for SB schedule messages')
    )
    
    class Meta:
        db_table = 'schedule_settings'
        verbose_name = _('Schedule Settings')
        verbose_name_plural = _('Schedule Settings')
    
    def save(self, *args, **kwargs):
        """Ensure only one instance exists."""
        self.pk = 1
        super().save(*args, **kwargs)
    
    def delete(self, *args, **kwargs):
        """Prevent deletion."""
        pass
    
    @classmethod
    def load(cls):
        """Load the singleton instance."""
        obj, created = cls.objects.get_or_create(pk=1)
        return obj
    
    def get_morning_sb_days_list(self) -> list:
        """Get list of morning SB days."""
        return [int(d.strip()) for d in self.morning_sb_days.split(',') if d.strip().isdigit()]


class TelegramMessage(TimeStampedModel):
    """
    Telegram message model - tracks sent Telegram messages.
    
    Used for updating existing messages instead of sending new ones.
    """
    
    class MessageType(models.TextChoices):
        """Message type choices."""
        BROADCAST_NOTIFICATION = 'broadcast', _('Broadcast Notification')
        SCHEDULE_WEEKLY = 'weekly_schedule', _('Weekly Schedule')
        SCHEDULE_DAILY = 'daily_schedule', _('Daily Schedule')
        CUSTOM = 'custom', _('Custom Message')
    
    message_type = models.CharField(
        _('Message Type'),
        max_length=20,
        choices=MessageType.choices,
        help_text=_('Type of message')
    )
    
    chat_id = models.BigIntegerField(
        _('Chat ID'),
        help_text=_('Telegram chat ID')
    )
    
    message_id = models.BigIntegerField(
        _('Message ID'),
        help_text=_('Telegram message ID')
    )
    
    text = models.TextField(
        _('Text'),
        help_text=_('Message text')
    )
    
    # Related schedule entry (for schedule messages)
    schedule_entry = models.ForeignKey(
        ScheduleEntry,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='telegram_messages',
        verbose_name=_('Schedule Entry'),
        help_text=_('Related schedule entry')
    )
    
    # Related broadcast (for broadcast notifications)
    broadcast = models.ForeignKey(
        'broadcasts.Broadcast',
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='telegram_messages',
        verbose_name=_('Broadcast'),
        help_text=_('Related broadcast')
    )
    
    class Meta:
        db_table = 'telegram_messages'
        verbose_name = _('Telegram Message')
        verbose_name_plural = _('Telegram Messages')
        ordering = ['-created_at']
    
    def __str__(self):
        return f'{self.get_message_type_display()} - {self.chat_id}:{self.message_id}'
