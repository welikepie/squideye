<?php

	class Blog_Comment extends Db_ActiveRecord
	{
		public $table_name = 'blog_comments';

		public $belongs_to = array(
			'status'=>array('class_name'=>'Blog_Comment_Status', 'foreign_key'=>'status_id'),
			'post'=>array('class_name'=>'Blog_Post', 'foreign_key'=>'post_id'),
		);
		
		public $calculated_columns = array(
			'status_code'=>array('sql'=>'status_calculated_join.code', 'type'=>db_varchar)
		);
		
		public $custom_columns = array(
			'subscribe_to_notifications'=>db_bool
		);

		protected $api_added_columns = array();

		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$configObj = Blog_Configuration::create();
			
			$this->define_column('created_at', 'Added')->order('desc');
			$this->define_relation_column('status', 'status', 'Status', db_varchar, '@name');
			$this->define_relation_column('post', 'post', 'Post', db_varchar, '@title');
			$this->define_relation_column('post_url', 'post', 'Post URL', db_varchar, '@url_title');
			
			$field = $this->define_column('author_name', 'Author')->validation()->fn('trim');
			if ($configObj->comment_name_required)
				$field->required("Please enter your name.");
			
			$field = $this->define_column('author_email', 'Email')->validation()->fn('trim')->fn('mb_strtolower')->email('Please specify a valid email address.');
			if ($configObj->comment_email_required)
				$field->required("Please specify your email address.");

			$this->define_column('author_url', 'Website URL')->validation()->fn('trim');
			$this->define_column('content', 'Comment')->validation()->fn('trim')->required("Please enter the comment.");
			$this->define_column('author_ip', 'IP');
			
			$this->defined_column_list = array();
			Backend::$events->fireEvent('blog:onExtendCommentModel', $this, $context);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}
		
		public function define_form_fields($context = null)
		{
			if ($context != 'preview')
			{
				$this->add_form_field('status');
			} else
			{
				$this->add_form_field('status', 'left')->previewNoRelation();
				$this->add_form_field('author_ip', 'right');
			}
			
			$this->add_form_field('author_name', 'left');
			$this->add_form_field('author_email', 'right');
			$this->add_form_field('author_url');
			$this->add_form_field('content');

			Backend::$events->fireEvent('blog:onExtendCommentForm', $this, $context);
			foreach ($this->api_added_columns as $column_name)
			{
				$form_field = $this->find_form_field($column_name);
				if ($form_field)
					$form_field->optionsMethod('get_added_field_options');
			}
		}
		
		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$result = Backend::$events->fireEvent('blog:onGetCommentFieldOptions', $db_name, $current_key_value);
			foreach ($result as $options)
			{
				if (is_array($options) || (strlen($options && $current_key_value != -1)))
					return $options;
			}
			
			return false;
		}
		
		public function before_create($deferred_session_key = null)
		{
			$this->author_ip = Phpr::$request->getUserIp();
			if (!$this->blog_owner_comment)
			{
				$this->status_id = Blog_Comment_Status::create()->find_by_code('new')->id;
				$configObj = Blog_Configuration::create();
				if ($configObj->comment_interval)
				{
					$current_time = Phpr_DateTime::now();
					
					$bind = array('ip'=>$this->author_ip, 'current_time'=>$current_time, 'time_interval'=>$configObj->comment_interval);
					$post_allowed = Db_DbHelper::scalar("select ifnull(DATE_ADD((select max(created_at) from blog_comments where author_ip=:ip), interval :time_interval minute) <= :current_time, 1)", $bind);
					if (!$post_allowed)
						throw new Phpr_ApplicationException("Please allow {$configObj->comment_interval} minute(s) between posts.");
				}
			}

		}
		
		public function after_create()
		{
			/*
			 * Notify users about new comment
			 */

			$configObj = Blog_Configuration::create();
			if (!$this->blog_owner_comment && $configObj->comment_notifications_rule != Blog_Configuration::notify_nobody)
			{
				$viewData = array();
				$post = Blog_Post::create()->find($this->post_id);
				if (!$post)
					return;
					
				$viewData['post'] = $post;
				$viewData['comment'] = $this;
				
				if ($configObj->comment_notifications_rule == Blog_Configuration::notify_authors)
				{
					$user = Users_User::create()->find($this->created_user_id);
					if ($user && $user->get_permission('blog', 'notify_blog_comments'))
						Core_Email::sendOne('blog', 'new_comment', $viewData, 'New comment in blog', $this->created_user_id);
				} elseif ($configObj->comment_notifications_rule == Blog_Configuration::notify_all)
				{
					$users = Users_User::list_users_having_permission('blog', 'notify_blog_comments');
					Core_Email::sendToList('blog', 'new_comment', $viewData, 'New comment in blog', $users);
				}
			}
			
			if ($this->subscribe_to_notifications)
			{
				$obj = Blog_Comment_Subscriber::create();

				if (!$obj->isSubscribed($this->post_id, $this->author_email))
				{
					$obj->email = $this->author_email;
					$obj->post_id = $this->post_id;
					$obj->subscriber_name = $this->author_name;
					$obj->save();
				}
			}
		}
		
		public function before_save($deferred_session_key = null)
		{
			if (strlen($this->content))
				$this->content_html = Phpr_Html::paragraphize($this->content);
		}
		
		public function after_save()
		{
			if (
					$this->status->code == Blog_Comment_Status::status_approved &&
					(!isset($this->fetched['status_id']) ||
					$this->status_id != $this->fetched['status_id'])
			)
			{
				Blog_Comment_Subscriber::send_notifications($this->post, $this);
			}
		}

		public function set_status($status_code)
		{
			$this->status_id = Blog_Comment_Status::create()->find_by_code($status_code)->id;
		}
		
		public static function getRecentComments($number = 5)
		{
			$obj = self::create();
			$obj->order('created_at desc');
			$obj->limit($number);

			return $obj->find_all();
		}
		
		public function getUrlFormatted()
		{
			if (!preg_match(',^(http://)|(https://),', $this->author_url))
				return 'http://'.$this->author_url;
				
			return $this->author_url;
		}
		
		public function after_modify($operation, $deferred_session_key)
		{
			Blog_Module::update_blog_content_version();
		}
	}

?>