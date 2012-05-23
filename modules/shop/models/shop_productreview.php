<?php

	class Shop_ProductReview extends Db_ActiveRecord
	{
		public $table_name = 'shop_product_reviews';
		
		const status_new = 'new';
		const status_approved = 'approved';

		public static $moderation_statuses = array(
			'new'=>'New',
			'approved'=>'Approved'
		);

		public $belongs_to = array(
			'customer_link'=>array('class_name'=>'Shop_Customer', 'foreign_key'=>'created_customer_id'),
			'product_link'=>array('class_name'=>'Shop_Product', 'foreign_key'=>'prv_product_id')
		);
		
		public $calculated_columns = array(
			'review_status'=>array('sql'=>"if(prv_moderation_status = 'new', 'New', 'Approved')"),
			'author'=>'review_author_name',
			'rating'=>'prv_rating',
			'title'=>'review_title'
		);

		public $prv_moderation_status = 'new';
		
		protected $api_added_columns = array();

		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$this->define_column('review_status', 'Status');
			$this->define_column('prv_moderation_status', 'Status')->invisible();
			$this->define_relation_column('product_link', 'product_link', 'Product', db_varchar, '@name');
			$this->define_column('prv_rating', 'Rating')->type(db_text);
			$this->define_column('created_at', 'Created At')->dateFormat('%x %H:%M')->order('desc');
			$this->define_column('customer_ip', 'Author IP')->defaultInvisible();
			
			$this->define_column('review_title', 'Title')->validation()->fn('trim')->required("Please specify the review title");
			$this->define_column('review_author_name', 'Author')->validation()->fn('trim')->method('validate_author_name');
			$this->define_column('review_author_email', 'Author Email')->validation()->fn('trim')->email(true, 'Please specify a valid email address')->method('validate_author_email');
			$this->define_column('review_text', 'Review Text')->invisible()->validation()->fn('trim')->required("Please enter the review text");
			
			$this->defined_column_list = array();
			Backend::$events->fireEvent('shop:onExtendProductReviewModel', $this, $context);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('prv_moderation_status')->renderAs(frm_dropdown)->tab('Review');
			$this->add_form_field('review_title')->tab('Review');
			$this->add_form_field('review_author_name', 'left')->tab('Review');
			$this->add_form_field('review_author_email', 'right')->tab('Review');
			$this->add_form_field('prv_rating')->tab('Review')->renderAs(frm_dropdown)->emptyOption('<no rating specified>');
			
			$this->add_form_field('review_text')->tab('Review');
			
			Backend::$events->fireEvent('shop:onExtendProductReviewForm', $this, $context);
			foreach ($this->api_added_columns as $column_name)
			{
				$form_field = $this->find_form_field($column_name);
				if ($form_field)
					$form_field->optionsMethod('get_added_field_options');
			}
		}

		public function get_prv_moderation_status_options($key_value = -1)
		{
			return self::$moderation_statuses;
		}
		
		public function get_prv_rating_options($key_value = -1)
		{
			return array(
				1 => '1 star',
				2 => '2 stars',
				3 => '3 stars',
				4 => '4 stars',
				5 => '5 stars',
			);
		}
		
		public function get_rating_name()
		{
			if (!$this->prv_rating)
				return null;
				
			$options = $this->get_prv_rating_options();
			if (!array_key_exists($this->prv_rating, $options))
				return null;
				
			return $options[$this->prv_rating];
		}
		
		public function before_create($deferred_session_key = null) 
		{
			$this->customer_ip = Phpr::$request->getUserIp();
			Backend::$events->fireEvent('shop:onProductReviewBeforeCreate', $this);
		}
		
		public function after_create()
		{
			$config = Shop_ReviewsConfiguration::create();

			if ($config->send_notifications)
			{
				try
				{
					$template = System_EmailTemplate::create()->find_by_code('shop:product_review_internal');
					if ($template)
					{
						$customer_email = $email = trim($this->review_author_email);
						if (!strlen($email))
							$email = 'email is not specified';

						$product = Shop_Product::create()->find($this->prv_product_id);
						$rating_name = $this->get_rating_name();
						if (!strlen($rating_name))
							$rating_name = '<no rating provided>';

						$review_edit_url = Phpr::$request->getRootUrl().url('shop/reviews/edit/'.$this->id.'?'.uniqid());
						$message = $this->set_email_variables($template->content, $rating_name, $review_edit_url, $product, $email);
						$template->subject = $this->set_email_variables($template->subject, $rating_name, $review_edit_url, $product, $email);
						
						$users = Users_User::list_users_having_permission('shop', 'manage_products');
						
						$template->send_to_team($users, $message, null, null, $customer_email, $this->review_author_name);
					}
				}
				catch (exception $ex) {}
			}
			
			Backend::$events->fireEvent('shop:onProductReviewAfterCreate', $this);
		}
		
		protected function set_email_variables($message, $rating_name, $review_edit_url, $product, $email)
		{
			$message = str_replace('{review_author_name}', h($this->review_author_name), $message);
			$message = str_replace('{review_author_email}', h($email), $message);
			$message = str_replace('{review_product_name}', h($product->name), $message);
			$message = str_replace('{review_text}', nl2br(h($this->review_text)), $message);
			$message = str_replace('{review_title}', h($this->review_title), $message);
			$message = str_replace('{review_rating}', h($rating_name), $message);
			$message = str_replace('{review_edit_url}', h($review_edit_url), $message);
			
			return $message;
		}
		
		public static function create_review($product, $customer, $review_data)
		{
			if (!$product)
				throw new Phpr_ApplicationException('Product not found');
				
			if ($product->grouped)
				$product = $product->master_grouped_product;

			if (!$product)
				throw new Phpr_ApplicationException('Product not found');
			
			$config = Shop_ReviewsConfiguration::create();
				
			if ($config->no_duplicate_reviews && self::ip_customer_review_exists(Phpr::$request->getUserIp(), $customer, $product->id))
			{
				$message = trim($config->duplicate_review_message);
				if (!strlen($message))
					$message = 'Posting multiple reviews for a single product is not allowed.';

				throw new Phpr_ApplicationException($message);
			}
			
			$rating = isset($review_data['rating']) ? $review_data['rating'] : null;
			$review_data['prv_rating'] = $rating;
			
			if ($config->rating_required && !$rating)
				throw new Phpr_ApplicationException('Please specify the product rating.');
				
			if ($rating > 5)
				throw new Phpr_ApplicationException('Product rating cannot be more than 5.');

			$obj = self::create();
			$obj->validation->focusPrefix = null;
			$obj->prv_product_id = $product->id;
			$obj->created_customer_id = $customer ? $customer->id : null;
			
			if ($customer)
			{
				$review_data['review_author_name'] = $customer->get_display_name();
				$review_data['review_author_email'] = $customer->email;
			}

			$obj->save($review_data);
		}
		
		public function validate_author_name($name, $value)
		{
			if (!$this->created_customer_id && !strlen(trim($value)))
				$this->validation->setError('Please specify your name', $name, true);
				
			return true;
		}
		
		public function validate_author_email($name, $value)
		{
			$config = Shop_ReviewsConfiguration::create();
			if (!$config->email_required)
				return true;
			
			if (!$this->created_customer_id && !strlen(trim($value)))
				$this->validation->setError('Please specify your email address', $name, true);
				
			return true;
		}
		
		public static function ip_customer_review_exists($ip, $customer, $product_id)
		{
			$customer_id = $customer ? $customer->id : null;
			
			$bind = array(
				'customer_ip'=>$ip, 
				'created_customer_id'=>$customer_id,
				'prv_product_id'=>$product_id);

			if ($customer_id)
				return Db_DbHelper::scalar('select count(*) from shop_product_reviews where created_customer_id=:created_customer_id and prv_product_id=:prv_product_id', $bind);
			else
				return Db_DbHelper::scalar('select count(*) from shop_product_reviews where customer_ip=:customer_ip and prv_product_id=:prv_product_id', $bind);
		}
		
		public function approve()
		{
			$bind = array(
				'status'=>self::status_approved,
				'id'=>$this->id
			);
			Db_DbHelper::query('update shop_product_reviews set prv_moderation_status=:status where id=:id', $bind);
			Shop_Product::update_rating_fields($this->prv_product_id);
		}
		
		public function after_modify($operation, $deferred_session_key)
		{
			Shop_Product::update_rating_fields($this->prv_product_id);
		}
	}

?>