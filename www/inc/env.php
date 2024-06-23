<?php

error_reporting(E_ALL^E_NOTICE^E_WARNING^E_DEPRECATED);

define('PROD', __DIR__ === '/home/bible-reading-challenge/www/inc');
define('DB_FILE', __DIR__."/../../brc.db");
define('SCHEME', PROD ? 'https' : 'http');
define('SESSION_LENGTH', 60*60*24*14); // 14-day sessions


$version = $REDIS_CLIENT->get('config/version');
if (!$version) {
  $version = trim(`git rev-parse --short HEAD`);
  $REDIS_CLIENT->set('config/version', $version);
  $REDIS_CLIENT->expire('config/version', 60);
}
define('VERSION', $vesion);

define('UPLOAD_DIR', __DIR__."/../../upload/");
define('IMG_DIR', __DIR__."/../img/");

define('ALL_TRANSLATIONS', ['rcv', 'kjv', 'esv', 'asv', 'niv', 'nlt']);
