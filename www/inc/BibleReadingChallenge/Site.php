<?php

namespace BibleReadingChallenge;

class SiteRegistry
{
  private static array $sites = [];

  protected function __construct()
  {
    // Prevent direct instantiation
  }
  
  protected function __clone()
  {
    // Prevent cloning
  }
  
  public function __wakeup()
  {
    // Prevent unserialization
    throw new Exception("Cannot unserialize a singleton.");
  }

  public static function get_site($id = 0)
  {
    if (!isset(self::$sites[$id])) {
      $inst = new static($id);
      if ($id === 0) {
        self::$sites[ 0 ] = $inst; // if no $id is passed, we need the to put our site in the 'default' slot of 0
      }
      self::$sites[ $inst->ID ] = $inst;
    }
    return self::$sites[ $id ];
  }
}



class Site extends SiteRegistry {
  public readonly string $DOMAIN;
  public readonly string $SOCKET_DOMAIN;
  public readonly \DateTimeZone $TZ;
  public readonly string $TZ_OFFSET;
  public readonly string $ID;

  private array $data;
  private Database $db;
  private array $env;

  private \Email\MailSender $ms;

  protected function __construct($id)
  {
    $this->db = Database::get_instance();
    // either a passed $id gets a specific site, or we default to the site that matches the server HOST value
    if ($id) {
      $stmt = $this->db->get_db()->prepare('SELECT * FROM sites WHERE enabled = 1 AND id =:id LIMIT 1');
      $stmt->bindValue(':id', $id);
    }
    else {
      $stmt = $this->db->get_db()->prepare('SELECT * FROM sites WHERE ENABLED = 1 AND domain_www = :domain OR domain_www_test = :domain LIMIT 1');
      $stmt->bindValue(':domain', $_SERVER['HTTP_HOST']);
    }
    $this->data = $this->db->get_db()->querySingle($stmt->getSQL(true), true);

    $this->ID = $this->data('id');

    $this->DOMAIN = PROD ? $this->data('domain_www') : $this->data('domain_www_test');
    $this->SOCKET_DOMAIN = PROD ? $this->data('domain_socket') : $this->data('domain_socket_test');

    if (!$this->data) {
      die("Nothing to see here: ".$id);
    }
    else {
			$this->TZ = new \DateTimeZone($this->data('time_zone_id') ?: 'UTC');
			$this->TZ_OFFSET = ''.intval($this->TZ->getOffset(new \DateTime('UTC')) / 3600);
    }

    foreach (explode("\n", $this->data('env')) as $line) {
      $line = trim($line);
      if ($line && !preg_match("/^\/\/.*$/", $line)) { // line doesn't begin with a comment
        list($key, $val) = explode("=", $line);
        $this->env[ $key ] = $val;
      }
    }

    $this->$ms = new \Email\MailSenderSendgrid(
      $this->env('SENDGRID_API_KEY'), 
      $this->env('SENDGRID_DAILY_EMAIL_TEMPLATE'), 
      $this->env('SENDGRID_REGISTER_EMAIL_TEMPLATE'), 
      $this->env('SENDGRID_FORGOT_PASSWORD_TEMPLATE')
    );
  }

  public function data(string $key)
  {
    return $this->data[ $key ];
  }

  public function env(string $key)
  {
    return $this->env[ $key ];
  }

  public function send_register_email($to, $link)
  {
    $this->ms->send_dynamic_email($to, $this->ms->register_email_template, [ 'confirm_link' => $link ]);
  }

  public function send_forgot_password_email($to, $link)
  {
    $this->ms->send_dynamic_email($to, $this->ms->forgot_password_template, [ "reset_link" => $link ]);
  }

  public function send_daily_verse_email($email, $name, $subject, $content, $streak)
  {
    $this->ms->send_dynamic_email($to, $this->ms->forgot_password_template, [
      "subject" => $subject,
      "name" => $name,
      "html" => $content,
      "streak" => $streak
    ]);
  }

	public function get_active_schedule()
  {
		return $this->db->row("SELECT * FROM schedules WHERE site_id = ".$this->ID." AND active = 1");
	}

