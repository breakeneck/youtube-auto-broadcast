<?php

require_once __DIR__ . '/vendor/autoload.php';

(new yuri\Youtube($_ENV['YOUTUBE_AUTH_FILE']))->listStreams();
