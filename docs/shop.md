TEMPORARY TATTOO SHOP
=====================

The main facility of the service, website managing the trade in temporary tattoos. The site will be running on LemonStand software stack, with any modifications that might be deemed neccessary.

Features & Capabilities
-----------------------

* Browse available tattoos
  * List featured tattoos [IDEA]
  * List most popular tattoos
  * Search for specific tattoos
    * Filter & sort by specific parameters
  * View whole category of tattoos
  * View single tattoo
* Buy the temporary tattoos
  * Process the order & notify the user
  * Allow to specify tattoo dimensions (vector graphics are easily scalable)

[ anything else? ]

Game Plan
---------

* **[DONE]** Set up LemonStand infrastructure
* **[DONE]** Add dummy products to the database

* **[DONE]** Code the UK Royal Mail Shipping module
* Obtain credentials for payment services

* Configure payment, shipping, billing options
* *[CURRENT]* Build temporary site design
* Create product pages (bulk & view)
* Ensure proper pagination and sorting
* Set up search functionality
* Style basket and payment processing pages

Notes
-----

UK Royal Mail - is there API or should I go with data scraping?

Possibly needed extensions to LemonStand:

* [Product Minimum Quantity](http://lemonstandapp.com/marketplace/module/meminqty/) (paid) / [Minimum Order Amount](http://lemonstandapp.com/marketplace/module/minorderamount/) (free)
* [Optional Product SKU](http://lemonstandapp.com/marketplace/module/optionalsku/) (free)

Since the designs used for printing ar going to be stored as vector graphics, should we give the customer ability to specify the dimensions of the print? Vector scales nicely and they might require bigger or smaller versions for whatever body parts.