  public function all_users($stale = false) {
    $nine_mo = strtotime('-9 months');
    if ($stale) {
      $where = "last_seen < '$nine_mo' OR (last_seen IS NULL AND date_created < '$nine_mo')";
    }
    else {
      // all users
      $where = "last_seen >= '$nine_mo' OR (last_seen IS NULL AND date_created >= '$nine_mo')";
    }
    return $this->db->select("
      SELECT u.id, u.name, u.emoji, u.email, u.staff, u.date_created, u.last_seen, MAX(rd.timestamp) last_read, u.email_verses, streak, max_streak, u.trans_pref
      FROM users u
      LEFT JOIN read_dates rd ON rd.user_id = u.id
      WHERE site_id = ".$this->ID." AND ($where)
      GROUP BY u.id
      ORDER BY LOWER(name) ASC");
  }

  public function mountain_for_emojis($emojis, $my_id = 0, $hidden = false)
  {  
    echo "<div class='mountain-wrap ".($hidden ? 'hidden' : '')."'>";
  
    foreach($emojis as $i => $datum) {
      $style = '';
      if ($datum['id'] == $my_id) {
        $style = "style='z-index: 10'";
      }
      echo "
      <span class='emoji' data-percent='$datum[percent_complete]' data-id='$datum[id]' $style>
        <span class='inner'>$datum[emoji]</span>
      </span>";
    }
    
    echo "<img src='".$this->resolve_img_src('progress')."' class='mountain'>";
    echo "</div>";
  }

  public function resolve_img_src($type)
  {
    static $pictures;
    if ($picutres[$type]) {
      return '/img/'.$pictures[$type];
    }
    else {
      $pictures[$type] = $this->data($type.'_image_id') ? $this->db->col("SELECT uploads_dir_filename FROM images WHERE id = ".$this->data($type.'_image_id')) : '';
    }
    return '/img/'.$pictures[$type];
  }


	/*
	 * @param scheduled_reading array the return value of get_reading()
	 * @param trans string one of the translations
	 * @param complete_key string the key to complete the reading from a row in the schedule_dates's table
	 * @param schedule the schedule from which we are generating a reading
	 * @param email bool whether this is going in an email or not
	 * @return the html of all the verses we are reading
	 */ 
	public function html_for_scheduled_reading($scheduled_reading, $trans, $complete_key, $schedule, $email=false)
  {
		ob_start();
		$article_style = "";
		if ($email) {
			$article_style = "style='line-height: 1.618; font-size: 1.1rem;'";
		}
		echo "<article $article_style>";
		if ($scheduled_reading) {
			$style = "";
			if ($email) {
				$style = "style='text-align: center; font-size: 1.4rem;'";
			}
			foreach($scheduled_reading['passages'] as $passage) {
				echo "<h4 class='text-center' $style>".$passage['book']['name']." ".$passage['chapter']['number']."</h4>";
				$book = $passage['book'];
				$verses = $this->db->select("SELECT number, $trans FROM verses WHERE chapter_id = ".$passage['chapter']['id']);
	
				$abbrev = json_decode($passage['book']['abbreviations'], true)[0];

				$ref_style = "class='ref'";
				$verse_style = "class='verse-text'";
				if ($email) {
					$ref_style = "style='font-weight: bold; user-select: none;'";
					$verse_style = "style='margin-left: 1rem;'";
				}
				foreach($verses as $verse_row) {
					echo "
						<div class='verse'><span $ref_style>".$verse_row['number']."</span><span $verse_style>".$verse_row[$trans]."</span></div>";
				}
			}
			$btn_style = "";
			$form_style = "id='done' class='center'";
			if ($email) {
				$btn_style = "style='color: rgb(249, 249, 249); padding: 2rem; width: 100%; background-color: #404892;'";
				$form_style = "style='display: flex; justify-content: center; margin: 7px auto; width: 50%;'";
			}
			$copyright_text = json_decode(file_get_contents(__DIR__."/../../copyright.json"), true);
			$copyright_style = "";
			if ($email) {
				$copyright_style = "font-size: 75%;";
			}
			echo "
			<div style='text-align: center; $copyright_style'><small><i>".$copyright_text[$trans]."</i></small></div>
			<form action='".SCHEME."://".$site->DOMAIN."/today' method='get' $form_style>
				<input type='hidden' name='complete_key' value='$complete_key'>
				<input type='hidden' name='today' value='$scheduled_reading[date]'>
				<button type='submit' name='done' value='1' $btn_style>Done!</button>
			</form>";
		}
		else {
			echo "<p>Nothing to read today!</p>";
	
			// look for the next time to read in the schedule.
			$days = get_schedule_days($schedule['id']);
			$today = new \Datetime();
			foreach($days as $day) {
				$dt = new \Datetime($day['date']);
				if ($today < $dt) {
					echo "<p>The next reading will be on <b>".$dt->format('F j')."</b>.</p>";
					break;
				}
			}
		}
		echo "</article>";
		return ob_get_clean();
	}


  public function deviation_for_user($user_id, $schedule)
  {
    $weekly_counts = $this->weekly_counts($user_id, $schedule)['counts'];
    // standard deviation
    $n = count($weekly_counts);
    if ($n === 0) {
      return null;
    }
    $mean = array_sum($weekly_counts) / $n;
    $variance = 0.0;
    foreach ($weekly_counts as $val) {
        $variance += pow($val - $mean, 2);
    }
    $variance /= $n;

    return round(sqrt($variance), 3);
  }

  public function weekly_progress_canvas($user_id, $schedule, $size=400)
  {
    $counts = $this->weekly_counts($user_id, $schedule);
    $week_starts = array_map(function($value) {
      // because date_create_from_format can't handle the week number, we do this manually
      list($year, $week) = explode('-', $value);
      return date('Y-m-d', strtotime($year.'-01-01 +'.(intval($week)*7).' days'));
    }, $counts['week']);
    $data = json_encode(array_combine($week_starts, $counts['counts']));
    return "<canvas data-graph='$data' class='weekly-counts-canvas' width='$size'></canvas>";
  }

  public function weekly_counts($user_id, $schedule)
  {
    $start = new \DateTime($schedule['start_date']);
    $end = new \DateTime($schedule['end_date']);
  
    $interval = $start->diff($end);
    $days_between = abs(intval($interval->format('%a')));
    $week_count = ceil($days_between / 7);
  
    $counts = $this->db->select("
      SELECT COALESCE(count, 0) count, sd.week, sd.start_of_week
      FROM (
          WITH RECURSIVE week_sequence AS (
                  SELECT date('now', '".$this->TZ_OFFSET." hours') AS cdate
                  UNION ALL
                  SELECT date(cdate, '-7 days') 
                    FROM week_sequence
                    LIMIT $week_count
              )
              SELECT strftime('%Y-%W', cdate) AS week,
              strftime('%Y-%m-%d', cdate, 'weekday 0') AS start_of_week
                FROM week_sequence
          )
          sd
          LEFT JOIN
          (
              SELECT strftime('%Y-%W', DATETIME(rd.timestamp, 'unixepoch', '".$this->TZ_OFFSET." hours')) AS week,
                      COUNT(rd.user_id) count
                FROM read_dates rd
                WHERE rd.user_id = $user_id
                GROUP BY week, rd.user_id
          )
          rd ON rd.week = sd.week
      WHERE sd.week >= strftime('%Y-%W', DATE('now', '-' || (7*$week_count) || ' days', '".$this->TZ_OFFSET." hours') ) 
      ORDER BY sd.week ASC
      LIMIT $week_count");
  
      return [
        'week' => array_column($counts, 'week'),
        'counts' => array_column($counts, 'count'),
        'start_of_week' => array_column($counts, 'start_of_week')
      ];
  }

  public function progress_canvas($user_id, $schedule_id, $size=400)
  {
    $progress = $this->progress($user_id, $schedule_id);
    return "<canvas data-graph='".json_encode($progress)."' class='progress-canvas' width='$size'></canvas>";
  }

  public function progress($user_id, $schedule_id)
  {   
    $total_words_in_schedule = total_words_in_schedule($schedule_id);

    $threshholds = [];
    for($i = 1; $i <= 100; $i++) {
      $threshholds[ $i ] = floor($total_words_in_schedule / 100 * $i);
    }

    $progress = [];
    $read_dates = $this->db->select("
      SELECT DATETIME(rd.timestamp, 'unixepoch', '".$this->TZ_OFFSET." hours') date, c.word_count
      FROM read_dates rd
      JOIN schedule_dates sd ON sd.id = rd.schedule_date_id
      JOIN JSON_EACH(sd.passage_chapter_ids)
      JOIN chapters c ON c.id = value
      WHERE sd.schedule_id = $schedule_id and rd.user_id = $user_id
      ORDER BY timestamp");
    
      $sum = 0;
    foreach($read_dates as $rd) {
      $sum += (int)$rd['word_count'];
      foreach ($threshholds as $thresh => $words) {
        if ($sum >= (int)$words && !$progress[ $thresh ]) {
          $dt = new \Datetime($rd['date']);
          $progress [ $thresh ] = $dt->format('Y-m-d');
        }
      }
    }
    return $progress;
  }
}