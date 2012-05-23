INSERT INTO `system_email_templates` (`code`,`subject`,`content`,`description`,`is_system`) VALUES ('shop:order_note_internal','New order note: {order_note_id}','<p>Hi!</p>\n<p>User {order_note_author} posted a new order for the order #{order_note_id}:</p>\n<blockquote>{order_note_text}</blockquote>\n<p>You can view or reply to the note on the <a href=\"{order_note_preview_url}\">Order Preview page</a>. Reply to this message to respond {order_note_author} directly by email. If you respond by email, your message will not be added to the order note list.</p>','This message is sent to the store team members when somebody posts an order note','1');

INSERT INTO `system_email_templates` (`code`,`subject`,`content`,`description`,`is_system`) VALUES ('shop:out_of_stock_internal','Product \"{out_of_stock_product}\" is out of stock','<p>Hi!</p>\n<p>This message is to inform you that product&nbsp;<strong>{out_of_stock_product}</strong> with SKU <strong>{out_of_stock_sku}</strong> is out of stock. Number of items remaining in stock: <strong>{out_of_stock_count}</strong></p>\n<p>You can update the product stock status on <a href=\"{out_of_stock_url}\">this page</a>.</p>','This message is sent to the store team members when a product runs out of stock','1');

INSERT INTO `system_email_templates` (`code`,`subject`,`content`,`description`,`is_system`) VALUES ('shop:product_review_internal','New review for \"{review_product_name}\" product','<p>Hi!</p>\n<p>Visitor {review_author_name} ({review_author_email}) posted a review for the {review_product_name} product:</p>\n<blockquote>{review_text}<br /></blockquote>\n<p>Product rating: {review_rating}</p>\n<p>You can approve or delete the review on <a href=\"{review_edit_url}\">this page</a>.</p>','This message is sent to the store team members on new product review','1');
