# YouTube auto broadcast

This application is used on server for automatizing YouTube broadcast with streaming software. Current version is used next scenario:
1. Creation of YouTube broadcast
2. ZoomIn camera
3. Send broadcast link to Telegram chat
4. ZoomOut camera after broadcast automatically ends after defined period of time


1. Install composer dependencies:
```bash
composer install
```
2. Go to Google Cloud Console https://console.cloud.google.com/apis/credentials/oauthclient 
3. Select Application type - Web Application. Add redirect url: http://localhost:8001
4. Download json auth file and fill in `.env.example` and rename it to `.env` with your auth data
5. Run Local server on machine, you will use for web auth:
```bash
php -S localhost:8001 server.php
```
6. Login on google and follow instructions:
```bash
php login.php
```
7. List streams and find needed stream_id and update .env file with it
```bash
php streams.php
``` 
8. Run application with parameter "minutes_length" of your broadcast: 
```bash
php index.php minutes_length
```
