"""
Custom User model for the broadcast application.

Provides user roles: ADMIN, BROADCASTER, VIEWER
"""
from django.contrib.auth.models import AbstractUser
from django.db import models
from django.utils.translation import gettext_lazy as _


class UserRole(models.TextChoices):
    """User role choices for the broadcast application."""
    ADMIN = 'admin', _('Administrator')
    BROADCASTER = 'broadcaster', _('Broadcaster')
    VIEWER = 'viewer', _('Viewer')


class User(AbstractUser):
    """
    Custom user model for the broadcast application.
    
    Extends Django's AbstractUser with:
    - Role-based access control
    - Language preference
    - Phone number for mobile access
    """
    
    role = models.CharField(
        _('Role'),
        max_length=20,
        choices=UserRole.choices,
        default=UserRole.VIEWER,
        help_text=_('User role determines access level')
    )
    
    language = models.CharField(
        _('Language'),
        max_length=10,
        default='uk',
        help_text=_('Preferred language for the user interface')
    )
    
    phone = models.CharField(
        _('Phone number'),
        max_length=20,
        blank=True,
        null=True,
        help_text=_('Phone number for mobile app access')
    )
    
    telegram_id = models.BigIntegerField(
        _('Telegram ID'),
        blank=True,
        null=True,
        unique=True,
        help_text=_('Telegram user ID for notifications')
    )
    
    class Meta:
        db_table = 'users'
        verbose_name = _('User')
        verbose_name_plural = _('Users')
        ordering = ['username']
    
    def __str__(self):
        return f'{self.username} ({self.get_role_display()})'
    
    @property
    def is_admin(self) -> bool:
        """Check if user has admin role."""
        return self.role == UserRole.ADMIN or self.is_superuser
    
    @property
    def is_broadcaster(self) -> bool:
        """Check if user has broadcaster role or higher."""
        return self.role in [UserRole.ADMIN, UserRole.BROADCASTER] or self.is_superuser
    
    def can_broadcast(self) -> bool:
        """Check if user can start/stop broadcasts."""
        return self.is_broadcaster
    
    def can_manage_schedule(self) -> bool:
        """Check if user can manage schedule."""
        return self.is_admin
