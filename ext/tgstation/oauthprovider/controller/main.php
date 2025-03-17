<?php

namespace tgstation\oauthprovider\controller;

use \Symfony\Component\HttpFoundation\Response;
use \Symfony\Component\HttpFoundation\JsonResponse;

class main
{
	const SCOPE_DEFAULT = 'user user.linked_accounts';
	const SCOPE_USER = 'user';
	const SCOPE_USER_DETAILS = 'user.details';
	const SCOPE_USER_GROUPS = 'user.groups';
	const SCOPE_USER_PRIVATE_GROUPS = 'user.groups.private';
	const SCOPE_USER_EMAIL = 'user.email';
	const SCOPE_USER_LINKED_ACCOUNTS = 'user.linked_accounts';

	const APP_DISABLED = (1 << 0);
	const APP_INTERNAL = (1 << 1);

	const AUTH_DISABLED = (1 << 0);
	const AUTH_CONSUMED = (1 << 1);
	const AUTH_REMEMBER = (1 << 2);

	const REFRESH_DISABLED = (1 << 0);
	const REFRESH_CONSUMED = (1 << 1);

	const SESSION_DISABLED = (1 << 0);

	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\cache\service */
	protected $cache;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\symfony_request */
	protected $symfony_request;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/* @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \phpbb\path_helper */
	protected $path_helper;

	protected $table_prefix;

	/**
	 * Construct
	 *
	 * @param \phpbb\auth\auth $auth
	 * @param \phpbb\cache\service $cache
	 * @param \phpbb\config\config $config
	 * @param \phpbb\request\request $request
	 * @param \phpbb\db\driver\driver_interface $db
	 * @param \phpbb\template\template $template
	 * @param \phpbb\user $user
	 * @param \phpbb\language\language	$language
	 * @param \phpbb\controller\helper $helper
	 */
	public function __construct(\phpbb\auth\auth $auth, \phpbb\cache\service $cache, \phpbb\config\config $config, \phpbb\request\request $request, \phpbb\symfony_request $symfony_request, \phpbb\db\driver\driver_interface $db, \phpbb\template\template $template, \phpbb\user $user, \phpbb\language\language $language, \phpbb\controller\helper $helper, \phpbb\path_helper $path_helper, $table_prefix)
	{
		$this->auth = $auth;
		$this->cache = $cache;
		$this->config = $config;
		$this->request = $request;
		$this->symfony_request = $symfony_request;
		$this->db = $db;
		$this->template = $template;
		$this->user = $user;
		$this->language = $language;
		$this->factory = $factory;
		$this->helper = $helper;
		$this->path_helper = $path_helper;

		$this->table_prefix = $table_prefix;
	}
	private function api_error($errordesc, $error = 'invalid_request', $status = 400)
	{
		if ($status < 100) {
			$status = 400;
		}
		return new \Symfony\Component\HttpFoundation\JsonResponse([
			'error' => $error,
			'error_description' => $errordesc,
		], $status);
	}
	private function redirect_error($redirect_uri, $errordesc, $error = 'server_error', $state = null)
	{

		$appendparam = 'error=' . urlencode($error);
		$appendparam .= '&errordesc=' . urlencode($errordesc);
		if (!empty($state)) {
			$appendparam .= '&state=' . urlencode($state);
		}

		$new_redirect_uri = $redirect_uri . (strpos($redirect_uri, '?') === false ? '?' : '&') . $appendparam;

		redirect($new_redirect_uri, false, true);
		throw new \phpbb\exception\http_exception(400, $error . ':' . $errordesc);
	}
	/**
	 * Demo controller for route /demo/{name}
	 *
	 * @param string $name
	 * @throws \phpbb\exception\http_exception
	 * @return \Symfony\Component\HttpFoundation\Response A Symfony Response object
	 */
	public function handle_html($name)
	{
		if (!confirm_box(true)) {
			$hidden = build_hidden_fields(array('mode' => 'moderate'));
			confirm_box(false, 'Confirm thing', $hidden);
		} else {
			$this->template->assign_var('OAUTH_DEMO_MESSAGE', 'Thing confirmed');
			return $this->helper->render('oauth_demo_body.html', $name);
		}
	}
	public function handle_api($name)
	{
		return new \Symfony\Component\HttpFoundation\JsonResponse(['data' => 123]);
	}

