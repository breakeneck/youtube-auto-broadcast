"""
Setup initial data management command.

Creates initial lectors and other required data.
"""
from django.core.management.base import BaseCommand
from django.db import transaction

from apps.broadcasts.models import Lector
from apps.schedule.models import ScheduleSettings


class Command(BaseCommand):
    """
    Setup initial data for the broadcast application.
    
    Creates:
    - Initial lectors
    - Schedule settings
    """
    
    help = 'Setup initial data for the broadcast application'
    
    # Initial lectors (alphabetically ordered, no duplicates)
    INITIAL_LECTORS = [
        'Бала пр.',
        'Вамана пр.',
        'Васудама пр.',
        'Ґовінда Валабга пр.',
        'Крішнадас пр.',
        'Патіта Павана пр.',
        'Према Махотсава пр.',
        'Санатана Дгарма пр.',
        'Санджай пр.',
        'Форостян Павло пр.',
        'Юґа Павана пр.',
        'Яшодадулал пр.',
    ]
    
    def handle(self, *args, **options):
        """Main command handler."""
        self.stdout.write('Setting up initial data...')
        
        # Create lectors
        self._create_lectors()
        
        # Create schedule settings
        self._create_schedule_settings()
        
        self.stdout.write(self.style.SUCCESS('Initial data setup completed!'))
    
    @transaction.atomic
    def _create_lectors(self):
        """Create initial lectors."""
        self.stdout.write('Creating lectors...')
        
        created_count = 0
        for name in self.INITIAL_LECTORS:
            lector, created = Lector.objects.get_or_create(
                name=name,
                defaults={'is_active': True}
            )
            if created:
                created_count += 1
                self.stdout.write(f'  Created: {name}')
            else:
                self.stdout.write(f'  Already exists: {name}')
        
        self.stdout.write(
            self.style.SUCCESS(f'Created {created_count} new lectors')
        )
    
    @transaction.atomic
    def _create_schedule_settings(self):
        """Create schedule settings."""
        self.stdout.write('Creating schedule settings...')
        
        settings, created = ScheduleSettings.objects.get_or_create(
            pk=1,
            defaults={
                'morning_sb_start_time': '07:00',
                'morning_sb_end_time': '08:30',
                'default_broadcast_duration_minutes': 120,
                'morning_sb_days': '0,1,2,3,4',  # Mon-Fri
            }
        )
        
        if created:
            self.stdout.write(self.style.SUCCESS('Created schedule settings'))
        else:
            self.stdout.write('Schedule settings already exist')
