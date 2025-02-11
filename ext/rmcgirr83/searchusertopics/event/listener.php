<?php
/**
*
* Search User Topics extension for the phpBB Forum Software package.
*
* @copyright (c) 2015 Rich McGirr (RMcGirr83)
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace rmcgirr83\searchusertopics\event;

/**
* @ignore
*/
use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\db\driver\driver_interface;
use phpbb\language\language;
use phpbb\template\template;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event listener
*/
class listener implements EventSubscriberInterface
{
	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var string phpBB root path */
	protected $root_path;

	/** @var string phpEx */
	protected $php_ext;

	public function __construct(
		auth $auth,
		config $config,
		driver_interface $db,
		language $language,
		template $template,
		$root_path,
		$php_ext)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->language = $language;
		$this->template = $template;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	/**
	* Assign functions defined in this class to event listeners in the core
	*
	* @return array
	* @static
	* @access public
	*/
	static public function getSubscribedEvents()
	{
		return array(
			'core.acp_extensions_run_action_after'	=>	'acp_extensions_run_action_after',
			'core.memberlist_view_profile'				=> 'memberlist_view_profile',
		);
	}

	/* Display additional metdate in extension details
	*
	* @param $event			event object
	* @param return null
	* @access public
	*/
	public function acp_extensions_run_action_after($event)
	{
		if ($event['ext_name'] == 'rmcgirr83/searchusertopics' && $event['action'] == 'details')
		{
			$this->language->add_lang('common', $event['ext_name']);
			$this->template->assign_var('S_BUY_ME_A_BEER_SUT', true);
		}
	}

	/**
	* Display number of topics on viewing user profile
	*
	* @param object $event The event object
	* @return null
	* @access public
	*/
	public function memberlist_view_profile($event)
	{
		$user_id = $event['member']['user_id'];
		$reg_date = $event['member']['user_regdate'];
		$this->language->add_lang('common', 'rmcgirr83/searchusertopics');

		// get all topics started by the user and make sure they are visible
		$sql = 'SELECT t.*, p.post_visibility
			FROM ' . TOPICS_TABLE . ' t
			LEFT JOIN ' . POSTS_TABLE . ' p ON t.topic_first_post_id = p.post_id
			WHERE t.topic_poster = ' . (int) $user_id;
		$result = $this->db->sql_query($sql);

		$topics_num = 0;
		while ($row = $this->db->sql_fetchrow($result))
		{
			if ($row['topic_status'] == ITEM_MOVED)
			{
				continue;
			}
			if (!$this->auth->acl_get('f_read', $row['forum_id']))
			{
				continue;
			}
			if ($row['post_visibility'] != ITEM_APPROVED && !$this->auth->acl_get('m_approve', $row['forum_id']))
			{
				continue;
			}
			++$topics_num;
		}
		$this->db->sql_freeresult($result);

		if ($topics_num)
		{
			// Do the relevant calculations
			$users_days = max(1, round((time() - $reg_date) / 86400));
			$topics_per_day = $topics_num / $users_days;
			$topics_percent = ($this->config['num_topics']) ? min(100, ($topics_num / $this->config['num_topics']) * 100) : 0;
			$this->template->assign_vars(array(
				'TOPICS'			=> $topics_num,
				'L_TOTAL_TOPICS'	=> $this->language->lang('TOTAL_TOPICS', $topics_num),
				'TOPICS_PER_DAY'	=> $this->language->lang('TOPICS_PER_DAY', $topics_per_day),
				'TOPICS_PERCENT'	=> $this->language->lang('TOPICS_PERCENT', $topics_percent),
				'U_SEARCH_TOPICS'	=> ($this->auth->acl_get('u_search')) ? append_sid("{$this->root_path}search.$this->php_ext", "author_id=$user_id&amp;sr=topics&amp;sf=firstpost") : '',
			));
		}
	}
}
