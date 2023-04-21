<?php

require_once __DIR__ . '/vendor/autoload.php';

(new \backend\src\yuri\Youtube($_ENV['YOUTUBE_AUTH_FILE']))->listStreams();
