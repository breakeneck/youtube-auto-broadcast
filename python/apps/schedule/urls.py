"""
URL configuration for the schedule application.
"""
from django.urls import path
from . import views

app_name = 'schedule'

urlpatterns = [
    # Schedule management
    path('', views.ScheduleListView.as_view(), name='list'),
    path('create/', views.ScheduleCreateView.as_view(), name='create'),
    path('<int:pk>/edit/', views.ScheduleUpdateView.as_view(), name='edit'),
    path('<int:pk>/delete/', views.ScheduleDeleteView.as_view(), name='delete'),
    
    # Weekly schedule
    path('week/', views.WeeklyScheduleView.as_view(), name='week'),
    path('generate/', views.generate_week_shlokas, name='generate'),
    
    # Telegram
    path('send-schedule/', views.send_schedule_to_telegram, name='send_schedule'),
]
