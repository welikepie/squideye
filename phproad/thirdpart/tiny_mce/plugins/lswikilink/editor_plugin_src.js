/**
 * $Id: editor_plugin_src.js 201 2007-02-12 15:56:56Z spocke $
 *
 * @author Moxiecode
 * @copyright Copyright © 2004-2008, Moxiecode Systems AB, All rights reserved.
 */

(function() {
	// Load plugin specific language pack
	tinymce.PluginManager.requireLangPack('lswikilink');

	tinymce.create('tinymce.plugins.LsWikiLinkPlugin', {
		/**
		 * Initializes the plugin, this will be executed after the plugin has been created.
		 * This call is done before the editor instance has finished it's initialization so use the onInit event
		 * of the editor instance to intercept that event.
		 *
		 * @param {tinymce.Editor} ed Editor instance that the plugin is initialized in.
		 * @param {string} url Absolute URL to where the plugin is located.
		 */
		init : function(ed, url) {
			// Register the command so that it can be invoked by using tinyMCE.activeEditor.execCommand('mceExample');
			ed.addCommand('mceWikiLink', function() {
				// ed.windowManager.open({
				// 					file : url + '/dialog.htm',
				// 					width : 320 + parseInt(ed.getLang('example.delta_width', 0)),
				// 					height : 120 + parseInt(ed.getLang('example.delta_height', 0)),
				// 					inline : 1
				// 				}, {
				// 					plugin_url : url, // Plugin absolute URL
				// 					some_custom_arg : 'custom arg' // Custom argument
				// 				});
				
				new PopupForm('onLoadWikiLinkPopup');
			});

			ed.addCommand('mceWikiLinkInsert', function(p1, value) {
				if (tinyMCE.activeEditor.selection.getContent().length)
					tinyMCE.execCommand("mceInsertLink", false, 'wiki_url('+value[0]+')');
				else
				{
					tinyMCE.activeEditor.execCommand('mceInsertContent', false, 'wiki_link('+value[0]+')');
				}
			});
						
			// Register example button
			ed.addButton('lswikilink', {
				title : 'lswikilink.desc',
				cmd : 'mceWikiLink',
				image : url + '/images/page_link.png'
			});

			// Add a node change handler, selects the button in the UI when a image is selected
			ed.onNodeChange.add(function(ed, cm, n) {
				//cm.setActive('example', n.nodeName == 'IMG');
			});
		},

		/**
		 * Creates control instances based in the incomming name. This method is normally not
		 * needed since the addButton method of the tinymce.Editor class is a more easy way of adding buttons
		 * but you sometimes need to create more complex controls like listboxes, split buttons etc then this
		 * method can be used to create those.
		 *
		 * @param {String} n Name of the control to create.
		 * @param {tinymce.ControlManager} cm Control manager to use inorder to create new control.
		 * @return {tinymce.ui.Control} New control instance or null if no control was created.
		 */
		createControl : function(n, cm) {
			return null;
		},

		/**
		 * Returns information about the plugin as a name/value array.
		 * The current keys are longname, author, authorurl, infourl and version.
		 *
		 * @return {Object} Name/value array containing information about the plugin.
		 */
		getInfo : function() {
			return {
				longname : 'LemonStand Wiki insert link plugin',
				author : 'Limewheel Creative Inc.',
				authorurl : 'http://lemonstandapp.com',
				version : "1.0"
			};
		}
	});

	// Register plugin
	tinymce.PluginManager.add('lswikilink', tinymce.plugins.LsWikiLinkPlugin);
})();