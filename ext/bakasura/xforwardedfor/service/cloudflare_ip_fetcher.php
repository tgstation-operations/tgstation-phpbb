<?php

namespace bakasura\xforwardedfor\service;

use phpbb\config\db_text;

/**
 * Service for fetching Cloudflare IP ranges
 */
class cloudflare_ip_fetcher
{
	/** @var db_text */
	protected $config_text;

	/** @var \phpbb\config\config */
	protected $config;

	const CLOUDFLARE_IPV4_URL = 'https://www.cloudflare.com/ips-v4';
	const CLOUDFLARE_IPV6_URL = 'https://www.cloudflare.com/ips-v6';
	const FETCH_INTERVAL = 86400; // 24 hours in seconds

	/**
	 * Constructor
	 *
	 * @param db_text $config_text
	 * @param \phpbb\config\config $config
	 */
	public function __construct(db_text $config_text, \phpbb\config\config $config)
	{
		$this->config_text = $config_text;
		$this->config = $config;
	}

	/**
	 * Fetch Cloudflare IP ranges from their public endpoints
	 *
	 * @return array Array with 'success' boolean and 'message' string
	 */
	public function fetch_cloudflare_ips()
	{
		$ipv4_ranges = $this->fetch_url(self::CLOUDFLARE_IPV4_URL);
		$ipv6_ranges = $this->fetch_url(self::CLOUDFLARE_IPV6_URL);

		if ($ipv4_ranges === false && $ipv6_ranges === false)
		{
			return [
				'success' => false,
				'message' => 'Failed to fetch IP ranges from Cloudflare'
			];
		}

		$all_ranges = [];
		
		if ($ipv4_ranges !== false)
		{
			$all_ranges = array_merge($all_ranges, $ipv4_ranges);
		}
		
		if ($ipv6_ranges !== false)
		{
			$all_ranges = array_merge($all_ranges, $ipv6_ranges);
		}

		if (empty($all_ranges))
		{
			return [
				'success' => false,
				'message' => 'No IP ranges received from Cloudflare'
			];
		}

		// Store the IP ranges
		$ip_string = implode(', ', $all_ranges);
		$this->config_text->set('xff_trusted_ips', $ip_string);
		
		// Update last fetch time
		$this->config->set('xff_last_fetch_time', time());

		return [
			'success' => true,
			'message' => 'Successfully fetched ' . count($all_ranges) . ' IP ranges from Cloudflare'
		];
	}

	/**
	 * Check if IPs should be refreshed based on last fetch time
	 *
	 * @return bool True if refresh is needed
	 */
	public function should_refresh()
	{
		$last_fetch = $this->config->offsetGet('xff_last_fetch_time');
		
		if (!$last_fetch)
		{
			return true;
		}

		return (time() - $last_fetch) > self::FETCH_INTERVAL;
	}

	/**
	 * Fetch content from a URL
	 *
	 * @param string $url URL to fetch
	 * @return array|false Array of IP ranges or false on failure
	 */
	protected function fetch_url($url)
	{
		// Try using curl first
		if (function_exists('curl_init'))
		{
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
			
			$content = curl_exec($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			if ($http_code == 200 && $content !== false)
			{
				return $this->parse_ip_list($content);
			}
		}

		// Fallback to file_get_contents if curl is not available
		if (ini_get('allow_url_fopen'))
		{
			$context = stream_context_create([
				'http' => [
					'timeout' => 10,
					'user_agent' => 'phpBB XFF Extension'
				]
			]);

			$content = @file_get_contents($url, false, $context);
			
			if ($content !== false)
			{
				return $this->parse_ip_list($content);
			}
		}

		return false;
	}

	/**
	 * Parse IP list from response content
	 *
	 * @param string $content Content from URL
	 * @return array Array of IP ranges
	 */
	protected function parse_ip_list($content)
	{
		$lines = explode("\n", trim($content));
		$ips = [];

		foreach ($lines as $line)
		{
			$line = trim($line);
			
			// Skip empty lines and comments
			if (empty($line) || strpos($line, '#') === 0)
			{
				continue;
			}

			// Validate CIDR notation
			if (preg_match('/^(?:[0-9]{1,3}\.){3}[0-9]{1,3}\/[0-9]{1,2}$/', $line) || // IPv4
				preg_match('/^[0-9a-fA-F:]+\/[0-9]{1,3}$/', $line)) // IPv6
			{
				$ips[] = $line;
			}
		}

		return $ips;
	}
}
