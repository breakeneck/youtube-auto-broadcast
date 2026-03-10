"""
Admin configuration for the schedule application.
"""
from django.contrib import admin
from django.utils.translation import gettext_lazy as _

from .models import ScheduleEntry, ScheduleSettings, TelegramMessage


@admin.register(ScheduleEntry)
class ScheduleEntryAdmin(admin.ModelAdmin):
    """Admin for the ScheduleEntry model."""
    
    list_display = [
        'date', 'start_time', 'end_time', 'get_verse_display',
        'lector', 'is_completed', 'is_skipped'
    ]
    list_filter = ['book', 'lector', 'is_completed', 'is_skipped', 'date']
    search_fields = ['verse', 'custom_title', 'theme', 'notes']
    date_hierarchy = 'date'
    ordering = ['-date', 'start_time']
    
    fieldsets = (
        (_('Date & Time'), {
            'fields': ('date', 'start_time', 'end_time')
        }),
        (_('Shastra Reference'), {
            'fields': ('book', 'verse', 'lector', 'theme')
        }),
        (_('Custom Title'), {
            'fields': ('custom_title',)
        }),
        (_('Status'), {
            'fields': ('is_completed', 'is_skipped', 'broadcast')
        }),
        (_('Notes'), {
            'fields': ('notes',)
        }),
    )
    
    def get_verse_display(self, obj):
        """Get verse display."""
        if obj.book and obj.verse:
            return f'{obj.get_book_display()} {obj.verse}'
        return '-'
    get_verse_display.short_description = _('Verse')
    
    actions = ['mark_completed', 'mark_skipped', 'duplicate_to_next_week']
    
    def mark_completed(self, request, queryset):
        """Mark selected entries as completed."""
        updated = queryset.update(is_completed=True)
        self.message_user(request, _('%(count)d entries marked as completed.') % {'count': updated})
    mark_completed.short_description = _('Mark as completed')
    
    def mark_skipped(self, request, queryset):
        """Mark selected entries as skipped."""
        updated = queryset.update(is_skipped=True)
        self.message_user(request, _('%(count)d entries marked as skipped.') % {'count': updated})
    mark_skipped.short_description = _('Mark as skipped')
    
    def duplicate_to_next_week(self, request, queryset):
        """Duplicate selected entries to next week."""
        from datetime import timedelta
        count = 0
        for entry in queryset:
            new_entry = ScheduleEntry(
                date=entry.date + timedelta(days=7),
                start_time=entry.start_time,
                end_time=entry.end_time,
                book=entry.book,
                verse=None,  # Verse will be generated
                lector=entry.lector,
                theme=entry.theme,
            )
            new_entry.save()
            count += 1
        self.message_user(request, _('%(count)d entries duplicated to next week.') % {'count': count})
    duplicate_to_next_week.short_description = _('Duplicate to next week')


@admin.register(ScheduleSettings)
class ScheduleSettingsAdmin(admin.ModelAdmin):
    """Admin for the ScheduleSettings model."""
    
    fieldsets = (
        (_('Morning SB Settings'), {
            'fields': (
                'morning_sb_start_time',
                'morning_sb_end_time',
                'morning_sb_days',
            )
        }),
        (_('Broadcast Settings'), {
            'fields': ('default_broadcast_duration_minutes',)
        }),
        (_('Telegram Settings'), {
            'fields': ('telegram_chat_id', 'telegram_subchannel_id')
        }),
    )
    
    def has_add_permission(self, request):
        """Prevent adding new instances."""
        return False
    
    def has_delete_permission(self, request, obj=None):
        """Prevent deletion."""
        return False


@admin.register(TelegramMessage)
class TelegramMessageAdmin(admin.ModelAdmin):
    """Admin for the TelegramMessage model."""
    
    list_display = ['message_type', 'chat_id', 'message_id', 'created_at']
    list_filter = ['message_type', 'chat_id']
    search_fields = ['text']
    readonly_fields = ['message_type', 'chat_id', 'message_id', 'text', 'schedule_entry', 'broadcast', 'created_at']
    date_hierarchy = 'created_at'
    
    def has_add_permission(self, request):
        return False
    
    def has_change_permission(self, request, obj=None):
        return False
