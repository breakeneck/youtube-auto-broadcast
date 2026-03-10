"""
Views for the broadcasts application.

Main broadcast control interface.
"""
import logging
from datetime import datetime, timedelta

from django.contrib import messages
from django.contrib.auth.decorators import login_required, permission_required
from django.contrib.auth.mixins import LoginRequiredMixin, UserPassesTestMixin
from django.http import JsonResponse
from django.shortcuts import render, redirect, get_object_or_404
from django.utils import timezone
from django.utils.translation import gettext as _
from django.views import View
from django.views.decorators.http import require_POST, require_GET

from .models import (
    Broadcast, BroadcastStatus, BroadcastLog, Lector, 
    Book, BOOK_MAPPING
)
from apps.schedule.models import ScheduleEntry
from apps.integrations.services import YouTubeService, OBSService, TelegramService

logger = logging.getLogger(__name__)


class BroadcasterRequiredMixin(UserPassesTestMixin):
    """Mixin to require broadcaster role."""
    
    def test_func(self):
        return self.request.user.is_broadcaster


class BroadcastView(LoginRequiredMixin, BroadcasterRequiredMixin, View):
    """
    Main broadcast control view.
    
    Shows current broadcast status and upcoming schedule.
    """
    
    template_name = 'broadcasts/index.html'
    
    def get(self, request):
        """Handle GET request."""
        # Get current active broadcast
        current_broadcast = Broadcast.objects.filter(
            status__in=[
                BroadcastStatus.LIVE,
                BroadcastStatus.STARTING,
                BroadcastStatus.ENDING,
            ]
        ).first()
        
        # Get upcoming schedule entries
        today = timezone.now().date()
        upcoming_entries = ScheduleEntry.objects.filter(
            date__gte=today,
            is_completed=False,
            is_skipped=False,
        ).select_related('lector').order_by('date', 'start_time')[:7]
        
        # Get lectors for dropdown
        lectors = Lector.objects.filter(is_active=True).order_by('name')
        
        context = {
            'current_broadcast': current_broadcast,
            'upcoming_entries': upcoming_entries,
            'lectors': lectors,
            'books': Book.choices,
            'today': today,
            'today_name': self._get_day_name(today.weekday()),
        }
        
        return render(request, self.template_name, context)
    
    def _get_day_name(self, day: int) -> str:
        """Get localized day name."""
        days = {
            0: _('Monday'),
            1: _('Tuesday'),
            2: _('Wednesday'),
            3: _('Thursday'),
            4: _('Friday'),
            5: _('Saturday'),
            6: _('Sunday'),
        }
        return days.get(day, '')


