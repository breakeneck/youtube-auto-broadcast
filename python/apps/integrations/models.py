"""
Models for the integrations application.

Contains models for external service configurations and cached data.
"""
from django.db import models
from django.utils.translation import gettext_lazy as _

from apps.core.models import TimeStampedModel


class SourceEngine(models.TextChoices):
    """Source engine choices for shastra content."""
    VEDABASE = 'vedabase', _('Vedabase.io')
    GITABASE = 'gitabase', _('Gitabase')


class Shloka(models.Model):
    """
    Shloka model - represents a verse from shastra.
    
    This model is read from the external shastra database.
    It's a managed model that maps to the existing shastra_parser database.
    """
    
    # This model uses the shastra database
    uses_shastra_db = True
    
    id = models.BigAutoField(primary_key=True)
    
    book = models.CharField(
        _('Book'),
        max_length=10,
        help_text=_('Book code (bg, sb, cc)')
    )
    
    chapter = models.IntegerField(
        _('Chapter'),
        help_text=_('Chapter number')
    )
    
    verse = models.CharField(
        _('Verse'),
        max_length=50,
        help_text=_('Verse number or range')
    )
    
    text = models.TextField(
        _('Sanskrit Text'),
        blank=True,
        default='',
        help_text=_('Original Sanskrit text')
    )
    
    translation = models.TextField(
        _('Translation'),
        blank=True,
        default='',
        help_text=_('Translation text')
    )
    
    purport = models.TextField(
        _('Purport'),
        blank=True,
        default='',
        help_text=_('Purport/commentary text')
    )
    
    has_purport = models.BooleanField(
        _('Has Purport'),
        default=False,
        help_text=_('Whether this verse has a purport')
    )
    
    language = models.CharField(
        _('Language'),
        max_length=10,
        default='en',
        help_text=_('Language code')
    )
    
    source = models.CharField(
        _('Source'),
        max_length=20,
        choices=SourceEngine.choices,
        default=SourceEngine.VEDABASE,
        help_text=_('Source engine')
    )
    
    url = models.URLField(
        _('URL'),
        blank=True,
        null=True,
        help_text=_('Source URL')
    )
    
    class Meta:
        db_table = 'shlokas'
        verbose_name = _('Shloka')
        verbose_name_plural = _('Shlokas')
        ordering = ['book', 'chapter', 'verse']
        managed = False  # Don't create migrations for this model
        unique_together = ['book', 'chapter', 'verse', 'language', 'source']
    
    def __str__(self):
        return f'{self.book} {self.chapter}.{self.verse}'
    
    @property
    def verse_reference(self) -> str:
        """Get full verse reference."""
        return f'{self.chapter}.{self.verse}'
    
    @property
    def full_reference(self) -> str:
        """Get full reference with book."""
        return f'{self.book} {self.verse_reference}'


class CachedVerse(TimeStampedModel):
    """
    Cached verse model - stores parsed verse data for quick access.
    
    This is used to cache verse data from external sources to avoid
    repeated API calls or database queries.
    """
    
    book = models.CharField(
        _('Book'),
        max_length=10,
        help_text=_('Book code')
    )
    
    verse = models.CharField(
        _('Verse'),
        max_length=50,
        help_text=_('Verse reference')
    )
    
    language = models.CharField(
        _('Language'),
        max_length=10,
        default='uk',
        help_text=_('Language code')
    )
    
    source = models.CharField(
        _('Source'),
        max_length=20,
        choices=SourceEngine.choices,
        default=SourceEngine.VEDABASE,
        help_text=_('Source engine')
    )
    
    sanskrit = models.TextField(
        _('Sanskrit'),
        blank=True,
        default='',
        help_text=_('Sanskrit text')
    )
    
    transliteration = models.TextField(
        _('Transliteration'),
        blank=True,
        default='',
        help_text=_('Transliteration text')
    )
    
    translation = models.TextField(
        _('Translation'),
        blank=True,
        default='',
        help_text=_('Translation text')
    )
    
    purport = models.TextField(
        _('Purport'),
        blank=True,
        default='',
        help_text=_('Purport text')
    )
    
    url = models.URLField(
        _('URL'),
        blank=True,
        null=True,
        help_text=_('Source URL')
    )
    
    class Meta:
        db_table = 'cached_verses'
        verbose_name = _('Cached Verse')
        verbose_name_plural = _('Cached Verses')
        unique_together = ['book', 'verse', 'language', 'source']
    
    def __str__(self):
        return f'{self.book} {self.verse} ({self.language})'
