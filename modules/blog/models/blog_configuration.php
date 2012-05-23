<?

	class Blog_Configuration extends Core_Configuration_Model
	{
		const notify_nobody = 1;
		const notify_authors = 2;
		const notify_all = 3;
		
		public $record_code = 'blog_configuration';
		
		public static function create()
		{
			$configObj = new Blog_Configuration();
			return $configObj->load();
		}
		
		protected function build_form()
		{
			$this->add_field('comment_name_required', 'Comment author name is required', 'full', db_bool)->tab('Comments')->comment("If this checkbox is checked, visitors will be forced to specify their name in the post comment form.");
			$this->add_field('comment_email_required', 'Comment author email is required', 'full', db_bool)->tab('Comments')->comment("Use this checkbox to force visitors to specify email in the post comment form.");

			$this->add_field('comment_interval', 'Comment Interval', 'full', db_number)->tab('Comments')->comment("Minimum interval between comments, in  minutes, from a same IP address. Leave the field empty to disable this feature.", "above");
			
			$this->add_field('comment_notifications_rule', 'New comment notifications', 'full', db_number)->tab('Notifications')->renderAs(frm_radio)->comment('Please choose which users should receive notifications about new blog comments.', 'above');
		}
		
		public function get_comment_notifications_rule_options()
		{
			return array(
				self::notify_nobody=>array('Nobody'=>'Do not send new comment notifications.'),
				self::notify_authors=>array('Authors only'=>'Send new comment notifications only to post author.'),
				self::notify_all=>array('All users'=>'Notify all users who have permissions to receive blog notifications.')
			);
		}
		
		protected function init_config_data()
		{
			$this->comment_notifications_rule = self::notify_nobody;
		}
	}

?>