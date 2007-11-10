<?php defined('SYSPATH') or die('No direct script access.');

class Controller extends Controller_Core {

	// Use auto-rendering, defaults to false
	protected $auto_render = FALSE;

	// Main template
	protected $template = 'layout';

	// RSS feeds
	protected $feeds = array
	(
		// 'forums' => 'http://kohanaphp.com/forums/index.php?action=.xml;limit=3;type=rss2',
		'trac'   => 'http://trac.kohanaphp.com/timeline?milestone=on&ticket=on&changeset=on&max=3&daysback=90&format=rss'
	);

	public function __construct()
	{
		parent::__construct();

		if ($this->uri->segment(1) == FALSE)
		{
			// Need the first segment so that the main menu has an active tab
			url::redirect('home');
		}

		$cache = APPPATH.'cache/';

		if ( ! is_writable($cache))
		{
			throw new Kohana_User_Exception
			(
				'Cache Unwritable',
				'Please make the application/cache directory writable!'
			);
		}

		if ($this->auto_render == TRUE)
		{
			// Load the template
			$this->template = new View($this->template);

			// Menu items
			$this->template->menu = array
			(
				'home'       => 'Home',
				'download'   => 'Download',
				'tutorials'  => 'Tutorials',
				// External links
				'http://forums.kohanaphp.com/' => 'Forums',
				'http://api.kohanaphp.com/'    => 'API Manual'
			);

			// Sidebar
			$this->template->sidebar = new View('sidebar');

			if ( ! extension_loaded('curl') AND ! @dl((strpos(PHP_OS, 'WIN') !== FALSE) ? 'curl.dll' : 'curl.so'))
			{
				throw new Kohana_User_Exception
				(
					'Cannot Fetch Remote Content',
					'Your PHP installation does not have the cURL extension ('.html::anchor('http://php.net/curl').') loaded. '.
					'This is required for remote feed fetching. Please enable it, then refresh the page.'
				);
			}

			// Feed caching
			foreach($this->feeds as $name => $link)
			{
				$filename = $cache.$name.'.xml';

				// Cache the feed for 30 minutes, 60 (one minute) * 30 = 1800
				if ( ! file_exists($filename) OR (time() - 1800) > filemtime($filename))
				{
					// Initialize cURL
					$curl = curl_init();

					// Set cURL options
					curl_setopt($curl, CURLOPT_URL, $link);        // Fetch remote feed
					curl_setopt($curl, CURLOPT_HEADER, 0);         // No headers in fetched page
					curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // Return the fetched page, instead of printing it
					curl_setopt($curl, CURLOPT_TIMEOUT, 3);        // Five second timeout

					// Fetch the remote feed
					$feed_content = curl_exec($curl);

					if (curl_errno($curl) === CURLE_OK)
					{
						// Cache the content if there was no error
						file_put_contents($filename, $feed_content);
					}
					else
					{
						// Log fetching errors
						Log::add('error', 'Error fetching remote feed ('.$link.'): '.curl_error($curl));
					}

					// Close cURL
					curl_close($curl);
				}

				$feeds[$name] = feed::parse($filename, 3);
			}

			// Add the feeds to the sidebar
			$this->template->sidebar->feeds = $feeds;

			// Auto-rendering
			Event::add('system.post_controller', array($this, '_display'));
		}
	}

	public function _display()
	{
		if ($this->auto_render == TRUE)
		{
			$this->template->render(TRUE);
		}
	}

} // End Controller