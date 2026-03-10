"""
YouTube API integration service.

Provides methods for creating and managing YouTube live broadcasts.
"""
import logging
from datetime import datetime, timedelta
from typing import Optional, Dict, Any

from django.conf import settings
from google.oauth2.credentials import Credentials
from google_auth_oauthlib.flow import InstalledAppFlow
from google.auth.transport.requests import Request
from googleapiclient.discovery import build
from googleapiclient.errors import HttpError

logger = logging.getLogger(__name__)


class YouTubeService:
    """
    Service class for YouTube API integration.
    
    Handles:
    - Authentication with YouTube API
    - Creating live broadcasts
    - Binding broadcasts to streams
    - Transitioning broadcast states
    """
    
    SCOPES = [
        'https://www.googleapis.com/auth/youtube',
        'https://www.googleapis.com/auth/youtube.force-ssl',
    ]
    
    def __init__(self, credentials_file: Optional[str] = None):
        """
        Initialize YouTube service.
        
        Args:
            credentials_file: Path to OAuth credentials JSON file.
                             If None, uses settings.YOUTUBE_AUTH_FILE
        """
        self.credentials_file = credentials_file or settings.YOUTUBE_AUTH_FILE
        self._service = None
        self._credentials = None
    
    @property
    def service(self):
        """Get or create YouTube service instance."""
        if self._service is None:
            self._service = self._get_service()
        return self._service
    
    def _get_credentials(self) -> Credentials:
        """
        Get OAuth credentials for YouTube API.
        
        Returns:
            Credentials object
            
        Raises:
            FileNotFoundError: If credentials file not found
            Exception: If authentication fails
        """
        import os
        import pickle
        
        creds = None
        token_file = self.credentials_file.replace('.json', '_token.pickle')
        
        # Load existing token if available
        if os.path.exists(token_file):
            with open(token_file, 'rb') as token:
                creds = pickle.load(token)
        
        # If no valid credentials, authenticate
        if not creds or not creds.valid:
            if creds and creds.expired and creds.refresh_token:
                creds.refresh(Request())
            else:
                flow = InstalledAppFlow.from_client_secrets_file(
                    self.credentials_file, self.SCOPES
                )
                creds = flow.run_local_server(port=0)
            
            # Save credentials for future use
            with open(token_file, 'wb') as token:
                pickle.dump(creds, token)
        
        return creds
    
    def _get_service(self):
        """
        Create YouTube API service instance.
        
        Returns:
            YouTube API service
        """
        credentials = self._get_credentials()
        return build('youtube', 'v3', credentials=credentials)
    
    def create_broadcast(
        self,
        title: str,
        description: str = '',
        start_time: Optional[datetime] = None,
        end_time: Optional[datetime] = None,
        privacy: str = 'public',
        enable_dvr: bool = True,
        enable_embed: bool = True,
    ) -> str:
        """
        Create a new YouTube live broadcast.
        
        Args:
            title: Broadcast title
            description: Broadcast description
            start_time: Scheduled start time (defaults to now)
            end_time: Scheduled end time (defaults to 2 hours from start)
            privacy: Privacy status ('public', 'unlisted', 'private')
            enable_dvr: Enable DVR playback
            enable_embed: Allow embedding
            
        Returns:
            Broadcast ID
            
        Raises:
            HttpError: If API request fails
        """
        if start_time is None:
            start_time = datetime.utcnow()
        if end_time is None:
            end_time = start_time + timedelta(
                minutes=settings.DEFAULT_BROADCAST_DURATION_MINUTES
            )
        
        # Format times for API
        start_str = start_time.strftime('%Y-%m-%dT%H:%M:%SZ')
        end_str = end_time.strftime('%Y-%m-%dT%H:%M:%SZ')
        
        broadcast_body = {
            'snippet': {
                'title': title,
                'description': description,
                'scheduledStartTime': start_str,
                'scheduledEndTime': end_str,
            },
            'status': {
                'privacyStatus': privacy,
                'selfDeclaredMadeForKids': False,
            },
            'contentDetails': {
                'enableAutoStart': False,
                'enableAutoStop': False,
                'enableClosedCaptions': True,
                'enableDvr': enable_dvr,
                'enableEmbed': enable_embed,
                'enableLowLatency': True,
                'monitorStream': {
                    'enableMonitorStream': False,
                },
                'startWithSlate': True,
            },
        }
        
        try:
            response = self.service.liveBroadcasts().insert(
                part='snippet,contentDetails,status',
                body=broadcast_body
            ).execute()
            
            broadcast_id = response['id']
            logger.info(f'Created broadcast: {broadcast_id} - {title}')
            return broadcast_id
            
        except HttpError as e:
            logger.error(f'Failed to create broadcast: {e}')
            raise
    
    def bind_to_stream(self, broadcast_id: str, stream_id: str) -> bool:
        """
        Bind a broadcast to a stream.
        
        Args:
            broadcast_id: YouTube broadcast ID
            stream_id: YouTube stream ID
            
        Returns:
            True if successful
            
        Raises:
            HttpError: If API request fails
        """
        try:
            self.service.liveBroadcasts().bind(
                part='id,contentDetails',
                id=broadcast_id,
                streamId=stream_id
            ).execute()
            
            logger.info(f'Bound broadcast {broadcast_id} to stream {stream_id}')
            return True
            
        except HttpError as e:
            logger.error(f'Failed to bind broadcast to stream: {e}')
            raise
    
    def go_live(self, broadcast_id: str) -> bool:
        """
        Transition broadcast to live state.
        
        Args:
            broadcast_id: YouTube broadcast ID
            
        Returns:
            True if successful
            
        Raises:
            HttpError: If API request fails
        """
        try:
            self.service.liveBroadcasts().transition(
                part='snippet,status',
                id=broadcast_id,
                status='live'
            ).execute()
            
            logger.info(f'Broadcast {broadcast_id} is now live')
            return True
            
        except HttpError as e:
            logger.error(f'Failed to transition broadcast to live: {e}')
            raise
    
    def finish_broadcast(self, broadcast_id: str) -> bool:
        """
        Transition broadcast to complete state.
        
        Args:
            broadcast_id: YouTube broadcast ID
            
        Returns:
            True if successful
            
        Raises:
            HttpError: If API request fails
        """
        try:
            self.service.liveBroadcasts().transition(
                part='snippet,status',
                id=broadcast_id,
                status='complete'
            ).execute()
            
            logger.info(f'Broadcast {broadcast_id} completed')
            return True
            
        except HttpError as e:
            logger.error(f'Failed to complete broadcast: {e}')
            raise
    
    def get_broadcast(self, broadcast_id: str) -> Optional[Dict[str, Any]]:
        """
        Get broadcast details.
        
        Args:
            broadcast_id: YouTube broadcast ID
            
        Returns:
            Broadcast details or None if not found
        """
        try:
            response = self.service.liveBroadcasts().list(
                part='snippet,contentDetails,status',
                id=broadcast_id
            ).execute()
            
            items = response.get('items', [])
            return items[0] if items else None
            
        except HttpError as e:
            logger.error(f'Failed to get broadcast: {e}')
            return None
    
    def list_streams(self) -> list:
        """
        List all live streams.
        
        Returns:
            List of stream details
        """
        try:
            response = self.service.liveStreams().list(
                part='cdn,status,snippet',
                mine=True
            ).execute()
            
            return response.get('items', [])
            
        except HttpError as e:
            logger.error(f'Failed to list streams: {e}')
            return []
    
    def get_stream_status(self, stream_id: str) -> Optional[Dict[str, Any]]:
        """
        Get stream status.
        
        Args:
            stream_id: YouTube stream ID
            
        Returns:
            Stream status details or None if not found
        """
        try:
            response = self.service.liveStreams().list(
                part='status,cdn',
                id=stream_id
            ).execute()
            
            items = response.get('items', [])
            return items[0] if items else None
            
        except HttpError as e:
            logger.error(f'Failed to get stream status: {e}')
            return None
