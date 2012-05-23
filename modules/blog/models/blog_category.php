<?php

	class Blog_Category extends Db_ActiveRecord
	{
		public $table_name = 'blog_categories';
		
		public $implement = 'Db_AutoFootprints';
		public $auto_footprints_visible = true;
		protected $api_added_columns = array();

		public $has_and_belongs_to_many = array(
			'all_posts'=>array('class_name'=>'Blog_Post', 'join_table'=>'blog_posts_categories', 'order'=>'created_at desc'),
			'posts'=>array('class_name'=>'Blog_Post', 'join_table'=>'blog_posts_categories', 'order'=>'blog_posts.published_date desc', 'conditions'=>'(is_published is not null and is_published=1)')
		);

		// public $has_many = array(
		// 	'all_posts'=>array('class_name'=>'Blog_Post', 'foreign_key'=>'category_id', 'order'=>'created_at desc'),
		// 	'posts'=>array('class_name'=>'Blog_Post', 'foreign_key'=>'category_id', 'order'=>'blog_posts.published_date desc', 'conditions'=>'(is_published is not null and is_published=1)')
		// );
		
		public $calculated_columns = array(
			'post_num'=>array('sql'=>"(select count(*) from blog_posts, blog_posts_categories where blog_posts_categories.blog_category_id=blog_categories.id and blog_posts_categories.blog_post_id=blog_posts.id)", 'type'=>db_number),
			'published_post_num'=>array('sql'=>"(select count(*) from blog_posts, blog_posts_categories where blog_posts_categories.blog_category_id=blog_categories.id and blog_posts_categories.blog_post_id=blog_posts.id and blog_posts.is_published is not null and blog_posts.is_published=1)", 'type'=>db_number)
		);

		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->required("Please specify the category name.");
			$this->define_column('url_name', 'URL Name')->validation()->fn('trim')->fn('mb_strtolower')->regexp('/^[0-9a-z_-]*$/i', 'URL Name can contain only latin characters, numbers, underscores and the minus sign')->required('Please specify the URL Name')->unique('The URL Name "%s" already in use. Please enter another URL Name.');
			$this->define_column('description', 'Description')->validation()->fn('trim');
			$this->define_column('post_num', 'Post Number');
			
			$this->define_column('code', 'API Code')->defaultInvisible()->validation()->fn('trim')->fn('mb_strtolower')->unique('Category with the specified  API code already exists.');
			
			$this->defined_column_list = array();
			Backend::$events->fireEvent('blog:onExtendCategoryModel', $this, $context);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}
		
		public function define_form_fields($context = null)
		{
			$this->add_form_field('name', 'left');
			$this->add_form_field('url_name', 'right');
			$this->add_form_field('description')->renderAs(frm_textarea)->size('small');
			$this->add_form_field('code')->comment('You can use the API Code for accessing the category in the API calls.', 'above');
			
			Backend::$events->fireEvent('blog:onExtendCategoryForm', $this, $context);
			foreach ($this->api_added_columns as $column_name)
			{
				$form_field = $this->find_form_field($column_name);
				if ($form_field)
					$form_field->optionsMethod('get_added_field_options');
			}
		}
		
		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$result = Backend::$events->fireEvent('blog:onGetCategoryFieldOptions', $db_name, $current_key_value);
			foreach ($result as $options)
			{
				if (is_array($options) || (strlen($options && $current_key_value != -1)))
					return $options;
			}
			
			return false;
		}

		public function before_delete($id=null)
		{
			if ($this->post_num)
				throw new Phpr_ApplicationException('This category cannot be deleted because it contains '.$this->post_num.' post(s).');
		}
		
		public function after_modify($operation, $deferred_session_key)
		{
			Blog_Module::update_blog_content_version();
		}
	}

?>