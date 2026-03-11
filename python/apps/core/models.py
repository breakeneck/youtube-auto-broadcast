"""
Core models - base models and mixins for the broadcast application.
"""
import uuid

from django.db import models
from django.utils.translation import gettext_lazy as _


class TimeStampedModel(models.Model):
    """
    Abstract base model that provides created and updated timestamps.
    
    All models that need timestamp tracking should inherit from this model.
    """
    created_at = models.DateTimeField(
        _('Created at'),
        auto_now_add=True,
        help_text=_('Date and time when this record was created')
    )
    updated_at = models.DateTimeField(
        _('Updated at'),
        auto_now=True,
        help_text=_('Date and time when this record was last updated')
    )

    class Meta:
        abstract = True


class UUIDModel(models.Model):
    """
    Abstract base model that uses UUID as primary key.
    
    Useful for models that need non-sequential identifiers.
    """
    id = models.UUIDField(
        primary_key=True,
        default=uuid.uuid4,
        editable=False,
        verbose_name=_('ID')
    )

    class Meta:
        abstract = True


class SingletonModel(models.Model):
    """
    Abstract base model for singleton configuration models.
    
    Only one instance of this model should exist in the database.
    """
    class Meta:
        abstract = True

    def save(self, *args, **kwargs):
        """
        Ensure only one instance exists.
        """
        self.pk = 1
        super().save(*args, **kwargs)

    def delete(self, *args, **kwargs):
        """
        Prevent deletion of singleton instance.
        """
        pass

    @classmethod
    def load(cls):
        """
        Load the singleton instance, creating it if necessary.
        """
        obj, created = cls.objects.get_or_create(pk=1)
        return obj
