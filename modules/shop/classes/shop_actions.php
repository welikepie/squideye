<?php

	class Shop_Actions extends Cms_ActionScope
	{
		/*
		 * Category functions
		 */
		
		public function category()
		{
			if (!Shop_ConfigurationRecord::get()->nested_category_urls) 
				$category_url_name = $this->request_param(0);
			else
				$category_url_name = Cms_Router::remove_page_segments(strtolower(Phpr::$request->getCurrentUri()));

			if (!strlen($category_url_name))
			{
				$this->data['category'] = null;
				return;
			}

			$params = array();
			$category = Shop_Category::find_by_url($category_url_name, $params);
			if (!$category || $category->category_is_hidden)
			{
				$this->data['category'] = null;
				return;
			}

			$this->data['category'] = $category;
			$this->data['category_url_name'] = $category->get_url_name();

			/*
			 * Override meta
			 */

			$this->page->title = strlen($category->title) ? $category->title : $category->name;
			$this->page->description = strlen($category->meta_description) ? $category->meta_description : $this->page->description;
			$this->page->keywords = strlen($category->meta_keywords) ? $category->meta_keywords : $this->page->meta_keywords;
		}

		/*
		 * Product functions
		 */

		public function product()
		{
			$this->data['product_unavailable'] = true;

			$product_url_name = $this->request_param(0);
			if (!strlen($product_url_name))
			{
				$this->data['product'] = null;
				return;
			}

			$this->data['product'] = null;
			
			$product_id = post('product_id');
			$specific_product = false;
			if (!strlen($product_id))
			{
				$product = Shop_Product::create()->where('(shop_products.grouped is null or (shop_products.grouped is not null and shop_products.product_id is not null))')->find_by_url_name($product_url_name);
				if ($product && $product->grouped && !$product->product_id)
				{
					$this->data['product'] = null;
					return null;
				}

				$configuration = Shop_ConfigurationRecord::get();
				if ($product && $configuration->product_details_behavior == 'exact')
				{
					$specific_product = true;
					$_POST['product_id'] = $product->id;
				}
			}
			else
			{
				$product = Shop_Product::create()->find_by_id($product_id);
				$specific_product = true;
			}

			if (!$product || $product->disable_completely)
			{
				$this->data['product'] = null;
				return null;
			}
			
			if ($product && strlen($product->product_id) && $product->master_grouped_product && $product->master_grouped_product->disable_completely)
			{
				$this->data['product'] = null;
				return null;
			}
			
			$customer_group_id = Cms_Controller::get_customer_group_id();
			if (!$product->visible_for_customer_group($customer_group_id))
			{
				$this->data['product'] = null;
				return null;
			}
				
			/*
			 * Find the first available product in the grouped product list
			 */
			
			if (!$specific_product)
			{
				$grouped_products = $product->grouped_products;
				if ($grouped_products->count)
					$product = $grouped_products[0];
					
				if (!$product->enabled || ($product->is_out_of_stock() && $product->hide_if_out_of_stock))
				{
					$this->data['product_unavailable'] = true;
					return;
				}
			}
			
			if (!$product)
			{
				$this->data['product_unavailable'] = true;
				return;
			}
			
			$this->data['product'] = $product;
			$this->data['product_unavailable'] = false;

			/*
			 * Override meta
			 */

			$this->page->title = strlen($product->title) ? $product->title : $product->name;
			$this->page->description = strlen($product->meta_description) ? $product->meta_description : $this->page->description;
			$this->page->keywords = strlen($product->meta_keywords) ? $product->meta_keywords : $this->page->meta_keywords;
			
			/*
			 * Process file uploads
			 */

			if (array_key_exists('product_file', $_FILES))
			{
				$file_data = Phpr_Files::extract_mutli_file_info($_FILES['product_file']);

				foreach ($file_data as $file)
					$product->add_file_from_post($file);
			}
			
			/*
			 * Handle events
			 */

			if (post('add_to_cart') && !$this->ajax_mode)
				$this->on_addToCart(false);

			if (post('add_review') && !$this->ajax_mode)
				$this->on_addProductReview(false);
		}
		
		public function on_deleteProductFile()
		{
			$this->action();
			
			if (isset($this->data['product']))
				$this->data['product']->delete_uploaded_file(post('file_id'));
		}
		
		public function on_addToCart($ajax_mode = true)
		{
			if ($ajax_mode)
				$this->action();

			$quantity = trim(post('product_cart_quantity', 1));

			if (!strlen($quantity) || !preg_match('/^[0-9]+$/', $quantity))
				throw new Cms_Exception('Invalid quantity value.');

			if (!isset($this->data['product']))
			{
				$product_id = post('product_id');
				if (!$product_id)
					throw new Cms_Exception('Product not found.');

				$product = Shop_Product::create()->find_by_id($product_id);
				if (!$product)
					throw new Cms_Exception('Product not found.');

				$this->data['product'] = $product;
			}
			
			Shop_Cart::add_cart_item($this->data['product'], array(
				'quantity'=>$quantity,
				'cart_name'=>post('cart_name', 'main'),
				'extra_options'=>post('product_extra_options', array()),
				'options'=>post('product_options', array()),
				'custom_data'=>post('item_data', array()),
				'bundle_data'=>post('bundle_data', array())
			));

			if (!post('no_flash'))
			{
				$message = post('message', '%s item(s) added to your cart.');
				Phpr::$session->flash['success'] = sprintf($message, $quantity);
			}
			
			$redirect = post('redirect');
			if ($redirect)
				Phpr::$response->redirect($redirect);
		}
		
		public function on_addProductReview($ajax_mode = true)
		{
			if ($ajax_mode)
				$this->action();
			
			if (!isset($this->data['product']))
			{
				$product_id = post('product_id');
				if (!$product_id)
					throw new Cms_Exception('Product not found.');

				$product = Shop_Product::create()->find_by_id($product_id);
				if (!$product)
					throw new Cms_Exception('Product not found.');

				$this->data['product'] = $product;
			}

			Shop_ProductReview::create_review($this->data['product'], $this->customer, $_POST);
			
			if (!post('no_flash'))
			{
				$message = post('message', 'Your review has been successfully posted. Thank you!');
				Phpr::$session->flash['success'] = $message;
			}
			
			$redirect = post('redirect');
			if ($redirect)
				Phpr::$response->redirect($redirect);
				
			$this->data['review_posted'] = true;
		}
		
		/*
		 * Cart functions
		 */
		
		public function cart()
		{
			$cart_name = post('cart_name', 'main');
			
			$delete_items = post('delete_item', array());
			foreach ($delete_items as $key)
				Shop_Cart::remove_item($key, $cart_name);

			$postpone_items = post('item_postponed', array());
			Shop_Cart::change_postpone_status($postpone_items, $cart_name);

			$shipping_info = Shop_CheckoutData::get_shipping_info();
			$countries = $this->data['countries'] = Shop_Country::get_list($shipping_info->country);
			$shipping_country = $shipping_info->country ? $shipping_info->country : $countries[0]->id;

			$this->data['states'] = Shop_CountryState::create(true)->where('country_id=?', $shipping_country)->order('name')->find_all();
			$this->data['shipping_info'] = $shipping_info;

			$cart_exception = null;
			try
			{
				if (post('set_coupon_code'))
				{
					$this->on_setCouponCode();
				}
				else
					$this->cart_applyQuantity($cart_name);
			} catch (exception $ex)
			{
				$cart_exception = $ex;
			}
				
			$this->cart_apply_custom_data($cart_name);
			$this->eval_cart_variables($cart_name);
			
			if ($cart_exception)
				throw $cart_exception;
		}
		
		protected function eval_cart_variables($cart_name)
		{
			$discount_info = Shop_CheckoutData::eval_discounts($cart_name);

			if (!Shop_CheckoutData::display_prices_incl_tax())
				$this->data['discount'] = $discount_info->cart_discount;
			else
				$this->data['discount'] = $discount_info->cart_discount_incl_tax;
			
			$this->data['subtotal'] = Shop_Cart::total_price($cart_name, true);
			$this->data['subtotal_no_discounts'] = Shop_Cart::total_price($cart_name, false);
			$this->data['cart_total'] = $cart_total = Shop_Cart::total_price($cart_name, true);
			$this->data['applied_discount_rules'] = $discount_info->applied_rules_info;
			$this->data['cart_total_tax_incl'] = Shop_Cart::total_price($cart_name, true, null, true);
			$this->data['cart_tax'] = Shop_Cart::total_tax($cart_name);
			$cart_taxes_details = Shop_TaxClass::calculate_taxes(Shop_Cart::list_active_items($cart_name), Shop_CheckoutData::get_shipping_info());
			$this->data['cart_taxes'] = $cart_taxes_details->taxes;
			$this->data['estimated_total'] = max(0, $cart_total);
			$this->data['coupon_code'] = Shop_CheckoutData::get_coupon_code();
		}
		
		public function on_evalShippingRate()
		{
			$cart_name = post('cart_name', 'main');

			$zip = trim(post('zip'));
			if (!strlen($zip))
				throw new Cms_Exception('Please specify a ZIP code.');

			$total_price = Shop_Cart::total_price_no_tax($cart_name);
			$total_volume = Shop_Cart::total_volume($cart_name);
			$total_weight = Shop_Cart::total_weight($cart_name);
			$total_item_num = Shop_Cart::get_item_total_num($cart_name);

			Shop_CheckoutData::set_shipping_location(post('country'), post('state', null), $zip);
			$available_options = Shop_CheckoutData::list_available_shipping_options($this->customer, $cart_name);

			$this->data['shipping_options'] = $available_options;
		}
		
		public function on_deleteCartItem()
		{
			$cart_name = post('cart_name', 'main');
			
			$this->data['countries'] = Shop_Country::get_list();
			Shop_Cart::remove_item(post('key'), $cart_name);
			
			$shipping_info = Shop_CheckoutData::get_shipping_info();
			$countries = $this->data['countries'] = Shop_Country::get_list($shipping_info->country);
			$shipping_country = $shipping_info->country ? $shipping_info->country : $countries[0]->id;

			$this->data['states'] = Shop_CountryState::create(true)->where('country_id=?', $shipping_country)->order('name')->find_all();
			$this->data['shipping_info'] = $shipping_info;

			$this->eval_cart_variables($cart_name);
		}
		
		private function cart_applyQuantity($cart_name)
		{
			if (array_key_exists('coupon', $_POST))
				$this->on_setCouponCode(false);
			
			$this->data['countries'] = Shop_Country::get_list();
			$quantity = post('item_quantity', array());

			$filtered_list = array();
			foreach ($quantity as $key=>$quantity)
			{
				$quantity = trim($quantity);
			
				if (!preg_match('/^[0-9]+$/', $quantity))
				{
					$item = Shop_Cart::find_item($key, $cart_name);
					if ($item)
						throw new Cms_Exception('Invalid quantity value for '.$item->product->name.' product.');
				}

				$item = Shop_Cart::find_item($key, $cart_name);

				if (($item && $item->get_quantity() == $quantity) || !$item)
					continue;
					
				$filtered_list[$key] = $quantity;
			}

			foreach ($filtered_list as $key=>$quantity)
				Shop_Cart::set_quantity($key, $quantity, $cart_name);
		}
		
		private function cart_apply_custom_data($cart_name)
		{
			$custom_data = post('item_data', array());
			foreach ($custom_data as $key=>$data)
				Shop_Cart::set_custom_data($key, $data, $cart_name);
		}

		/*
		 * Session functions
		 */

		public function login()
		{
			if (post('login'))
				$this->on_login();
			elseif (post('signup'))
				$this->on_signup();
		}
		
		public function signup()
		{
			if (post('signup'))
				$this->on_signup();
		}
		
		public function on_login()
		{
			if (post('flash'))
				Phpr::$session->flash['success'] = post('flash');
			
			$redirect = post('redirect');
			$validation = new Phpr_Validation();
			if (!Phpr::$frontend_security->login($validation, $redirect, post('email'), post('password')))
			{
				$validation->add('email')->focusId('login_email');
				$validation->setError( "Invalid email or password.", 'email', true );
			}
		}

		public function on_signup()
		{
			$customer = new Shop_Customer();
			$customer->disable_column_cache('front_end', false);
			
			$customer->init_columns_info('front_end');
			$customer->validation->focusPrefix = null;
			$customer->validation->getRule('email')->focusId('signup_email');
			
			if (!array_key_exists('password', $_POST))
				$customer->generate_password();
			
			$shipping_params = Shop_ShippingParams::get();

			if (!post('shipping_country_id'))
			{
				$customer->shipping_country_id = $shipping_params->default_shipping_country_id;
				$customer->shipping_state_id = $shipping_params->default_shipping_state_id;
			}

			if (!post('shipping_zip'))
				$customer->shipping_zip = $shipping_params->default_shipping_zip;
				
			if (!post('shipping_city'))
				$customer->shipping_city = $shipping_params->default_shipping_city;

			$customer->save($_POST);
			$customer->send_registration_confirmation();
			
			if (post('flash'))
				Phpr::$session->flash['success'] = post('flash');
				
			if (post('customer_auto_login'))
				Phpr::$frontend_security->customerLogin($customer->id);

			$redirect = post('redirect');
			if ($redirect)
				Phpr::$response->redirect($redirect);
		}
		
		public function on_setCouponCode($allow_redirect = true)
		{
			$coupon_code = trim(post('coupon'));
			$return = Backend::$events->fireEvent('shop:onBeforeSetCouponCode', $coupon_code);
			foreach($return as $changed_code)
			{
				if($changed_code === false)
					throw new Cms_Exception('The entered coupon cannot be used.');
				elseif($changed_code)
					$coupon_code = $changed_code;
			}
			
			if (strlen($coupon_code))
			{
				$coupon = Shop_Coupon::find_coupon($coupon_code);
				if (!$coupon)
					throw new Cms_Exception('A coupon with the specified code is not found');
				$validation_result = Shop_CartPriceRule::validate_coupon_code($coupon_code, $this->customer);
				if($validation_result !== true)
					throw new Cms_Exception($validation_result);
			}
				
			Shop_CheckoutData::set_coupon_code($coupon_code);
			
			$redirect = post('redirect');
			if ($allow_redirect && $redirect)
				Phpr::$response->redirect($redirect);
		}

		public function password_restore()
		{
			if (post('password_restore'))
				$this->on_passwordRestore();
		}
		
		public function on_passwordRestore()
		{
			$validation = new Phpr_Validation();
			$validation->add('email', 'Email')->fn('trim')->required('Please specify your email address')->email()->fn('mb_strtolower');
			if (!$validation->validate($_POST))
				$validation->throwException();

			try
			{
				Shop_Customer::reset_password($validation->fieldValues['email']);

				if (post('flash'))
					Phpr::$session->flash['success'] = post('flash');

				$redirect = post('redirect');
				if ($redirect)
					Phpr::$response->redirect($redirect);
			}
			catch (Exception $ex)
			{
				throw new Cms_Exception($ex->getMessage());
			}
		}
		
		public function change_password()
		{
			$this->data['customer'] = $this->customer;

			if (post('change_password'))
				$this->on_changePassword();
		}
		
		public function on_changePassword()
		{
			$validation = new Phpr_Validation();
			$validation->add('old_password', 'Old Password')->fn('trim')->required("Please specify the old password");
			$validation->add('password', 'Password')->fn('trim')->required("Please specify new password");
			$validation->add('password_confirm', 'Password Confirmation')->fn('trim')->matches('password', 'Password and confirmation password do not match.');
			
			if (!$validation->validate($_POST))
				$validation->throwException();
				
			if (Phpr_SecurityFramework::create()->salted_hash($validation->fieldValues['old_password']) != $this->customer->password)
				$validation->setError('Invalid old password.', 'old_password', true);

			try
			{
				$customer = Shop_Customer::create()->where('id=?', $this->customer->id)->find(null, array(), 'front_end');
				$customer->disable_column_cache('front_end', true);
				$customer->password = $validation->fieldValues['password'];
				$customer->password_confirm = $validation->fieldValues['password_confirm'];
				$customer->save();
				
				if (post('flash'))
					Phpr::$session->flash['success'] = post('flash');
				
				$redirect = post('redirect');
				if ($redirect)
					Phpr::$response->redirect($redirect);
			}
			catch (Exception $ex)
			{
				throw new Cms_Exception($ex->getMessage());
			}
		}

		/*
		 * Checkout functions
		 */
		
		public function checkout()
		{
			global $activerecord_no_columns_info;
			
			$checkout_step = post('checkout_step');
			$skip_to = post('skip_to');

			$shipping_required = $this->data['shipping_required'] = Shop_CheckoutData::shipping_required();
			$skip_shipping_step = false;

			if (!$shipping_required)
			{
					$no_shipping_option = Shop_ShippingOption::find_by_api_code('no_shipping_required');
					if ($no_shipping_option)
					{
						Shop_CheckoutData::set_shipping_method($no_shipping_option->id, post('cart_name', 'main'));
						if (post('auto_skip_shipping') && $checkout_step == 'shipping_info')
						{
							$skip_shipping_step = true;
							$skip_to = post('auto_skip_to', 'payment_method');
						}
					}
			} else
			{
				$shipping_method = Shop_CheckoutData::get_shipping_method();
				$no_shipping_option = Shop_ShippingOption::find_by_api_code('no_shipping_required');

				if ($no_shipping_option && $shipping_method && $shipping_method->id == $no_shipping_option->id)
					Shop_CheckoutData::reset_shiping_method();
			}

			/*
			 * Process return to previous steps
			 */

			$skip_data = false;
			if ($move_to = post('move_to'))
			{
				$skip_data = true;
				switch ($move_to)
				{
					case 'billing_info' : $checkout_step = null; break;
					case 'shipping_info' : $checkout_step = 'billing_info'; break;
					case 'shipping_method' : $checkout_step = 'shipping_info'; break;
					case 'payment_method' : $checkout_step = 'shipping_method'; break;
				}
			}

			if ($skip_to && !$move_to)
			{
				$skip_data = true;

				switch ($checkout_step)
				{
					case 'billing_info' : Shop_CheckoutData::set_billing_info($this->customer); break;
					case 'shipping_info' : Shop_CheckoutData::set_shipping_info(); break;
					case 'shipping_method' : 
						if (!$skip_shipping_step)
							Shop_CheckoutData::set_shipping_method(null, post('cart_name', 'main')); 
					break;
					case 'payment_method' : Shop_CheckoutData::set_payment_method(); break;
				}
				
				switch ($skip_to)
				{
					case 'billing_info' : $checkout_step = null; break;
					case 'shipping_info' : $checkout_step = 'billing_info'; break;
					case 'shipping_method' : $checkout_step = 'shipping_info'; break;
					case 'payment_method' : $checkout_step = 'shipping_method'; break;
					case 'review' : $checkout_step = 'payment_method'; break;
				}
			}

			/*
			 * Reset the checkout data if the cart content has been changed
			 */
			
			$activerecord_no_columns_info = true;

			$cart_name = post('cart_name', 'main');

			if (post('checkout_step'))
			{
				$cart_content_id = Shop_CheckoutData::get_cart_id();
				$new_content_id = Shop_Cart::get_content_id($cart_name);

				if ($new_content_id != $cart_content_id)
				{
					Shop_CheckoutData::reset_data();
					Phpr::$response->redirect(root_url($this->page->url).'/?'.uniqid());
				}
			} else
			{
				Shop_CheckoutData::set_cart_id(Shop_Cart::get_content_id($cart_name));
			}

			$activerecord_no_columns_info = false;

			/*
			 * Set customer notes - on any step
			 */

			if (array_key_exists('customer_notes', $_POST))
				Shop_CheckoutData::set_customer_notes(post('customer_notes'));
				
			/*
			 * Set coupon code - on any step, as well
			 */
			
			if (array_key_exists('coupon', $_POST))
				$this->on_setCouponCode(false);

			/*
			 * Handle the Next button click
			 */

			$billing_info = Shop_CheckoutData::get_billing_info();
			$this->data['billing_info'] = $billing_info;
			
			$shipping_info = Shop_CheckoutData::get_shipping_info();
			$this->data['shipping_info'] = $shipping_info;

			$shipping_method = Shop_CheckoutData::get_shipping_method();
			$this->data['shipping_method'] = $shipping_method;

			$payment_method = Shop_CheckoutData::get_payment_method();
			$this->data['payment_method'] = $payment_method;

			$this->data['coupon_code'] = Shop_CheckoutData::get_coupon_code();

			if (!$checkout_step)
			{
				$this->data['checkout_step'] = 'billing_info';
			
				$billing_countries = Shop_Country::get_list($billing_info->country);
				$this->data['countries'] = $billing_countries;

				$billing_country = $billing_info->country ? $billing_info->country : $billing_countries[0]->id;
				$this->data['states'] = Shop_CountryState::create(true)->where('country_id=?', $billing_country)->order('name')->find_all();
			} 
			elseif ($checkout_step == 'billing_info')
			{
				if (!$skip_data)
					Shop_CheckoutData::set_billing_info($this->customer);

				$shipping_countries = Shop_Country::get_list($shipping_info->country);
				$this->data['countries'] = $shipping_countries;

				$shipping_country = $shipping_info->country ? $shipping_info->country : $shipping_countries[0]->id;
				$this->data['states'] = Shop_CountryState::create(true)->where('country_id=?', $shipping_country)->order('name')->find_all();

				$this->data['checkout_step'] = 'shipping_info';
			}
			elseif ($checkout_step == 'shipping_info')
			{
				if (!$skip_data)
					Shop_CheckoutData::set_shipping_info();

				$this->data['shipping_options'] = Shop_CheckoutData::list_available_shipping_options($this->customer, $cart_name);

				$this->data['checkout_step'] = 'shipping_method';
			}
			elseif ($checkout_step == 'shipping_method')
			{
				if (!$skip_data)
					Shop_CheckoutData::set_shipping_method(null, post('cart_name', 'main'));

				$discount_info = Shop_CheckoutData::eval_discounts($cart_name);
				
				$tax_info = Shop_TaxClass::calculate_taxes(Shop_Cart::list_active_items($cart_name), Shop_CheckoutData::get_shipping_info());
				
				$total_product_tax = $tax_info->tax_total;
				$this->data['total_product_tax'] = $total_product_tax;
				
				$total = $this->data['goods_tax'] = $total_product_tax;
				$total += $this->data['subtotal'] = Shop_Cart::total_price_no_tax($cart_name);
				$total += $this->data['shipping_quote'] = $shipping_method->is_free ? 0 : $shipping_method->quote_no_tax;

				$shiping_taxes = $this->data['shipping_taxes'] = Shop_TaxClass::get_shipping_tax_rates($shipping_method->id, Shop_CheckoutData::get_shipping_info(), $shipping_method->quote_no_tax);
				$total += $this->data['shipping_tax'] = Shop_TaxClass::eval_total_tax($shiping_taxes);

				$payment_methods = Shop_PaymentMethod::list_applicable(Shop_CheckoutData::get_billing_info()->country, $total, false)->as_array();
				$this->data['payment_methods'] = $payment_methods;

				$this->data['checkout_step'] = 'payment_method';
			} elseif ($checkout_step == 'payment_method')
			{
				if (!$skip_data)
					Shop_CheckoutData::set_payment_method();
					
				$display_prices_including_tax = Shop_CheckoutData::display_prices_incl_tax();

				$totals = Shop_CheckoutData::calculate_totals($cart_name);

				$this->data['discount'] = $display_prices_including_tax ? $totals->discount_tax_incl : $totals->discount;
				$this->data['goods_tax'] = $totals->goods_tax;
				$this->data['subtotal_no_discounts'] = $totals->subtotal;
				$this->data['subtotal'] = $display_prices_including_tax ? $totals->subtotal_tax_incl : $totals->subtotal_discounts;
				$this->data['shipping_taxes'] = $totals->shipping_taxes;
				$this->data['shipping_tax'] = $totals->shipping_tax;
				$this->data['shipping_quote'] = $display_prices_including_tax ? $totals->shipping_quote_tax_incl : $totals->shipping_quote;
				$this->data['shipping_tax_incl'] = $totals->shipping_quote_tax_incl;
				$this->data['total'] = $totals->total;
				$this->data['product_taxes'] = $totals->product_taxes;
				$this->data['taxes'] = $totals->all_taxes;

				$this->data['checkout_step'] = 'review';
			} elseif ($checkout_step == 'review')
			{
				$payment_method_info = Shop_CheckoutData::get_payment_method();
				$payment_method = Shop_PaymentMethod::create()->find($payment_method_info->id);
				if (!$payment_method)
					throw new Cms_Exception('The selected payment method is not found');
					
				$payment_method->define_form_fields();

				$order = Shop_CheckoutData::place_order($this->customer, post('register_customer', false), post('cart_name', 'main'), post('empty_cart', true));
				$this->data['checkout_step'] = 'pay';

				$custom_pay_page = $payment_method->get_paymenttype_object()->get_custom_payment_page($payment_method);
				$pay_page = $custom_pay_page ? $custom_pay_page : Cms_Page::create()->find_by_action_reference('shop:pay');
				if (!$pay_page)
					throw new Cms_Exception('The Pay page is not found.');

				Phpr::$response->redirect(root_url($pay_page->url.'/'.$order->order_hash));
			}

			/*
			 * Reload updated checkout data
			 */

			$billing_info = Shop_CheckoutData::get_billing_info();
			$this->data['billing_info'] = $billing_info;
			
			$shipping_info = Shop_CheckoutData::get_shipping_info();
			$this->data['shipping_info'] = $shipping_info;

			$shipping_method = Shop_CheckoutData::get_shipping_method();
			$this->data['shipping_method'] = $shipping_method;
			
			$payment_method = Shop_CheckoutData::get_payment_method();
			$this->data['payment_method'] = $payment_method;

			$this->load_checkout_estimated_data($cart_name);
		}
		
		protected function load_checkout_estimated_data($cart_name)
		{
			$display_prices_including_tax = Shop_CheckoutData::display_prices_incl_tax();
			$discount_info = Shop_CheckoutData::eval_discounts($cart_name);

			if (!array_key_exists('discount', $this->data))
			{
				if (!$display_prices_including_tax)
					$this->data['discount'] = $discount_info->cart_discount;
				else
					$this->data['discount'] = $discount_info->cart_discount_incl_tax;
			}
			else 
				$discount = $this->data['discount'];

			$this->data['cart_total'] = $cart_total = Shop_Cart::total_price($cart_name, true);
			$shipping_tax = 0;

			if (!array_key_exists('total', $this->data))
			{
				$totals = Shop_CheckoutData::calculate_totals($cart_name);
				$this->data['estimated_total'] = $totals->total;
				$shipping_tax = $totals->shipping_tax;
				$this->data['estimated_tax'] = $totals->goods_tax + $totals->shipping_tax;
			} else
			{
				$this->data['estimated_total'] = $this->data['total'];
				$shipping_tax = $this->data['shipping_tax'];
				$this->data['estimated_tax'] = $this->data['goods_tax'] + $this->data['shipping_tax'];
			}

			if ($display_prices_including_tax && isset($this->data['shipping_method']))
			{
				$shipping_method = $this->data['shipping_method'];
	  			if ($shipping_method->id)
				{
					$shipping_method->quote = $shipping_method->quote_no_tax + $shipping_tax;
				}
			}
			
			$this->data['applied_discount_rules'] = $discount_info->applied_rules_info;
		}

		public function on_copyBillingInfo()
		{
			$billing_info = Shop_CheckoutData::get_billing_info();

			$shipping_info = Shop_CheckoutData::get_shipping_info();
			$shipping_info->copy_from($billing_info);
			Shop_CheckoutData::set_shipping_info($shipping_info);
			$_POST['move_to'] = 'shipping_info';
			$this->checkout();
		}
		
		public function on_updateStateList()
		{
			$this->data['states'] = Shop_CountryState::create()->where('country_id=?', post('country'))->order('name')->find_all();
			$this->data['control_name'] = post('control_name');
			$this->data['control_id'] = post('control_id');
			$this->data['current_state'] = post('current_state');
		}

		public function payment_receipt()
		{
			$this->data['order'] = null;
			$this->data['payment_processed'] = false;

			$order_hash = trim($this->request_param(0));
			if (!strlen($order_hash))
				return;
				
			$order = Shop_Order::create()->find_by_order_hash($order_hash);
			if (!$order)
				return;
				
			$this->data['order'] = $order;
			$this->data['items'] = $order->items;

			if (!$order->payment_processed())
				return;

			$this->data['payment_processed'] = true;

			/*
			 * Add Google Analytics E-Commerce transaction tracking
			 */
			$gaSettings = Cms_Stats_Settings::get();
			if ($gaSettings->ga_enabled)
				$this->add_tracking_code($this->get_ga_ec_tracking_code($gaSettings, $order));
		}
		
		private function get_ga_ec_tracking_code($gaSettings, $order)
		{
			return $gaSettings->get_ga_ec_tracking_code($order);
		}

		/*
		 * Step-by step checkout
		 */
		
		public function checkout_billing_info()
		{
			Shop_CheckoutData::reset_data();
			Shop_CheckoutData::set_cart_id(Shop_Cart::get_content_id(post('cart_name', 'main')));

			$this->loadCheckoutBillingStepData();

			$this->setCheckoutFollowUpInfo();

			if (array_key_exists('submit', $_POST))
				$this->on_checkoutSetBillingInfo(false);

			$this->loadCheckoutBillingStepData(true);
		}
		
		public function on_checkoutSetBillingInfo($ajax_mode = true)
		{
			if ($ajax_mode)
				$this->setCheckoutFollowUpInfo();

			Shop_CheckoutData::set_billing_info($this->customer);

			if ($ajax_mode)
				$this->loadCheckoutBillingStepData(true);

			$redirect = post('redirect');
			if ($redirect)
				Phpr::$response->redirect($redirect);
		}
		
		public function checkout_shipping_info()
		{
			Shop_CheckoutData::set_cart_id(Shop_Cart::get_content_id(post('cart_name', 'main')));

			$this->loadCheckoutShippingStepData();

			$this->setCheckoutFollowUpInfo();

			if (array_key_exists('submit', $_POST))
				$this->on_checkoutSetShippingInfo(false);

			$this->loadCheckoutShippingStepData(true);
		}
		
		public function on_checkoutSetShippingInfo($ajax_mode = true)
		{
			if ($ajax_mode)
				$this->setCheckoutFollowUpInfo();

			Shop_CheckoutData::set_shipping_info();

			if ($ajax_mode)
				$this->loadCheckoutShippingStepData(true);

			$redirect = post('redirect');
			if ($redirect)
				Phpr::$response->redirect($redirect);
		}
		
		public function checkout_shipping_method()
		{
			$cart_name = post('cart_name', 'main');
			Shop_CheckoutData::set_cart_id(Shop_Cart::get_content_id($cart_name));

			$this->data['shipping_options'] = Shop_CheckoutData::list_available_shipping_options($this->customer, $cart_name);
			$this->data['shipping_method'] = Shop_CheckoutData::get_shipping_method();

			$this->setCheckoutFollowUpInfo();

			if (array_key_exists('submit', $_POST))
				$this->on_checkoutSetShippingMethod(false);

			$this->load_checkout_estimated_data($cart_name);
			$this->data['shipping_method'] = Shop_CheckoutData::get_shipping_method();
		}
		
		public function on_checkoutSetShippingMethod($ajax_mode = true)
		{
			if ($ajax_mode)
				$this->setCheckoutFollowUpInfo();

			Shop_CheckoutData::set_shipping_method(null, post('cart_name', 'main'));

			$redirect = post('redirect');
			if ($redirect)
				Phpr::$response->redirect($redirect);
		}

		public function checkout_payment_method()
		{
			$shipping_method = Shop_CheckoutData::get_shipping_method();
			
			$cart_name = post('cart_name', 'main');

			$discount_info = Shop_CheckoutData::eval_discounts($cart_name);
			
			$tax_info = Shop_TaxClass::calculate_taxes(Shop_Cart::list_active_items($cart_name), Shop_CheckoutData::get_shipping_info());
			$total_product_tax = $tax_info->tax_total;
			$this->data['total_product_tax'] = $total_product_tax;
			$total = $this->data['goods_tax'] = $total_product_tax;

			$total += $this->data['subtotal'] = Shop_Cart::total_price_no_tax($cart_name);
			$total += $this->data['shipping_quote'] = $shipping_method->quote_no_tax;
			
			$shiping_taxes = $this->data['shipping_taxes'] = Shop_TaxClass::get_shipping_tax_rates($shipping_method->id, Shop_CheckoutData::get_shipping_info(), $shipping_method->quote_no_tax);
			$total += $this->data['shipping_tax'] = Shop_TaxClass::eval_total_tax($shiping_taxes);

			$payment_methods = Shop_PaymentMethod::list_applicable(Shop_CheckoutData::get_billing_info()->country, $total)->as_array();

			$this->data['payment_methods'] = $payment_methods;
			$this->data['payment_method'] = Shop_CheckoutData::get_payment_method();
			$this->data['applied_discount_rules'] = $discount_info->applied_rules_info;
			
			$this->setCheckoutFollowUpInfo();

			if (array_key_exists('submit', $_POST))
				$this->on_checkoutSetPaymentMethod(false);
				
			$this->data['payment_method'] = Shop_CheckoutData::get_payment_method();
			$this->load_checkout_estimated_data($cart_name);
		}
		
		public function on_checkoutSetPaymentMethod($ajax_mode = true)
		{
			if ($ajax_mode)
				$this->setCheckoutFollowUpInfo();

			Shop_CheckoutData::set_payment_method();

			$redirect = post('redirect');
			if ($redirect)
				Phpr::$response->redirect($redirect);
		}
		
		public function checkout_order_review()
		{
			$display_prices_including_tax = Shop_CheckoutData::display_prices_incl_tax();

			$totals = Shop_CheckoutData::calculate_totals(post('cart_name', 'main'));
			$this->data['discount'] = $display_prices_including_tax ? $totals->discount_tax_incl : $totals->discount;
			$this->data['goods_tax'] = $totals->goods_tax;
			$this->data['subtotal_no_discounts'] = $totals->subtotal;
			$this->data['subtotal'] = $display_prices_including_tax ? $totals->subtotal_tax_incl : $totals->subtotal_discounts;
			$this->data['shipping_taxes'] = $totals->shipping_taxes;
			$this->data['shipping_tax'] = $totals->shipping_tax;
			$this->data['shipping_quote'] = $display_prices_including_tax ? $totals->shipping_quote_tax_incl : $totals->shipping_quote;
			$this->data['total'] = $totals->total;
			$this->data['product_taxes'] = $totals->product_taxes;
			
			$this->data['billing_info'] = Shop_CheckoutData::get_billing_info();
			$this->data['shipping_info'] = Shop_CheckoutData::get_shipping_info();
			$this->data['shipping_method'] = Shop_CheckoutData::get_shipping_method();
			$this->data['payment_method'] = Shop_CheckoutData::get_payment_method();

			$this->setCheckoutFollowUpInfo();
			$this->load_checkout_estimated_data(post('cart_name', 'main'));

			if (array_key_exists('submit', $_POST))
				$this->on_checkoutPlaceOrder(false);
		}

		public function on_checkoutPlaceOrder($ajax_mode = true)
		{
			if ($ajax_mode)
				$this->setCheckoutFollowUpInfo();

			$payment_method_info = Shop_CheckoutData::get_payment_method();
			$payment_method = Shop_PaymentMethod::create()->find($payment_method_info->id);
			if (!$payment_method)
				throw new Cms_Exception('The selected payment method is not found');
				
			$payment_method->define_form_fields();
			
			$order = Shop_CheckoutData::place_order($this->customer, post('register_customer', false), post('cart_name', 'main'), post('empty_cart', true));

			if (!post('no_redirect'))
			{
				$custom_pay_page = $payment_method->get_paymenttype_object()->get_custom_payment_page($payment_method);
				$pay_page = $custom_pay_page ? $custom_pay_page : Cms_Page::create()->find_by_action_reference('shop:pay');
				if (!$pay_page)
					throw new Cms_Exception('The Pay page is not found.');

				Phpr::$response->redirect(root_url($pay_page->url.'/'.$order->order_hash));
			}
			
			return $order;
		}

		protected function setCheckoutFollowUpInfo()
		{
			if (array_key_exists('customer_notes', $_POST))
				Shop_CheckoutData::set_customer_notes(post('customer_notes'));

			if (array_key_exists('coupon', $_POST))
				$this->on_setCouponCode(false);
		}
		
		protected function loadCheckoutBillingStepData($data_updated = false)
		{
			$this->data['coupon_code'] = Shop_CheckoutData::get_coupon_code();

			$billing_info = Shop_CheckoutData::get_billing_info();
			$billing_countries = Shop_Country::get_list($billing_info->country);
			$this->data['countries'] = $billing_countries;

			if ($data_updated)
				$billing_country = $billing_info->country ? $billing_info->country : $billing_countries[0]->id;
			else
			{
				$posted_country = post('country', $billing_info->country);
				$billing_country = $posted_country ? $posted_country : $billing_countries[0]->id;
			}

			$this->data['states'] = Shop_CountryState::create()->where('country_id=?', $billing_country)->order('name')->find_all();

			$this->data['billing_info'] = $billing_info;
			$this->load_checkout_estimated_data(post('cart_name', 'main'));
		}
		
		protected function loadCheckoutShippingStepData($data_updated = false)
		{
			$this->data['coupon_code'] = Shop_CheckoutData::get_coupon_code();

			$shipping_info = Shop_CheckoutData::get_shipping_info();
			$shipping_countries = Shop_Country::get_list($shipping_info->country);
			$this->data['countries'] = $shipping_countries;

			if ($data_updated)
				$shipping_country = $shipping_info->country ? $shipping_info->country : $shipping_countries[0]->id;
			else
			{
				$posted_country = post('country', $shipping_info->country);
				$shipping_country = $posted_country ? $posted_country : $shipping_countries[0]->id;
			}

			$this->data['states'] = Shop_CountryState::create()->where('country_id=?', $shipping_country)->order('name')->find_all();

			$this->data['shipping_info'] = $shipping_info;
			$this->load_checkout_estimated_data(post('cart_name', 'main'));
		}

		/*
		 * Payment functions
		 */
		
		public function pay()
		{
			$this->data['order'] = $order = $this->pay_find_order();
			if (!$this->data['order'])
				return;

			$this->data['payment_method'] = $order->payment_method;
			$order->payment_method->define_form_fields();
			$this->data['payment_method_obj'] = $order->payment_method->get_paymenttype_object();
			
			if (post('submit_payment'))
				$this->on_pay($order);
		}
		
		public function on_updatePaymentMethod()
		{
			$this->data['order'] = $order = $this->pay_find_order();
			if (!$this->data['order'])
				return;
			
			$order->payment_method_id = post('payment_method');
			$order->save();
			
			$order->payment_method = Shop_PaymentMethod::create()->find($order->payment_method_id);
			$order->payment_method->define_form_fields();
			$this->data['payment_method'] = $order->payment_method;
			$this->data['payment_method_obj'] = $order->payment_method->get_paymenttype_object();
		}
		
		public function payment_information()
		{
			$this->pay();
		}
		
		public function on_pay($order = null)
		{
			if (!$order)
				$order = $this->pay_find_order();
				
			if (!$order)
				return;
				
			$order->payment_method->define_form_fields();
			$payment_method_obj = $order->payment_method->get_paymenttype_object();
			
			if (!post('pay_from_profile') || post('pay_from_profile') != 1)
				$payment_method_obj->process_payment_form($_POST, $order->payment_method, $order);
			else
			{
				if (!$this->customer)
					throw new Phpr_ApplicationException('Please log in to pay using the stored credit card.');
					
				if ($this->customer->id != $order->customer_id)
					throw new Phpr_ApplicationException('The order does not belong to your customer account.');
				
				$payment_method_obj->pay_from_profile($order->payment_method, $order);
			}

			$return_page = $order->payment_method->receipt_page;
			if ($return_page)
				Phpr::$response->redirect(root_url($return_page->url.'/'.$order->order_hash));
		}

		private function pay_find_order()
		{
			$order_hash = trim($this->request_param(0));
			if (!strlen($order_hash))
				return null;
				
			$order = Shop_Order::create()->find_by_order_hash($order_hash);
			if (!$order)
				return null;

			if (!$order->payment_method)
				return null;
			
			$order->payment_method->define_form_fields();

			return $order;
		}
		
		/*
		 * Payment profile functions
		 */

		public function payment_profile()
		{
			$this->data['payment_method'] = null;

			$this->data['payment_method'] = $this->payment_profile_find_method();
			if (!$this->data['payment_method'])
				return;
			
			$this->data['payment_method_obj'] = $this->data['payment_method']->get_paymenttype_object();
			$this->data['payment_profile'] = $this->data['payment_method']->find_customer_profile($this->customer);
			
			if (post('submit_profile'))
				$this->on_updatePaymentProfile($this->data['payment_method']);
				
			if (post('delete_profile'))
				$this->on_deletePaymentProfile($this->data['payment_method']);
		}
		
		public function on_updatePaymentProfile($payment_method = null)
		{
			if (!$payment_method)
				$payment_method = $this->payment_profile_find_method();

			if (!$payment_method)
				throw new Phpr_ApplicationException('Payment method not found.');

			if (!$this->customer)
				throw new Phpr_ApplicationException('Please log in to manage payment profiles.');

			$payment_method_obj = $payment_method->get_paymenttype_object();
			$payment_method_obj->update_customer_profile($payment_method, $this->customer, $_POST);
			
			Phpr::$session->flash['success'] = 'The payment profile has been successfully updated.';
			$return_page = Cms_Page::create()->find_by_action_reference('shop:payment_profiles');
			if (!$return_page)
				throw new Cms_Exception('The Payment Profiles page is not found.');

			Phpr::$response->redirect(root_url($return_page->url));
		}
		
		public function on_deletePaymentProfile($payment_method = null)
		{
			if (!$payment_method)
				$payment_method = $this->payment_profile_find_method();

			if (!$payment_method)
				throw new Phpr_ApplicationException('Payment method not found.');

			if (!$this->customer)
				throw new Phpr_ApplicationException('Please log in to manage payment profiles.');

			$payment_method_obj = $payment_method->get_paymenttype_object();
			$payment_method->delete_customer_profile($this->customer);
			
			if (!post('no_flash'))
				Phpr::$session->flash['success'] = post('message', 'The payment profile has been successfully deleted.');
			
			$redirect = post('redirect');
			if ($redirect)
				Phpr::$response->redirect($redirect);
		}
		
		public function payment_profiles()
		{
			$this->data['payment_methods'] = array();
			
			if (!$this->customer)
				return;
				
			$methods = Shop_PaymentMethod::list_applicable($this->customer->billing_country_id, 1);
			$payment_profile_methods = array();
			foreach ($methods as $method)
			{
				if ($method->supports_payment_profiles())
					$payment_profile_methods[] = $method;
			}

			$this->data['payment_methods'] = $payment_profile_methods;
		}
		
		protected function payment_profile_find_method()
		{
			$method_id = trim($this->request_param(0));
			if (!strlen($method_id))
				return null;

			$obj = Shop_PaymentMethod::create()->find($method_id);
			if ($obj)
				$obj->define_form_fields();
			
			return $obj;
		}

		/*
		 * Customer orders functions
		 */
		
		public function orders()
		{
			$this->data['orders'] = null;

			$customer = $this->customer;
			if (!$customer)
				return;

			$orders = Shop_Order::create()->where('deleted_at is null')->where('customer_id=?', $customer->id)->order('order_datetime desc')->find_all();
			$this->data['orders'] = $orders;
		}
		
		public function order()
		{
			$this->data['order'] = null;

			$order_id = $this->request_param(0);
			if (!strlen($order_id))
				return;
				
			$order = Shop_Order::create()->find($order_id);
			if (!$order)
				return;
				
			if ($order->customer_id != $this->customer->id)
				return;
				
			$this->data['order'] = $order;
			$this->data['items'] = $order->items;
		}
		
		/*
		 * Product search
		 */
		
		public function search()
		{
			$request = trim(Phpr::$request->getField('query'));
			$request = urldecode($request);
			$this->data['query'] = Phpr_Html::encode($request);

			/*
			 * Load categories
			 */
			
			$categories = Phpr::$request->get_value_array('categories');
			if (!is_array($categories))
				$categories = array();
				
			$categories_specified = false;
			foreach ($categories as $category)
			{
				if (strlen($category))
				{
					$categories_specified = true;
					break;
				}
			}

			/*
			 * Load manufacturers
			 */
			
			$manufacturers = Phpr::$request->get_value_array('manufacturers');
			if (!is_array($manufacturers))
				$manufacturers = array();
				
			$manufacturers_specified = false;
			foreach ($manufacturers as $manufacturer)
			{
				if (strlen($manufacturer))
				{
					$manufacturers_specified = true;
					break;
				}
			}

			/*
			 * Load options
			 */

			$option_names = Phpr::$request->get_value_array('option_names');
			if (!is_array($option_names))
				$option_names = array();

			$option_values = Phpr::$request->get_value_array('option_values');
			if (!is_array($option_values))
				$option_values = array();
				
			$selected_options = array();
			$options_specified = false;
			foreach ($option_names as $index=>$name)
			{
				if (array_key_exists($index, $option_values))
				{
					$selected_options[$name] = urldecode($option_values[$index]);
					if (strlen(trim($option_values[$index])))
						$options_specified = true;
				}
			}

			/*
			 * Load attributes
			 */

			$attribute_names = Phpr::$request->get_value_array('attribute_names');
			if (!is_array($attribute_names))
				$attribute_names = array();

			$attribute_values = Phpr::$request->get_value_array('attribute_values');
			if (!is_array($attribute_values))
				$attribute_values = array();

			$selected_attributes = array();
			$attributes_specified = false;
			foreach ($attribute_names as $index=>$name)
			{
				if (array_key_exists($index, $attribute_values))
				{
					$selected_attributes[$name] = urldecode($attribute_values[$index]);
					if (strlen(trim($attribute_values[$index])))
						$attributes_specified = true;
				}
			}
			
			/*
			 * Load custom groups
			 */
			
			$custom_groups = Phpr::$request->get_value_array('custom_groups');
			if (!is_array($custom_groups))
				$custom_groups = array();
			
			$custom_groups_specified = false;
			foreach ($custom_groups as &$custom_group)
			{
				$custom_group = urldecode($custom_group);
				if (strlen($custom_group))
					$custom_groups_specified = true;
			}
			
			/*
			 * Load price range
			 */
			
			$min_price = urldecode(trim(Phpr::$request->getField('min_price')));
			$max_price = urldecode(trim(Phpr::$request->getField('max_price')));
			
			if (!strlen($min_price))
				$min_price = null;

			if (!strlen($max_price))
				$max_price = null;

			/*
			 * Run the search request
			 */

			$page = $this->request_param(0, 1);
			$records = trim(Phpr::$request->getField('records', 20));
			if ($records < 1)
				$records = 1;

			$max_records = Phpr::$config->get('SEARCH_MAX_RECORDS');
			if (strlen($max_records) && $records > $max_records)
				$records = $max_records;

			$sorting = Phpr::$request->getField('sorting');
			
			$this->data['sorting'] = $sorting;
			$this->data['records'] = $records;
			$this->data['selected_categories'] = $categories;
			$this->data['selected_custom_groups'] = $custom_groups;
			$this->data['selected_options'] = $selected_options;
			$this->data['selected_attributes'] = $selected_attributes;
			$this->data['selected_manufacturers'] = $manufacturers;
			$this->data['min_price'] = $min_price;
			$this->data['max_price'] = $max_price;

			$no_query = $this->data['no_query'] = !(
				strlen($request) 
				|| $options_specified 
				|| $categories_specified 
				|| $custom_groups_specified
				|| $attributes_specified 
				|| $manufacturers_specified
				|| strlen($min_price)
				|| strlen($max_price)
			);

			$pagination = new Phpr_Pagination($records);
			if (!$no_query)
			{
				$options = array();
				$options['category_ids'] = $categories;
				$options['manufacturer_ids'] = $manufacturers;
				$options['options'] = $selected_options;
				$options['attributes'] = $selected_attributes;
				$options['min_price'] = $min_price;
				$options['max_price'] = $max_price;
				$options['sorting'] = $sorting;
				$options['custom_groups'] = $custom_groups;

				$this->data['products'] = Shop_Product::find_products($request, $pagination, $page, $options);
			} else
				$this->data['products'] = Shop_Product::create()->where('shop_products.id<>shop_products.id');

			/*
			 * Format search parameters
			 */

			$search_params = array();
			$search_params[] = 'query='.urlencode($request);
			$search_params[] = 'records='.urlencode($records);
			$search_params[] = 'min_price='.urlencode($min_price);
			$search_params[] = 'max_price='.urlencode($max_price);
			$search_params[] = 'sorting='.urlencode($sorting);
			
			$this->format_search_array_value($categories, 'categories[]', $search_params);
			$this->format_search_array_value($custom_groups, 'custom_groups[]', $search_params);
			$this->format_search_array_value($manufacturers, 'manufacturers[]', $search_params);
			$this->format_search_array_value($option_names, 'option_names[]', $search_params);
			$this->format_search_array_value($option_values, 'option_values[]', $search_params);
			$this->format_search_array_value($attribute_names, 'attribute_names[]', $search_params);
			$this->format_search_array_value($attribute_values, 'attribute_values[]', $search_params);

			$search_params_str = implode('&amp;', $search_params);
			$this->data['search_params_str'] = '?'.$search_params_str;

			$this->data['pagination'] = $pagination;
		}
		
		private function format_search_array_value($values, $name, &$search_params)
		{
			foreach ($values as $value)
				$search_params[] = $name.'='.urlencode($value);
		}
		
		/*
		 * Compare list functions
		 */
		
		public function on_addToCompare()
		{
			$product_id = trim(post('product_id'));
			
			if (!strlen($product_id) || !preg_match('/^[0-9]+$/', $product_id))
				throw new Cms_Exception('Product not found.');
				
			Shop_ComparisonList::add_product($product_id);
		}
		
		public function on_removeFromCompare()
		{
			$product_id = trim(post('product_id'));
			
			if (!strlen($product_id) || !preg_match('/^[0-9]+$/', $product_id))
				return;
				
			Shop_ComparisonList::remove_product($product_id);
			$this->compare();
		}
		
		public function on_clearCompareList()
		{
			Shop_ComparisonList::clear();
		}
		
		public function compare()
		{
			$products = $this->data['products'] = Shop_ComparisonList::list_products();
			
			$all_attribute_names = array();
			foreach ($products as $product)
			{
				foreach ($product->properties as $attribute)
				{
					$key = mb_strtolower($attribute->name);
					if (!array_key_exists($key, $all_attribute_names))
						$all_attribute_names[$key] = $attribute->name;
				}
			}

			$this->data['attributes'] = $all_attribute_names;
		}

		/*
		 * Manufacturers
		 */
		
		public function manufacturers()
		{
			$this->data['manufacturers'] = Shop_Manufacturer::create()->order('name')->find_all();
		}
		
		public function manufacturer()
		{
			$this->data['manufacturer'] = null;

			$url_name = trim($this->request_param(0));
			if (!strlen($url_name))
				return;

			$manufacturer = Shop_Manufacturer::create()->find_by_url_name($url_name);
			if (!$manufacturer)
				return;
				
			$this->data['manufacturer'] = $manufacturer;
		}
	}

?>