"""
OBS (Open Broadcaster Software) integration service.

Provides methods for controlling OBS remotely via SSH.
"""
import logging
import time
from typing import Optional

import paramiko
from django.conf import settings

logger = logging.getLogger(__name__)


class OBSService:
    """
    Service class for OBS integration via SSH.
    
    Handles:
    - Starting OBS
    - Stopping OBS
    - Restarting camera/VLC stream
    """
    
    def __init__(
        self,
        host: Optional[str] = None,
        port: Optional[int] = None,
        username: Optional[str] = None,
        password: Optional[str] = None,
    ):
        """
        Initialize OBS service.
        
        Args:
            host: SSH host. If None, uses settings.OBS_HOST
            port: SSH port. If None, uses settings.OBS_PORT
            username: SSH username. If None, uses settings.OBS_USERNAME
            password: SSH password. If None, uses settings.OBS_PASSWORD
        """
        self.host = host or settings.OBS_HOST
        self.port = port or settings.OBS_PORT
        self.username = username or settings.OBS_USERNAME
        self.password = password or settings.OBS_PASSWORD
        self._client = None
    
    def _connect(self) -> paramiko.SSHClient:
        """
        Connect to SSH server.
        
        Returns:
            SSH client
            
        Raises:
            Exception: If connection fails
        """
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        
        try:
            client.connect(
                hostname=self.host,
                port=self.port,
                username=self.username,
                password=self.password,
                timeout=30,
            )
            logger.info(f'Connected to OBS server at {self.host}:{self.port}')
            return client
            
        except Exception as e:
            logger.error(f'Failed to connect to OBS server: {e}')
            raise
    
    def _disconnect(self, client: paramiko.SSHClient):
        """
        Disconnect from SSH server.
        
        Args:
            client: SSH client to disconnect
        """
        try:
            client.close()
            logger.info('Disconnected from OBS server')
        except Exception as e:
            logger.warning(f'Error disconnecting from OBS server: {e}')
    
    def _execute_command(self, command: str) -> tuple:
        """
        Execute a command on the remote server.
        
        Args:
            command: Command to execute
            
        Returns:
            Tuple of (stdout, stderr, exit_code)
        """
        client = self._connect()
        try:
            stdin, stdout, stderr = client.exec_command(command)
            exit_code = stdout.channel.recv_exit_status()
            stdout_text = stdout.read().decode('utf-8')
            stderr_text = stderr.read().decode('utf-8')
            
            logger.debug(f'Command: {command}')
            logger.debug(f'Exit code: {exit_code}')
            if stdout_text:
                logger.debug(f'Stdout: {stdout_text}')
            if stderr_text:
                logger.debug(f'Stderr: {stderr_text}')
            
            return stdout_text, stderr_text, exit_code
            
        finally:
            self._disconnect(client)
    
    def start_obs(self) -> bool:
        """
        Start OBS and VLC stream.
        
        Returns:
            True if successful
        """
        try:
            # Start VLC stream
            self._execute_command('systemctl --user start vlc')
            
            # Start OBS
            self._execute_command('systemctl --user start obs-start')
            
            logger.info('OBS started successfully')
            return True
            
        except Exception as e:
            logger.error(f'Failed to start OBS: {e}')
            return False
    
    def stop_obs(self) -> bool:
        """
        Stop OBS and VLC stream.
        
        Returns:
            True if successful
        """
        try:
            # Stop VLC stream
            self._execute_command('systemctl --user stop vlc')
            
            # Stop OBS
            self._execute_command('systemctl --user start obs-stop')
            
            logger.info('OBS stopped successfully')
            return True
            
        except Exception as e:
            logger.error(f'Failed to stop OBS: {e}')
            return False
    
    def restart_camera(self) -> bool:
        """
        Restart the camera/VLC stream.
        
        Returns:
            True if successful
        """
        try:
            self._execute_command('systemctl --user restart vlc')
            
            logger.info('Camera restarted successfully')
            return True
            
        except Exception as e:
            logger.error(f'Failed to restart camera: {e}')
            return False
    
    def check_status(self) -> dict:
        """
        Check status of OBS and VLC services.
        
        Returns:
            Dictionary with status information
        """
        status = {
            'vlc': 'unknown',
            'obs': 'unknown',
        }
        
        try:
            # Check VLC status
            stdout, _, _ = self._execute_command(
                'systemctl --user is-active vlc'
            )
            status['vlc'] = stdout.strip()
            
            # Check OBS status (this is a simplified check)
            stdout, _, _ = self._execute_command(
                'pgrep -x obs || echo "not running"'
            )
            status['obs'] = 'active' if stdout.strip().isdigit() else 'inactive'
            
        except Exception as e:
            logger.error(f'Failed to check status: {e}')
        
        return status
    
    def wait(self, seconds: int):
        """
        Wait for a specified number of seconds.
        
        Args:
            seconds: Number of seconds to wait
        """
        logger.info(f'Waiting {seconds} seconds...')
        time.sleep(seconds)
