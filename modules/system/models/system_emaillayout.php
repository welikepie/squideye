<?php

	class System_EmailLayout extends Db_ActiveRecord 
	{
		public $table_name = 'system_email_layouts';
		
		public static function create($values = null) 
		{
			return new self($values);
		}
		
		public function define_columns($context = null)
		{
			$this->define_column('code', 'Code')->validation()->fn('trim')->required('Please specify the layout code.')->unique('Code %s is already in use. Please specify another code.')->regexp('/^[a-z_0-9:]*$/i', 'Template code can only contain latin characters, numbers, colons and underscores.');
			$this->define_column('name', 'Name')->validation()->fn('trim')->required('Please specify the layout name.');
			$this->define_column('content', 'Content')->invisible()->validation()->required('Please fill out the layout content.');
			$this->define_column('css', 'CSS')->invisible()->validation();
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('content')->tab('Layout Content')->cssClasses('code')->renderAs(frm_code_editor)->size('huge')->comment('Please provide the HTML/PHP code for the email layout', 'above');
			$this->add_form_field('css')->tab('CSS')->cssClasses('code')->renderAs(frm_code_editor)->size('huge')->comment('This CSS document will be included to the layout', 'above')->language('css');
		}
		
		public static function find_by_code($code)
		{
			$code = trim($code);
			
			if (!strlen($code))
				throw new Phpr_ApplicationException('The specified layout is not found');
				
			$obj = self::create()->where('code=?', $code)->find();
			if (!$obj)
				throw new Phpr_ApplicationException('The specified layout is not found');

			return $obj;
		}
		
		public function format($message, $params = array())
		{
			$result = null;
			$engine = null;
			try
			{
				$engine = System_EmailParams::get_templating_engine();
				if ($engine == 'php')
				{
					ob_start();
					extract($params);
					eval('?> '.$this->content);
					$result = ob_get_clean();
				} else
				{
					$result = Core_Twig::get()->parse($this->content, array_merge($params, array('message'=>$message, 'this'=>$this)), 'Email layout "'.$this->name.'"');
				}
			} catch (exception $ex) 
			{
				if ($engine == 'php')
					ob_end_clean();

				$result = 'ERROR: '.h($ex->getMessage());
			}
			
			return $result;
		}
	}
	
?>