"""
Models for the broadcasts application.

Contains:
- Lector: Person who gives lectures
- Broadcast: YouTube broadcast record
- BroadcastLog: Log of broadcast actions
"""
from django.db import models
from django.conf import settings
from django.utils.translation import gettext_lazy as _

from apps.core.models import TimeStampedModel


class Book(models.TextChoices):
    """Book choices for shastra references."""
    BG = 'bg', _('Bhagavad-gita')
    SB = 'sb', _('Srimad-Bhagavatam')
    CC = 'cc', _('Caitanya-caritamrta')


class BookUkrainian(models.TextChoices):
    """Ukrainian book abbreviations."""
    BG = 'БГ', _('Бгаґавад-ґіта')
    SB = 'ШБ', _('Шрімад-Бгаґаватам')
    CC = 'ЧЧ', _('Чайтанья-чарітамріта')


BOOK_MAPPING = {
    Book.BG: BookUkrainian.BG,
    Book.SB: BookUkrainian.SB,
    Book.CC: BookUkrainian.CC,
}

BOOK_MAPPING_REVERSE = {v: k for k, v in BOOK_MAPPING.items()}


class Lector(TimeStampedModel):
    """
    Lector model - represents a person who gives lectures.
    
    Lectors are used in schedules and broadcasts to track who is speaking.
    """
    
    name = models.CharField(
        _('Name'),
        max_length=255,
        unique=True,
        help_text=_('Full name of the lector')
    )
    
    is_active = models.BooleanField(
        _('Active'),
        default=True,
        help_text=_('Whether this lector is currently active')
    )
    
    telegram_id = models.BigIntegerField(
        _('Telegram ID'),
        blank=True,
        null=True,
        help_text=_('Telegram user ID for notifications')
    )
    
    notes = models.TextField(
        _('Notes'),
        blank=True,
        default='',
        help_text=_('Additional notes about this lector')
    )
    
    class Meta:
        db_table = 'lectors'
        verbose_name = _('Lector')
        verbose_name_plural = _('Lectors')
        ordering = ['name']
    
    def __str__(self):
        return self.name


class BroadcastStatus(models.TextChoices):
    """Broadcast status choices."""
    SCHEDULED = 'scheduled', _('Scheduled')
    STARTING = 'starting', _('Starting')
    LIVE = 'live', _('Live')
    ENDING = 'ending', _('Ending')
    COMPLETED = 'completed', _('Completed')
    ERROR = 'error', _('Error')
    CANCELLED = 'cancelled', _('Cancelled')


class Broadcast(TimeStampedModel):
    """
    Broadcast model - represents a YouTube live broadcast.
    
    Tracks the state and metadata of each broadcast session.
    """
    
    # YouTube data
    youtube_id = models.CharField(
        _('YouTube ID'),
        max_length=50,
        blank=True,
        null=True,
        unique=True,
        help_text=_('YouTube broadcast ID')
    )
    
    title = models.CharField(
        _('Title'),
        max_length=500,
        help_text=_('Broadcast title')
    )
    
    description = models.TextField(
        _('Description'),
        blank=True,
        default='',
        help_text=_('Broadcast description (shown on YouTube)')
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
        related_name='broadcasts',
        verbose_name=_('Lector'),
        help_text=_('Person giving the lecture')
    )
    
    # Status
    status = models.CharField(
        _('Status'),
        max_length=20,
        choices=BroadcastStatus.choices,
        default=BroadcastStatus.SCHEDULED,
        help_text=_('Current broadcast status')
    )
    
    # Timing
    scheduled_start = models.DateTimeField(
        _('Scheduled Start'),
        null=True,
        blank=True,
        help_text=_('Planned start time')
    )
    
    scheduled_end = models.DateTimeField(
        _('Scheduled End'),
        null=True,
        blank=True,
        help_text=_('Planned end time')
    )
    
    actual_start = models.DateTimeField(
        _('Actual Start'),
        null=True,
        blank=True,
        help_text=_('Actual start time')
    )
    
    actual_end = models.DateTimeField(
        _('Actual End'),
        null=True,
        blank=True,
        help_text=_('Actual end time')
    )
    
    # User who started the broadcast
    started_by = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='started_broadcasts',
        verbose_name=_('Started by'),
        help_text=_('User who started this broadcast')
    )
    
    # Error tracking
    error_message = models.TextField(
        _('Error Message'),
        blank=True,
        default='',
        help_text=_('Error message if broadcast failed')
    )
    
    # Notification
    telegram_message_id = models.BigIntegerField(
        _('Telegram Message ID'),
        null=True,
        blank=True,
        help_text=_('ID of the Telegram notification message')
    )
    
    # Custom title flag
    is_custom_title = models.BooleanField(
        _('Custom Title'),
        default=False,
        help_text=_('Whether the title was manually set')
    )
    
    class Meta:
        db_table = 'broadcasts'
        verbose_name = _('Broadcast')
        verbose_name_plural = _('Broadcasts')
        ordering = ['-scheduled_start']
    
    def __str__(self):
        return f'{self.title} ({self.get_status_display()})'
    
    @property
    def is_live(self) -> bool:
        """Check if broadcast is currently live."""
        return self.status == BroadcastStatus.LIVE
    
    @property
    def is_active(self) -> bool:
        """Check if broadcast is in an active state."""
        return self.status in [
            BroadcastStatus.SCHEDULED,
            BroadcastStatus.STARTING,
            BroadcastStatus.LIVE,
            BroadcastStatus.ENDING,
        ]
    
    def get_youtube_url(self) -> str:
        """Get the YouTube URL for this broadcast."""
        if self.youtube_id:
            return f'https://www.youtube.com/watch?v={self.youtube_id}'
        return ''
    
    def get_verse_display_uk(self) -> str:
        """Get verse display in Ukrainian format."""
        if not self.book or not self.verse:
            return ''
        
        book_uk = BOOK_MAPPING.get(self.book, self.book)
        return f'{book_uk} {self.verse}'


class BroadcastLog(TimeStampedModel):
    """
    Broadcast log model - tracks all broadcast-related actions.
    
    Provides an audit trail for debugging and accountability.
    """
    
    class ActionType(models.TextChoices):
        """Action type choices."""
        SCHEDULE = 'schedule', _('Scheduled')
        START = 'start', _('Started')
        STOP = 'stop', _('Stopped')
        ERROR = 'error', _('Error')
        RESET = 'reset', _('Reset')
        NOTIFY = 'notify', _('Notified')
        UPDATE = 'update', _('Updated')
    
    broadcast = models.ForeignKey(
        Broadcast,
        on_delete=models.CASCADE,
        related_name='logs',
        verbose_name=_('Broadcast'),
        help_text=_('Related broadcast')
    )
    
    action = models.CharField(
        _('Action'),
        max_length=20,
        choices=ActionType.choices,
        help_text=_('Action performed')
    )
    
    user = models.ForeignKey(
        settings.AUTH_USER_MODEL,
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name='broadcast_logs',
        verbose_name=_('User'),
        help_text=_('User who performed the action')
    )
    
    message = models.TextField(
        _('Message'),
        blank=True,
        default='',
        help_text=_('Log message or details')
    )
    
    additional_data = models.JSONField(
        _('Additional Data'),
        default=dict,
        blank=True,
        help_text=_('Additional data in JSON format')
    )
    
    class Meta:
        db_table = 'broadcast_logs'
        verbose_name = _('Broadcast Log')
        verbose_name_plural = _('Broadcast Logs')
        ordering = ['-created_at']
    
    def __str__(self):
        return f'{self.broadcast.title} - {self.get_action_display()}'
