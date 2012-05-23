TATTOO WORKSHOP
===============

The workshop is going to be a service complimentary to the tattoo shop. The shop users will be able to submit their own designs there and vote on the best ones. Periodically, the best designs will be selected from the workshop and added to the shop's inventory.

Features & Capabilities
-----------------------

* Browse the submitted tattoos
  * Browse latest submissions
  * Browse best submissions
  * Search for specific tattoos
     * Filter tattoos by various fields
  * Sort tattoos by various fields
  * View single tattoo
* Upload the tattoo designs
  * Upload vector graphics (automatically convert non-SVGs) - non svg formats allowed, ideally get from the user an editable file like psd, ia etc and get them to upload a png/jpg for use on the site
  * Set appropriate submission fields on the tattoo
  * Confirm the submission explicitly (double-check the details and see the result of conversion before uploading; they're not always exactly correct)
* Upvote the tattoos
  * Keep track of upvotes, so only one can happen per user-submission combination
* Administrate the tattoos
  * Remove the selected submissions
  * Mark the submissions as officially accepted (should make something like an award appear to it) - upon tattoo going to the store have an automated email go out to all those who voted for it, consider use of mailchimp api for this
    * Automatically add the officially accepted tattoos to the store's inventory
  * Add awards / medals / achievements to various submissions
* Administrate the users
  * Block repeat offenders

Notes
-----

Submission fields (what data to include):

* Tattoo name
* Uploader (automatically set at upload)
* Upload date & time (automatically set at upload)
* Description
* Tags
* Upvote count (set to 0, modified after upload)

How about assigning discounts and awards? Something like X% off the next order for the author of officially accepted submission? something to consider, was also going to pay them anyway