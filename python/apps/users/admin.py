"""
Admin configuration for the users application.
"""
from django.contrib import admin
from django.contrib.auth.admin import UserAdmin as BaseUserAdmin
from django.utils.translation import gettext_lazy as _

from .models import User, UserRole


@admin.register(User)
class UserAdmin(BaseUserAdmin):
    """Custom admin for the User model."""
    
    list_display = [
        'username', 'email', 'role', 'language', 'is_active', 
        'is_staff', 'date_joined'
    ]
    list_filter = ['role', 'is_active', 'is_staff', 'language']
    search_fields = ['username', 'email', 'first_name', 'last_name']
    ordering = ['username']
    
    fieldsets = (
        (None, {'fields': ('username', 'password')}),
        (_('Personal info'), {
            'fields': ('first_name', 'last_name', 'email', 'phone', 'telegram_id')
        }),
        (_('Role & Language'), {
            'fields': ('role', 'language')
        }),
        (_('Permissions'), {
            'fields': ('is_active', 'is_staff', 'is_superuser', 'groups', 'user_permissions'),
        }),
        (_('Important dates'), {
            'fields': ('last_login', 'date_joined')
        }),
    )
    
    add_fieldsets = (
        (None, {
            'classes': ('wide',),
            'fields': ('username', 'email', 'role', 'password1', 'password2'),
        }),
    )
    
    def get_form(self, request, obj=None, **kwargs):
        """Limit role choices based on user permissions."""
        form = super().get_form(request, obj, **kwargs)
        if not request.user.is_superuser:
            # Non-superusers cannot create superusers
            form.base_fields['is_superuser'].disabled = True
        return form
