<?php

	class System_ColorThemeParams extends Db_ActiveRecord 
	{
		public $table_name = 'system_colortheme_settings';
		public static $loadedInstance = null;
		
		public $has_many = array(
			'logo'=>array('class_name'=>'Db_File', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='System_ColorThemeParams'", 'order'=>'id', 'delete'=>true),
		);

		public static function create($values = null) 
		{
			return new self($values);
		}
		
		public static function get()
		{
			if (self::$loadedInstance)
				return self::$loadedInstance;
			
			return self::$loadedInstance = self::create()->order('id desc')->find();
		}

		public static function isConfigured()
		{
			$obj = self::get();
			if (!$obj)
				return false;

			return strlen($obj->smtp_address);
		}
		
		public function define_columns($context = null)
		{
			$this->define_column('header_text', 'Header Text')->validation()->fn('trim');
			$this->define_multi_relation_column('logo', 'logo', 'Logo', '@name')->invisible();
			$this->define_column('logo_border', 'Draw border around logo');
			$this->define_column('theme_id', 'Theme');
			$this->define_column('hide_footer_logos', 'Hide footer logos');
			$this->define_column('footer_text', 'Footer text')->validation()->fn('trim');
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('logo', 'left')->renderAs(frm_file_attachments)->renderFilesAs('single_image')->addDocumentLabel('Upload logo')->tab('Logo and Header Text')->noAttachmentsLabel('Logo is not uploaded')->imageThumbSize(170)->noLabel()->fileDownloadBaseUrl(url('ls_backend/files/get/'));
			$this->add_form_field('logo_border')->tab('Logo and Header Text');
			$this->add_form_field('header_text')->tab('Logo and Header Text');
			$this->add_form_field('theme_id')->tab('Theme')->renderAs('theme_selector')->comment('Please choose a theme for Administration Area.', 'above');
			$this->add_form_field('hide_footer_logos')->tab('Footer')->comment('Hide LemonStand and Limewheel Creative Inc. logos in the Administration Area footer');
			
			$this->add_form_field('footer_text')->tab('Footer')->renderAs(frm_html)->htmlButtons1("cut,copy,paste,separator,undo,redo,separator,link,unlink,separator,bold,italic,underline,code");
		}
		
		public static function list_themes()
		{
			$result = array();
			
			$themesPath = PATH_APP."/modules/backend/themes";
			$iterator = new DirectoryIterator( $themesPath );
			foreach ( $iterator as $dir )
			{
				if ( $dir->isDir() && !$dir->isDot() )
				{
					$dirPath = $themesPath."/".$dir->getFilename();
					$themeId = $dir->getFilename();

					$infoPath = $dirPath."/"."info.php";

					if (!file_exists($infoPath))
						continue;
						
					include($infoPath);
					
					$previewPath = $dirPath."/"."preview.png";
					$theme_info['preview_available'] = file_exists($previewPath);
					$theme_info['preview_path'] = '/modules/backend/themes/'.$themeId.'/preview.png';
					
					$result[$themeId] = $theme_info;
				}
			}
			
			uasort($result, array('System_ColorThemeParams', 'sort_themes'));

			return $result;
		}
		
		public function get_logo_url()
		{
			if (!$this->logo->count)
				return null;

			return $this->logo[0]->getThumbnailPath('auto', 50, false);
		}
		
		public static function sort_themes($a, $b)
		{
			return strcmp($a['name'], $b['name']);
		}
	}
?>