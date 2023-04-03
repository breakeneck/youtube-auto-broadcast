1. Install composer dependencies:
```bash
composer install
```
2. Go to Google Cloud Console https://console.cloud.google.com/apis/credentials/oauthclient 
3. Select Application type - Web Application. Add redirect url: http://localhost:8001
4. Download json auth file, rename it to `secret.json` and put into root directory
5. Run Local server on machine, you will use for web auth:
```bash
php -S localhost:8001 server.php
```
6. Login on google and follow instructions:
```bash
php login.php
```
7. List streams and find needed stream_id:
```bash
php streams.php
``` 
7. Run application using stream_id from previous step: 
```bash
php index.php stream_id
```
