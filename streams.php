<?php

require_once __DIR__ . '/vendor/autoload.php';

(new App\Youtube($_ENV['YOUTUBE_AUTH_FILE']))->listStreams();