	public function handle_auth()
	{
		$get_response_type = $this->request->variable('response_type', 'code');
		if ($get_response_type != "code") {
			throw new \phpbb\exception\http_exception(400, 'Only code response_types are accepted', [$get_response_type]);
		}
		$get_client_id = (int)$this->request->variable('client_id', 0);
		if ($get_client_id < 1) {
			throw new \phpbb\exception\http_exception(400, 'invalid client_id given', [$get_client_id]);
		}
		$get_redirect_uri = $this->request->variable('redirect_uri', '');

		$get_scope = $this->request->variable('scope', $this::SCOPE_DEFAULT);

		$get_state = $this->request->variable('state', '');

		if ($this->user->data['user_id'] <= 1 || $this->user->data['is_bot']) {
			login_box($this->helper->get_current_url());
			return new \phpbb\exception\http_exception(401, 'Unauthorized');
		}


		$scopes = $this->process_scopes($get_scope);
		if (count($scopes) < 1) {
			throw new \phpbb\exception\http_exception(500, 'Invalid scopes given and the default scopes failed to parse', []);
		}
		$application_sql_array = [
			'SELECT'    => 'a.application_id, a.application_name, a.application_website, a.redirect_uri, a.application_flags, a.refresh_ttl, a.session_ttl, a.user_id',
			'FROM'      => [
				$this->table_prefix . 'oauth_server_application'  => 'a',
			],
			'WHERE'     => '
				a.application_id = ' . (int) $get_client_id . '
				AND
				(a.application_flags & ' . $this::APP_DISABLED . ') = 0
			',
		];

		$application_sql = $this->db->sql_build_query('SELECT', $application_sql_array);

		// Now run the query...
		$application_result = $this->db->sql_query_limit($application_sql, 1);
		$application_row = $this->db->sql_fetchrow($application_result);

		if (empty($application_row) || !$application_row || count($application_row) < 1) {
			throw new \phpbb\exception\http_exception(400, 'Invalid client_id.', [$get_client_id]);
		}
		$parsed_get_redirect_uri = parse_url($get_redirect_uri);
		$redirect_uri = $get_redirect_uri;
		if (empty($get_redirect_uri)) {
			$redirect_uri = $application_row['redirect_uri'];
		}
		$parsed_redirect_uri = parse_url($redirect_uri);
		if ((int)$this->user->data['user_id'] !== (int)$application_row['user_id'] || $this->request->variable('checks', '') !== "UNSAFE") {

			if (!empty($get_redirect_uri) && (!isset($parsed_get_redirect_uri['host']) || $parsed_get_redirect_uri['host'] != 'localhost') && !$this->str_starts_with($get_redirect_uri, $application_row['redirect_uri'])) {
				throw new \phpbb\exception\http_exception(403, 'This redirect_uri is not authorized to receive auth codes for the given oauth application id.', [$get_redirect_uri]);
			}


			if ($parsed_redirect_uri === false || !isset($parsed_redirect_uri['scheme']) || (strtolower($parsed_redirect_uri['scheme']) != 'https' && $parsed_redirect_uri['host'] != 'localhost')) {
				//print_r($application_row);
				throw new \phpbb\exception\http_exception(400, 'This redirect_uri is not secure.', [$get_redirect_uri]);
			}

			if (empty($parsed_redirect_uri['host']) || !isset($parsed_redirect_uri['path'])) {
				throw new \phpbb\exception\http_exception(400, 'This uri is invalid or incomplete.', [$get_redirect_uri]);
			}
		}
		if ($application_row['user_id'] < 1) {
			return $this->redirect_error($redirect_uri, 'Oauth client_id has no user_id attached to it internally. MSO likely forgot to add it.', 'invalid_client', $get_state);
		}
		if (($application_row['application_flags'] & $this::APP_INTERNAL) !== $this::APP_INTERNAL && !confirm_box(true)) {
			$userscopes = $this->map_scope_details($scopes);
			$this->template->assign_var('OAUTH_HOST', $application_row['application_name'] . ' (' . $parsed_redirect_uri['host'] . ')');
			$this->template->assign_var('OAUTH_DATA_PREVIEW', json_encode($this->get_user_details_from_scopes($scopes, $this->user->data['user_id']), JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_PRETTY_PRINT));
			$this->template->assign_block_vars_array('oauth_scope', $userscopes);

			$this->template->set_filenames(array(
				'oauth_user_prompt'	=> 'oauth_user_prompt.html',
			));

			$hidden = build_hidden_fields([
				'response_type' => $get_response_type,
				'client_id' => $get_client_id,
				'redirect_uri' => $get_redirect_uri,
				'scope' => $get_scope,
				'state' => $get_state,
			]);

			confirm_box(false, $this->template->assign_display('oauth_user_prompt'), $hidden);
			$this->redirect_error($redirect_uri, 'The user did not authorize the request or there was an error processing their authorization.', 'access_denied', $get_state);
			/*$this->template->assign_var('OAUTH_DEMO_MESSAGE', 'Test after confirm_box');
			return $this->helper->render('oauth_demo_body.html', $name);*/
		} else {
			$authcode = $this->base64url_encode($this->generate_token());
			$allowed_scopes = implode(' ', $scopes);

			$authorization_data = [
				'application_id' => (int) $application_row['application_id'],
				'user_id' => (int) $this->user->data['user_id'],
				'auth_code' => $this->token_hash('' . $application_row['application_id'] . $authcode),
				'redirect_uri' => (empty($get_redirect_uri) ? '' : $get_redirect_uri),
				'challenge_token' => '',
				'scopes' => $allowed_scopes,
				'user_passchg' => (int) $this->user->data['user_passchg'],
				'authorized' => (int) time(),
			];
			$this->db->sql_multi_insert($this->table_prefix . 'oauth_server_authorization', [$authorization_data]);
			$auth_id = (int) $this->db->sql_nextid();

			$appendparam = 'code=' . urlencode($this->base64url_encode_64uint($auth_id) . '~' . $authcode);
			if (!empty($get_state)) {
				$appendparam .= '&state=' . urlencode($get_state);
			}

			$new_redirect_uri = $redirect_uri . (strpos($redirect_uri, '?') === false ? '?' : '&') . $appendparam;

			//$this->template->assign_var('OAUTH_DEMO_MESSAGE', 'This is the end of the line bud');
			return redirect($new_redirect_uri, false, true);
		}
	}

