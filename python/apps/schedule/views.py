"""
Views for the schedule application.

Schedule management and weekly schedule generation.
"""
import logging
from datetime import timedelta, datetime

from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.contrib.auth.mixins import LoginRequiredMixin, UserPassesTestMixin
from django.http import JsonResponse
from django.shortcuts import render, redirect, get_object_or_404
from django.urls import reverse_lazy
from django.utils import timezone
from django.utils.translation import gettext as _
from django.views import View
from django.views.generic import ListView, CreateView, UpdateView, DeleteView

from .models import ScheduleEntry, ScheduleSettings, TelegramMessage
from apps.broadcasts.models import Lector, Book
from apps.integrations.services import TelegramService, ShastraService

logger = logging.getLogger(__name__)

# Ukrainian day names
DAY_NAMES_UK = {
    0: 'пн', 1: 'вт', 2: 'ср', 3: 'чт', 4: 'пт', 5: 'сб', 6: 'нд'
}


class AdminRequiredMixin(UserPassesTestMixin):
    """Mixin to require admin role."""
    
    def test_func(self):
        return self.request.user.is_admin


class ScheduleListView(LoginRequiredMixin, AdminRequiredMixin, ListView):
    """List view for schedule entries."""
    
    model = ScheduleEntry
    template_name = 'schedule/list.html'
    context_object_name = 'entries'
    ordering = ['-date', 'start_time']
    paginate_by = 30
    
    def get_queryset(self):
        queryset = super().get_queryset()
        
        # Filter by date range
        date_from = self.request.GET.get('date_from')
        date_to = self.request.GET.get('date_to')
        
        if date_from:
            queryset = queryset.filter(date__gte=date_from)
        if date_to:
            queryset = queryset.filter(date__lte=date_to)
        
        return queryset


class ScheduleCreateView(LoginRequiredMixin, AdminRequiredMixin, CreateView):
    """Create view for schedule entries."""
    
    model = ScheduleEntry
    template_name = 'schedule/form.html'
    fields = ['date', 'start_time', 'end_time', 'book', 'verse', 'lector', 'custom_title', 'theme', 'notes']
    success_url = reverse_lazy('schedule:list')
    
    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        context['lectors'] = Lector.objects.filter(is_active=True).order_by('name')
        context['books'] = Book.choices
        return context
    
    def form_valid(self, form):
        messages.success(self.request, _('Schedule entry created successfully.'))
        return super().form_valid(form)


class ScheduleUpdateView(LoginRequiredMixin, AdminRequiredMixin, UpdateView):
    """Update view for schedule entries."""
    
    model = ScheduleEntry
    template_name = 'schedule/form.html'
    fields = ['date', 'start_time', 'end_time', 'book', 'verse', 'lector', 'custom_title', 'theme', 'is_skipped', 'notes']
    success_url = reverse_lazy('schedule:list')
    
    def get_context_data(self, **kwargs):
        context = super().get_context_data(**kwargs)
        context['lectors'] = Lector.objects.filter(is_active=True).order_by('name')
        context['books'] = Book.choices
        return context
    
    def form_valid(self, form):
        messages.success(self.request, _('Schedule entry updated successfully.'))
        return super().form_valid(form)


class ScheduleDeleteView(LoginRequiredMixin, AdminRequiredMixin, DeleteView):
    """Delete view for schedule entries."""
    
    model = ScheduleEntry
    success_url = reverse_lazy('schedule:list')
    
    def delete(self, request, *args, **kwargs):
        messages.success(request, _('Schedule entry deleted.'))
        return super().delete(request, *args, **kwargs)


