"""
Scheduler management command.

Runs the broadcast scheduler that checks for pending actions every minute.
"""
import logging
from datetime import datetime, timedelta

from django.core.management.base import BaseCommand
from django.utils import timezone
from django.conf import settings

from apps.broadcasts.models import Broadcast, BroadcastStatus, BroadcastLog
from apps.schedule.models import ScheduleEntry, ScheduleSettings
from apps.integrations.services import YouTubeService, OBSService, TelegramService

logger = logging.getLogger(__name__)


class Command(BaseCommand):
    """
    Run the broadcast scheduler.
    
    This command checks for scheduled broadcasts and executes them at the
    appropriate time. It should be run via cron every minute.
    
    Usage:
        python manage.py run_scheduler
    """
    
    help = 'Run the broadcast scheduler to check for pending actions'
    
    def handle(self, *args, **options):
        """Main command handler."""
        self.stdout.write(f'Running scheduler at {timezone.now()}')
        
        # Check for broadcasts to start
        self._check_start_broadcasts()
        
        # Check for broadcasts to stop
        self._check_stop_broadcasts()
        
        self.stdout.write('Scheduler run completed')
    
    def _check_start_broadcasts(self):
        """Check for broadcasts that should be started."""
        now = timezone.now()
        
        # Get schedule entries that should start
        # Look for entries within the next 2 minutes
        start_window = now + timedelta(minutes=2)
        
        entries = ScheduleEntry.objects.filter(
            date=now.date(),
            start_time__lte=start_window.time(),
            start_time__gt=now.time(),
            is_completed=False,
            is_skipped=False,
            broadcast__isnull=True,
        ).select_related('lector')
        
        for entry in entries:
            # Check if we should start now (within 1 minute of scheduled time)
            scheduled_datetime = datetime.combine(entry.date, entry.start_time)
            scheduled_datetime = timezone.make_aware(scheduled_datetime)
            
            time_until_start = (scheduled_datetime - now).total_seconds()
            
            if 0 < time_until_start <= 60:
                self._start_scheduled_broadcast(entry)
    
    def _start_scheduled_broadcast(self, entry: ScheduleEntry):
        """
        Start a scheduled broadcast.
        
        Args:
            entry: ScheduleEntry to start
        """
        self.stdout.write(f'Starting scheduled broadcast: {entry.get_title()}')
        
        try:
            # Create broadcast record
            broadcast = Broadcast.objects.create(
                title=entry.get_title(),
                book=entry.book,
                verse=entry.verse,
                lector=entry.lector,
                status=BroadcastStatus.STARTING,
                scheduled_start=timezone.make_aware(
                    datetime.combine(entry.date, entry.start_time)
                ),
            )
            
            # Link to schedule entry
            entry.broadcast = broadcast
            entry.save()
            
            # Log the action
            BroadcastLog.objects.create(
                broadcast=broadcast,
                action=BroadcastLog.ActionType.START,
                message=f'Scheduled broadcast started automatically',
            )
            
            # Start OBS
            obs_service = OBSService()
            obs_service.start_obs()
            obs_service.wait(10)
            
            # Create YouTube broadcast
            youtube_service = YouTubeService()
            broadcast_id = youtube_service.create_broadcast(
                title=broadcast.title,
                description='',
                privacy=settings.YOUTUBE_PRIVACY,
            )
            
            broadcast.youtube_id = broadcast_id
            
            # Bind to stream
            youtube_service.bind_to_stream(broadcast_id, settings.YOUTUBE_STREAM_ID)
            
            # Go live
            youtube_service.go_live(broadcast_id)
            
            broadcast.status = BroadcastStatus.LIVE
            broadcast.actual_start = timezone.now()
            broadcast.save()
            
            # Send Telegram notification
            telegram_service = TelegramService()
            result = telegram_service.send_broadcast_notification(broadcast_id, broadcast.title)
            if result:
                broadcast.telegram_message_id = result.get('message_id')
                broadcast.save()
            
            # Log success
            BroadcastLog.objects.create(
                broadcast=broadcast,
                action=BroadcastLog.ActionType.NOTIFY,
                message=f'Broadcast started successfully: {broadcast_id}',
            )
            
            self.stdout.write(
                self.style.SUCCESS(f'Successfully started broadcast: {broadcast_id}')
            )
            
        except Exception as e:
            logger.exception(f'Failed to start scheduled broadcast: {e}')
            
            if 'broadcast' in locals():
                broadcast.status = BroadcastStatus.ERROR
                broadcast.error_message = str(e)
                broadcast.save()
                
                BroadcastLog.objects.create(
                    broadcast=broadcast,
                    action=BroadcastLog.ActionType.ERROR,
                    message=str(e),
                )
            
            self.stdout.write(
                self.style.ERROR(f'Failed to start broadcast: {e}')
            )
    
    def _check_stop_broadcasts(self):
        """Check for broadcasts that should be stopped."""
        now = timezone.now()
        
        # Get settings
        settings_obj = ScheduleSettings.load()
        
        # Get active broadcasts
        active_broadcasts = Broadcast.objects.filter(
            status=BroadcastStatus.LIVE
        )
        
        for broadcast in active_broadcasts:
            # Check if broadcast has been running too long
            if broadcast.actual_start:
                running_time = (now - broadcast.actual_start).total_seconds()
                max_duration = settings_obj.default_broadcast_duration_minutes * 60
                
                if running_time >= max_duration:
                    self._stop_broadcast(broadcast)
    
    def _stop_broadcast(self, broadcast: Broadcast):
        """
        Stop a broadcast.
        
        Args:
            broadcast: Broadcast to stop
        """
        self.stdout.write(f'Stopping broadcast: {broadcast.title}')
        
        try:
            broadcast.status = BroadcastStatus.ENDING
            broadcast.save()
            
            # End YouTube broadcast
            if broadcast.youtube_id:
                youtube_service = YouTubeService()
                youtube_service.finish_broadcast(broadcast.youtube_id)
            
            # Stop OBS
            obs_service = OBSService()
            obs_service.stop_obs()
            
            broadcast.status = BroadcastStatus.COMPLETED
            broadcast.actual_end = timezone.now()
            broadcast.save()
            
            # Mark schedule entry as completed
            if hasattr(broadcast, 'schedule_entries'):
                for entry in broadcast.schedule_entries.all():
                    entry.is_completed = True
                    entry.save()
            
            # Log the action
            BroadcastLog.objects.create(
                broadcast=broadcast,
                action=BroadcastLog.ActionType.STOP,
                message='Broadcast stopped automatically',
            )
            
            self.stdout.write(
                self.style.SUCCESS(f'Successfully stopped broadcast: {broadcast.youtube_id}')
            )
            
        except Exception as e:
            logger.exception(f'Failed to stop broadcast: {e}')
            
            broadcast.status = BroadcastStatus.ERROR
            broadcast.error_message = str(e)
            broadcast.save()
            
            BroadcastLog.objects.create(
                broadcast=broadcast,
                action=BroadcastLog.ActionType.ERROR,
                message=str(e),
            )
            
            self.stdout.write(
                self.style.ERROR(f'Failed to stop broadcast: {e}')
            )
