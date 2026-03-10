"""
Database routers for multi-database support.

This module provides database routers to route models to the correct database:
- Default database: broadcast application data
- Shastra database: shlokas and shastra data (read-only for this app)
"""


class ShastraRouter:
    """
    Router to route all shastra-related models to the shastra database.
    
    Models in apps.integrations.models that are prefixed with 'Shastra' 
    or explicitly marked for shastra database will use this router.
    """
    
    # List of model names that should use the shastra database
    SHASTRA_MODELS = {
        'shloka',
        'verse',
        'book',
        'chapter',
        'translation',
        'purport',
        'source',
    }
    
    # Apps that should use shastra database
    SHASTRA_APPS = {
        'apps.integrations',
    }
    
    def db_for_read(self, model, **hints):
        """
        Suggest the database to use for read operations.
        
        Args:
            model: The model class
            **hints: Additional hints from the query
            
        Returns:
            str or None: Database name or None for default routing
        """
        if model._meta.app_label in self.SHASTRA_APPS:
            return 'shastra'
        
        # Check if model name indicates shastra usage
        model_name = model._meta.model_name.lower()
        if model_name in self.SHASTRA_MODELS:
            return 'shastra'
        
        # Check for custom attribute on model
        if getattr(model, 'uses_shastra_db', False):
            return 'shastra'
        
        return None
    
    def db_for_write(self, model, **hints):
        """
        Suggest the database to use for write operations.
        
        Note: Shastra database is typically read-only for this application.
        
        Args:
            model: The model class
            **hints: Additional hints from the query
            
        Returns:
            str or None: Database name or None for default routing
        """
        if model._meta.app_label in self.SHASTRA_APPS:
            return 'shastra'
        
        model_name = model._meta.model_name.lower()
        if model_name in self.SHASTRA_MODELS:
            return 'shastra'
        
        if getattr(model, 'uses_shastra_db', False):
            return 'shastra'
        
        return None
    
    def allow_relation(self, obj1, obj2, **hints):
        """
        Determine if a relation between two objects is allowed.
        
        Relations between objects in different databases are not allowed
        by default in Django.
        
        Args:
            obj1: First object in the relation
            obj2: Second object in the relation
            **hints: Additional hints
            
        Returns:
            bool or None: True if relation allowed, False if not, None for default
        """
        # Allow relations within the same database
        db1 = getattr(obj1, '_state', None) and obj1._state.db
        db2 = getattr(obj2, '_state', None) and obj2._state.db
        
        if db1 and db2:
            return db1 == db2
        
        # Allow relations if both are in default or both are in shastra
        obj1_shastra = (
            obj1._meta.app_label in self.SHASTRA_APPS or
            obj1._meta.model_name.lower() in self.SHASTRA_MODELS
        )
        obj2_shastra = (
            obj2._meta.app_label in self.SHASTRA_APPS or
            obj2._meta.model_name.lower() in self.SHASTRA_MODELS
        )
        
        if obj1_shastra == obj2_shastra:
            return True
        
        return None
    
    def allow_migrate(self, db, app_label, model_name=None, **hints):
        """
        Determine if a migration operation is allowed.
        
        Args:
            db: Database alias
            app_label: Application label
            model_name: Model name (optional)
            **hints: Additional hints
            
        Returns:
            bool or None: True if migration allowed, False if not, None for default
        """
        # Don't run migrations for shastra apps on default database
        if db == 'default' and app_label in self.SHASTRA_APPS:
            return False
        
        # Don't run migrations for non-shastra apps on shastra database
        if db == 'shastra' and app_label not in self.SHASTRA_APPS:
            return False
        
        # For shastra database, only allow shastra models
        if db == 'shastra':
            if model_name and model_name.lower() in self.SHASTRA_MODELS:
                return True
            if app_label in self.SHASTRA_APPS:
                return True
            return False
        
        return None
