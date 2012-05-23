<?php

	class Blog_Module extends Core_ModuleBase
	{
		/**
		 * Creates the module information object
		 * @return Core_ModuleInfo
		 */
		protected function createModuleInfo()
		{
			return new Core_ModuleInfo(
				"Blog",
				"Adds blog features to your LemonStand store",
				"Limewheel Creative Inc." );
		}

		/**
		 * Returns a list of the module back-end GUI tabs.
		 * @param Backend_TabCollection $tabCollection A tab collection object to populate.
		 * @return mixed
		 */
		public function listTabs($tabCollection)
		{
			$user = Phpr::$security->getUser();
			$tabs = array(
				'posts'=>array('posts', 'Posts', array('manage_posts_and_categories', 'manage_comments')),
				'categories'=>array('categories', 'Categories', 'manage_posts_and_categories'),
				'settings'=>array('settings', 'Settings', 'manage_settings')
			);

			$first_tab = null;
			foreach ($tabs as $tab_id=>$tab_info)
			{
				if (($tabs[$tab_id][3] = $user->get_permission('blog', $tab_info[2])) && !$first_tab)
					$first_tab = $tab_info[0];
			}

			if ($first_tab)
			{
				$tab = $tabCollection->tab('blog', 'Blog', $first_tab, 60);
				foreach ($tabs as $tab_id=>$tab_info)
				{
					if ($tab_info[3])
						$tab->addSecondLevel($tab_id, $tab_info[1], $tab_info[0]);
				}
			}
		}
		
		/**
		 * Builds user permissions interface
		 * For drop-down and radio fields you should also add methods returning 
		 * options. For example, of you want to have "Access Level" drop-down:
		 * public function get_access_level_options();
		 * This method should return array with keys corresponding your option identifiers
		 * and values corresponding its titles.
		 * 
		 * @param $host_obj ActiveRecord object to add fields to
		 */
		public function buildPermissionsUi($host_obj)
		{
			$host_obj->add_field($this, 'notify_blog_comments', 'Notify user about new blog comments')->renderAs(frm_checkbox)->comment('Check this checkbox if you want this user to be notified about new blog post comments.', 'above');
			
			$host_obj->add_field($this, 'manage_posts_and_categories', 'Manage posts and categories', 'left')->renderAs(frm_checkbox)->comment('Create or update blog posts and categories.', 'above');
			$host_obj->add_field($this, 'manage_settings', 'Manage blog settings', 'right')->renderAs(frm_checkbox)->comment('Manage commenting rules and notifications settings.', 'above');
			$host_obj->add_field($this, 'manage_comments', 'Manage comments')->renderAs(frm_checkbox)->comment('Create or delete blog post comments.', 'above');
		}

		/**
		 * Returns a list of dashboard reports in format
		 * array('report_code'=>array('partial'=>'partial_name.htm', 'name'=>'Report Name')).
		 * Partials must be placed to the module dashboard directory:
		 * /modules/cms/dashboard
		 */
		public function listDashboardReports()
		{
			return array(
				'recent_comments'=>array('partial'=>'recentcomments_report.htm', 'name'=>'Recent Blog Comments')
			);
		}
		
		/**
		 * Returns a list of HTML Editor configurations used by the module
		 * The method must return an array of configuration codes and descriptions:
		 * array('blog_post_content'=>'Blog post')
		 * @return array
		 */
		public function listHtmlEditorConfigs()
		{
			return array('blog_post_content'=>'Blog post content');
		}

		public function checkTemplateDeletion($template)
		{
			if ($template->code == 'blog:new_comment_notification')
				throw new Phpr_ApplicationException("This template is used by the Blog module and cannot be deleted.");
		}		
		
		/**
		 * Returns a list of email template variables provided by the module.
		 * The method must return an array of section names, variable names, 
		 * descriptions and demo-values:
		 * array('Shop variables'=>array(
		 * 	'order_total'=>array('Outputs order total value', '$99.99')
		 * ))
		 * @return array
		 */
		public function listEmailVariables()
		{
			return array(
				'Blog variables'=>array(
					'comments_subscriber_name'=>array('Outputs post comments subscriber name', 'John Smith'),
					'post_name_and_url'=>array('Outputs a post title and URL', '<a href="#">Post Title</a>'),
					'post_name'=>array('Outputs a post title', 'Post Title'),
					'comment_author_name'=>array('Outputs a blog comment author name', 'Danny'),
					'comment_text'=>array('Outputs a blog comment text', '<p>Blog comment text</p>'),
					'comments_unsubscribe_link'=>array('Outputs post comments notifications unsubscribe link', '<a href="#">http://some.hostname.com/unsubscribe</a>')
				)
			);
		}
		
		/**
		 * Blog cache version management
		 */
		
		public static function get_blog_content_version()
		{
			return Db_ModuleParameters::get( 'blog', 'content_version', 0 );
		}
		
		public static function update_blog_content_version()
		{
			Db_ModuleParameters::set( 'blog', 'content_version', time() );
		}
	}
?>