<?

	class Blog_Comments extends Backend_Controller
	{
		public $implement = 'Db_FormBehavior';

		public $form_preview_title = 'Comment';
		public $form_create_title = 'New Comment';
		public $form_edit_title = 'Edit Comment';
		public $form_model_class = 'Blog_Comment';
		public $form_not_found_message = 'Comment not found';
		public $form_redirect = null;
		public $form_create_save_redirect = null;
		
		public $form_edit_save_flash = 'Comment has been successfully saved';
		public $form_create_save_flash = 'Comment has been successfully added';
		public $form_edit_delete_flash = 'Comment has been successfully deleted';
		public $form_edit_save_auto_timestamp = true;

		protected $required_permissions = array('blog:manage_posts_and_categories', 'blog:manage_comments');

		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'blog';
			$this->app_module_name = 'Blog';
			$this->app_page = 'posts';
			$this->form_redirect = url('/blog/comments/preview/%s');
			$this->viewData['can_manage_comments'] = $this->currentUser->get_permission('blog', 'manage_comments');
			
			if (post('create_mode'))
			{
				$this->form_create_save_redirect = url('/blog/posts/preview/'.Phpr::$router->param('param1')).'#comments';
			}
		}

		protected function preview_onSetCommentStatus($id)
		{
			try
			{
				$comment = $this->formFindModelObject($id);
				$comment->set_status(post('status'));
				$comment->save();

				Phpr::$response->redirect(url('blog/posts/preview/'.$comment->post_id.'?'.uniqid()).'#comment_'.$comment->id);
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public function create_formBeforeRender($model)
		{
			if (!$this->currentUser->get_permission('blog', 'manage_comments'))
				Phpr::$response->redirect(url('/'));
			
			$post_id = Phpr::$router->param('param1');
			if (!strlen($post_id))
				throw new Phpr_ApplicationException('Post not found');
				
			$post = Blog_Post::create()->find($post_id);
			if (!$post)
				throw new Phpr_ApplicationException('Post not found');
			
			$model->set_status(Blog_Comment_Status::status_approved);
			$model->author_name = $this->currentUser->firstName.' '.$this->currentUser->lastName;
		}
		
		public function edit_formBeforeRender()
		{
			if (!$this->currentUser->get_permission('blog', 'manage_comments'))
				Phpr::$response->redirect(url('/'));
		}
		
		public function formBeforeCreateSave($model)
		{
			$model->blog_owner_comment = 1;
			$model->post_id = Phpr::$router->param('param1');
		}
	}

?>