	public function handle_token()
	{
		if ($this->symfony_request->getContentType() === 'json') {
			$json = $this->symfony_request->getContent();
			$data = json_decode($json, true);
			if (json_last_error() !== JSON_ERROR_NONE) {
				return $this->api_error('Failure to parse json body');
			}

			foreach ($data as $key => $value) {
				$this->request->overwrite($key, $value);
			}
		}

		/*if (!$this->request->is_set_post('code')) {
			return $this->api_error('This endpoint accepts post requests only');
		}*/

		$get_grant_type = $this->request->variable('grant_type', 'authorization_code');

		$get_client_id = (int)$this->request->variable('client_id', 0);
		$get_client_secret = $this->request->variable('client_secret', '');
		$get_redirect_uri = $this->request->variable('redirect_uri', '');

		$get_code = $this->request->variable('code', '');
		$get_refresh_token = $this->request->variable('refresh_token', '');



		$authtoken = '';


		switch ($get_grant_type) {
			case 'authorization_code':
				if (empty($get_code)) {
					return $this->api_error('Invalid code given');
				}
				$authtoken = $get_code;
				break;
			case 'refresh_token':
				if (empty($get_code)) {
					return $this->api_error('Invalid code given');
				}
				$authtoken = $get_refresh_token;
				break;
			default:
				return $this->api_error('Invalid grant_type. Supported grants: authorization_code, refresh_token.', 'unsupported_grant_type');
		}

		if (empty($get_client_secret)) {
			return $this->api_error('No `client_secret`. Client Secret must be sent via the post body in this api. Contact mso if you need Authorization header support');
		}


		if ($get_client_id < 1) {
			return $this->api_error('Invalid client_id given');
		}



		$auth_id = (int) $this->base64url_decode_64uint(strtok($authtoken, '~'));
		$auth_secret = strtok("\0"); // get remaining text from $authtoken.
		if ($auth_id < 1 || empty($auth_secret)) {
			return $this->api_error('Supplied authorization_code in invalid format or could not be parsed.', 'invalid_grant');
		}

		$authorization_sql_array = [
			'SELECT'    => 'au.*, a.application_id, a.user_id as application_user_id, a.application_flags, a.refresh_ttl, a.session_ttl, a.client_secret',
			'FROM'      => [
				$this->table_prefix . 'oauth_server_authorization'  => 'au',
			],
			'LEFT_JOIN' => [
				[
					'FROM'  => [$this->table_prefix . 'oauth_server_application' => 'a'],
					'ON'    => 'a.application_id = au.application_id',
				],
			],
			'WHERE'     => '
				a.application_id = ' . (int) $get_client_id . '
				AND
				(a.application_flags & ' . $this::APP_DISABLED . ') = 0
				AND
				((au.authorization_flags & ' . $this::AUTH_DISABLED . ') = 0)
			',
		];
		switch ($get_grant_type) {
			case 'authorization_code':
				$authorization_sql_array['WHERE'] .= ' AND au.authorization_id = ' . (int) $auth_id . ' AND au.authorized > ' . (time() - (60 * 10));
				break;
			case 'refresh_token':
				$authorization_sql_array['SELECT'] .= ', r.refresh_token_flags, r.refresh_token, r.consumed_on as refresh_token_consumed';
				$authorization_sql_array['LEFT_JOIN'][] = [
					'FROM' => [$this->table_prefix . 'oauth_server_refresh_token' => 'r'],
					'ON' => 'r.authorization_id = au.authorization_id',
				];
				$authorization_sql_array['WHERE'] .= ' AND ((r.refresh_token_flags & ' . $this::REFRESH_DISABLED . ') = 0) AND r.refresh_token_id = ' . $auth_id . ' AND au.authorization_id = r.authorization_id AND r.expires > ' . time();
				break;
		}

		$authorization_sql = $this->db->sql_build_query('SELECT', $authorization_sql_array);
		$authorization_result = $this->db->sql_query_limit($authorization_sql, 1);
		$authorization_row = $this->db->sql_fetchrow($authorization_result);
		if (empty($authorization_row) || !$authorization_row || count($authorization_row) < 1) {
			return $this->api_error('Could not fetch authorization grant with provided authorization_code and client_id', 'invalid_grant');
		}

		$parsed_get_redirect_uri = parse_url($get_redirect_uri);
		if ((!isset($parsed_get_redirect_uri['host']) || $parsed_get_redirect_uri['host'] != 'localhost') && (empty($get_redirect_uri) ? '' : $get_redirect_uri) != $authorization_row['redirect_uri']) {
			return $this->api_error('This redirect_uri is not authorized to receive auth codes for the given oauth authorization_code grant.');
		}

		if ($authorization_row['application_user_id'] < 1) {
			return $this->api_error('Oauth application does not have a user???', 'invalid_client', 401);
		}

		if ($this->token_hash_validate($authorization_row['client_secret'], $authorization_row['application_user_id'] . $get_client_secret) !== TRUE) {
			return $this->api_error('Wrong client secret', 'invalid_client', 401);
		}

		switch ($get_grant_type) {
			case 'authorization_code':
				if ($this->token_hash_validate($authorization_row['auth_code'], $authorization_row['application_id'] . $auth_secret) !== TRUE) {
					return $this->api_error('Wrong auth code.', 'invalid_grant', 401);
				}
				if (((int)$authorization_row['authorization_flags']) & $this::AUTH_CONSUMED) {
					$this->invalidate_auth((int)$authorization_row['authorization_id']);
					return $this->api_error('SECURITY ERROR: Double spend on auth_code! This entire authorization grant has been invalided.', 'invalid_grant', 401);
				}
				if (!$this->consume_authcode((int)$authorization_row['authorization_id'])) {
					return $this->api_error('Unknown error consuming auth code.', 'invalid_grant', 500);
				}
				break;
			case 'refresh_token':
				if ($this->token_hash_validate($authorization_row['refresh_token'], $authorization_row['authorization_id'] . $auth_secret) !== TRUE) {
					return $this->api_error('Wrong refresh_token.', 'invalid_grant', 401);
				}
				if ((int)$authorization_row['refresh_token'] & $this::REFRESH_CONSUMED) {
					$this->invalidate_auth((int)$authorization_row['authorization_id']);
					return $this->api_error('SECURITY ERROR: Double spend on refresh_token! This entire authorization grant has been invalided.', 'invalid_grant', 401);
				}
				if (!$this->consume_refresh_token($authorization_row['refresh_token_id'])) {
					return $this->api_error('Unknown error consuming refresh_token.', 'invalid_grant', 500);
				}
				break;

			default:
				return $this->api_error('Server error: unreachable code reached', 'SERVER_ERROR', 500);
		}
		$session_data = $this->generate_session_token($authorization_row['authorization_id'], $authorization_row['session_ttl']);
		$refresh_data = $this->generate_refresh_token($authorization_row['authorization_id'], $authorization_row['refresh_ttl']);
		$api_response = [
			'access_token' 		=> $this->base64url_encode_64uint($session_data['session_id']) . '~' . $session_data['session_token_real'],
			'token_type'		=> 'bearer',
			'expires_in'		=> (int) $authorization_row['session_ttl'],
			'refresh_token' 	=> $this->base64url_encode_64uint($refresh_data['refresh_token_id']) . '~' . $refresh_data['refresh_token_real'],
		];
		return new \Symfony\Component\HttpFoundation\JsonResponse($api_response);
	}
	public function handle_user()
	{
		//return $this->api_error('TODO: code user api endpoint', 'todo');

		$auth = $this->symfony_request->headers->get('Authorization');
		if (!$auth) {
			return $this->api_error('No Authorization header.', 'invalid_client', 401);
		}
		$token_type = strtok($auth, ' ');
		$token = strtok("\0");
		if ($token_type != "Bearer") {
			return $this->api_error('Unsupported Authorization header (not Bearer).', 'invalid_request', 401);
		}
		$auth_id = (int) $this->base64url_decode_64uint(strtok($token, '~'));
		$auth_secret = strtok("\0"); // get remaining text from $token.

		if ($auth_id < 1 || empty($auth_secret)) {
			return $this->api_error('Supplied authorization_code in invalid format or could not be parsed.', 'invalid_grant');
		}

		$session_sql_array = [
			'SELECT'    => 's.session_token, s.last_used, au.authorization_id, au.user_id, au.scopes',
			'FROM'      => [
				$this->table_prefix . 'oauth_server_session_token'  => 's',
			],
			'LEFT_JOIN' => [
				[
					'FROM'  => [$this->table_prefix . 'oauth_server_authorization' => 'au'],
					'ON'    => 's.authorization_id = au.authorization_id',
				],
				[
					'FROM'  => [$this->table_prefix . 'oauth_server_application' => 'a'],
					'ON'    => 'a.application_id = au.application_id',
				],
				[
					'FROM' => [USERS_TABLE => 'u'],
					'ON' => 'au.user_id = u.user_id',
				],
			],
			'WHERE'     => '
				s.session_id = ' . (int) $auth_id . '
				AND
				((a.application_flags & ' . $this::APP_DISABLED . ') = 0)
				AND
				((au.authorization_flags & ' . $this::AUTH_DISABLED . ') = 0)
				AND
				((s.session_token_flags & ' . $this::SESSION_DISABLED . ') = 0)
				AND
				au.user_passchg = u.user_passchg
				AND
				s.expires > ' . time() . '
			',
		];


		$session_sql = $this->db->sql_build_query('SELECT', $session_sql_array);
		$session_result = $this->db->sql_query_limit($session_sql, 1);
		$session_row = $this->db->sql_fetchrow($session_result);
		if (empty($session_row) || !$session_row || count($session_row) < 1) {
			return $this->api_error('Bearer token not found.', 'invalid_token', 401);
		}
		if ($this->token_hash_validate($session_row['session_token'], $session_row['authorization_id'] . $auth_secret) !== TRUE) {
			return $this->api_error('Bearer token invalid.', 'invalid_auth', 401);
		}

		$scopes = $this->process_scopes($session_row['scopes']);
		if (!count($scopes)) {
			return $this->api_error('Unknown error processing authorized scopes', 'server_error', 500);
		}
		$user_id = (int)$session_row['user_id'];

		if ($user_id <= 1) {
			return $this->api_error('Unknown error fetching user details', 'server_error', 500);
		}

		return new \Symfony\Component\HttpFoundation\JsonResponse($this->get_user_details_from_scopes($scopes, $user_id));
	}