@login_required
@require_POST
def start_broadcast(request):
    """
    Start a new broadcast.
    
    Creates a YouTube broadcast, starts OBS, and sends notifications.
    """
    if not request.user.is_broadcaster:
        messages.error(request, _('You do not have permission to start broadcasts.'))
        return redirect('broadcasts:index')
    
    # Check if there's already an active broadcast
    active_broadcast = Broadcast.objects.filter(
        status__in=[BroadcastStatus.LIVE, BroadcastStatus.STARTING]
    ).first()
    
    if active_broadcast:
        messages.warning(request, _('A broadcast is already in progress.'))
        return redirect('broadcasts:index')
    
    # Get form data
    title = request.POST.get('title', '').strip()
    book = request.POST.get('book', '').strip()
    verse = request.POST.get('verse', '').strip()
    lector_id = request.POST.get('lector', '').strip()
    skip_notification = request.POST.get('skip_notification') == '1'
    
    # Validate
    if not title and not (book and verse):
        messages.error(request, _('Please provide a title or book/verse reference.'))
        return redirect('broadcasts:index')
    
    try:
        # Get lector
        lector = None
        if lector_id:
            lector = Lector.objects.filter(id=lector_id).first()
        
        # Build title
        if not title:
            book_display = dict(Book.choices).get(book, book)
            book_uk = BOOK_MAPPING.get(book, book)
            title = f'{book_uk} {verse} - {lector}' if lector else f'{book_display} {verse}'
        
        # Create broadcast record
        broadcast = Broadcast.objects.create(
            title=title,
            book=book if book else None,
            verse=verse if verse else None,
            lector=lector,
            status=BroadcastStatus.STARTING,
            started_by=request.user,
            scheduled_start=timezone.now(),
        )
        
        # Log the action
        BroadcastLog.objects.create(
            broadcast=broadcast,
            action=BroadcastLog.ActionType.START,
            user=request.user,
            message=f'Starting broadcast: {title}',
        )
        
        # Start OBS
        obs_service = OBSService()
        obs_service.start_obs()
        obs_service.wait(10)  # Wait for OBS to start
        
        # Create YouTube broadcast
        youtube_service = YouTubeService()
        broadcast_id = youtube_service.create_broadcast(
            title=title,
            description='',  # Will be updated later
            privacy='public',
        )
        
        broadcast.youtube_id = broadcast_id
        
        # Bind to stream
        from django.conf import settings
        youtube_service.bind_to_stream(broadcast_id, settings.YOUTUBE_STREAM_ID)
        
        # Go live
        youtube_service.go_live(broadcast_id)
        
        broadcast.status = BroadcastStatus.LIVE
        broadcast.actual_start = timezone.now()
        broadcast.save()
        
        # Send Telegram notification
        if not skip_notification:
            telegram_service = TelegramService()
            result = telegram_service.send_broadcast_notification(broadcast_id, title)
            if result:
                broadcast.telegram_message_id = result.get('message_id')
                broadcast.save()
        
        # Log success
        BroadcastLog.objects.create(
            broadcast=broadcast,
            action=BroadcastLog.ActionType.NOTIFY if not skip_notification else BroadcastLog.ActionType.START,
            user=request.user,
            message=f'Broadcast started successfully: {broadcast_id}',
        )
        
        messages.success(request, _('Broadcast started successfully!'))
        
    except Exception as e:
        logger.exception(f'Failed to start broadcast: {e}')
        
        # Update status
        if 'broadcast' in locals():
            broadcast.status = BroadcastStatus.ERROR
            broadcast.error_message = str(e)
            broadcast.save()
            
            BroadcastLog.objects.create(
                broadcast=broadcast,
                action=BroadcastLog.ActionType.ERROR,
                user=request.user,
                message=str(e),
            )
        
        messages.error(request, _('Failed to start broadcast: %(error)s') % {'error': str(e)})
    
    return redirect('broadcasts:index')


@login_required
@require_POST
def stop_broadcast(request):
    """
    Stop the current broadcast.
    
    Ends the YouTube broadcast and stops OBS.
    """
    if not request.user.is_broadcaster:
        messages.error(request, _('You do not have permission to stop broadcasts.'))
        return redirect('broadcasts:index')
    
    # Get current broadcast
    broadcast = Broadcast.objects.filter(
        status__in=[BroadcastStatus.LIVE, BroadcastStatus.STARTING]
    ).first()
    
    if not broadcast:
        messages.warning(request, _('No active broadcast to stop.'))
        return redirect('broadcasts:index')
    
    try:
        # Update status
        broadcast.status = BroadcastStatus.ENDING
        broadcast.save()
        
        # End YouTube broadcast
        if broadcast.youtube_id:
            youtube_service = YouTubeService()
            youtube_service.finish_broadcast(broadcast.youtube_id)
        
        # Stop OBS
        obs_service = OBSService()
        obs_service.stop_obs()
        
        # Update status
        broadcast.status = BroadcastStatus.COMPLETED
        broadcast.actual_end = timezone.now()
        broadcast.save()
        
        # Log the action
        BroadcastLog.objects.create(
            broadcast=broadcast,
            action=BroadcastLog.ActionType.STOP,
            user=request.user,
            message='Broadcast stopped',
        )
        
        messages.success(request, _('Broadcast stopped successfully!'))
        
    except Exception as e:
        logger.exception(f'Failed to stop broadcast: {e}')
        
        broadcast.status = BroadcastStatus.ERROR
        broadcast.error_message = str(e)
        broadcast.save()
        
        BroadcastLog.objects.create(
            broadcast=broadcast,
            action=BroadcastLog.ActionType.ERROR,
            user=request.user,
            message=str(e),
        )
        
        messages.error(request, _('Failed to stop broadcast: %(error)s') % {'error': str(e)})
    
    return redirect('broadcasts:index')