class WeeklyScheduleView(LoginRequiredMixin, AdminRequiredMixin, View):
    """View for weekly schedule management - Google Sheets style."""
    
    template_name = 'schedule/week.html'
    
    def get(self, request):
        # Get settings
        settings = ScheduleSettings.load()
        
        # Get week parameter or use current week
        week_param = request.GET.get('week')
        today = timezone.now().date()
        
        if week_param:
            try:
                week_start = datetime.strptime(week_param, '%Y-%m-%d').date()
                # Adjust to Monday
                week_start = week_start - timedelta(days=week_start.weekday())
            except ValueError:
                week_start = today - timedelta(days=today.weekday())
        else:
            week_start = today - timedelta(days=today.weekday())
        
        week_end = week_start + timedelta(days=6)
        
        # Navigation weeks
        prev_week = (week_start - timedelta(days=7)).strftime('%Y-%m-%d')
        next_week = (week_start + timedelta(days=7)).strftime('%Y-%m-%d')
        current_week = today - timedelta(days=today.weekday())
        current_week = current_week.strftime('%Y-%m-%d')
        
        # Get all entries for the week
        entries = ScheduleEntry.objects.filter(
            date__gte=week_start,
            date__lte=week_end,
        ).select_related('lector').order_by('date', 'start_time')
        
        # Group entries by date
        entries_by_date = {}
        for entry in entries:
            date_str = entry.date.strftime('%Y-%m-%d')
            if date_str not in entries_by_date:
                entries_by_date[date_str] = []
            entries_by_date[date_str].append(entry)
        
        # Build week days data
        week_days = []
        for i in range(7):
            day_date = week_start + timedelta(days=i)
            date_str = day_date.strftime('%Y-%m-%d')
            day_entries = entries_by_date.get(date_str, [])
            
            week_days.append({
                'date': day_date,
                'day_name': DAY_NAMES_UK.get(day_date.weekday(), ''),
                'is_today': day_date == today,
                'entries': day_entries,
            })
        
        # Get lectors and books for forms
        lectors = Lector.objects.filter(is_active=True).order_by('name')
        
        context = {
            'settings': settings,
            'week_start': week_start,
            'week_end': week_end,
            'week_days': week_days,
            'prev_week': prev_week,
            'next_week': next_week,
            'current_week': current_week,
            'lectors': lectors,
            'books': Book.choices,
        }
        
        return render(request, self.template_name, context)


@login_required
def generate_week_shlokas(request):
    """
    Generate shlokas for the next week.
    
    AJAX endpoint that generates verse numbers for the next week
    based on the last used verse.
    """
    if not request.user.is_admin:
        return JsonResponse({'error': 'Permission denied'}, status=403)
    
    try:
        shastra_service = ShastraService()
        
        # Get book from request
        book = request.POST.get('book', 'sb')
        
        # Get count
        count = int(request.POST.get('count', 5))
        
        # Generate verses
        verses = shastra_service.generate_next_week_shlokas(
            book=book,
            count=count,
        )
        
        return JsonResponse({
            'success': True,
            'verses': verses,
        })
        
    except Exception as e:
        logger.exception(f'Failed to generate shlokas: {e}')
        return JsonResponse({
            'success': False,
            'error': str(e),
        }, status=500)


@login_required
def send_schedule_to_telegram(request):
    """
    Send weekly schedule to Telegram.
    """
    if not request.user.is_admin:
        messages.error(request, _('Permission denied.'))
        return redirect('schedule:week')
    
    try:
        # Get date range
        date_from = request.POST.get('date_from')
        date_to = request.POST.get('date_to')
        
        if not date_from or not date_to:
            messages.error(request, _('Please provide date range.'))
            return redirect('schedule:week')
        
        # Get entries
        entries = ScheduleEntry.objects.filter(
            date__gte=date_from,
            date__lte=date_to,
            is_skipped=False,
        ).select_related('lector').order_by('date', 'start_time')
        
        if not entries:
            messages.warning(request, _('No entries found for the selected date range.'))
            return redirect('schedule:week')
        
        # Format message
        telegram_service = TelegramService()
        message_text = telegram_service.format_schedule_message(entries)
        
        # Send message
        result = telegram_service.send_schedule_message(message_text)
        
        if result:
            # Save message record
            TelegramMessage.objects.create(
                message_type=TelegramMessage.MessageType.SCHEDULE_WEEKLY,
                chat_id=result.get('chat', {}).get('id', 0),
                message_id=result.get('message_id'),
                text=message_text,
            )
            
            messages.success(request, _('Schedule sent to Telegram successfully!'))
        else:
            messages.error(request, _('Failed to send schedule to Telegram.'))
        
    except Exception as e:
        logger.exception(f'Failed to send schedule: {e}')
        messages.error(request, _('Failed to send schedule: %(error)s') % {'error': str(e)})
    
    return redirect('schedule:week')


