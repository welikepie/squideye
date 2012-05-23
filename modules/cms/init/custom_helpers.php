<?php

	function open_form($attributes = array())
	{
		$attributes = array_merge(array(
			'id'=>null,
			'onsubmit'=>null,
			'enctype'=>'multipart/form-data'
		), $attributes);

		$result = Phpr_Form::open_tag($attributes);
		$session_key = post('ls_session_key', uniqid('lsk', true));
		$result .= "\n".'<input type="hidden" name="ls_session_key" value="'.h($session_key).'"/>';
		
		return $result;
	}
	
	if(!function_exists('close_form')) {
		function close_form() {
			$result = Phpr_Form::close_tag();
			return $result;
		}
	}
	
	function flash_message()
	{
		if (array_key_exists('system', Phpr::$session->flash->flash))
		{
			$system_message = Phpr::$session->flash['system'];

			if (strpos($system_message, 'flash_partial') !== false && !array_key_exists('error', Phpr::$session->flash->flash))
			{
				$partial_name = substr($system_message, 14);
				$success_message = array_key_exists('success', Phpr::$session->flash->flash) ? Phpr::$session->flash->flash['success'] : null;
				
				Cms_Controller::get_instance()->render_partial($partial_name, array('message'=>$success_message));

				Phpr::$session->flash->now();
				return;
			}
		}

		return Cms_Html::flash();
	}
	
	function content_block($code, $name)
	{
		echo Cms_Html::content_block($code, $name);
	}
	
	function global_content_block($code, $return_content = false)
	{
		return Cms_Html::global_content_block($code, $return_content);
	}
	
	if(!function_exists('site_url'))
	{
		function site_url($url)
		{
			return Cms_Html::site_url($url);
		}
	}
	
	function include_resources($src_mode = false)
	{
		return Cms_Html::include_resources($src_mode);
	}
	
	function process_ls_tags($str)
	{
		return Core_String::process_ls_tags($str);
	}

	/**
	 * Returns file URL relative to the currently active theme resources directory.
	 * @param string $path File path in the theme resources directory.
	 * @param boolean $root_url Return URL relative to the LemonStand domain root.
	 * @param string $add_host_name_and_protocol Return absolute URL with the host name and protocol. 
	 * This parameter works only if the $root_url parameter is true.
	 */
	function theme_resource_url($path, $root_url = true, $add_host_name_and_protocol = false)
	{
		return Cms_Html::theme_resource_url($path, $root_url, $add_host_name_and_protocol);
	}
	
	/**
	 * Returns file URL relative to the website resources directory (/resources by default).
	 * @param string $path File path in the resources directory.
	 * @param boolean $root_url Return URL relative to the LemonStand domain root.
	 * @param string $add_host_name_and_protocol Return absolute URL with the host name and protocol. 
	 * This parameter works only if the $root_url parameter is true.
	 */
	function resource_url($path, $root_url = true, $add_host_name_and_protocol = false)
	{
		return Cms_Html::resource_url($path, $root_url, $add_host_name_and_protocol);
	}

?>