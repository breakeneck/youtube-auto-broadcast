"""
URL configuration for the broadcasts application.
"""
from django.urls import path
from . import views

app_name = 'broadcasts'

urlpatterns = [
    # Main broadcast control
    path('', views.BroadcastView.as_view(), name='index'),
    path('start/', views.start_broadcast, name='start'),
    path('stop/', views.stop_broadcast, name='stop'),
    path('reset/', views.reset_broadcast, name='reset'),
    path('reset-camera/', views.reset_camera, name='reset_camera'),
    path('exit-apps/', views.exit_apps, name='exit_apps'),
    
    # API endpoints
    path('api/status/', views.broadcast_status, name='api_status'),
    path('api/upcoming/', views.upcoming_schedule, name='api_upcoming'),
]