	private function invalidate_auth($authorization_id)
	{
		$sql = 'UPDATE ' . $this->table_prefix . 'oauth_server_authorization' . '
			SET authorization_flags = (authorization_flags | ' . $this::AUTH_DISABLED . ')
			WHERE authorization_id = ' . (int) $authorization_id;
		$this->db->sql_query($sql);
		//print_r($sql);
		$res = $this->db->sql_query($sql);
		$rtn = $this->db->sql_affectedrows();
		$this->db->sql_freeresult($res);

		return $rtn;
	}

	private function token_hash($token, $algo = 'sha3-224')
	{
		return $this->base64url_encode($algo . '~' . $this->base64url_encode(hash($algo, $token, TRUE)));
	}

	private function token_hash_validate($hashed_token, $user_token): bool
	{
		$decoded_token = $this->base64url_decode($hashed_token);

		$algo = strtok($decoded_token, '~');
		$token_hash = strtok("\0");
		if (!in_array($algo, hash_algos())) {
			return false;
		}
		$hashed_user_token = $this->token_hash($user_token, $algo);

		//decode and split token again so that format changes in how tokens are stored don't trigger false negitives.
		$decoded_hashed_user_token = $this->base64url_decode($hashed_user_token);
		$user_algo = strtok($decoded_hashed_user_token, '~');
		$user_token_hash = strtok("\0");

		if (strlen($token_hash) < 20 || strlen($user_token_hash) < 20) {
			return false;
		}
		return hash_equals($token_hash, $user_token_hash);
	}

