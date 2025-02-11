<?php
namespace mothblocks\playerlounge\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

const BYOND_KEY_FIELD = "byond_username";
const CACHE_TTL = 12 * (60 * 60); // 6 hours (changed to 24 hours ~mso)
const PLAYTIME_MINUTES = 90;
const PLAYTIME_WITHIN_DAYS = 45;
const TG_DATABASE = "tgstation13";

class main_listener implements EventSubscriberInterface {
	private $auth;
	private $cache;
	private $db;
	private $profile_manager;
	private $user;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\cache\service $cache,
		\phpbb\db\driver\factory $db,
		\phpbb\profilefields\manager $profile_manager,
		\phpbb\user $user
	) {
		$this->auth = $auth;
		$this->cache = $cache;
		$this->db = $db;
		$this->profile_manager = $profile_manager;
		$this->user = $user;
	}

	public static function getSubscribedEvents() {
		return [
			"core.modify_posting_auth" => "modify_posting_auth",
			'core.viewtopic_modify_poll_data' => 'viewtopic_modify_poll_data',
			"core.permissions" => "permissions",
			"core.viewtopic_modify_quick_reply_template_vars" => "viewtopic_modify_quick_reply_template_vars",
		];
	}

	public function permissions($event) {
		$permissions = $event["permissions"];

		$permissions["f_limit_to_players"] = array(
			"lang" => "ACL_F_LIMIT_TO_PLAYERS",
			"cat" => "post",
		);

		$permissions["u_bypass_player_limits"] = array(
			"lang" => "ACL_U_BYPASS_PLAYER_LIMITS",
			"cat" => "post",
		);

		$event["permissions"] = $permissions;
	}

	public function modify_posting_auth($event) {
		if (!$this->auth->acl_get("f_limit_to_players", $event["forum_id"])) {
			return;
		}

		$event["is_authed"] = $this->user_is_authed();
	}
	
	public function viewtopic_modify_poll_data($event) {
		if (!$this->auth->acl_get("f_limit_to_players", $event["forum_id"])) {
			return;
		}

		$event["s_can_vote"] = $this->user_is_authed();
	}

	public function viewtopic_modify_quick_reply_template_vars($event) {
		if (!$this->auth->acl_get("f_limit_to_players", $event["topic_data"]["forum_id"])) {
			return;
		}

		$tpl_ary = $event["tpl_ary"];
		$tpl_ary["S_QUICK_REPLY"] = $this->user_is_authed();
		$event["tpl_ary"] = $tpl_ary;
	}

	function ckey($key) {
		return strtolower(preg_replace('/[^a-zA-Z0-9@]/', '', $key));
		//return preg_replace("/\W+/u", "", strtolower($key));
	}

	function user_is_authed() {
		return $this->auth->acl_get("u_bypass_player_limits") || $this->user_is_player();
	}

	function user_is_player() {
		$profile_fields = $this->profile_manager->grab_profile_fields_data($this->user->data["user_id"])[$this->user->data["user_id"]];
		$byond_key = $profile_fields[BYOND_KEY_FIELD];

		if (is_null($byond_key)) {
			return false;
		}

		$byond_key = $byond_key["value"];

		if ($byond_key === "") {
			return false;
		}

		$ckey = $this->ckey($byond_key);
		return in_array($ckey, $this->get_cached_player_list());
	}

	function get_cached_player_list() {
		$player_list = $this->cache->get("_player_lounge_player_list");
		if (!$player_list) {
			$query = $this->db->sql_query("
				SELECT
					ckey
				FROM
					" . TG_DATABASE . ".role_time_log
				WHERE
					datetime > (CURRENT_DATE - INTERVAL " . PLAYTIME_WITHIN_DAYS . " DAY)
						AND job = 'Living'
				GROUP BY ckey
				HAVING SUM(delta) >= " . PLAYTIME_MINUTES);

			$player_list = array();

			while ($row = $this->db->sql_fetchrow($query)) {
				$player_list[] = $row["ckey"];
			}

			$this->db->sql_freeresult($query);

			$this->cache->put("_player_lounge_player_list", $player_list, CACHE_TTL);
		}

		return $player_list;
	}
}
