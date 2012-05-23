<?php

	class Blog_Actions extends Cms_ActionScope
	{
		public function archive()
		{
			$posts = Blog_Post::create();
			$posts->where('is_published is not null and is_published=1');
			$posts->order('blog_posts.published_date desc');
			
			$this->data['posts'] = $posts;
		}
		
		public function category()
		{
			$this->data['category'] = null;

			$url_name = $this->request_param(0);
			if (!strlen($url_name))
				return;

			$category = Blog_Category::create()->find_by_url_name($url_name);
			if (!$category)
				return;

			$this->data['category'] = $category;
			$this->data['posts'] = $category->posts_list;
		}
		
		public function post()
		{
			$this->data['post'] = null;
			$this->data['comment_success'] = false;
			
			$url_title = $this->request_param(0);
			if (!strlen($url_title))
				return;

			$post = Blog_Post::create()->find_by_url_title($url_title);
			if (!$post)
				return;

			$this->page->title = $post->title;
			$this->data['post'] = $post;
			
			if (post('send_comment_action'))
				$this->on_postComment();
		}
		
		public function on_postComment()
		{
			$this->data['comment_success'] = false;

			$url_title = $this->request_param(0);
			if (!strlen($url_title))
				return;

			$post = Blog_Post::create()->find_by_url_title($url_title);
			if (!$post)
				return;

			$comment = Blog_Comment::create();
			$comment->init_columns_info('front_end');
			$comment->validation->focusPrefix = null;
			$comment->validation->getRule('content')->focusId('comment_content');
			$comment->post_id = $post->id;
			$comment->save($_POST);
			
			$this->data['comment_success'] = true;
			
			$redirect = post('redirect');
			if ($redirect)
				Phpr::$response->redirect($redirect);
		}
		
		public function rss()
		{
		}
		
		public function unsubscribe_new_comments()
		{
			$post_id = $this->request_param(0);
			if (!strlen($post_id))
				return;

			$email_hash = $this->request_param(1);
			if (!strlen($email_hash))
				return;
				
			Blog_Comment_Subscriber::unsubscribe($post_id, $email_hash);
		}
	}
	
?>