	private function generate_session_token(int $authorization_id, int $ttl): array
	{
		$token = $this->base64url_encode($this->generate_token());
		$session_token_data = [
			'session_token' => $this->token_hash($authorization_id . $token),
			'authorization_id' => $authorization_id,
			'created' => (int) time(),
			'expires' => (int) time() + $ttl,
		];
		$this->db->sql_multi_insert($this->table_prefix . 'oauth_server_session_token', [$session_token_data]);
		$session_token_data['session_id'] = (int) $this->db->sql_nextid();
		$session_token_data['session_token_real'] = $token;
		return $session_token_data;
	}

	private function consume_refresh_token(int $refresh_token_id)
	{
		$sql = 'UPDATE ' . $this->table_prefix . 'oauth_server_refresh_token' . '
			SET consumed_on = ' . (int) time() . ', refresh_token_flags = (refresh_token_flags | ' . $this::REFRESH_CONSUMED . ')
			WHERE refresh_token_id = ' . (int) $refresh_token_id;
		$this->db->sql_query($sql);

		$res = $this->db->sql_query($sql);
		$rtn = $this->db->sql_affectedrows();
		$this->db->sql_freeresult($res);

		return $rtn;
	}

	private function consume_authcode(int $authorization_id)
	{
		$sql = 'UPDATE ' . $this->table_prefix . 'oauth_server_authorization' . '
			SET authorization_flags = (authorization_flags | ' . $this::AUTH_CONSUMED . ')
			WHERE authorization_id = ' . (int) $authorization_id;
		$this->db->sql_query($sql);
		//print_r($sql);
		$res = $this->db->sql_query($sql);
		$rtn = $this->db->sql_affectedrows();
		$this->db->sql_freeresult($res);

		return $rtn;
	}