@login_required
def entry_create(request):
    """
    Create a new schedule entry via AJAX.
    """
    if not request.user.is_admin:
        return JsonResponse({'success': False, 'error': 'Permission denied'}, status=403)
    
    if request.method != 'POST':
        return JsonResponse({'success': False, 'error': 'POST required'}, status=405)
    
    try:
        date_str = request.POST.get('date')
        start_time_str = request.POST.get('start_time')
        book = request.POST.get('book') or None
        verse = request.POST.get('verse') or None
        lector_id = request.POST.get('lector') or None
        theme = request.POST.get('theme') or None
        
        if not date_str or not start_time_str:
            return JsonResponse({'success': False, 'error': 'Date and time are required'}, status=400)
        
        # Parse date and time
        date = datetime.strptime(date_str, '%Y-%m-%d').date()
        start_time = datetime.strptime(start_time_str, '%H:%M').time()
        
        # Get lector
        lector = None
        if lector_id:
            try:
                lector = Lector.objects.get(pk=int(lector_id))
            except (Lector.DoesNotExist, ValueError):
                pass
        
        # Create entry
        entry = ScheduleEntry.objects.create(
            date=date,
            start_time=start_time,
            book=book,
            verse=verse,
            lector=lector,
            theme=theme,
        )
        
        return JsonResponse({
            'success': True,
            'entry_id': entry.id,
        })
        
    except Exception as e:
        logger.exception(f'Failed to create entry: {e}')
        return JsonResponse({'success': False, 'error': str(e)}, status=500)


@login_required
def entry_update(request, pk):
    """
    Update a schedule entry field via AJAX.
    """
    if not request.user.is_admin:
        return JsonResponse({'success': False, 'error': 'Permission denied'}, status=403)
    
    if request.method != 'POST':
        return JsonResponse({'success': False, 'error': 'POST required'}, status=405)
    
    try:
        entry = get_object_or_404(ScheduleEntry, pk=pk)
        
        field = request.POST.get('field')
        value = request.POST.get('value')
        
        if field == 'start_time':
            if value:
                entry.start_time = datetime.strptime(value, '%H:%M').time()
            else:
                return JsonResponse({'success': False, 'error': 'Time is required'}, status=400)
        elif field == 'book':
            entry.book = value or None
        elif field == 'verse':
            entry.verse = value or None
        elif field == 'lector':
            if value:
                try:
                    entry.lector = Lector.objects.get(pk=int(value))
                except (Lector.DoesNotExist, ValueError):
                    entry.lector = None
            else:
                entry.lector = None
        elif field == 'theme':
            entry.theme = value or None
        else:
            return JsonResponse({'success': False, 'error': 'Invalid field'}, status=400)
        
        entry.save()
        
        return JsonResponse({'success': True})
        
    except Exception as e:
        logger.exception(f'Failed to update entry: {e}')
        return JsonResponse({'success': False, 'error': str(e)}, status=500)


@login_required
def entry_delete(request, pk):
    """
    Delete a schedule entry via AJAX.
    """
    if not request.user.is_admin:
        return JsonResponse({'success': False, 'error': 'Permission denied'}, status=403)
    
    if request.method != 'POST':
        return JsonResponse({'success': False, 'error': 'POST required'}, status=405)
    
    try:
        entry = get_object_or_404(ScheduleEntry, pk=pk)
        entry.delete()
        
        return JsonResponse({'success': True})
        
    except Exception as e:
        logger.exception(f'Failed to delete entry: {e}')
        return JsonResponse({'success': False, 'error': str(e)}, status=500)
