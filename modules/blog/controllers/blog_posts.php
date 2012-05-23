<?

	class Blog_Posts extends Backend_Controller
	{
		public $implement = 'Db_ListBehavior, Db_FormBehavior, Backend_FileBrowser';
		public $list_model_class = 'Blog_Post';
		public $list_record_url = null;
		public $list_custom_body_cells;
		public $list_custom_head_cells;
		public $list_no_pagination;
		public $list_cell_partial = false;
		public $list_custom_prepare_func = null;
		public $list_handle_row_click = false;
		public $listColumns;

		public $form_preview_title = 'Post';
		public $form_create_title = 'New Post';
		public $form_edit_title = 'Edit Post';
		public $form_model_class = 'Blog_Post';
		public $form_not_found_message = 'Post not found';
		public $form_redirect = null;
		public $form_create_save_redirect;
		public $form_edit_save_redirect;
		public $form_delete_redirect;
		public $form_flash_id = 'form_flash';
		
		public $form_edit_save_flash = 'The post has been successfully saved';
		public $form_create_save_flash = 'The post has been successfully added';
		public $form_edit_delete_flash = 'The post has been successfully deleted';
		public $form_edit_save_auto_timestamp = true;
		public $enable_concurrency_locking = true;

		public $filebrowser_onFileClick = null;
		public $filebrowser_dirs = array(
			'resources'=>array('path'=>'/resources', 'root_upload'=>false)
		);
		public $filebrowser_absoluteUrls = true;
		
		public $list_search_enabled = true;
		public $list_search_fields = array('@title', '@description');
		public $list_search_prompt = 'find posts by title or description';
		public $file_browser_file_list_class = 'ui-layout-anchor-window-bottom offset-24';

		protected $required_permissions = array('blog:manage_posts_and_categories', 'blog:manage_comments');

		protected $globalHandlers = array('onSave');

		public function __construct()
		{
			$this->filebrowser_dirs['resources']['path'] = '/'.Cms_SettingsManager::get()->resources_dir_path;
			
			parent::__construct();
			$this->app_tab = 'blog';
			$this->app_module_name = 'Blog';
			
			if ($this->currentUser)
			{
				$this->viewData['can_manage_posts'] = $this->currentUser->get_permission('blog', 'manage_posts_and_categories');
				$this->viewData['can_manage_comments'] = $this->currentUser->get_permission('blog', 'manage_comments');
			}

			if (Phpr::$router->action == 'edit')
			{
				$referer = Phpr::$router->param('param2');
				if ($referer != 'list')
					$this->form_edit_save_redirect = url('/blog/posts/preview/%s').'?'.uniqid();
				else
					$this->form_edit_save_redirect = url('/blog/posts/').'?'.uniqid();
			}

			$this->list_record_url = url('/blog/posts/preview/');
			$this->form_redirect = url('/blog/posts');
			$this->form_create_save_redirect = url('/blog/posts/edit/%s/list').'?'.uniqid();
			$this->form_delete_redirect = url('/blog/posts');
			$this->app_page = 'posts';
			
			if (post('comment_list_mode'))
			{
				$this->list_model_class = 'Blog_Comment';
				$this->listColumns = array('created_at', 'status', 'author_name', 'author_email', 'content');
				$this->list_search_enabled = false;
				
				$this->list_custom_prepare_func = 'prepare_comment_list';
				$this->list_record_url = null;
				$this->list_no_setup_link = true;
				$this->list_items_per_page = 10000;
				$this->list_no_data_message = 'This post is not commented';
				$this->list_record_url = url('blog/comments/preview/');
				$this->list_custom_body_cells = false;
				$this->list_custom_head_cells = false;
				$this->list_no_pagination = true;
				$this->list_cell_partial = PATH_APP.'/modules/blog/controllers/blog_posts/_comment_row_controls.htm';
			}
		}

		public function listGetRowClass($model)
		{
			if ($model instanceof Blog_Comment)
			{
				$class = null;
				if ($model->blog_owner_comment)
					$class = 'safe ';
				
				if ($model->status_code == Blog_Comment_Status::status_deleted)
					return $class.'deleted';
				if ($model->status_code == Blog_Comment_Status::status_new)
					return $class.'new';
					
				return $class;
			}
		}

		public function index()
		{
			$this->app_page_title = 'Posts';
		}
		
		protected function index_onResetFilters()
		{
			$this->listCancelSearch();
			Phpr::$response->redirect(url('blog/posts'));
		}

		protected function eval_posts_statistics()
		{
			return Blog_Post::eval_posts_statistics();
		}

		protected function onSave($id)
		{
			Phpr::$router->action == 'create' ? $this->create_onSave() : $this->edit_onSave($id);
		}
		
		public function prepare_comment_list()
		{
			$id = Phpr::$router->param('param1');
			return Blog_Comment::create()->where('post_id=?', $id);
		}
		
		protected function preview_onSetCommentStatus($post_id)
		{
			try
			{
				$comment = $this->find_comment(post('id'));
				$comment->set_status(post('status'));
				$comment->save();
				
				$this->viewData['form_model'] = $this->formFindModelObject($post_id);
				$this->renderPartial('comment_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function preview_onDeleteComment($post_id)
		{
			$this->set_comment_status($post_id, Blog_Comment_Status::status_deleted);
		}
		
		private function find_comment($id)
		{
			if (!strlen($id))
				throw new Phpr_ApplicationException('Comment not found');
				
			$obj = Blog_Comment::create()->where('id=?', $id)->find();
			if (!$obj)
				throw new Phpr_ApplicationException('Comment not found');
				
			return $obj;
		}
		
		public function edit_formBeforeRender()
		{
			if (!$this->currentUser->get_permission('blog', 'manage_posts_and_categories'))
				Phpr::$response->redirect(url('/'));
		}
		
		public function create_formBeforeRender()
		{
			if (!$this->currentUser->get_permission('blog', 'manage_posts_and_categories'))
				Phpr::$response->redirect(url('/'));
		}
		
		public function formAfterCreateSave($page, $session_key)
		{
			if (post('create_close'))
			{
				$this->form_create_save_redirect = url('/blog/posts').'?'.uniqid();
			}
		}
		
		public function formAfterEditSave($model, $session_key)
		{
			$model = $this->viewData['form_model'] = Blog_Post::create()->find($model->id);
			$model->updated_user_name = $this->currentUser->name;
			
			$this->renderMultiple(array(
				'form_flash'=>flash(),
				'object-summary'=>'@_post_summary'
			));
			
			return true;
		}
	}

?>