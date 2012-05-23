<?php

	class Blog_Post extends Db_ActiveRecord
	{
		public $table_name = 'blog_posts';
		
		public $implement = 'Db_AutoFootprints';
		public $auto_footprints_visible = true;
		public $auto_footprints_default_invisible = false;

		public $has_and_belongs_to_many = array(
			'categories'=>array('class_name'=>'Blog_Category', 'join_table'=>'blog_posts_categories', 'order'=>'name'),
		);

		public $has_many = array(
			'comments'=>array('class_name'=>'Blog_Comment', 'foreign_key'=>'post_id', 'order'=>'blog_comments.created_at desc', 'conditions'=>'blog_comments.status_id <> (select id from blog_comment_statuses where code = \'deleted\')'),
			'approved_comments'=>array('class_name'=>'Blog_Comment', 'foreign_key'=>'post_id', 'conditions'=>'blog_comments.status_id=(select id from blog_comment_statuses where code=\'approved\')', 'order'=>'blog_comments.created_at asc')
		);
		
		public $calculated_columns = array(
			'new_comment_num'=>array('sql'=>'(select count(*) from blog_comments, blog_comment_statuses where blog_comments.post_id = blog_posts.id and blog_comments.status_id=blog_comment_statuses.id and blog_comment_statuses.code=\'new\')', 'type'=>db_number),
			'comment_num'=>array('sql'=>'(select count(*) from blog_comments where blog_comments.post_id = blog_posts.id)', 'type'=>db_number),
			'approved_comment_num'=>array('sql'=>'(select count(*) from blog_comments, blog_comment_statuses where blog_comments.post_id = blog_posts.id and blog_comments.status_id=blog_comment_statuses.id and blog_comment_statuses.code=\'approved\')', 'type'=>db_number),
			'email_subscribers'=>array('sql'=>'(select count(*) from blog_comment_subscribers where post_id=blog_posts.id)', 'type'=>db_number),
			'author_first_name'=>array('sql'=>'author_users.firstName ', 'join'=>array('users as author_users'=>'author_users.id = blog_posts.created_user_id'), 'type'=>db_text),
			'author_last_name'=>array('sql'=>'author_users.lastName', 'type'=>db_text)
		);
		
		protected $api_added_columns = array();

		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			
			$this->define_column('title', 'Title')->order('asc')->validation()->fn('trim')->required("Please specify the post title.");
			$this->define_column('url_title', 'URL Title')->validation()->fn('trim')->fn('mb_strtolower')->regexp('/^[0-9a-z_-]*$/i', 'URL Title can contain only latin characters, numbers, underscores and the minus sign')->required('Please specify the URL Title')->unique('The URL Title "%s" already in use. Please enter another URL Title.');
			$this->define_multi_relation_column('categories', 'categories', 'Categories', '@name')->defaultInvisible()->validation()->required("Please, choose a category.");
			$this->define_column('description', 'Description')->validation()->fn('trim');
			$this->define_column('content', 'Content')->invisible()->validation()->fn('trim')->required('Please provide the post content');
			$this->define_column('is_published', 'Published');
			$this->define_column('comments_allowed', 'Comments Allowed');
			$this->define_column('published_date', 'Date Published');
			$this->define_column('comment_num', 'Total Comments');
			$this->define_column('new_comment_num', 'New Comments');
			$this->define_column('email_subscribers', 'Email Subscribers');
			
			$this->defined_column_list = array();
			Backend::$events->fireEvent('blog:onExtendPostModel', $this, $context);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}
		
		public function define_form_fields($context = null)
		{
			if ($context != 'preview')
			{
				$this->add_form_field('title', 'left')->tab('Post')->comment('The post title will be shown in the post lists and on the post page.', 'above');
				$this->add_form_field('url_title', 'right')->tab('Post')->comment('Post URL title, to reference the post in URLs, for example: my_first_post', 'above');
				$this->add_form_field('is_published', 'left')->tab('Post');
				$this->add_form_field('published_date', 'right')->tab('Post');
				$this->add_form_field('comments_allowed')->tab('Post');
				$this->add_form_field('categories')->tab('Categories');

				$this->add_form_field('description')->renderAs(frm_textarea)->size('small')->tab('Content');
				$content_field = $this->add_form_field('content')->renderAs(frm_html)->size('huge')->tab('Content');
				
				$editor_config = System_HtmlEditorConfig::get('blog', 'blog_post_content');
				$editor_config->apply_to_form_field($content_field);
				$content_field->htmlPlugins .= ',save,fullscreen,inlinepopups';
				$content_field->htmlButtons1 = 'save,separator,'.$content_field->htmlButtons1.',separator,fullscreen';
				$content_field->saveCallback('save_code');
				$content_field->htmlFullWidth = true;
			} else 
			{
				$this->add_form_field('title', 'left');
				$this->add_form_field('url_title', 'right');
				$this->add_form_field('description');
				$this->add_form_field('is_published', 'left');
				$this->add_form_field('comments_allowed', 'right');
				$this->add_form_field('categories');
			}
			
			Backend::$events->fireEvent('blog:onExtendPostForm', $this, $context);
			foreach ($this->api_added_columns as $column_name)
			{
				$form_field = $this->find_form_field($column_name);
				if ($form_field)
					$form_field->optionsMethod('get_added_field_options');
			}
		}
		
		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$result = Backend::$events->fireEvent('blog:onGetPostFieldOptions', $db_name, $current_key_value);
			foreach ($result as $options)
			{
				if (is_array($options) || (strlen($options && $current_key_value != -1)))
					return $options;
			}
			
			return false;
		}
		
		public function before_save($deferred_session_key = null)
		{
			if ($this->is_published && !$this->published_date)
				$this->published_date = Phpr_DateTime::now();
		}
		
		public static function get_rss($feed_name, $feed_description, $feed_url, $post_url, $category_url, $blog_url, $post_number = 20, $exclude_category_ids = array())
		{
			$posts = Blog_Post::create();
			$posts->where('is_published is not null and is_published=1');
			$posts->order('blog_posts.created_at desc');
			
			if ($exclude_category_ids)
			{
				$posts->where('(not exists (select * from blog_categories, blog_posts_categories where blog_categories.id=blog_posts_categories.blog_category_id and blog_posts_categories.blog_post_id=blog_posts.id and blog_categories.id in (?)))', array($exclude_category_ids));
			}
			
			$posts = $posts->limit($post_number)->find_all();
			
			$rss = new Core_Rss( $feed_name, $blog_url, $feed_description, $feed_url );
			foreach ( $posts as $post )
			{
				$link = $post_url.$post->url_title;

				$category_links = array();
				foreach ($post->categories as $category)
				{
					$cat_url = $category_url.$category->url_name;
					$category_links[] = "<a href=\"$cat_url\">".h($category->name)."</a>";
				}

				$category_str = "<p>Posted in: ".implode(', ', $category_links)."</p>";

				$rss->add_entry( $post->title,
					$link,
					$post->id,
					$post->published_date,
					strlen($post->description) ? '<p>'.$post->description.'</p>'.$category_str : $post->content.$category_str,
					$post->published_date,
					$post->created_user_name,
					$post->content.$category_str );
			}

			return $rss->to_xml();
		}
		
		public static function get_comments_rss($feed_name, $feed_description, $feed_url, $post_url, $category_url, $blog_url, $comment_number = 20, $exclude_category_ids = array())
		{
			$status = Blog_Comment_Status::create()->find_by_code(Blog_Comment_Status::status_approved);
			$comments = Blog_Comment::create()->where('status_id=?', $status->id)->order('created_at desc')->limit($comment_number);
			
			if ($exclude_category_ids)
			{
				$comments->where('(not exists (select * from blog_posts, blog_categories, blog_posts_categories where blog_posts.id=blog_comments.post_id and  blog_categories.id=blog_posts_categories.blog_category_id and blog_posts_categories.blog_post_id=blog_posts.id and blog_categories.id in (?)))', array($exclude_category_ids));
			}
			
			$comments = $comments->find_all();
			
			$rss = new Core_Rss( $feed_name, $blog_url, $feed_description, $feed_url );
			foreach ( $comments as $comment )
			{
				$link = $post_url.$comment->displayField('post_url').'#comment'.$comment->id;

				$rss->add_entry( $comment->displayField('post'),
					$link,
					'comment_'.$comment->id,
					$comment->created_at,
					'<p>Comment by '.h($comment->author_name).': <blockquote>'.$comment->content_html.'</blockquote>',
					$comment->created_at,
					$comment->author_name,
					'<p>Comment by '.h($comment->author_name).': <blockquote>'.$comment->content_html.'</blockquote>' );
			}

			return $rss->to_xml();
		}
		
		public static function eval_posts_statistics()
		{
			return Db_DbHelper::object(
				"select
					(select count(*) from blog_posts) as post_num,
					(select count(*) from blog_posts where published_date is not null) as published_num
				"
			);
		}
		
		public static function list_recent_posts($number = 10)
		{
			$posts = Blog_Post::create();
			$posts->where('is_published is not null and is_published=1');
			$posts->order('blog_posts.published_date desc');
			$posts->limit($number);
			return $posts->find_all();
		}
		
		public function after_modify($operation, $deferred_session_key)
		{
			Blog_Module::update_blog_content_version();
		}
	}

?>