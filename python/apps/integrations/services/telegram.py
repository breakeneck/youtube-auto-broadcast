"""
Telegram API integration service.

Provides methods for sending and managing Telegram messages.
"""
import logging
from typing import Optional, Dict, Any

import requests
from django.conf import settings

logger = logging.getLogger(__name__)


class TelegramService:
    """
    Service class for Telegram API integration.
    
    Handles:
    - Sending messages
    - Updating existing messages
    - Managing message formatting
    """
    
    API_BASE_URL = 'https://api.telegram.org/bot{token}/{method}'
    
    def __init__(self, token: Optional[str] = None):
        """
        Initialize Telegram service.
        
        Args:
            token: Telegram bot token. If None, uses settings.TG_API_TOKEN
        """
        self.token = token or settings.TG_API_TOKEN
    
    def _get_api_url(self, method: str) -> str:
        """
        Get full API URL for a method.
        
        Args:
            method: Telegram API method name
            
        Returns:
            Full API URL
        """
        return self.API_BASE_URL.format(token=self.token, method=method)
    
    def _make_request(
        self,
        method: str,
        data: Optional[Dict[str, Any]] = None
    ) -> Optional[Dict[str, Any]]:
        """
        Make a request to Telegram API.
        
        Args:
            method: API method name
            data: Request data
            
        Returns:
            Response data or None if failed
        """
        url = self._get_api_url(method)
        
        try:
            response = requests.post(url, json=data, timeout=30)
            response.raise_for_status()
            result = response.json()
            
            if result.get('ok'):
                return result.get('result')
            else:
                logger.error(f'Telegram API error: {result}')
                return None
                
        except requests.RequestException as e:
            logger.error(f'Telegram API request failed: {e}')
            return None
    
    def send_message(
        self,
        chat_id: int,
        text: str,
        parse_mode: str = 'HTML',
        disable_notification: bool = False,
        reply_to_message_id: Optional[int] = None,
    ) -> Optional[Dict[str, Any]]:
        """
        Send a message to a chat.
        
        Args:
            chat_id: Telegram chat ID
            text: Message text
            parse_mode: Parse mode ('HTML', 'Markdown', 'MarkdownV2')
            disable_notification: Send silently
            reply_to_message_id: Message ID to reply to
            
        Returns:
            Message data or None if failed
        """
        data = {
            'chat_id': chat_id,
            'text': text,
            'parse_mode': parse_mode,
            'disable_notification': disable_notification,
        }
        
        if reply_to_message_id:
            data['reply_to_message_id'] = reply_to_message_id
        
        result = self._make_request('sendMessage', data)
        
        if result:
            logger.info(f'Sent message to chat {chat_id}')
        return result
    
    def edit_message(
        self,
        chat_id: int,
        message_id: int,
        text: str,
        parse_mode: str = 'HTML',
    ) -> Optional[Dict[str, Any]]:
        """
        Edit an existing message.
        
        Args:
            chat_id: Telegram chat ID
            message_id: Message ID to edit
            text: New message text
            parse_mode: Parse mode
            
        Returns:
            Message data or None if failed
        """
        data = {
            'chat_id': chat_id,
            'message_id': message_id,
            'text': text,
            'parse_mode': parse_mode,
        }
        
        result = self._make_request('editMessageText', data)
        
        if result:
            logger.info(f'Edited message {message_id} in chat {chat_id}')
        return result
    
    def delete_message(
        self,
        chat_id: int,
        message_id: int,
    ) -> bool:
        """
        Delete a message.
        
        Args:
            chat_id: Telegram chat ID
            message_id: Message ID to delete
            
        Returns:
            True if successful
        """
        data = {
            'chat_id': chat_id,
            'message_id': message_id,
        }
        
        result = self._make_request('deleteMessage', data)
        
        if result:
            logger.info(f'Deleted message {message_id} in chat {chat_id}')
        return bool(result)
    
    def send_broadcast_notification(
        self,
        broadcast_id: str,
        title: str,
        chat_id: Optional[int] = None,
    ) -> Optional[Dict[str, Any]]:
        """
        Send a broadcast notification message.
        
        Args:
            broadcast_id: YouTube broadcast ID
            title: Broadcast title
            chat_id: Chat ID (uses default if not provided)
            
        Returns:
            Message data or None if failed
        """
        if chat_id is None:
            chat_id = settings.TG_CHAT_ID
        
        youtube_url = f'https://www.youtube.com/watch?v={broadcast_id}'
        text = f'🔴 Пряма трансляція: {title}\n\n{youtube_url}'
        
        return self.send_message(chat_id, text)
    
    def send_schedule_message(
        self,
        schedule_text: str,
        chat_id: Optional[int] = None,
    ) -> Optional[Dict[str, Any]]:
        """
        Send a schedule message.
        
        Args:
            schedule_text: Formatted schedule text
            chat_id: Chat ID (uses subchannel if not provided)
            
        Returns:
            Message data or None if failed
        """
        if chat_id is None:
            chat_id = settings.TG_SUBCHANNEL_ID or settings.TG_CHAT_ID
        
        return self.send_message(chat_id, schedule_text)
    
    def format_schedule_message(
        self,
        entries: list,
        title: str = 'Розклад лекцій',
    ) -> str:
        """
        Format schedule entries as a Telegram message.
        
        Args:
            entries: List of ScheduleEntry objects
            title: Message title
            
        Returns:
            Formatted message text
        """
        lines = [f'📅 {title}']
        
        # Group entries by date
        current_date = None
        for entry in entries:
            if entry.date != current_date:
                current_date = entry.date
                # Format date in Ukrainian
                date_str = entry.date.strftime('%d.%m')
                day_name = self._get_day_name_uk(entry.date.weekday())
                lines.append(f'\n{day_name} {date_str}')
            
            # Format entry
            if entry.book and entry.verse:
                book_uk = self._get_book_uk(entry.book)
                verse_str = f'{book_uk} {entry.verse}'
            else:
                verse_str = ''
            
            lector_str = str(entry.lector) if entry.lector else ''
            
            line_parts = [p for p in [verse_str, lector_str] if p]
            lines.append('  ' + '  '.join(line_parts))
        
        return '\n'.join(lines)
    
    def _get_day_name_uk(self, day: int) -> str:
        """Get Ukrainian day name abbreviation."""
        days = {
            0: 'пн', 1: 'вт', 2: 'ср', 3: 'чт', 4: 'пт', 5: 'сб', 6: 'нд'
        }
        return days.get(day, '')
    
    def _get_book_uk(self, book: str) -> str:
        """Get Ukrainian book abbreviation."""
        books = {
            'bg': 'БГ',
            'sb': 'ШБ',
            'cc': 'ЧЧ',
        }
        return books.get(book, book)
    
    def get_updates(self, offset: Optional[int] = None) -> list:
        """
        Get bot updates (for getting chat IDs).
        
        Args:
            offset: Update offset for pagination
            
        Returns:
            List of updates
        """
        data = {}
        if offset:
            data['offset'] = offset
        
        result = self._make_request('getUpdates', data)
        return result or []
