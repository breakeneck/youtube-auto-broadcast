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
php -S localhost:8001 router.php
```
6. Login on google and follow instructions:

**If you already made a login to Google** from selected browser, then you will not receive new refresh_token. To logout, please, delete old auth here https://myaccount.google.com/connections?filters=3,4&hl=en and try again
```bash
php login.php
```
7. List streams and find needed stream_id and update .env file with it
```bash
php streams.php
``` 
Alternatively you can use google samples:  or you can use this url https://developers.google.com/youtube/v3/live/docs/liveStreams/list?authuser=1&apix_params=%7B%22part%22%3A%5B%22snippet%2Ccdn%2CcontentDetails%2Cstatus%22%5D%2C%22mine%22%3Atrue%7D
8. Run application with parameter "minutes_length" of your broadcast: 
```bash
php index.php minutes_length
```

9. Start OBS:
```bash
[Unit]
Description=example systemd service unit file.
PartOf=graphical-session.target

[Service]
Type=exec
Restart=no
ExecStart=obs --startstreaming --disable-shutdown-check
Environment=DISPLAY=:0

[Install]
WantedBy=graphical-session.target
```

and `pkill obs` for stop service, which should be placed at `~/.config/systemd/user` and can be run with systemctl --user start
