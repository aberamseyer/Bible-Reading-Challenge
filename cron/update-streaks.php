<?php

//
// Updates user streaks–should be run daily
//
// crontab entry: 50 * * * * php /home/bible-reading-challenge/cron/update-streaks.php

require __DIR__."/../www/inc/env.php";
require __DIR__."/../www/inc/functions.php";

$db = new SQLite3(DB_FILE);

foreach(select("SELECT * FROM sites") as $site) {
  $tz = new DateTimeZone($site['time_zone_id']);
  $dt = new DateTime('now', $tz);
  // this cron runs every hour, we only want to update the sites who's local time is 3:50 AM
  if ($dt->format('G') !== 3) {
    continue;
  }
  $site_tz_offset = intval($tz->getOffset(new DateTime('UTC')) / 3600);

  $schedule = get_active_schedule();

  $yesterday = new Datetime('@'.strtotime('yesterday'));
  $scheduled_reading = get_reading($yesterday, $schedule['id']);

  if ($scheduled_reading) {
    foreach(select("SELECT * FROM users WHERE site_id = $site[id]") as $user) {
      $current_streak = $user['streak'];
      
      $read_yesterday = col("
        SELECT id
        FROM read_dates
        WHERE user_id = $user[id] AND
          DATE(timestamp, 'unixepoch', '".$site_tz_offset." hours') = '".$yesterday->format('Y-m-d')."'"); // irrespective of schedule

      update('users', [
        'streak' => $read_yesterday
          ? $user['streak'] + 1
          : 0, 
        'max_streak' => $read_yesterday
          ? max(intval($user['max_streak']), intval($user['streak']) + 1)
          : $user['max_streak']
      ], "id = ".$user['id']);
    }
  }
}