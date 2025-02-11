<?php
/*
*
* @package Vector Icons
* @copyright (c) mazeltof (www.mazeland.fr)
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
* 
*/

namespace mazeltof\vectoricons\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{

	protected $user;
	
	public function __construct(\phpbb\template\template $template, \phpbb\user $user, $phpbb_root_path)
	{
		$this->template = $template;
		$this->user = $user;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->ext_name = "mazeltof/vectoricons";
	}	
	
	static public function getSubscribedEvents()
	{
		return array(
			'core.page_header_after'					=> 'generate_paths',
		);
	}	

	public function generate_paths($event)
	{
		$ext_style_path = $this->phpbb_root_path . 'ext/' . $this->ext_name . '/styles/';
		$css_lang_link = rawurlencode($this->user->style['style_path']) . '/theme/' . $this->user->lang_name . '/stylesheet.css';
		if (!file_exists($ext_style_path . $css_lang_link))
		{
			$css_lang_link = rawurlencode($this->user->style['style_path']) . '/theme/en/stylesheet.css';
			if (!file_exists($ext_style_path . $css_lang_link))
			{
				$css_lang_link = 'prosilver/theme/en/stylesheet.css';
			}
		}

		$this->template->assign_vars(array(
			'VECTORICONS_STYLESHEET_LANG_LINK'	=> $css_lang_link,
		));
	}
}
