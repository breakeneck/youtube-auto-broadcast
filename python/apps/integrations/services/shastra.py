"""
Shastra database integration service.

Provides methods for fetching shlokas from the external shastra database.
"""
import logging
from typing import Optional, List, Dict, Any

from django.db import connections
from django.conf import settings

logger = logging.getLogger(__name__)


class ShastraService:
    """
    Service class for Shastra database integration.
    
    Handles:
    - Fetching shlokas from the shastra database
    - Finding next shloka with purport
    - Getting verse content for broadcasts
    """
    
    # Language priority for fallback
    LANGUAGE_PRIORITY = ['uk', 'ru', 'en']
    
    # Book mappings
    BOOK_NAMES = {
        'bg': 'Bhagavad-gita',
        'sb': 'Srimad-Bhagavatam',
        'cc': 'Caitanya-caritamrta',
    }
    
    BOOK_NAMES_UK = {
        'bg': 'БГ',
        'sb': 'ШБ',
        'cc': 'ЧЧ',
    }
    
    # CC Lila mappings
    CC_LILA = {
        '1': 'adi',
        '2': 'madhya',
        '3': 'antya',
    }
    
    def __init__(self):
        """Initialize Shastra service."""
        self.db_alias = 'shastra'
    
    def get_shloka(
        self,
        book: str,
        chapter: int,
        verse: str,
        language: str = 'uk',
        source: str = 'vedabase',
    ) -> Optional[Dict[str, Any]]:
        """
        Get a shloka from the database.
        
        Args:
            book: Book code (bg, sb, cc)
            chapter: Chapter number
            verse: Verse number or range
            language: Language code
            source: Source engine
            
        Returns:
            Shloka data or None if not found
        """
        try:
            with connections[self.db_alias].cursor() as cursor:
                cursor.execute(
                    """
                    SELECT id, book, chapter, verse, text, translation, 
                           purport, has_purport, language, source, url
                    FROM shlokas
                    WHERE book = %s AND chapter = %s AND verse = %s 
                          AND language = %s AND source = %s
                    LIMIT 1
                    """,
                    [book, chapter, verse, language, source]
                )
                
                row = cursor.fetchone()
                
                if row:
                    return {
                        'id': row[0],
                        'book': row[1],
                        'chapter': row[2],
                        'verse': row[3],
                        'text': row[4],
                        'translation': row[5],
                        'purport': row[6],
                        'has_purport': row[7],
                        'language': row[8],
                        'source': row[9],
                        'url': row[10],
                    }
                    
        except Exception as e:
            logger.error(f'Failed to get shloka: {e}')
        
        return None
    
    def get_shloka_with_fallback(
        self,
        book: str,
        chapter: int,
        verse: str,
        preferred_language: str = 'uk',
        source: str = 'vedabase',
    ) -> Optional[Dict[str, Any]]:
        """
        Get a shloka with language fallback.
        
        Tries preferred language first, then falls back to other languages.
        
        Args:
            book: Book code
            chapter: Chapter number
            verse: Verse number
            preferred_language: Preferred language code
            source: Source engine
            
        Returns:
            Shloka data or None if not found
        """
        # Build language priority list
        languages = [preferred_language]
        for lang in self.LANGUAGE_PRIORITY:
            if lang not in languages:
                languages.append(lang)
        
        # Try each language
        for language in languages:
            shloka = self.get_shloka(book, chapter, verse, language, source)
            if shloka:
                return shloka
        
        return None
    
    def get_next_shloka_with_purport(
        self,
        book: str,
        chapter: int,
        verse: str,
        language: str = 'uk',
        source: str = 'vedabase',
    ) -> Optional[Dict[str, Any]]:
        """
        Get the next shloka that has a purport.
        
        For SB morning classes, we need shlokas with purports.
        If the current verse doesn't have a purport, find the next one that does.
        
        Args:
            book: Book code
            chapter: Chapter number
            verse: Starting verse number
            language: Language code
            source: Source engine
            
        Returns:
            Shloka data with purport or None if not found
        """
        # First check if current verse has purport
        shloka = self.get_shloka(book, chapter, verse, language, source)
        
        if shloka and shloka.get('has_purport'):
            return shloka
        
        # Find next verse with purport
        try:
            with connections[self.db_alias].cursor() as cursor:
                cursor.execute(
                    """
                    SELECT id, book, chapter, verse, text, translation, 
                           purport, has_purport, language, source, url
                    FROM shlokas
                    WHERE book = %s AND chapter = %s AND verse >= %s 
                          AND has_purport = TRUE
                          AND language = %s AND source = %s
                    ORDER BY verse
                    LIMIT 1
                    """,
                    [book, chapter, verse, language, source]
                )
                
                row = cursor.fetchone()
                
                if row:
                    return {
                        'id': row[0],
                        'book': row[1],
                        'chapter': row[2],
                        'verse': row[3],
                        'text': row[4],
                        'translation': row[5],
                        'purport': row[6],
                        'has_purport': row[7],
                        'language': row[8],
                        'source': row[9],
                        'url': row[10],
                    }
                    
        except Exception as e:
            logger.error(f'Failed to get next shloka with purport: {e}')
        
        return None
    
    def parse_verse_reference(self, verse: str) -> tuple:
        """
        Parse a verse reference into chapter and verse numbers.
        
        Args:
            verse: Verse reference (e.g., "11.28.30" or "11.28.31-32")
            
        Returns:
            Tuple of (chapter, verse_number)
        """
        parts = verse.split('.')
        
        if len(parts) >= 2:
            chapter = int(parts[0])
            verse_num = '.'.join(parts[1:])
            return chapter, verse_num
        
        return None, verse
    
    def get_verse_range(self, verse: str) -> List[str]:
        """
        Expand a verse range into individual verses.
        
        Args:
            verse: Verse reference (e.g., "31-32")
            
        Returns:
            List of verse numbers
        """
        if '-' in verse:
            start, end = verse.split('-')
            try:
                start_num = int(start)
                end_num = int(end)
                return [str(i) for i in range(start_num, end_num + 1)]
            except ValueError:
                pass
        
        return [verse]
    
    def get_vedabase_url(
        self,
        book: str,
        chapter: int,
        verse: str,
        language: str = 'uk',
    ) -> str:
        """
        Generate Vedabase URL for a verse.
        
        Args:
            book: Book code
            chapter: Chapter number
            verse: Verse number
            language: Language code
            
        Returns:
            Vedabase URL
        """
        # Determine language for URL
        lang = language
        if book == 'sb' and chapter > 3:
            lang = 'ru'  # SB canto 4+ only in Russian
        if book == 'cc':
            lang = 'ru'  # CC only in Russian on vedabase.io
        
        # Handle CC lila
        path_book = book
        if book == 'cc':
            path_book = self.CC_LILA.get(str(chapter), 'adi')
            # Parse verse to get chapter within lila
            parts = verse.split('.')
            if len(parts) >= 2:
                chapter = int(parts[0])
                verse = '.'.join(parts[1:])
        
        return f'https://vedabase.io/{lang}/library/{path_book}/{chapter}/{verse}'
    
    def format_broadcast_description(
        self,
        book: str,
        verse: str,
        lector: str = '',
        language: str = 'uk',
    ) -> str:
        """
        Format a broadcast description with verse content.
        
        Args:
            book: Book code
            verse: Verse reference (e.g., "11.28.30")
            lector: Lector name
            language: Language code
            
        Returns:
            Formatted description
        """
        chapter, verse_num = self.parse_verse_reference(verse)
        
        if not chapter:
            return ''
        
        # Get shloka with fallback
        shloka = self.get_shloka_with_fallback(book, chapter, verse_num, language)
        
        if not shloka:
            return ''
        
        lines = []
        
        # Title line
        book_uk = self.BOOK_NAMES_UK.get(book, book)
        lines.append(f'{book_uk} {verse}')
        
        # Sanskrit text
        if shloka.get('text'):
            lines.append(shloka['text'])
        
        lines.append('')  # Empty line
        
        # Translation
        if shloka.get('translation'):
            lines.append('Переклад')
            lines.append(shloka['translation'])
        
        lines.append('')  # Empty line
        
        # URL
        url = self.get_vedabase_url(book, chapter, verse_num, language)
        lines.append(url)
        
        return '\n'.join(lines)
    
    def get_last_used_verse(self, book: str) -> Optional[str]:
        """
        Get the last used verse for a book from the broadcast database.
        
        Args:
            book: Book code
            
        Returns:
            Last used verse reference or None
        """
        from apps.broadcasts.models import Broadcast
        
        try:
            last_broadcast = Broadcast.objects.filter(
                book=book,
                verse__isnull=False,
            ).exclude(verse='').order_by('-created_at').first()
            
            if last_broadcast:
                return last_broadcast.verse
                
        except Exception as e:
            logger.error(f'Failed to get last used verse: {e}')
        
        return None
    
    def generate_next_week_shlokas(
        self,
        book: str = 'sb',
        count: int = 5,
        start_verse: Optional[str] = None,
        language: str = 'uk',
    ) -> List[str]:
        """
        Generate shloka references for the next week.
        
        Args:
            book: Book code
            count: Number of verses to generate
            start_verse: Starting verse (if None, uses last used + 1)
            language: Language code
            
        Returns:
            List of verse references
        """
        if start_verse is None:
            start_verse = self.get_last_used_verse(book)
        
        if not start_verse:
            return []
        
        chapter, verse_num = self.parse_verse_reference(start_verse)
        
        if not chapter:
            return []
        
        verses = []
        current_verse = verse_num
        
        while len(verses) < count:
            # Get next shloka with purport
            shloka = self.get_next_shloka_with_purport(
                book, chapter, current_verse, language
            )
            
            if shloka:
                verse_ref = f'{chapter}.{shloka["verse"]}'
                verses.append(verse_ref)
                current_verse = shloka['verse']
                
                # Move to next verse
                if '-' in current_verse:
                    # Handle verse ranges
                    end = current_verse.split('-')[1]
                    try:
                        current_verse = str(int(end) + 1)
                    except ValueError:
                        break
                else:
                    try:
                        current_verse = str(int(current_verse) + 1)
                    except ValueError:
                        break
            else:
                break
        
        return verses
