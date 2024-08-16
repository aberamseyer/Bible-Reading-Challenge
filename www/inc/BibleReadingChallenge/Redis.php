<?php

namespace BibleReadingChallenge;

class Redis {
	private static $instance;
  
	private \Predis\Client $client;
	
	const SITE_NAMESPACE = 'bible-reading-challenge:';
	const SESSION_KEYSPACE = 'sessions/';	// session storage, keyed with session ids
	const CONFIG_KEYSPACE = 'config/';		// 	the site config

	// keyed by $user_id's
	CONST USER_STATS_KEYSPACE = 'user-stats/';
	CONST SITE_STATS_KEYSPACE = 'site-stats/';
  const LAST_SEEN_KEYSPACE = 'last-seen/';
	const WEBSOCKET_NONCE_KEYSPACE = 'websocket-nonce/';
	CONST VERIFY_EMAIL_KEYSPACE = 'email-verify/';
	const FORGOT_PASSWORD_KEYSPACE = 'forgot-password/';
	const STATS_QUEUE = 'stats-queue';	// a LIST of jobs to work on
	const STATS_PROCESSING_SET = 'stats-queue-processing';	// a SET of the currently running jobs

	private $offline = false;

  private function __construct()
  {
		try {
			$this->client = new \Predis\Client([
				'host' => '127.0.0.1',
				'port' => '6379',
				'timeout' => 1,
				'read_write_timeout' => 0,
				'persistent' => true
			], [ 'prefix' => Redis::SITE_NAMESPACE ]);
			$this->client->connect();
		} catch (\Exception $e) {
			$this->offline = true;
			error_log("redis offline");
		}
  }
  
  private function __clone()
  {
      // Do nothing
  }

  public function __wakeup()
  {
      // Do nothing
			throw new \Exception("Cannot unserialize a singleton.");
  }

  public static function get_instance()
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  public function client(): \Predis\Client
  {
    return $this->client;
  }

	public function is_offline(): bool
	{
		return $this->offline;
	}

	public function get_site_version(): string
	{
		$version = false;
		if ($this->client()) {
			$version = $this->client()->get('config/version');
		}
		if (!$version) {
			$version = trim(`git rev-parse --short HEAD`);
		}
		if ($this->client()) {
			$this->client()->set('config/version', $version);
			$this->client()->expire('config/version', 60);
		}
		return $version;
	}

	/**
	 * call this after calculating the stats of a user when stats are not found
	 */
	public function cache_stats($id, $stats)
	{
		return $this->client->hmset(Redis::USER_STATS_KEYSPACE.$id, $stats);
	}

	/**
	 * used by php to cache a user's appearance
	 */
	public function update_last_seen($id, $time): \Predis\Response\Status
	{
		return $this->client->set(Redis::LAST_SEEN_KEYSPACE.$id, $time);
	}

	/**
	 * used by a daily cron to update each user's last_seen time
	 */
	public function get_last_seen($id): string|null
	{
		return $this->client->get(Redis::LAST_SEEN_KEYSPACE.$id);
	}

	/**
	 * sets a nonce on 'today' page load for the websocket client to use as an auth token for the current user
	 */
	public function set_websocket_nonce($id, $nonce)
	{
		$key = Redis::WEBSOCKET_NONCE_KEYSPACE.$nonce;
		$this->client->set($key, $id);
		$this->client->expire($key, 10);

		return true;
	}

	public function set_verify_email_key($user_id, $key)
	{
		return $this->client->set(Redis::VERIFY_EMAIL_KEYSPACE.$user_id, $key);
	}

	public function get_verify_email_key($user_id)
	{
		return $this->client->get(Redis::VERIFY_EMAIL_KEYSPACE.$user_id);
	}

	public function delete_verify_email_key($user_id)
	{
		return $this->client->del(Redis::VERIFY_EMAIL_KEYSPACE.$user_id);
	}

	public function set_forgot_password_token($user_id, $key)
	{
		return $this->client->set(Redis::FORGOT_PASSWORD_KEYSPACE.$user_id, $key);
	}

	public function get_forgot_password_token($user_id)
	{
		return $this->client->get(Redis::FORGOT_PASSWORD_KEYSPACE.$user_id) ?: false;
	}

	public function delete_forgot_password_token($user_id)
	{
		return $this->client->del(Redis::FORGOT_PASSWORD_KEYSPACE.$user_id);
	}

	/**
	 * iterates over all the keys for last seen users
	 */
	public function user_iterator(): null|\Predis\Collection\Iterator\Keyspace
	{
		return $this->client()
			? new \Predis\Collection\Iterator\Keyspace(
					$this->client,
					Redis::SITE_NAMESPACE.Redis::LAST_SEEN_KEYSPACE.'*')
			: null;
	}

	/**
	 * iterates over alll the keys for user stats
	 */
	public function user_stats_iterator(): null|\Predis\Collection\Iterator\Keyspace
	{
		return $this->client()
			? new \Predis\Collection\Iterator\Keyspace(
					$this->client,
					Redis::SITE_NAMESPACE.Redis::USER_STATS_KEYSPACE.'*')
			: null;
	}

	/**
	 * iterates over all the keys for site stats
	 */
	public function site_stats_iterator(): null|\Predis\Collection\Iterator\Keyspace
	{
		return $this->client()
			? new \Predis\Collection\Iterator\Keyspace(
					$this->client,
					Redis::SITE_NAMESPACE.Redis::SITE_STATS_KEYSPACE.'*')
			: null;
	}

	// ------- statistics functions -------
  public function set_user_stats($user_id, $stats)
  {
    return $this->client->hmset(Redis::USER_STATS_KEYSPACE.$user_id, $stats);
  }

	public function get_user_stats($user_id)
	{
		return $this->client->hgetall(Redis::USER_STATS_KEYSPACE.$user_id) ?: [];
	}

  public function set_site_stats($site_id, $stats)
  {
    return $this->client->hmset(Redis::SITE_STATS_KEYSPACE.$site_id, $stats);
  }

	public function get_site_stats($site_id)
	{
		return $this->client->hgetall(Redis::SITE_STATS_KEYSPACE.$site_id) ?: [];
	}

	public function delete_user_stats($site_id, $user_id)
	{
		$retval = $this->client->del(Redis::USER_STATS_KEYSPACE.$user_id);
		return $retval;
	}

	public function delete_site_stats($site_id)
	{
		$retval = $this->client->del(Redis::SITE_STATS_KEYSPACE.$site_id);
		return $retval;
	}

	public function enqueue_stats($stats_key)
	{
		$this->client->lrem(Redis::STATS_QUEUE, 0, $stats_key);
		return $this->client->lpush(Redis::STATS_QUEUE, $stats_key);
	}

	/**
	 * a stats job is a string in either of these formats:
	 * 1) $site_id
	 * 2) $site_id|$user_id
	 */
	public function dequeue_stats()
	{
		list($set_key, $stats_job) = $this->client->brpop(Redis::STATS_QUEUE, 0);
		if ($this->client->sismember(Redis::STATS_PROCESSING_SET, $stats_job)) {
			// stats got double-queued and is already being processed, so don't duplicate the work
			throw new \Exception("double-queued job: $stats_job");
		}
		else {
			$this->client->sadd(Redis::STATS_PROCESSING_SET, $stats_job);
			return $stats_job;
		}
	}

	public function stats_job_finished($job)
	{
		return $this->client->srem(Redis::STATS_PROCESSING_SET, $job);
	}
}
