<?php

namespace BibleReadingChallenge;

class SiteRegistry
{
  private static array $sites = [];

  private function __construct()
  {
    // Prevent direct instantiation
  }
  
  private function __clone()
  {
    // Prevent cloning
  }
  
  public function __wakeup()
  {
    // Prevent unserialization
    throw new \Exception("Cannot unserialize a singleton.");
  }

  public static function get_site($id = 0, $soft=false): Site
  {
    if (!isset(self::$sites[ $id ])) {
      $inst = new Site($id, $soft);
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

  private Schedule|null $active_schedule;

  /**
   * @var $id a specific site to get
   * @var $soft 'soft'-retrieves a site without initializing all of its objects (useful when setting up a site)
   */
  protected function __construct($id, $soft=false)
  {
    $this->active_schedule = null;
    $this->db = Database::get_instance();
    // either a passed $id gets a specific site, or we default to the site that matches the server HOST value
    if ($id) {
      $stmt = $this->db->get_db()->prepare('SELECT * FROM sites WHERE enabled = 1 AND id =:id LIMIT 1');
      $stmt->bindValue(':id', $id);
    }
    else {
      if ($soft) {
        $stmt = $this->db->get_db()->prepare('SELECT * FROM sites WHERE domain_www = :domain OR domain_www_test = :domain LIMIT 1');
      }
      else {
        $stmt = $this->db->get_db()->prepare('SELECT * FROM sites WHERE enabled = 1 AND (domain_www = :domain OR domain_www_test = :domain) LIMIT 1');
      }
      $stmt->bindValue(':domain', $_SERVER['HTTP_HOST']);
    }
    $this->data = $this->db->get_db()->querySingle($stmt->getSQL(true), true);

    if (!$this->data) {
      die("Nothing to see here: ".$id);
    }
    else {
      $this->ID = $this->data('id');
      
      if (!$soft) {
        $this->DOMAIN = PROD ? $this->data('domain_www') : $this->data('domain_www_test');
        $this->SOCKET_DOMAIN = PROD ? $this->data('domain_socket') : $this->data('domain_socket_test');
        $this->TZ = new \DateTimeZone($this->data('time_zone_id') ?: 'UTC');
        $this->TZ_OFFSET = ''.intval($this->TZ->getOffset(new \DateTime('UTC')) / 3600);
      }
    }

    if (!$soft) {
      foreach (explode("\n", $this->data('env')) as $line) {
        $line = trim($line);
        if ($line && !preg_match("/^\/\/.*$/", $line)) { // line doesn't begin with a comment
          list($key, $val) = explode("=", $line);
          $this->env[ $key ] = $val;
        }
      }
  
      $this->ms = new \Email\MailSenderSendgrid(
        $this->env('SENDGRID_API_KEY'), 
        $this->env('SENDGRID_DAILY_EMAIL_TEMPLATE'), 
        $this->env('SENDGRID_REGISTER_EMAIL_TEMPLATE'), 
        $this->env('SENDGRID_FORGOT_PASSWORD_TEMPLATE'),
        $this->data('email_from_address'),
        $this->data('email_from_name')
      );
    }
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
    $this->ms->send_dynamic_email($to, $this->ms->register_email_template(), [ 'confirm_link' => $link ]);
  }

  public function send_forgot_password_email($to, $link)
  {
    $this->ms->send_dynamic_email($to, $this->ms->forgot_password_template(), [ "reset_link" => $link ]);
  }

  public function send_daily_verse_email($to, $name, $subject, $content, $streak)
  {
    $this->ms->send_dynamic_email($to, $this->ms->forgot_password_template(), [
      "subject" => $subject,
      "name" => $name,
      "html" => $content,
      "streak" => $streak
    ]);
  }

	public function get_active_schedule()
  {
    if (!$this->active_schedule) {
      $this->active_schedule = new Schedule();
    }
    return $this->active_schedule;
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
    ob_start();
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
    return ob_get_clean();
  }

  public function resolve_img_src($type)
  {
    static $pictures;
    if ($pictures[$type]) {
      // return cached value
      return '/img/'.$pictures[$type];
    }
    else {
      // find the active image
      if ($this->data($type.'_image_id')) {
        $img_filename = $this->db->col("SELECT uploads_dir_filename FROM images WHERE id = ".$this->data($type.'_image_id'));
      }
      // if the active image doesn't exist e.g., in development environment
      if (!$img_filename || !file_exists(IMG_DIR.$img_filename)) {
        $img_filename = "static/".$type."-placeholder.svg";
      }
      $pictures[$type] = $img_filename;
    }
    return '/img/'.$pictures[$type];
  }


	/**
	 * returns a structured array from the details within the passage_chapter_readings column of the schedule_dates table
	 * @param $json 	string|array	valid JSON, directly from the database, or a decoded json array
	 */
	function parse_passage_from_json(string|array $json_or_arr)
  {
		$arr = is_array($json_or_arr)
			? $json_or_arr
			: json_decode($json_or_arr, true);
		
		$passages = [];
		foreach($arr as $chp_reading) {
			$chapter = $this->db->row("SELECT * FROM chapters WHERE id = $chp_reading[id]");
			$book = $this->db->row("SELECT * FROM books WHERE id = $chapter[book_id]");
			$verses = $this->db->select("
				SELECT id, number, word_count, ".implode(',', $this->get_translations_for_site())."
				FROM verses 
				WHERE chapter_id = $chapter[id] 
					AND number IN(".implode(',', range($chp_reading['s'], $chp_reading['e'])).")
				ORDER BY number");
			$passages[] = [
				'book' => $book,
				'chapter' => $chapter,
				'word_count' => array_sum(array_column($verses, 'word_count')),
				'range' => [ $verses[0]['number'], $verses[ count($verses)-1 ]['number'] ],
				'verses' => $verses
			];
		}
		return $passages;
	}


	/*
	 * @param scheduled_reading array the return value of get_reading()
	 * @param trans string one of the translations
	 * @param complete_key string the key to complete the reading from a row in the schedule_dates's table
	 * @param schedule the schedule from which we are generating a reading
	 * @param email bool whether this is going in an email or not
	 * @return the html of all the verses we are reading
	 */ 
	public function html_for_scheduled_reading($scheduled_reading, $trans, $complete_key, $schedule, $today, $email=false)
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
        $verse_range = ":".$passage['range'][0]."-".$passage['range'][1];
        if ($passage['chapter']['verses'] == $passage['range'][1]-$passage['range'][0]+1) {
          // if the range is the whole chapter, hide the range
          $verse_range = "";
        }
				echo "<h4 class='text-center' $style>".$passage['book']['name']." ".$passage['chapter']['number'].$verse_range."</h4>";

				$ref_style = "class='ref'";
				$verse_style = "class='verse-text'";
				if ($email) {
					$ref_style = "style='font-weight: bold; user-select: none;'";
					$verse_style = "style='margin-left: 1rem;'";
				}
				foreach($passage['verses'] as $verse_row) {
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
			$copyright_text = json_decode(file_get_contents(DOCUMENT_ROOT."../../extras/copyright.json"), true);
			$copyright_style = "";
			if ($email) {
				$copyright_style = "font-size: 75%;";
			}
			echo "
			<div style='text-align: center; $copyright_style'><small><i>".$copyright_text[$trans]."</i></small></div>
			<form action='".SCHEME."://".$this->DOMAIN."/today' method='get' $form_style>
        <input type='hidden' name='schedule_id' value='".$schedule->ID."'>
				<input type='hidden' name='complete_key' value='$complete_key".($email ? '-e' : '' /* bypass wpm check from an email */)."'>
				<input type='hidden' name='today' value='$scheduled_reading[date]'>
				<button type='submit' name='done' value='1' $btn_style>Done!</button>
			</form>";
		}
		else {
			echo "<p>Nothing to read today!</p>";
	
			// look for the next time to read in the schedule.
			$days = $schedule->get_schedule_days();
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

  public function on_target_for_user(int $user_id)
  {
    return $this->db->col("
      SELECT 
        ROUND(
          COUNT(*) FILTER(
            WHERE rd.user_id = $user_id AND DATE(rd.timestamp, 'unixepoch', '".$this->TZ_OFFSET." hours') = sd.date
          ) / CAST(
                COUNT(*) FILTER (WHERE rd.user_id = 1)
                AS FLOAT
              ) * 100
          , 2
        ) || '%'
      FROM schedule_dates sd
      LEFT JOIN read_dates rd ON rd.schedule_date_id = sd.id
      WHERE sd.schedule_id = ".$this->active_schedule->ID);
  }

  public function deviation_for_user($user_id)
  {
    $weekly_counts = $this->weekly_counts($user_id)['counts'];
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

  public function weekly_progress_canvas($user_id, $size=400)
  {
    $counts = $this->weekly_counts($user_id);
    $week_starts = array_map(function($value) {
      // because date_create_from_format can't handle the week number, we do this manually
      list($year, $week) = explode('-', $value);
      return date('Y-m-d', strtotime($year.'-01-01 +'.(intval($week)*7).' days'));
    }, $counts['week']);
    $data = json_encode(array_combine($week_starts, $counts['counts']));
    return "<canvas data-graph='$data' class='weekly-counts-canvas' width='$size'></canvas>";
  }

  public function weekly_counts($user_id)
  {
    $start = new \DateTime($this->active_schedule->data('start_date'));
    $end = new \DateTime($this->active_schedule->data('end_date'));
  
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

  public function progress_canvas($user_id, $size=400)
  {
    $progress = $this->progress($user_id);
    return "<canvas data-graph='".json_encode($progress)."' class='progress-canvas' width='$size'></canvas>";
  }

  public function progress($user_id)
  {
    $total_words_in_schedule = $this->active_schedule->total_words_in_schedule();

    $threshholds = [];
    for($i = 1; $i <= 100; $i++) {
      $threshholds[ $i ] = floor($total_words_in_schedule / 100 * $i);
    }

    $progress = [];
    $read_dates = $this->db->select("
      SELECT DATETIME(rd.timestamp, 'unixepoch', '".$this->TZ_OFFSET." hours') date, SUM(sdv.word_count) word_count
      FROM read_dates rd
      JOIN schedule_date_verses sdv ON sdv.schedule_date_id = rd.schedule_date_id
      WHERE sdv.schedule_id = ".$this->active_schedule->ID." and rd.user_id = $user_id
      GROUP BY sdv.chapter_id
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

  public function create_user($email, $name, $password=false, $emoji=false, $verified=false) {
    $uuid = uniqid();
    $hash = password_hash($password ?: bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
    $verify_token = uniqid("", true).uniqid("", true);
    $user_id = $this->db->insert("users", [ 
      'site_id' => $this->ID,
      'uuid' => $uuid,
      'name' => $name,
      'email' => $email,
      'password' => $hash,
      'trans_pref' => 'rcv',
      'date_created' => time(),
      'email_verify_token' => $verify_token,
      '__email_verified' => $verified,
      'emoji' => $emoji ?: $this->data('default_emoji')
    ]);
    $this->db->insert('schedules', [
      'site_id' => $this->ID,
      'user_id' => $user_id,
      'name' => "$name's Default Schedule",
      'start_date' => date('Y-m-d', strtotime('January 1')),
      'end_date' => date('Y-m-d', strtotime('December 1')),
      'active' => 1
    ]);
    return [
      'insert_id' => $user_id,
      'verify_token' => $verify_token,
      'uuid' => $uuid
    ];
  }

  public function get_translations_for_site()
  {
    static $translations;
    if (!$translations) {
      $translations = json_decode($this->data('translations'), true);
    }
    return $translations;
  }

  public function check_translation($trans)
  {
    return in_array($trans, $this->get_translations_for_site(), true);
  }

  public function words_read($user = 0, $schedule_id = 0) {
    $word_qry = "
        SELECT SUM(word_count)
        FROM schedule_date_verses sdv
        JOIN read_dates rd ON sdv.schedule_date_id = rd.schedule_date_id
        JOIN schedules s ON s.id = sdv.schedule_id
        WHERE s.site_id = ".$this->ID." AND %s
    ";
    if (!$user) {
      $words_read = $this->db->col(sprintf($word_qry, '1'));
    }
    else {
      $schedule_where = $schedule_id
        ? " AND sdv.schedule_id = ".$schedule_id
        : "";
      $words_read = $this->db->col(sprintf($word_qry, " rd.user_id = ".$user['id'].$schedule_where));
    }

    return $words_read;
  }
}