	private function generate_refresh_token(int $authorization_id, int $ttl): array
	{
		$token = $this->base64url_encode($this->generate_token());
		$refresh_token_data = [
			'refresh_token' => $this->token_hash($authorization_id . $token),
			'authorization_id' => $authorization_id,
			'created' => (int) time(),
			'expires' => (int) time() + $ttl,
		];
		$this->db->sql_multi_insert($this->table_prefix . 'oauth_server_refresh_token', [$refresh_token_data]);
		$refresh_token_data['refresh_token_id'] = (int) $this->db->sql_nextid();
		$refresh_token_data['refresh_token_real'] = $token;
		$sql = 'UPDATE ' . $this->table_prefix . 'oauth_server_authorization' . '
			SET last_refreshed = ' . (int) time() . '
			WHERE authorization_id = ' . (int) $authorization_id;
		$this->db->sql_freeresult($this->db->sql_query($sql));
		return $refresh_token_data;
	}

	protected function process_scopes($scopes, $no_nest = false): array
	{
		$scopes = explode(' ', $scopes);
		$new_scopes = array();
		foreach ($scopes as $scope) {
			switch ($scope) {
				case $this::SCOPE_USER_EMAIL:
					$new_scopes[] = $this::SCOPE_USER_EMAIL;
					$new_scopes[] = $this::SCOPE_USER;
					break;
				case $this::SCOPE_USER_DETAILS:
					$new_scopes[] = $this::SCOPE_USER_DETAILS;
					$new_scopes[] = $this::SCOPE_USER;
					break;
				case $this::SCOPE_USER_PRIVATE_GROUPS:
					$new_scopes[] = $this::SCOPE_USER_PRIVATE_GROUPS;
					$new_scopes[] = $this::SCOPE_USER_GROUPS;
					$new_scopes[] = $this::SCOPE_USER;
					break;
				case $this::SCOPE_USER_GROUPS:
					$new_scopes[] = $this::SCOPE_USER_GROUPS;
					$new_scopes[] = $this::SCOPE_USER;
					break;
				case $this::SCOPE_USER_LINKED_ACCOUNTS:
					$new_scopes[] = $this::SCOPE_USER_LINKED_ACCOUNTS;
					$new_scopes[] = $this::SCOPE_USER;
					break;
				case $this::SCOPE_USER:
					$new_scopes[] = $this::SCOPE_USER;
					break;
			}
		}
		if (count($new_scopes) < 1) {
			if ($no_nest) {
				throw new \phpbb\exception\http_exception(500, 'Server Error processing scopes: could not inject default scopes: nesting detected', []);
			}
			return $this->process_scopes($this::SCOPE_DEFAULT, TRUE);
		}
		return array_unique($new_scopes);
	}
	private function map_scope_details($scopes): array
	{
		$new_scopes = array();
		foreach ($scopes as $scope) {
			switch ($scope) {
				case $this::SCOPE_USER_EMAIL:
					$new_scopes[] = [
						'SCOPE_ID' => $scope,
						'SCOPE_EXPLAIN' => 'The email address you registered your forum account with',
						'SCOPE_BOLD' => TRUE,
						'SCOPE_RED' => TRUE,
					];
					break;
				case $this::SCOPE_USER_DETAILS:
					$new_scopes[] = [
						'SCOPE_ID' => $scope,
						'SCOPE_EXPLAIN' => 'Details about your forum account (avatar, post count, date of register and last access, etc)',
						'SCOPE_BOLD' => FALSE,
						'SCOPE_RED' => FALSE,
					];
					break;
				case $this::SCOPE_USER_PRIVATE_GROUPS:
					$new_scopes[] = [
						'SCOPE_ID' => $scope,
						'SCOPE_EXPLAIN' => 'A list of the private forum groups you are in',
						'SCOPE_BOLD' => TRUE,
						'SCOPE_RED' => FALSE,
					];
					break;
				case $this::SCOPE_USER_GROUPS:
					$new_scopes[] = [
						'SCOPE_ID' => $scope,
						'SCOPE_EXPLAIN' => 'A list of the public forum groups you are in',
						'SCOPE_BOLD' => FALSE,
						'SCOPE_RED' => FALSE,
					];
					break;
				case $this::SCOPE_USER_LINKED_ACCOUNTS:
					$new_scopes[] = [
						'SCOPE_ID' => $scope,
						'SCOPE_EXPLAIN' => 'Details about your linked accounts (byond, github, reddit)',
						'SCOPE_BOLD' => FALSE,
						'SCOPE_RED' => FALSE,
					];
					break;
				case $this::SCOPE_USER:
					$new_scopes[] = [
						'SCOPE_ID' => $scope,
						'SCOPE_EXPLAIN' => 'Your forum username and user id.',
						'SCOPE_BOLD' => FALSE,
						'SCOPE_RED' => FALSE,
					];
					break;
			}
		}
		return $new_scopes;
	}
	protected function get_user_details_from_scopes(array $scopes, int $user_id): array
	{
		$user_details = array();
		// Array with data for the full SQL statement

		$sql_array = [
			'SELECT'    => 'u.user_id as user_id, username, username_clean',

			'FROM'      => [
				USERS_TABLE  => 'u',
			],
			'WHERE'     => 'u.user_id = ' . (int) $user_id,
		];

		if (in_array($this::SCOPE_USER_GROUPS, $scopes)) {
			$sql_array['SELECT'] .= ', group_id as primary_group';
		}
		if (in_array($this::SCOPE_USER_DETAILS, $scopes)) {
			$sql_array['SELECT'] .= ', user_type, user_regdate as registered, user_lastvisit as lastvisit, user_rank as rank, user_posts as posts, user_timezone as timezone, user_avatar as avatar';
		}
		if (in_array($this::SCOPE_USER_EMAIL, $scopes)) {
			$sql_array['SELECT'] .= ', user_email as email';
		}
		if (in_array($this::SCOPE_USER_LINKED_ACCOUNTS, $scopes)) {
			$sql_array['SELECT'] .= ', pf_byond_username as byond_key, pf_github as github_username, pf_reddit as reddit_username';
			$sql_array['LEFT_JOIN'] = [
				[
					'FROM'  => [PROFILE_FIELDS_DATA_TABLE => 'p'],
					'ON'    => 'p.user_id = u.user_id',
				],
			];
		}
		// Build the SQL statement
		$sql = $this->db->sql_build_query('SELECT', $sql_array);

		// Now run the query...
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$user_row = array();
		foreach ($row as $key => $value) {
			switch ($key) {
				//date casting (currently fall through to int)
				case 'registered':
				case 'lastvisit':

					//int casting
				case 'user_id':
				case 'primary_group':
				case 'user_type':
				case 'posts':
					$user_row[$key] = (int) $value;
					break;

				//string transforming
				case 'byond_key':
					$user_row['byond_key'] = $value;
					$user_row['byond_ckey'] = $this->keytockey($value);
					break;
				case 'username':
					$user_row['username'] = $value;
					$user_row['phpbb_username'] = $value;
					break;
				default:
					$user_row[$key] = $value;
			}
		}

		$user_details = $user_row;

		if (in_array($this::SCOPE_USER_GROUPS, $scopes)) {
			$group_sql_array = [
				'SELECT'    => 'g.group_id, g.group_name, ug.group_leader, g.group_type',
				'FROM'      => [
					USER_GROUP_TABLE  => 'ug',
				],
				'LEFT_JOIN' => [
					[
						'FROM'  => [GROUPS_TABLE => 'g'],
						'ON'    => 'g.group_id = ug.group_id',
					],
				],
				'WHERE'     => 'ug.user_id = ' . (int) $user_id,
				'ORDER_BY'  => 'g.group_name',
			];
			if (!in_array($this::SCOPE_USER_PRIVATE_GROUPS, $scopes)) {
				$group_sql_array['WHERE'] .= ' AND (g.group_type <> ' . (int) GROUP_HIDDEN . ' OR g.group_id = ' . (int) $row['primary_group'] . ')';
			}
			// Build the SQL statement
			$group_sql = $this->db->sql_build_query('SELECT', $group_sql_array);

			// Now run the query...
			$group_result = $this->db->sql_query($group_sql);
			$group_rowset = array();
			while (($group_row = $this->db->sql_fetchrow($group_result))) {
				$new_group_row = array();
				foreach ($group_row as $key => $value) {
					switch ($key) {
						//int casting
						case 'group_id':
						case 'group_type':
							$new_group_row[$key] = (int) $value;
							break;

						//bool casting
						case 'group_leader':
							$new_group_row[$key] = (bool) $value;
							break;

						default:
							$new_group_row[$key] = $value;
					}
				}
				$group_rowset[] = $new_group_row;
			}
			$user_details['groups'] = $group_rowset;
			$this->db->sql_freeresult($group_result);
		}


		$this->db->sql_freeresult($result);
		return $user_details;
	}
	protected function base64url_encode($data)
	{
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}

	protected function base64url_decode($data)
	{
		return base64_decode(strtr($data, '-_', '+/'));
	}

	protected function base64url_encode_64uint(int $num)
	{
		return $this->base64url_encode(rtrim(pack('P', $num), "\0"));
	}
	protected function str_starts_with($haystack, $needle)
	{
		return (string)$needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
	}
	protected function base64url_decode_64uint(string $text)
	{
		return unpack('P', str_pad($this->base64url_decode($text), 8, "\0"))[1];
	}
	protected function generate_token()
	{
		$secure = FALSE;
		$r_bytes = openssl_random_pseudo_bytes(5120, $secure);
		if (!$secure) {
			for ($i = 1; $i > 1024; $i++)
				$r_bytes .= openssl_random_pseudo_bytes(5120);
		}
		return hash('sha512/256', $r_bytes, TRUE);
	}
	protected function keytockey($key)
	{
		return strtolower(preg_replace('/[^a-zA-Z0-9@]/', '', $key));
	}
}
