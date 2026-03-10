"""
Admin configuration for the broadcasts application.
"""
from django.contrib import admin
from django.utils.translation import gettext_lazy as _

from .models import Lector, Broadcast, BroadcastLog


@admin.register(Lector)
class LectorAdmin(admin.ModelAdmin):
    """Admin for the Lector model."""
    
    list_display = ['name', 'is_active', 'telegram_id', 'created_at']
    list_filter = ['is_active']
    search_fields = ['name', 'notes']
    ordering = ['name']


class BroadcastLogInline(admin.TabularInline):
    """Inline admin for BroadcastLog."""
    
    model = BroadcastLog
    extra = 0
    readonly_fields = ['action', 'user', 'message', 'created_at']
    
    def has_add_permission(self, request, obj=None):
        return False
    
    def has_change_permission(self, request, obj=None):
        return False


@admin.register(Broadcast)
class BroadcastAdmin(admin.ModelAdmin):
    """Admin for the Broadcast model."""
    
    list_display = [
        'title', 'lector', 'get_verse_display_short', 'status',
        'scheduled_start', 'actual_start', 'started_by'
    ]
    list_filter = ['status', 'book', 'lector', 'is_custom_title']
    search_fields = ['title', 'youtube_id', 'verse', 'description']
    readonly_fields = ['youtube_id', 'actual_start', 'actual_end', 'created_at', 'updated_at']
    inlines = [BroadcastLogInline]
    date_hierarchy = 'scheduled_start'
    
    fieldsets = (
        (_('YouTube Info'), {
            'fields': ('youtube_id', 'title', 'description', 'is_custom_title')
        }),
        (_('Shastra Reference'), {
            'fields': ('book', 'verse', 'lector')
        }),
        (_('Status'), {
            'fields': ('status', 'error_message')
        }),
        (_('Timing'), {
            'fields': ('scheduled_start', 'scheduled_end', 'actual_start', 'actual_end')
        }),
        (_('User'), {
            'fields': ('started_by',)
        }),
        (_('Telegram'), {
            'fields': ('telegram_message_id',)
        }),
        (_('Timestamps'), {
            'fields': ('created_at', 'updated_at'),
            'classes': ('collapse',)
        }),
    )
    
    def get_verse_display_short(self, obj):
        """Get short verse display."""
        if obj.book and obj.verse:
            return f'{obj.get_book_display()} {obj.verse}'
        return '-'
    get_verse_display_short.short_description = _('Verse')


@admin.register(BroadcastLog)
class BroadcastLogAdmin(admin.ModelAdmin):
    """Admin for the BroadcastLog model."""
    
    list_display = ['broadcast', 'action', 'user', 'created_at']
    list_filter = ['action', 'user']
    search_fields = ['broadcast__title', 'message']
    readonly_fields = ['broadcast', 'action', 'user', 'message', 'additional_data', 'created_at']
    date_hierarchy = 'created_at'
    
    def has_add_permission(self, request):
        return False
    
    def has_change_permission(self, request, obj=None):
        return False
