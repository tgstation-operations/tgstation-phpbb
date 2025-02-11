<?php

/**
 *
 * @package phpBB Extension - tas2580 lastpostlink
 * @copyright (c) 2016 tas2580 (https://tas2580.net)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace tas2580\lastpostlink\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event listener
 */
class listener implements EventSubscriberInterface {
	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var string phpbb_root_path */
	protected $phpbb_root_path;

	/** @var string php_ext */
	protected $php_ext;

	/**
	 * Constructor
	 *
	 * @param \phpbb\auth\auth				auth				Authentication object
	 * @param \phpbb\config\config			$config				Config Object
	 * @param \phpbb\template\template		$template			Template object
	 * @param \phpbb\request\request		$request			Request object
	 * @param \phpbb\user					$user				User Object
	 * @param \phpbb\path_helper			$path_helper		Controller helper object
	 * @param string                        $phpbb_root_path	phpbb_root_path
	 * @access public
	 */
	public function __construct(\phpbb\auth\auth $auth, \phpbb\config\config $config, \phpbb\request\request $request, $phpbb_root_path, $php_ext) {
		$this->auth = $auth;
		$this->config = $config;
		$this->request = $request;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext =$php_ext;
	}

	/**
	 * Assign functions defined in this class to event listeners in the core
	 *
	 * @return array
	 * @static
	 * @access public
	 */
	static public function getSubscribedEvents() {
		return array(
			'core.display_forums_modify_sql'			=> 'display_forums_modify_sql',
			'core.display_forums_modify_template_vars'	=> 'display_forums_modify_template_vars',
			'core.display_forums_modify_forum_rows'		=> 'display_forums_modify_forum_rows',
			/*'core.viewforum_modify_topicrow'			=> 'viewforum_modify_topicrow',
			'core.search_modify_tpl_ary'				=> 'search_modify_tpl_ary',
			'core.viewtopic_modify_post_row'			=> 'viewtopic_modify_post_row',*/
		);
	}

	/**
	 * Get informations for the last post from database
	 *
	 * @param	object	$event	The event object
	 * @return	null
	 * @access	public
	 */
	public function display_forums_modify_sql($event) {
		$sql_array = $event['sql_ary'];
		$sql_array['LEFT_JOIN'][] = array(
			'FROM' => array(TOPICS_TABLE => 't'),
			'ON' => "f.forum_last_post_id = t.topic_last_post_id"
		);
		$sql_array['SELECT'] .= ', t.topic_title, t.topic_id, t.topic_posts_approved, t.topic_posts_unapproved, t.topic_posts_softdeleted';
		$event['sql_ary'] = $sql_array;
	}

	/**
	 * Store informations for the last post in forum_rows array
	 *
	 * @param	object	$event	The event object
	 * @return	null
	 * @access	public
	 */
	public function display_forums_modify_forum_rows($event) {
		$forum_rows = $event['forum_rows'];
		if ($event['row']['forum_last_post_id'] == $forum_rows[$event['parent_id']]['forum_last_post_id']) {
			$forum_rows[$event['parent_id']]['topic_id_last_post'] = $event['row']['topic_id'];
			$event['forum_rows'] = $forum_rows;
		}
	}

	/**
	 * Rewrite links to last post in forum index
	 * also correct the path of the forum images if we are in a forum
	 *
	 * @param	object	$event	The event object
	 * @return	null
	 * @access	public
	 */
	public function display_forums_modify_template_vars($event)	{
		$forum_row = $event['forum_row'];
		$forum_row['U_LAST_POST_UNREAD'] = $this->phpbb_root_path . 'viewtopic.' . $this->php_ext . '?t=' . $event['row']['topic_id_last_post'] .'&view=unread#unread';
		$forum_row['U_LAST_POST_TOPIC_ID'] = $event['row']['topic_id_last_post'];
		$event['forum_row'] = $forum_row;
	}

}
