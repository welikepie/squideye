<?php

	class Blog_Comment_Subscriber extends Db_ActiveRecord
	{
		public $table_name = 'blog_comment_subscribers';
		
		public static function create()
		{
			return new self();
		}
		
		public function isSubscribed($post_id, $email)
		{
			return Db_DbHelper::scalar(
				"select count(*) from blog_comment_subscribers where post_id=:post_id and email=:email",
				array(
					'post_id'=>$post_id,
					'email'=>$email
				)
			);
		}
		
		public function before_save($deferred_session_key = null)
		{
			$this->email_hash = md5($this->email);
		}
		
		public static function send_notifications($post, $comment)
		{
			$post_page = Cms_Page::create()->find_by_action_reference('blog:post');
			if (!$post_page)
				return;

			$post_page_url = Phpr::$request->getRootUrl().$post_page->url.'/'.$post->url_title;

			$template = System_EmailTemplate::create()->find_by_code('blog:new_comment_notification');
			if (!$template)
				return;

			$template_text = $template->content;

			$template_text = str_replace('{post_name_and_url}', '<a href="'.$post_page_url.'">'.h($post->title).'</a>', $template_text);
			$template_text = str_replace('{post_name}', h($post->title), $template_text);
			
			$template_text = str_replace('{comment_author_name}', h($comment->author_name), $template_text);
			
			$unsubscribe_page = Cms_Page::create()->find_by_action_reference('blog:unsubscribe_new_comments');
			if (!$unsubscribe_page)
				return;
				
			$template_text  = str_replace('{comment_text}', $comment->content_html, $template_text);

			$subscribers = self::create()->where('post_id=?', $post->id)->find_all();
			foreach ($subscribers as $subscriber)
			{
				if ($subscriber->email == $comment->author_email)
					continue;
				
				$unsubscribe_url = Phpr::$request->getRootUrl().$unsubscribe_page->url.'/'.$post->id.'/'.md5($subscriber->email);
				$unsubscribe_url = '<a href="'.$unsubscribe_url.'">'.$unsubscribe_url.'</a>';
				
				$template_text = str_replace('{comments_unsubscribe_link}', $unsubscribe_url, $template_text);
				$template_text  = str_replace('{comments_subscriber_name}', $subscriber->subscriber_name, $template_text);
				
				$template->send($subscriber->email, $template_text, $subscriber->subscriber_name);
			}
		}
		
		public static function unsubscribe($post_id, $email_hash)
		{
			$obj = self::create()->where('post_id=?', $post_id);
			$obj = $obj->where('email_hash=?', $email_hash)->find();
			if ($obj)
				$obj->delete();
		}
	}

?>