@login_required
def reset_broadcast(request):
    """
    Reset broadcast state (clear error state).
    """
    if not request.user.is_broadcaster:
        messages.error(request, _('You do not have permission to reset broadcasts.'))
        return redirect('broadcasts:index')
    
    # Get stuck broadcasts
    stuck_broadcasts = Broadcast.objects.filter(
        status__in=[BroadcastStatus.ERROR, BroadcastStatus.STARTING, BroadcastStatus.ENDING]
    )
    
    for broadcast in stuck_broadcasts:
        old_status = broadcast.status
        broadcast.status = BroadcastStatus.CANCELLED
        broadcast.save()
        
        BroadcastLog.objects.create(
            broadcast=broadcast,
            action=BroadcastLog.ActionType.RESET,
            user=request.user,
            message=f'Reset from {old_status} to cancelled',
        )
    
    messages.success(request, _('Broadcast state reset.'))
    return redirect('broadcasts:index')


@login_required
def reset_camera(request):
    """
    Restart the camera/VLC stream.
    """
    if not request.user.is_broadcaster:
        messages.error(request, _('You do not have permission to reset camera.'))
        return redirect('broadcasts:index')
    
    try:
        obs_service = OBSService()
        obs_service.restart_camera()
        messages.success(request, _('Camera restarted successfully!'))
    except Exception as e:
        logger.exception(f'Failed to restart camera: {e}')
        messages.error(request, _('Failed to restart camera: %(error)s') % {'error': str(e)})
    
    return redirect('broadcasts:index')


@login_required
def exit_apps(request):
    """
    Stop OBS and exit all applications.
    """
    if not request.user.is_broadcaster:
        messages.error(request, _('You do not have permission to exit apps.'))
        return redirect('broadcasts:index')
    
    try:
        obs_service = OBSService()
        obs_service.stop_obs()
        messages.success(request, _('Applications stopped.'))
    except Exception as e:
        logger.exception(f'Failed to stop apps: {e}')
        messages.error(request, _('Failed to stop apps: %(error)s') % {'error': str(e)})
    
    return redirect('broadcasts:index')


@require_GET
def broadcast_status(request):
    """
    API endpoint to get current broadcast status.
    """
    broadcast = Broadcast.objects.filter(
        status__in=[BroadcastStatus.LIVE, BroadcastStatus.STARTING, BroadcastStatus.ENDING]
    ).first()
    
    if broadcast:
        return JsonResponse({
            'status': broadcast.status,
            'youtube_id': broadcast.youtube_id,
            'title': broadcast.title,
            'youtube_url': broadcast.get_youtube_url(),
            'started_at': broadcast.actual_start.isoformat() if broadcast.actual_start else None,
        })
    
    return JsonResponse({'status': None})


@require_GET
def upcoming_schedule(request):
    """
    API endpoint to get upcoming schedule.
    """
    today = timezone.now().date()
    entries = ScheduleEntry.objects.filter(
        date__gte=today,
        is_completed=False,
        is_skipped=False,
    ).select_related('lector').order_by('date', 'start_time')[:7]
    
    data = []
    for entry in entries:
        data.append({
            'id': entry.id,
            'date': entry.date.isoformat(),
            'time': entry.start_time.strftime('%H:%M'),
            'title': entry.get_title(),
            'book': entry.book,
            'verse': entry.verse,
            'lector': str(entry.lector) if entry.lector else None,
        })
    
    return JsonResponse({'entries': data})
