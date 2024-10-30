<?php
/**
 * Plugin Name: &copy;Feed
 * Plugin URI:  http://bueltge.de/wp-feed-plugin/204/
 * Text Domain: copyfeed
 * Domain Path: /languages
 * Description: Extends the feed! A report of copyright, a digital fingerprint and the IP of the feed reader can be added. In addition, some search engines are scanned for the digital fingerprint in order to find possible content theft. The feed can be also be supplemented with comments and topic-relevant contributions. The complete RSS feed can be delivered even if the <code>&lt;--more--&gt;</code> tag is used in WP 2.1+. It is possible to view related post using the plugin <em>Simple Tagging</em> or <em>Simple Tags</em>.
 * Author:      Frank Bültge
 * Version:     4.7.10
 * License:     GPLv3
 * Author URI:  https://bueltge.de/
 *
 *
 * ------------------------------------------------------------------------------------
 *  ACKNOWLEDGEMENTS
 * ------------------------------------------------------------------------------------
 * Thanks to Angsuman Chakraborty for his idea and plugin "Angsuman's Feed Copyrighter"
 * http://blog.taragana.com/
 *
 * Filtering own blog entries from Christian Hess-Gruenig
 * http://christian.hess-gruenig.de
 *
 * Translate in english by Sadly
 * http://www.sadlyno.com
 * ------------------------------------------------------------------------------------
 */

// Pre-2.6 compatibility
if ( ! defined('WP_CONTENT_URL') )
	define( 'WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
if ( ! defined('WP_CONTENT_DIR') )
	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );


/**
 * load language file
 *
 */
function fbcf_textdomain() {

	if (function_exists('load_plugin_textdomain')) {
		if ( !defined('WP_PLUGIN_DIR') ) {
			load_plugin_textdomain('copyfeed', str_replace( ABSPATH, '', dirname(__FILE__) ) . '/languages');
		} else {
			load_plugin_textdomain('copyfeed', false, dirname( plugin_basename(__FILE__) ) . '/languages');
		}
	}
}


/**
 * Add action link(s) to plugins page
 * Thanks Dion Hulse -- http://dd32.id.au/wordpress-plugins/?configure-link
 */
function fbcf_filter_plugin_actions( $links, $file ) {
	static $this_plugin;

	if ( ! $this_plugin )
		$this_plugin = plugin_basename(__FILE__);

	if( $file == $this_plugin ) {
		$settings_link = '<a href="options-general.php?page=copyfeed.php">' . __('Settings') . '</a>';
		$links = array_merge( array($settings_link), $links); // before other links
//	$links[] = $settings_link; // ... or after other links
	}
	return $links;
}


/**
 * settings in plugin-admin-page
 */
function fbcf_add_settings_page() {
	if( current_user_can('switch_themes') ) {
		if ( empty($viewcount) )
			$viewcount = '';
		add_options_page('&copy;Feed', '&copy;Feed' . $viewcount, 'manage_options', basename(__FILE__), 'fbcf_sub_page');
		add_filter('plugin_action_links', 'fbcf_filter_plugin_actions', 10, 2);
	}
}


/**
 * credit in wp-footer
 */
function fbcf_add_admin_footer() {
	if ( basename($_SERVER['REQUEST_URI']) == 'options-general.php?page=copyfeed.php') {
		$plugin_data = get_plugin_data( __FILE__ );
		printf('%1$s ' . __('plugin') . ' | ' . __('Version') . ' <a href="http://bueltge.de/wp-feed-plugin/204/#historie" title="'
            . __('History', 'copyfeed') . '">%2$s</a> | ' . __('Author') . ' %3$s<br />', $plugin_data['Title'], $plugin_data['Version'], $plugin_data['Author']);
	}
}


/**
 * For function fetch_rss
 * @include magpie-function of wp-core
 */
if (file_exists(ABSPATH . WPINC . '/rss.php')) {
	@require_once (ABSPATH . WPINC . '/rss.php');
	// It's Wordpress 2.1.* since it has been loaded successfully
} elseif (file_exists(ABSPATH . WPINC . '/rss-functions.php')) {
	@require_once (ABSPATH . WPINC . '/rss-functions.php');
	// In Wordpress < 2.1, a other file name is being used
} else {
	die (__('Error in file: ', 'copyfeed') . __FILE__ . __(' on line: ', 'copyfeed')
        . __LINE__ . __('<br />The WordPress file "rss-functions.php" or "rss.php" could not be included.', 'copyfeed'));
}


/**
 * Filtering own posts
 * @get_option fbcf_rss_filter_own_posts
 */
if ($fbcf_rss_filter_own_posts = array_key_exists('copyrightfeed_options_save', $_POST)) {
	$_POST['fbcf_rss_filter_own_posts'];
} else {
	 get_option('fbcf_rss_filter_own_posts'); // necessary for immediate effect after option change
}

/**
 * Filter function to remove own-blog-entries from RSS
 *
 * @param $array_element
 *
 * @return bool $pageUrl
 */
function fbcf_filter_feed_own_posts($array_element) {

	$pageUrl          = get_bloginfo('url');
	$pageUrlLength    = strlen($pageUrl);

	return ($pageUrl != substr($array_element['link'], 0, $pageUrlLength));
}

/**
 * Wrapper for fetch_rss: If OPTION set, removes own blog posts in search result
 *
 * @param $url
 *
 * @return bool|MagpieRSS $return_rss
 */
function fbcf_filtered_fetch_rss($url) {
	global $fbcf_rss_filter_own_posts;

	$result_rss = fetch_rss($url);

	if ($fbcf_rss_filter_own_posts == 1) {
		if (is_array($result_rss->items)) {
			$result_rss->items = array_filter($result_rss->items, 'fbcf_filter_feed_own_posts');
		} else {
			$result_rss->items = array($result_rss->items);
			$result_rss->items = array_filter($result_rss->items, 'fbcf_filter_feed_own_posts');
		}
	}

	return $result_rss;
}

/**
 * check url
 *
 * @param $url
 *
 * @return boolean
 */
function fbcf_check_url($url) {

	if ($url) {
		$fbcf_search_timeout = get_option('fbcf_search_timeout');
		if ($fbcf_search_timeout == 0) {
			$url = 'http://' . $url;
			$fp = @fopen($url, "r");
		} else {
			$fp = @fsockopen($url, 80, $errno, $errstr, $fbcf_search_timeout);
		}
	}

	if ($fp) {
		$return = true;
		fclose($fp);
	} else {
		$return = false;
	}

	return $return;
}


/**
 * some basic security with nonce
 * @var $fbcf_nonce
 */
$fbcf_nonce = '';
if ( ! function_exists('wp_nonce_field') ) {
    /**
     * @param int $action
     */
    function fbcf_nonce_field($action = -1) {
		return;
	}
	$fbcf_nonce = -1;
} else {
    /**
     * @param int $action
     *
     * @return string
     */
    function fbcf_nonce_field($action = -1) {
		return wp_nonce_field($action);
	}
	$fbcf_nonce = 'fbcf-update-key';
}


/**
 * options-page in wp-admin
 */
function fbcf_sub_page() {
	global $wp_version, $fbcf_nonce;

	if ( isset($_POST['fbcf_copyright_message_start']) && $_POST['fbcf_copyright_message_start'] ) {
		fbcf_update();
	}

	if ( isset($_POST['action']) && ($_POST['action'] == 'deactivate') && $_POST['fbcf_uninstall'] ) {

		if ( function_exists('current_user_can') && current_user_can('edit_plugins') ) {
			check_admin_referer($fbcf_nonce);
			fbcf_uninstall();
		} else {
			wp_die('<p>'.__('You do not have sufficient permissions to edit plugins for this blog.').'</p>');
		}
	}

	// Get options for form fields
	$fingerprint = md5( uniqid( rand() ) );
	$fbcf_search_fingerprint = str_replace(" ", "+", get_option('fbcf_fingerprint'));

	if ( ((get_option('fbcf_search_fingerprint') == 1) && strpos($_SERVER['REQUEST_URI'], 'copyfeed.php')) || ((get_option('fbcf_search_fingerprint') == 1) && strpos($_SERVER['REQUEST_URI'], 'index.php')) ) {

		$search_result_answer_true = __('Domain is reachable.', 'copyfeed');
		$search_result_answer_false  = __('Domain is <b>not</b> reachable.', 'copyfeed');

		// google blog
		$googleblog_search_result_url = 'http://blogsearch.google.com/blogsearch_feeds?hl=en&amp;q=%22' . $fbcf_search_fingerprint . '%22&amp;scoring=d&amp;ie=utf-8&num=10&output=rss';
		if ( fbcf_check_url('blogsearch.google.com') ) {
			$googleblog_search_result_rss = fbcf_filtered_fetch_rss($googleblog_search_result_url);
			$googleblog_search_result = count($googleblog_search_result_rss->items);
			$googleblog_search_result_answer = $search_result_answer_true;
		} else {
			$googleblog_search_result = 0;
			$googleblog_search_result_answer = $search_result_answer_false;
		}

		// icerocket
		$icerocket_search_result_url = 'http://www.icerocket.com/search?tab=blog&amp;q=%22' . $fbcf_search_fingerprint . '%22&rss=1';
		if ( fbcf_check_url('www.icerocket.com') ) {
			$icerocket_search_result_rss = fbcf_filtered_fetch_rss($icerocket_search_result_url);
			$icerocket_search_result = count($icerocket_search_result_rss->items);
			$icerocket_search_result_answer = $search_result_answer_true;
		} else {
			$icerocket_search_result = 0;
			$icerocket_search_result_answer = $search_result_answer_false;
		}

		// bloglines
		$bloglines_search_result_url = 'http://www.bloglines.com/search?q=%22' . $fbcf_search_fingerprint . '%22&s=fr&pop=l&news=m&amp;format=rss';
		if ( fbcf_check_url('www.bloglines.com') ) {
			$bloglines_search_result_rss = fbcf_filtered_fetch_rss($bloglines_search_result_url);
			$bloglines_search_result = count($bloglines_search_result_rss->items);
			$bloglines_search_result_answer = $search_result_answer_true;
		} else {
			$bloglines_search_result = 0;
			$bloglines_search_result_answer = $search_result_answer_false;
		}

		// blogpulse
		$blogpulse_search_result_url = 'http://www.blogpulse.com/rss?query=%22' . $fbcf_search_fingerprint . '%22&amp;sort=date&amp;operator=phrase';
		if ( fbcf_check_url('www.blogpulse.com') ) {
			$blogpulse_search_result_rss = fbcf_filtered_fetch_rss($blogpulse_search_result_url);
			$blogpulse_search_result = count($blogpulse_search_result_rss->items);
			$blogpulse_search_result_answer = $search_result_answer_true;
		} else {
			$blogpulse_search_result = 0;
			$blogpulse_search_result_answer = $search_result_answer_false;
		}

		// sphere
		$sphere_search_result_url = 'http://rss.sphere.com/rss?q=%22' . $fbcf_search_fingerprint . '%22';
		if ( fbcf_check_url('www.sphere.com') ) {
			$sphere_search_result_rss = fbcf_filtered_fetch_rss($sphere_search_result_url);
			$sphere_search_result = count($sphere_search_result_rss->items);
			$sphere_search_result_answer = $search_result_answer_true;
		} else {
			$sphere_search_result = 0;
			$sphere_search_result_answer = $search_result_answer_false;
		}
	}
?>

	<div class="wrap" id="top">
			<h2><?php _e('&copy;Feed', 'copyfeed'); ?></h2>

			<h3><?php _e('&copy;Feed MiniMenu', 'copyfeed'); ?></h3>
			<ul>
				<li><a href="#config" ><?php _e('Options &copy;Feed', 'copyfeed'); ?></a></li>
				<li><a href="#config_comment" ><?php _e('Options for comments in feed', 'copyfeed'); ?></a></li>
				<li><a href="#config_related_post" ><?php _e('Options for related post in feed', 'copyfeed'); ?></a> </li>
			<?php if ( version_compare($wp_version, '2.5', '<') || (get_option('fbcf_fullfeed') == '1') ) { ?>
				<li><a href="#config_fullfeed" ><?php _e('FullFeed', 'copyfeed'); ?></a> </li>
			<?php } ?>
				<li><a href="#view" ><?php _e('Preview', 'copyfeed'); ?></a></li>
				<?php if (get_option('fbcf_search_fingerprint') == 1) { ?><li><a href="#search" ><?php _e('Search for a digital fingerprint', 'copyfeed'); ?></a> </li><?php } ?>
				<li><a href="#more" ><?php _e('Further search options', 'copyfeed'); ?></a></li>
				<li><a href="#submitbutton" ><?php _e('UPDATE &raquo;', 'copyfeed'); ?></a></li>
				<li><a href="#hint_to_plugin" ><?php _e('Information on plugin/GUID', 'copyfeed'); ?></a></li>
				<li><a href="#uninstall" ><?php _e('Uninstall', 'copyfeed'); ?></a> </li>
			</ul>

			<form method="post" id="copyrightfeed_options" action="" >
				<?php fbcf_nonce_field($fbcf_nonce); ?>

				<h3 id="config"><?php _e('Options &copy;Feed', 'copyfeed') ?></h3>
				<table summary="config" class="form-table">
					<tr valign="top">
						<th><?php _e('Start of copyright notice', 'copyfeed'); ?></th>
						<td><textarea style="width: 95%;" class="code" rows="5" cols="60" name="fbcf_copyright_message_start" id="fbcf_copyright_message_start" ><?php echo htmlspecialchars(stripslashes(get_option('fbcf_copyright_message_start'))); ?></textarea><br /><?php _e('Use HTML, use of PHP is not possible. You may use %title%, %author%, %authorlink%, %date%, %time%, %permalink% and %commentsnumber% as placeholders.<br />Example: &lt;a href="%permalink%#comments" title="to the comments"&gt;To the comments&lt;/a&gt;, Author: &lt;a href="%authorlink%" &gt;%author%&lt;/a&gt;', 'copyfeed'); ?></td>
					</tr>
					<tr valign="top">
						<th><?php _e('digital fingerprint', 'copyfeed'); ?></th>
						<td><textarea style="width: 95%;" rows="1" cols="60" name="fbcf_fingerprint" id="fbcf_fingerprint" ><?php echo get_option('fbcf_fingerprint'); ?></textarea><br /><?php _e('Possible key:', 'copyfeed'); echo ' ' . $fingerprint; ?></td>
					</tr>
					<tr valign="top">
						<th><?php _e('End of copyright notice', 'copyfeed'); ?></th>
						<td><textarea style="width: 95%;" class="code" rows="5" cols="60" name="fbcf_copyright_message_end" id="fbcf_copyright_message_end" ><?php echo htmlspecialchars(stripslashes(get_option('fbcf_copyright_message_end'))); ?></textarea><br /><?php _e('Use HTML, use of PHP is not possible. You may use %title%, %author%, %authorlink%, %date%, %time%, %permalink%, and %commentsnumber% as placeholders.<br />Example: &lt;a href="%permalink%#comments" title="to the comments"&gt;To the comments&lt;/a&gt;, Author: &lt;a href="%authorlink%" &gt;%author%&lt;/a&gt;', 'copyfeed'); ?></td>
					</tr>
					<tr valign="top">
						<th><?php _e('On Dashboard', 'copyfeed'); ?></th>
						<td><input name="fbcf_dashboardinfo" id="fbcf_dashboardinfo" value='1' <?php if (get_option('fbcf_dashboardinfo') == '1') { echo "checked='checked'"; } ?> type="checkbox" /> <label for="fbcf_dashboardinfo"><?php _e('Show content theft in Dashboard?', 'copyfeed'); ?></label></td>
					</tr>
					<tr valign="top">
						<th><?php _e('Scan', 'copyfeed'); ?></th>
						<td><input name="fbcf_search_fingerprint" id="fbcf_search_fingerprint" value='1' <?php if (get_option('fbcf_search_fingerprint') == '1') { echo "checked='checked'"; } ?> type="checkbox" /> <label for="fbcf_search_fingerprint"> <?php _e('Scan for the digital fingerprint in order to find possible contenttheft?', 'copyfeed'); ?></label></td>
					</tr>
					<tr valign="top">
						<th><?php _e('Scantime', 'copyfeed'); ?></th>
						<td>
							<?php $fbcf_search_timeout = get_option('fbcf_search_timeout'); ?>
							<select name="fbcf_search_timeout">
								<option value="0"<?php if($fbcf_search_timeout == '0') { echo ' selected="selected"'; } ?>><?php _e('Fast', 'copyfeed'); ?></option>
								<option value="1"<?php if($fbcf_search_timeout == '1') { echo ' selected="selected"'; } ?>>1</option>
								<option value="3"<?php if($fbcf_search_timeout == '3') { echo ' selected="selected"'; } ?>>3</option>
								<option value="5"<?php if($fbcf_search_timeout == '5') { echo ' selected="selected"'; } ?>>5</option>
								<option value="10"<?php if($fbcf_search_timeout == '10') { echo ' selected="selected"'; } ?>>10</option>
								<option value="30"<?php if($fbcf_search_timeout == '30') { echo ' selected="selected"'; } ?>>30</option>
							</select> <?php _e('How long may the check for domain availability take (in seconds)?', 'copyfeed'); ?>
						</td>
					</tr>
					<tr valign="top">
						<th><?php _e('Hide own blog', 'copyfeed'); ?></th>
						<td><input name="fbcf_rss_filter_own_posts" id="fbcf_rss_filter_own_posts" value='1' <?php if (get_option('fbcf_rss_filter_own_posts') == '1') { echo ' checked="checked"'; } ?> type="checkbox" /> <label for="fbcf_rss_filter_own_posts"><?php _e('Hide search results of your own blog?', 'copyfeed'); ?></label></td>
					</tr>
					<tr valign="top">
						<th><?php _e('Feedreader IP', 'copyfeed'); ?></th>
						<td><input name="fbcf_ipinfo" id="fbcf_ipinfo" value='1' <?php if (get_option('fbcf_ipinfo') == '1') { echo "checked='checked'";  } ?> type="checkbox" /> <label for="fbcf_ipinfo"> <?php _e('Include the IP address of the feed reader?', 'copyfeed'); ?></label></td>
					</tr>
					<tr valign="top">
						<th><?php _e('Short Feed', 'copyfeed'); ?></th>
						<td><input name="fbcf_excerptfeed" id="fbcf_excerptfeed" value='1' <?php if (get_option('fbcf_excerptfeed') == '1') { echo "checked='checked'";  } ?> type="checkbox" /> <label for="fbcf_excerptfeed"> <?php _e('Also attach on short version of the feed?', 'copyfeed'); ?><br /><?php _e('If the feed in shortened version is spent, then is the report of copyright likewise angehangen to become. The attitude applies also to comments and similar contributions.', 'copyfeed'); ?></label></td>
					</tr>
					<tr valign="top">
						<th><?php _e('WhiteList', 'copyfeed'); ?></th>
						<td><textarea name="fbcf_whitelist" cols="60" rows="5" id="fbcf_whitelist" style="width: 95%;" class="code"><?php form_option('fbcf_whitelist'); ?></textarea><br /><?php echo __('One URL or IP per line and thus those copyright report one does not insert. Please enter the domain without HTTP:// and without Subdomain (I.E.: 123.123.23.1, example.com or only example). This does also applie to all other settings like similar contributions and comments.', 'copyfeed'); ?></td>
					</tr>
					<tr valign="top">
						<th><?php _e('BlackList', 'copyfeed'); ?></th>
						<td><textarea name="fbcf_blacklist" cols="60" rows="5" id="fbcf_blacklist" style="width: 95%;" class="code"><?php form_option('fbcf_blacklist'); ?></textarea><br /><?php echo __('One URL or IP per line and thus those copyright report one does not insert. Please enter the domain without HTTP:// and without Subdomain (I.E.: 123.123.23.1, example.com or only example). This does also applie to all other settings like similar contributions and comments.', 'copyfeed'); ?></td>
					</tr>
					<tr valign="top">
						<th><?php _e('BlackList Message', 'copyfeed'); ?></th>
						<td><textarea style="width: 95%;" class="code" rows="5" cols="60" name="fbcf_blacklist_message" id="fbcf_blacklist_message" ><?php echo htmlspecialchars(stripslashes(get_option('fbcf_blacklist_message'))); ?></textarea><br /><?php echo __('Use HTML, use of PHP is not possible.', 'copyfeed'); ?></td>
					</tr>
				</table>

				<div class="tablenav">
					<div style="float: right"><a href="#wpbody"><?php _e('Top', 'copyfeed'); ?></a></div>
				</div>

				<h3 id="config_comment"><?php _e('Options for comments in feed', 'copyfeed') ?></h3>
				<table summary="config" class="form-table">
					<tr valign="top">
						<th><?php _e('Comments in feed?', 'copyfeed'); ?></th>
						<td><input name="fbcf_commentinfeed" id="fbcf_commentinfeed" value='1' <?php if (get_option('fbcf_commentinfeed') == '1') { echo "checked='checked'";  } ?> type="checkbox" /> <label for="fbcf_commentinfeed"><?php _e('Checked for yes - i like comments in feed?', 'copyfeed'); ?></label></td>
					</tr>
					<tr valign="top">
						<th><?php _e('Before the comments', 'copyfeed'); ?></th>
						<td><textarea style="width: 95%;" rows="1" cols="60" name="fbcf_commentinfeed_before" id="fbcf_commentinfeed_before" ><?php echo htmlspecialchars(stripslashes(get_option('fbcf_commentinfeed_before'))); ?></textarea><br /><?php _e('Use HTML, use of PHP is not possible. Examples: &lt;h2&gt;Comment&lt;/h2&gt;&lt;ul&gt;', 'copyfeed'); ?></td>
					</tr>
					<tr valign="top">
						<th><?php _e('Layout of the comments', 'copyfeed'); ?></th>
						<td><textarea style="width: 95%;" class="code" rows="5" cols="60" name="fbcf_commentinfeed_layout" id="fbcf_commentinfeed_layout" ><?php echo htmlspecialchars(stripslashes(get_option('fbcf_commentinfeed_layout'))); ?></textarea><br /><?php echo __('Use HTML, use of PHP is not possible. You may use %title%, %author%, %authorlink%, %date%, %time%, %comment%, %excerpt%, %permalink%, %commentpermalink%, %trackback% and %commentsnumber% as placeholders.<br />Example: &lt;li&gt;&lt;a href="%permalink%" &gt;%date%&lt;/a&gt;, %authorlink% write:%comment%&lt;li&gt;', 'copyfeed'); ?></td>
					</tr>
					<tr valign="top">
						<th><?php _e('After the comments', 'copyfeed'); ?></th>
						<td><textarea style="width: 95%;" rows="1" cols="60" name="fbcf_commentinfeed_after" id="fbcf_commentinfeed_after" ><?php echo htmlspecialchars(stripslashes(get_option('fbcf_commentinfeed_after'))); ?></textarea><br /><?php _e('Use HTML, use of PHP is possible. Examples: &lt;/ul&gt;', 'copyfeed'); ?></td>
					</tr>
				</table>

				<div class="tablenav">
					<div style="float: right"><a href="#wpbody"><?php _e('Top', 'copyfeed'); ?></a></div>
				</div>

				<h3 id="config_related_post"><?php _e('Options related post in feed', 'copyfeed') ?></h3>
				<table summary="config_related_post" class="form-table">
					<tr valign="top">
						<th><?php _e('Related Post', 'copyfeed'); ?></th>
						<td><input name="fbcf_relatedpostinfeed" id="fbcf_relatedpostinfeed" value='1' <?php if (get_option('fbcf_relatedpostinfeed') == '1') { echo "checked='checked'"; } ?> type="checkbox" /> <label for="fbcf_relatedpostinfeed"><?php _e('Include similar content in feed?', 'copyfeed'); ?></label></td>
					</tr>
					<tr valign="top">
						<th><?php _e('How much', 'copyfeed'); ?></th>
						<td><input name="fbcf_relatedpostinfeed_limit" id="fbcf_relatedpostinfeed_limit" type="text" size="2" value="<?php echo get_option('fbcf_relatedpostinfeed_limit'); ?>" /> <?php _e('Number of items with similar content', 'copyfeed'); ?> <?php _e('How many titles should be displayed?', 'copyfeed'); ?></td>
					</tr>
					<tr valign="top">
						<th><?php _e('Passwordposts', 'copyfeed'); ?></th>
						<td><input name="fbcf_relatedpostinfeed_show_pass_post" id="fbcf_relatedpostinfeed_show_pass_post" value='1' <?php if (get_option('fbcf_relatedpostinfeed_show_pass_post') == '1') { echo "checked='checked'"; } ?> type="checkbox" /> <label for="fbcf_relatedpostinfeed_show_pass_post"><?php _e('Identify posts with password?', 'copyfeed'); ?></label></td>
					</tr>
					<tr valign="top">
						<th><?php _e('Add Excerpt', 'copyfeed'); ?></th>
						<td><input name="fbcf_relatedpostinfeed_show_excerpt" id="fbcf_relatedpostinfeed_show_excerpt" value='1' <?php if (get_option('fbcf_relatedpostinfeed_show_excerpt') == '1') { echo "checked='checked'"; } ?> type="checkbox" /> <label for="fbcf_relatedpostinfeed_show_excerpt"><?php _e('Add excerpt of posts?', 'copyfeed'); ?></label></td>
					</tr>
					<tr valign="top">
						<th><?php _e('Number of words', 'copyfeed'); ?></th>
						<td><input name="fbcf_relatedpostinfeed_len" id="fbcf_relatedpostinfeed_len" type="text" size="2" value="<?php echo get_option('fbcf_relatedpostinfeed_len'); ?>" /> <?php _e('How many words should be included in post excerpts?', 'copyfeed'); ?></td>
					</tr>
					<tr valign="top">
						<th><?php _e('Before items with similar content', 'copyfeed'); ?></th>
						<td><textarea style="width: 95%;" rows="1" cols="60" name="fbcf_relatedpostinfeed_before" id="fbcf_relatedpostinfeed_before" ><?php echo htmlspecialchars(stripslashes(get_option('fbcf_relatedpostinfeed_before'))); ?></textarea><br /><?php _e('Use HTML, use of PHP is possible. Examples: &lt;/ul&gt; &lt;h2&gt;Related posts&lt;/h2&gt;&lt;ul&gt;', 'copyfeed'); ?></td>
					</tr>
					<tr valign="top">
						<th><?php _e('Before the titles of items with similar content', 'copyfeed'); ?></th>
						<td><textarea style="width: 95%;" rows="1" cols="60" name="fbcf_relatedpostinfeed_before_title" id="fbcf_relatedpostinfeed_before_title" ><?php echo htmlspecialchars(stripslashes(get_option('fbcf_relatedpostinfeed_before_title'))); ?></textarea><br /><?php _e('Use HTML, use of PHP is not possible. Examples: &lt;li&gt;', 'copyfeed'); ?></td>
					</tr>
					<tr valign="top">
						<th><?php _e('After the titles of items with similar content', 'copyfeed'); ?></th>
						<td><textarea style="width: 95%;" rows="1" cols="60" name="fbcf_relatedpostinfeed_after_title" id="fbcf_relatedpostinfeed_after_title" ><?php echo htmlspecialchars(stripslashes(get_option('fbcf_relatedpostinfeed_after_title'))); ?></textarea><br /><?php _e('Use HTML, use of PHP is not possible. Examples: &lt;/li&gt;', 'copyfeed'); ?></td>
					</tr>
					<tr valign="top">
						<th><?php _e('After the items with similar content', 'copyfeed'); ?></th>
						<td><textarea style="width: 95%;" rows="1" cols="60" name="fbcf_relatedpostinfeed_after" id="fbcf_relatedpostinfeed_after" ><?php echo htmlspecialchars(stripslashes(get_option('fbcf_relatedpostinfeed_after'))); ?></textarea><br /><?php _e('Use HTML, use of PHP is not possible. Examples: &lt;/ul&gt;', 'copyfeed'); ?></td>
					</tr>
					<?php if ( class_exists('SimpleTagging') || class_exists('SimpleTags')) { ?>
					<tr valign="top">
						<th><?php _e('Simple Tags/Simple Tagging', 'copyfeed'); ?></th>
						<td><input name="fbcf_stp_relatedpostinfeed" id="fbcf_stp_relatedpostinfeed" value='1' <?php if (get_option('fbcf_stp_relatedpostinfeed') == '1') { echo "checked='checked'"; } ?> type="checkbox" /> <label><?php _e('Simple Tags/Simple Tagging Related Post in feed?', 'copyfeed'); ?><br /><?php _e('You have the plugin Simple Tags/Simple Tagging actively and can on basis this plugins the related posts in the feed indicate.', 'copyfeed'); ?></label></td>
					</tr>
					<?php } ?>
				</table>

				<div class="tablenav">
					<div style="float: right"><a href="#wpbody"><?php _e('Top', 'copyfeed'); ?></a></div>
				</div>

			<?php if ( version_compare($wp_version, '2.5', '<') || (get_option('fbcf_fullfeed') == '1') ) { ?>
				<h3 id="config_fullfeed"><?php _e('Options FullFeed', 'copyfeed') ?></h3>
				<table summary="config_fullfeed" class="form-table">
					<tr valign="top">
						<th><?php _e('Full Text Feed?', 'copyfeed'); ?></th>
						<td><input name="fbcf_fullfeed" id="fbcf_fullfeed" value='1' <?php if (get_option('fbcf_fullfeed') == '1') { echo "checked='checked'";  } ?> type="checkbox" /> <label for="fbcf_fullfeed"><?php _e('Prevents WordPress 2.1+ from adding a more link to your website\'s feed. WordPress 2.1 introduces a nasty "feature" to feeds, no more in WordPress 2.5. Instead of abiding by your feed preferences, WordPress will truncate your feed\'s content anywhere you insert a &lt;!--more--&gt; tag. Full Text Feed undos this functionality, allowing your feeds to contain the full text regardless of &lt;!--more--&gt; usage.', 'copyfeed'); ?></label></td>
					</tr>
				</table>

				<div class="tablenav">
					<div style="float: right"><a href="#wpbody"><?php _e('Top', 'copyfeed'); ?></a></div>
				</div>

			<?php } ?>

				<h3 id="view"><?php _e('Preview &copy;Feed', 'copyfeed') ?></h3>
				<table summary="view" class="form-table">
					<tr valign="top">
						<td>
							<?php
							// copyright message
							// IP of reader
							if (get_option('fbcf_ipinfo') == 1) {
								$fbfc_ipadress = $_SERVER['REMOTE_ADDR'];
								$copyright = fbcf_copyright_layout( stripslashes( get_option('fbcf_copyright_message_start') ) ) . ' ' . get_option('fbcf_fingerprint') . ' (' . $fbfc_ipadress . ') ' . fbcf_copyright_layout( stripslashes( get_option('fbcf_copyright_message_end') ) );
							} else {
								$copyright = fbcf_copyright_layout( stripslashes( get_option('fbcf_copyright_message_start') ) ) . ' ' . get_option('fbcf_fingerprint') . fbcf_copyright_layout( stripslashes( get_option('fbcf_copyright_message_end') ) );
							}
							echo $copyright;
							?>
						</td>
					</tr>
					<tr>
						<td><input type="button" class="button-secondary" value="<?php _e('Update Preview', 'copyfeed'); ?>" onClick="parent.top.location='options-general.php?page=copyfeed.php'" /> <?php _e('When you change the options of &copy;Feed; you can update the preview.', 'copyfeed'); ?></td>
					</tr>
				</table>

				<div class="tablenav">
					<div style="float: right"><a href="#wpbody"><?php _e('Top', 'copyfeed'); ?></a></div>
				</div>

				<?php if ( ((get_option('fbcf_search_fingerprint') == 1) && strpos($_SERVER['REQUEST_URI'], 'copyfeed.php')) || ((get_option('fbcf_search_fingerprint') == 1) && strpos($_SERVER['REQUEST_URI'], 'index.php')) ) { ?>
				<h3 id="search"><?php _e('Scan for the digital fingerprint', 'copyfeed') ?></h3>
				<table summary="search" class="form-table">
					<tr valign="top">
						<td>
							<?php _e('Google Blog Search', 'copyfeed'); ?>
							<ul>
								<?php if ($googleblog_search_result > 0 ) {
									foreach ( $googleblog_search_result_rss->items as $item ) {
										echo '<li><a href="' . $item['link'] . '" title="' . $item['title'] . '">' . $item['title'] . '</a> - '. $item['link'] .'</li>';
									}
									echo '<li><small>' . __('Search further with:', 'copyfeed') . ' <a href="http://blogsearch.google.com/blogsearch?hl=en&amp;q=%22' . $fbcf_search_fingerprint . '%22&amp;ie=UTF-8&amp;scoring=d">Google Blog Search</a></small></li>';
								} else {
									echo '<li>' . $googleblog_search_result_answer . '</li>';
									echo '<li>' . __('Search further with:', 'copyfeed') . ' <a href="http://blogsearch.google.com/blogsearch?hl=en&amp;q=%22' . $fbcf_search_fingerprint . '%22&amp;ie=UTF-8&amp;scoring=d">Google Blog Search</a>.</li>';
								} ?>
							</ul>
						</td>
					</tr>
					<tr valign="top">
						<td>
							<?php _e('IceRocket Blog Search', 'copyfeed'); ?>
							<ul>
								<?php if ($icerocket_search_result > 0 ) {
											foreach ( $icerocket_search_result_rss->items as $item ) {
												echo '<li><a href="' . $item['link'] . '" title="' . $item['title'] . '">' . $item['title'] . '</a> - '. $item['link'] .'</li>';
											}
											echo '<li><small>' . __('Search further with:', 'copyfeed') . ' <a href="http://blogs.icerocket.com/search?q=%22' . $fbcf_search_fingerprint . '%22">IceRocket Blog Search</a></small></li>';
										} else {
											echo '<li>' . $icerocket_search_result_answer . '</li>';
											echo '<li>' . __('Search further with:', 'copyfeed') . ' <a href="http://blogs.icerocket.com/search?q=%22' . $fbcf_search_fingerprint . '%22">IceRocket Blog Search</a>.</li>';
										} ?>
							</ul>
						</td>
					</tr>
					<tr valign="top">
						<td>
							<?php _e('Bloglines', 'copyfeed'); ?>
							<ul>
								<?php if ($bloglines_search_result > 0 ) {
											foreach ( $bloglines_search_result_rss->items as $item ) {
												echo '<li><a href="' . $item['link'] . '" title="' . $item['title'] . '">' . $item['title'] . '</a> - '. $item['link'] .'</li>';
											}
											echo '<li><small>' . __('Search further with:', 'copyfeed') . ' <a href="http://www.bloglines.com/search?q=%22' . $fbcf_search_fingerprint . '%22">Bloglines Blog Search</a></small></li>';
										} else {
											echo '<li>' . $bloglines_search_result_answer . '</li>';
											echo '<li>' . __('Search further with:', 'copyfeed') . ' <a href="http://www.bloglines.com/search?q=%22' . $fbcf_search_fingerprint . '%22">Bloglines Blog Search</a>.</li>';
										} ?>
							</ul>
						</td>
					</tr>
					<tr valign="top">
						<td>
							<?php _e('BlogPulse Search', 'copyfeed'); ?>
							<ul>
								<?php if ($blogpulse_search_result > 0 ) {
											foreach ( $blogpulse_search_result_rss->items as $item ) {
												echo '<li><a href="' . $item['link'] . '" title="' . $item['title'] . '">' . $item['title'] . '</a> - '. $item['link'] .'</li>';
											}
											echo '<li><small>' . __('Search further with:', 'copyfeed') . ' <a href="http://www.blogpulse.com/search?boolean=false&amp;operator=phrase&amp;keywords=' . $fbcf_search_fingerprint . '">BlogPulse</a></small></li>';
										} else {
											echo '<li>' . $blogpulse_search_result_answer . '</li>';
											echo '<li>' . __('Search further with:', 'copyfeed') . ' <a href="http://www.blogpulse.com/search?boolean=false&amp;operator=phrase&amp;keywords=' . $fbcf_search_fingerprint . '">BlogPulse</a>.</li>';
										} ?>
							</ul>
						</td>
					</tr>
					<tr valign="top">
						<td>
							<?php _e('Sphere Blog Posts', 'copyfeed'); ?>
							<ul>
								<?php if ($sphere_search_result > 0 ) {
											foreach ( $sphere_search_result_rss->items as $item ) {
												echo '<li><a href="' . $item['link'] . '" title="' . $item['title'] . '">' . $item['title'] . '</a> - '. $item['link'] .'</li>';
											}
											echo '<li><small>' . __('Search further with:', 'copyfeed') . ' <a href="http://www.sphere.com/search?q=%22' . $fbcf_search_fingerprint . '%22&amp;datedrop=0&amp;sortby=date&amp;lang=all&amp;allfrom=&amp;startdate=&amp;enddate=&amp;histdays=120">Sphere</a></small></li>';
										} else {
											echo '<li>' . $sphere_search_result_answer . '</li>';
											echo '<li>' . __('Search further with:', 'copyfeed') . ' <a href="http://www.sphere.com/search?q=%22' . $fbcf_search_fingerprint . '%22&amp;datedrop=0&amp;sortby=date&amp;lang=all&amp;allfrom=&amp;startdate=&amp;enddate=&amp;histdays=120">Sphere</a>.</li>';
										} ?>
							</ul>
						</td>
					</tr>
				</table>

				<div class="tablenav">
					<div style="float: right"><a href="#wpbody"><?php _e('Top', 'copyfeed'); ?></a></div>
				</div>
				<?php } ?>

				<h3 id="more"><?php _e('further search options', 'copyfeed') ?></h3>
				<table summary="more" class="form-table">
					<tr valign="top">
						<td><?php _e('Search further with:', 'copyfeed'); ?> <a href="http://www.google.com/search?q=+%22<?php echo $fbcf_search_fingerprint; ?>%22&amp;btnG=Suche&amp;lr=">Google Search</a></td>
					</tr>
					<tr valign="top">
						<td><?php _e('Search further with:', 'copyfeed'); ?> <a href="http://search.live.com/results.aspx?q=%22<?php echo $fbcf_search_fingerprint; ?>%22&amp;form=QBNO&amp;go.x=0&amp;go.y=0&amp;go=Search">Live Search</a></td>
					</tr>
					<tr valign="top">
						<td><?php _e('Search further with:', 'copyfeed'); ?> <a href="http://copyscape.com/?q=<?php echo $fbcf_search_fingerprint; ?>">Copyscape</a></td>
					</tr>
				</table>

				<p class="submit" id="submitbutton">
					<input type="hidden" name="action" value="insert" />
					<input class="button-primary" type="submit" name="fbcf_save" value="<?php _e('Update Options', 'copyfeed'); ?> &raquo;" /><input type="hidden" name="page_options" value="'dofollow_timeout'" />
				</p>
			</form>

			<div class="tablenav">
				<div style="float: right"><a href="#wpbody"><?php _e('Top', 'copyfeed'); ?></a></div>
			</div>

			<h3 id="hint_to_plugin"><?php _e('Information on the plugin', 'copyfeed') ?></h3>
			<p><?php _e('The plugin adds a copyright notice to each post. The text fields will be displayed in the following order:', 'copyfeed'); ?></p>
			<p style="color: blue;">"<?php _e('Start of copyright notice', 'copyfeed'); ?>" " <?php _e('digital fingerprint', 'copyfeed'); ?>" " (<?php _e('Include the IP address of the feed reader?', 'copyfeed'); ?>) " "<?php _e('End of copyright notice', 'copyfeed'); ?>"</p>
			<p><?php _e('The digital fingerprint and the transmission of the IP address of the feed reader is optional.', 'copyfeed'); ?></p>
			<p><?php _e('xHTML can also be used. A digital fingerprint, which can be used to identify possible content theft through some search engines, can also be added. The plugin will search using search engines that include RSS feeds in their results, and include the 10 most relevant results. More information can be found here. A unique GUID will be displayed here each time the page is generated. Copy it in the relevant digital fingerprint field or use your own digital fingerprint. Examplekey: ', 'copyfeed'); ?><span style="color: blue;"><?php echo $fingerprint; ?></span></p>
			<p><?php _e('You can also choose to display comments and entries with related content. Careful! This content will be generated dynamically and may cause some feed services to show the post as new each time a comment is posted and/or when an entry with related content is shown', 'copyfeed'); ?></p>

			<h3 id="uninstall"><?php _e('Uninstall options', 'copyfeed') ?></h3>
			<p><?php _e('This button deletes all options of the &copy;Feed plugin. Please use it <strong>before</strong> deactivating the plugin.<br /><strong>Attention: </strong>You cannot undo this!', 'copyfeed'); ?></p>
			<form name="form2" method="post" action="<?php if ( isset($location) ) echo $location; ?>">
				<?php fbcf_nonce_field($fbcf_nonce); ?>
				<p class="submit">
					<input type="hidden" name="action" value="deactivate" />
					<input class="button" type="submit" name="fbcf_uninstall" value="<?php _e('Delete Options', 'copyfeed'); ?> &raquo;" />
				</p>
			</form>

			<hr />
			<p><small><?php _e('Further information: Visit the <a href=\'http://bueltge.de/wp-feed-plugin/204\'>plugin homepage</a> for further information or to grab the latest version of this plugin.', 'copyfeed'); ?><br />&copy; Copyright 2007 - <?php echo date("Y"); ?> <a href="http://bueltge.de">Frank B&uuml;ltge</a> | <?php _e('You want to thank me? Visit my <a href=\'http://bueltge.de/wunschliste\'>wishlist</a>.', 'copyfeed'); ?></small></p>


			<div class="tablenav">
				<div style="float: right"><a href="#wpbody"><?php _e('Top', 'copyfeed'); ?></a></div>
			</div>
		</div>
	<?php
}


/**
 * admin-page
 * @add_options_page with $viewcount
 */
function fbcf_add_menu() {
	global $viewcount;

	if (function_exists('add_options_page') && current_user_can('manage_options')) {
		$fbcf_search_fingerprint = str_replace(" ", "+", get_option('fbcf_fingerprint'));

		if (get_option('fbcf_search_fingerprint') == 1) {

			// google blog
			$googleblog_search_result_url = 'http://blogsearch.google.com/blogsearch_feeds?hl=en&amp;q=%22' . $fbcf_search_fingerprint . '%22&amp;scoring=d&amp;ie=utf-8&num=10&output=rss';
			if ( fbcf_check_url('blogsearch.google.com') ) {
				$googleblog_search_result_rss = fbcf_filtered_fetch_rss($googleblog_search_result_url);
				$googleblog_search_result = count($googleblog_search_result_rss->items);
			} else {
				$googleblog_search_result = 0;
			}

			// icerocket
			$icerocket_search_result_url = 'http://www.icerocket.com/search?tab=blog&amp;q=%22' . $fbcf_search_fingerprint . '%22&rss=1';
			if ( fbcf_check_url('www.icerocket.com') ) {
				$icerocket_search_result_rss = fbcf_filtered_fetch_rss($icerocket_search_result_url);
				$icerocket_search_result = count($icerocket_search_result_rss->items);
			} else {
				$icerocket_search_result = 0;
			}

			// bloglines
			$bloglines_search_result_url = 'http://www.bloglines.com/search?q=%22' . $fbcf_search_fingerprint . '%22&s=fr&pop=l&news=m&amp;format=rss';
			if ( fbcf_check_url('www.bloglines.com') ) {
				$bloglines_search_result_rss = fbcf_filtered_fetch_rss($bloglines_search_result_url);
				$bloglines_search_result = count($bloglines_search_result_rss->items);
			} else {
				$bloglines_search_result = 0;
			}

			// blogpulse
			$blogpulse_search_result_url = 'http://www.blogpulse.com/rss?query=%22' . $fbcf_search_fingerprint . '%22&amp;sort=date&amp;operator=phrase';
			if ( fbcf_check_url('www.blogpulse.com') ) {
				$blogpulse_search_result_rss = fbcf_filtered_fetch_rss($blogpulse_search_result_url);
				$blogpulse_search_result = count($blogpulse_search_result_rss->items);
			} else {
				$blogpulse_search_result = 0;
			}

			// sphere
			$sphere_search_result_url = 'http://rss.sphere.com/rss?q=%22' . $fbcf_search_fingerprint . '%22';
			if ( fbcf_check_url('www.sphere.com') ) {
				$sphere_search_result_rss = fbcf_filtered_fetch_rss($sphere_search_result_url);
				$sphere_search_result = count($sphere_search_result_rss->items);
			} else {
				$sphere_search_result = 0;
			}

			$viewcount = $googleblog_search_result + $icerocket_search_result + $bloglines_search_result + $blogpulse_search_result + $sphere_search_result;
			$viewcount = ' (' . $viewcount . ')';
		} else {
			$viewcount = '';
		}
	}
}

/**
 * related post with content in database
 * function is from Plugin "Related Posts" - http://www.w-a-s-a-b-i.com/archives/2006/02/02/wordpress-related-entries-20/
 *
 * @param int $limit
 * @param int $len
 * @param $show_pass_post
 * @param $show_excerpt
 *
 * @return string $output
 */
function fbcf_relatedpost($limit = 5, $len = 10, $show_pass_post, $show_excerpt) {
	global $wpdb, $post;

	$limit = (int)$limit;
	$len   = (int)$len;

	$postcustom = get_post_custom_values('keyword');
	if (!empty($postcustom)) {
		$values = array_map('trim', $postcustom);
		$terms  = implode($values, ' ');
	} else {
		$terms = str_replace('-', ' ', $post->post_name);
	}

	$time_difference = get_option('gmt_offset');
	$now = gmdate( 'Y-m-d H:i:s',( time()+($time_difference * 3600) ) );

	// Primary SQL query
	$sql = "SELECT ID, post_title, post_content,"
		 . "MATCH (post_name, post_content) "
		 . "AGAINST ('$terms') AS score "
		 . "FROM $wpdb->posts WHERE "
		 . "MATCH (post_name, post_content) "
		 . "AGAINST ('$terms') "
		 . "AND post_date <= '$now' "
		 . "AND (post_status IN ('publish', 'static') "
		 . "AND ID != $post->ID) ";
	if ($show_pass_post == 'false') { $sql .= "AND post_password = '' "; }
	$sql .= "ORDER BY score DESC LIMIT $limit";

	$results = $wpdb->get_results($sql);

	$output = '';

	if ($results) {
		$output .= stripslashes(get_option('fbcf_relatedpostinfeed_before'));
		foreach ($results as $result) {
			$title     = stripslashes(apply_filters('the_title', $result->post_title));
			$permalink = get_permalink($result->ID);
			$output   .= get_option('fbcf_relatedpostinfeed_before_title');
			$output   .= '<a href="' . $permalink . '" rel="bookmark" title="Permanent Link: ' . $title . '">' . $title . '</a>';

			if ($show_excerpt == 'true') {
				$post_content = strip_tags($result->post_content);
				$post_content = stripslashes($post_content);
				$words        = explode(' ',$post_content);
				$post_strip   = join(' ', array_slice($words, 0, $len));
				$output      .= '<br />' . $post_strip;
			}

			$output .= get_option('fbcf_relatedpostinfeed_after_title');
		}
		$output .= stripslashes(get_option('fbcf_relatedpostinfeed_after'));

		return $output;
	}

	return false;
}


/**
 * whitelist
 * ! a experiment
 * @return boolean TRUE or FALSE
 */
function fbcf_whitelist() {

	$fbcf_url_host        = array_key_exists( 'HTTP_HOST', $_SERVER ) ? $_SERVER['HTTP_HOST'] : '';
	$fbcf_url_request     = array_key_exists( 'REQUEST_URI', $_SERVER ) ? $_SERVER['REQUEST_URI'] : '';
	$fbcf_url_reader      = array_key_exists( 'HTTP_HOST', $_SERVER ) ? $_SERVER['HTTP_HOST'] : '';
	$fbcf_url_ip          = array_key_exists( 'REMOTE_ADDR', $_SERVER ) ? $_SERVER['REMOTE_ADDR'] : '';
	$fbcf_url_remote_host = array_key_exists( 'REMOTE_HOST', $_SERVER ) ? $_SERVER['REMOTE_HOST'] : '';

	$words = preg_split("/\r\n/", get_option('fbcf_whitelist'));

	if ( $words == '')
		return false;

	foreach ( (array) $words as $key => $word ) {
		$word = trim($word);

		// Skip empty lines
		if ( empty($word) ) {
			continue;
		}

		$word = preg_quote($word, '#');
		$pattern = "#$word#i";
		if (
				preg_match($pattern, $fbcf_url_host)
				|| preg_match($pattern, $fbcf_url_request)
				|| preg_match($pattern, $fbcf_url_reader)
				|| preg_match($pattern, $fbcf_url_ip)
				|| preg_match($pattern, $fbcf_url_remote_host)
				)

		return true;
	}

	return false;
}


/**
 * blacklist
 * ! a experiment
 * @return boolean TRUE or FALSE
 */
function fbcf_blacklist() {

	$fbcf_url_host        = array_key_exists( 'HTTP_HOST', $_SERVER ) ? $_SERVER['HTTP_HOST'] : '';
	$fbcf_url_request     = array_key_exists( 'REQUEST_URI', $_SERVER ) ? $_SERVER['REQUEST_URI'] : '';
	$fbcf_url_reader      = array_key_exists( 'HTTP_HOST', $_SERVER ) ? $_SERVER['HTTP_HOST'] : '';
	$fbcf_url_ip          = array_key_exists( 'REMOTE_ADDR', $_SERVER ) ? $_SERVER['REMOTE_ADDR'] : '';
	$fbcf_url_remote_host = array_key_exists( 'REMOTE_HOST', $_SERVER ) ? $_SERVER['REMOTE_HOST'] : '';

	$words = preg_split("/\r\n/", get_option('fbcf_blacklist'));

	if ( ! isset( $words ) || $words == '' )
		return false;

	foreach ( (array) $words as $key => $word ) {
		$word = trim($word);

		// Skip empty lines
		if ( empty($word) ) {
			continue;
		}

		$word = preg_quote($word, '#');
		$pattern = "#$word#i";
		if (
				preg_match($pattern, $fbcf_url_host)
				|| preg_match($pattern, $fbcf_url_request)
				|| preg_match($pattern, $fbcf_url_reader)
				|| preg_match($pattern, $fbcf_url_ip)
				|| preg_match($pattern, $fbcf_url_remote_host)
				)

		return true;
	}

	return false;
}

/**
 * wordpress >2.1, <2.5
 * return string fullfeed, replace more-tag in feed-content
 * @echo $content
 *
 * @param $old_content
 *
 * @return string
 */
function fbcf_full_feed($old_content) {

	if (!is_feed())
		return $old_content;

	remove_filter('the_content', 'fbcf_full_feed', -1 ); //To get rid of infinite loops
	$content = fbcf_get_the_content();
	$content = apply_filters('the_content', $content );
	$content = str_replace('<!--more-->', '', $content);
	$content = str_replace(']]>', ']]&gt;', $content);
	add_filter('the_content', 'fbcf_full_feed', -1, 1 ); //To make it work in the future

	echo $content;
}


/**
 * Password check
 * non echo content
 * @return string
 */
function fbcf_get_the_content() {
	global $post;

	// Password checking copied from get_the_content()
	if (!empty($post->post_password))
	if (stripslashes($_COOKIE['wp-postpass_'.COOKIEHASH]) !=  $post->post_password)
		return get_the_password_form();

	return $post->post_content;
}

/**
 * Add to the content, when feed
 * related post, comments and check white- and blacklist
 *
 * @param $content
 *
 * @return string $content + $copyright
 */
function fbcf_add_content_to_feed($content) {
	global $wpdb, $id, $comment;

	if (fbcf_blacklist()) {
		$content = stripslashes( get_option('fbcf_blacklist_message') );
		return ($content);
	} else {

		if (is_feed() && !fbcf_whitelist()) {

			if (get_option('fbcf_commentinfeed') == 1) {
				$comments = $wpdb->get_results("SELECT *
												FROM $wpdb->comments
												WHERE comment_post_ID = '$id'
												AND comment_approved = 1
												ORDER BY comment_date");

				if ($comments) {
					$content .= fbcf_commentlayout(stripslashes(get_option('fbcf_commentinfeed_before')));

					foreach($comments as $comment) {
						$content .= fbcf_commentlayout(stripslashes(get_option('fbcf_commentinfeed_layout')));
					}

					$content .= fbcf_commentlayout(stripslashes(get_option('fbcf_commentinfeed_after')));
				}
			}

			// related post
			if (get_option('fbcf_relatedpostinfeed') == 1) {
				$limit          = get_option('fbcf_relatedpostinfeed_limit');
				$len            = get_option('fbcf_relatedpostinfeed_len');
				$show_pass_post = get_option('fbcf_relatedpostinfeed_show_pass_post');

				if ($show_pass_post == 1) {
					$show_pass_post = 'true';
				} else {
					$show_pass_post = 'false';
				}

				$show_excerpt   = get_option('fbcf_relatedpostinfeed_show_excerpt');

				if ($show_excerpt == 1) {
					$show_excerpt = 'true';
				} else {
					$show_excerpt = 'false';
				}

				$content .= fbcf_relatedpost($limit, $len, $show_pass_post, $show_excerpt);
			}

			// related post with plugin simple tagging
			if ( (get_option('fbcf_stp_relatedpostinfeed') == 1) ) {
				$content .= stripslashes(get_option('fbcf_relatedpostinfeed_before'));
				if ( class_exists('SimpleTagging') ) {
					$content .= STP_GetRelatedPosts();
				} elseif ( class_exists('SimpleTags') ) {
					$content .= st_get_related_posts();
				}
				$content .= stripslashes(get_option('fbcf_relatedpostinfeed_after'));
			}

			// copyright message
			// IP of reader
			if (get_option('fbcf_ipinfo') == 1) {
				$fbfc_ipadress = $_SERVER['REMOTE_ADDR'];
				$copyright = fbcf_copyright_layout( stripslashes( get_option('fbcf_copyright_message_start') ) ) . ' ' . get_option('fbcf_fingerprint') . ' (' . $fbfc_ipadress . ') ' . fbcf_copyright_layout( stripslashes( get_option('fbcf_copyright_message_end') ) );
			} else {
				$copyright = fbcf_copyright_layout( stripslashes( get_option('fbcf_copyright_message_start') ) ) . ' ' . get_option('fbcf_fingerprint') . fbcf_copyright_layout( stripslashes( get_option('fbcf_copyright_message_end') ) );
			}

			return ($content . $copyright);
		} else {
			return ($content);
		}
	}
}

/**
 * for functions in copyright-content
 * replace the layout
 *
 * @param string $string
 *
 * @return string The contents with functions.
 */
function fbcf_copyright_layout($string = '') {
	global $post;

	if( empty($post) )
		return $string;

	$string = str_replace('%title%', get_the_title(), $string);
	$string = str_replace('%author%', get_the_author(), $string);
	$string = str_replace('%authorlink%', get_the_author_meta('url'), $string);
	$string = str_replace('%date%', get_post_time( get_option('date_format') ), $string);
	$string = str_replace('%time%', get_the_time(), $string);
	$string = str_replace('%permalink%', get_permalink($post->ID), $string);
	$string = str_replace('%trackback%', get_trackback_url(), $string);
	$string = str_replace('%commentsnumber%', get_comments_number(), $string);

	return $string;
}

/**
 * for comments in feed
 * replace the layout
 *
 * @param string $string
 *
 * @return string The contents with functions.
 */
function fbcf_commentlayout($string = '') {
	global $post;

	if( empty($post) )
		return $string;

	$string = str_replace('%title%', get_the_title(), $string);
	$string = str_replace('%author%', get_comment_author(), $string);
	$string = str_replace('%authorlink%', get_comment_author_link(), $string);
	$string = str_replace('%date%', get_comment_date(), $string);
	$string = str_replace('%time%', get_comment_time(), $string);
	$string = str_replace('%comment%', get_comment_text(), $string);
	$string = str_replace('%excerpt%', get_comment_excerpt(), $string);
	$string = str_replace('%permalink%', get_permalink($post->ID), $string);
	$string = str_replace('%commentpermalink%', get_permalink($post->ID) . '#comment-' . get_comment_ID(), $string);
	$string = str_replace('%trackback%', get_trackback_url(), $string);
	$string = str_replace('%commentsnumber%', get_comments_number(), $string);

	return $string;
}


/**
 * Dashboard information >= 2.5
 * @print content in dashboard
 */
function fbcf_admin_dashboard() {
	global $viewcount;

	$fbcf_content = '<p class="youhave">';
	$fbcf_content.= '<strong>&copy;Feed:</strong> ' . __('Scan for the digital fingerprint find ', 'copyfeed');
	$fbcf_content.= '<strong><a href="options-general.php?page=copyfeed.php#search" title="' . __('Go to &copy;Feed-Search ', 'copyfeed') . '">' . $viewcount . '</a></strong> ' . __('Plagiarism', 'copyfeed') . '</li>';
	$fbcf_content.= '</p>';

	print $fbcf_content;
}


/**
 * Dashboard information < 2.5
 * @print content in dashboard
 */
function fbcf_admin_footer() {
	global $viewcount;

		$viewcount = str_replace(array(' (', ')'), '', $viewcount);
		$admin = dirname($_SERVER['SCRIPT_FILENAME']);
		$admin = substr($admin, strrpos($admin, '/')+1);

		if ($admin == 'wp-admin' && basename($_SERVER['SCRIPT_FILENAME']) == 'index.php') {
			$fbcf_content = '<h3>&copy;Feed <a href=\"options-general.php?page=copyfeed.php\">&raquo;</a> </h3>';
			$fbcf_content.= '<ul>';
			$fbcf_content.= '<li><strong><a href=\"options-general.php?page=copyfeed.php#search\">' . $viewcount . '</a></strong> ' . __('Plagiarism', 'copyfeed') . '</li>';
			$fbcf_content.= '</ul>';

			print ' <script language="javascript" type="text/javascript"> var ele = document.getElementById("zeitgeist");
			if (ele) {
				var div = document.createElement("DIV");
				div.innerHTML = "'.$fbcf_content.'";
				ele.appendChild(div);
			} </script> ';
		}
}

/**
 * for a smaller database
 * @param $option
 */
function fbcf_get_update($option) {
    if ( ! isset($_POST[$option]) )
        $_POST[$option] = '';
    if ( ($_POST[$option] == '0') || $_POST[$option] == '') {
        delete_option($option);
    } else {
        update_option($option , $_POST[$option]);
    }
}

/**
 * Update options in database
 * check with current_user_can
 */
function fbcf_update() {
	global $wp_version, $fbcf_nonce;

	if ( ($_POST['action'] == 'insert') && $_POST['fbcf_save'] ) {

		if ( function_exists('current_user_can') && current_user_can('edit_plugins') ) {
			check_admin_referer($fbcf_nonce);

			fbcf_get_update('fbcf_copyright_message_start');
			fbcf_get_update('fbcf_fingerprint');
			fbcf_get_update('fbcf_copyright_message_end');
			fbcf_get_update('fbcf_dashboardinfo');
			fbcf_get_update('fbcf_rss_filter_own_posts');
			fbcf_get_update('fbcf_ipinfo');
			fbcf_get_update('fbcf_whitelist');
			fbcf_get_update('fbcf_blacklist');
			fbcf_get_update('fbcf_blacklist_message');
			fbcf_get_update('fbcf_commentinfeed');
			fbcf_get_update('fbcf_commentinfeed_before');
			fbcf_get_update('fbcf_commentinfeed_layout');
			fbcf_get_update('fbcf_commentinfeed_after');
			fbcf_get_update('fbcf_relatedpostinfeed');
			fbcf_get_update('fbcf_relatedpostinfeed_limit');
			fbcf_get_update('fbcf_relatedpostinfeed_show_pass_post');
			fbcf_get_update('fbcf_relatedpostinfeed_len');
			fbcf_get_update('fbcf_relatedpostinfeed_show_excerpt');
			fbcf_get_update('fbcf_relatedpostinfeed_before');
			fbcf_get_update('fbcf_relatedpostinfeed_before_title');
			fbcf_get_update('fbcf_relatedpostinfeed_after_title');
			fbcf_get_update('fbcf_relatedpostinfeed_after');
			fbcf_get_update('fbcf_stp_relatedpostinfeed');
			if ( version_compare($wp_version, '2.5', '<') ) {
				update_option('fbcf_fullfeed', $_POST['fbcf_fullfeed']);
			} else {
				if (get_option('fbcf_fullfeed') == '1')
					delete_option('fbcf_fullfeed');
			}
			fbcf_get_update('fbcf_excerptfeed');
			fbcf_get_update('fbcf_search_fingerprint');
			fbcf_get_update('fbcf_search_timeout');

			echo '<div class="updated fade"><p>' . __('The options have been saved!', 'copyfeed') . '</p></div>';
		} else {
			wp_die('<p>'.__('You do not have sufficient permissions to edit plugins for this blog.').'</p>');
		}
	}
}


/**
 * Delete options in database
 * check with current_user_can
 */
function fbcf_uninstall() {
	global $fbcf_nonce;

	if ( ($_POST['action'] == 'deactivate') && $_POST['fbcf_uninstall'] ) {

		if ( function_exists('current_user_can') && current_user_can('edit_plugins') ) {
			check_admin_referer($fbcf_nonce);

			delete_option('fbcf_copyright_message_start');
			delete_option('fbcf_fingerprint');
			delete_option('fbcf_copyright_message_end');
			delete_option('fbcf_dashboardinfo');
			delete_option('fbcf_rss_filter_own_posts');
			delete_option('fbcf_ipinfo');
			delete_option('fbcf_whitelist');
			delete_option('fbcf_blacklist');
			delete_option('fbcf_blacklist_message');
			delete_option('fbcf_commentinfeed');
			delete_option('fbcf_commentinfeed_before');
			delete_option('fbcf_commentinfeed_layout');
			delete_option('fbcf_commentinfeed_after');
			delete_option('fbcf_relatedpostinfeed');
			delete_option('fbcf_relatedpostinfeed_limit');
			delete_option('fbcf_relatedpostinfeed_show_pass_post');
			delete_option('fbcf_relatedpostinfeed_len');
			delete_option('fbcf_relatedpostinfeed_show_excerpt');
			delete_option('fbcf_relatedpostinfeed_before');
			delete_option('fbcf_relatedpostinfeed_before_title');
			delete_option('fbcf_relatedpostinfeed_after_title');
			delete_option('fbcf_relatedpostinfeed_after');
			delete_option('fbcf_stp_relatedpostinfeed');
			delete_option('fbcf_fullfeed');
			delete_option('fbcf_excerptfeed');
			delete_option('fbcf_search_fingerprint');
			delete_option('fbcf_search_timeout');

			echo '<div class="updated fade"><p>' . __('The options have been deleted!', 'copyfeed') . '</p></div>';
		} else {
			wp_die('<p>'.__('You do not have sufficient permissions to edit plugins for this blog.').'</p>');
		}
	}
}


/**
 * Install options in database
 *
 */
function fbcf_install(){
	global $wpdb;

	$sql = 'ALTER TABLE `'.$wpdb->posts.'` ADD FULLTEXT `post_related` ( `post_name`, `post_content` )';

	$wpdb->hide_errors();
	$sql_result = $wpdb->query($sql);
	$wpdb->show_errors();

	add_option('fbcf_copyright_message_start', "<hr /><small>Copyright &copy; 2008<br />".__(" This feed is for personal, non-commercial use only. <br /> The use of this feed on other websites breaches copyright. If this content is not in your news reader, it makes the page you are viewing an infringement of the copyright. (Digital Fingerprint:", 'copyfeed')."<br />");
	add_option('fbcf_copyright_message_end', ')</small>');
	add_option('fbcf_commentinfeed_before', '<hr /><h2>'.__('Comments', 'copyfeed').'</h2><ul>');
	add_option('fbcf_commentinfeed_layout', '<li><a href="%permalink%">%date%</a>, %authorlink% '.__('writes', 'copyfeed').': %comment%</li>');
	add_option('fbcf_commentinfeed_after', '</ul>');
	add_option('fbcf_relatedpostinfeed_limit', 5);
	add_option('fbcf_relatedpostinfeed_len', 10);
	add_option('fbcf_relatedpostinfeed_before', '<hr /><h2>'.__('Related posts:', 'copyfeed').'</h2><ul>');
	add_option('fbcf_relatedpostinfeed_before_title', '<li>');
	add_option('fbcf_relatedpostinfeed_after_title', '</li>');
	add_option('fbcf_relatedpostinfeed_after', '</ul>');
	add_option('fbcf_search_fingerprint', '');
	update_option('fbcf_search_fingerprint', '');
}


/**
 * actions-hooks for wordpress
 *
 */
if ( function_exists('add_action') && is_admin() ) {
	/*
	if ( isset($_GET['activate']) && $_GET['activate'] == 'true' ) {
		add_action('init', 'fbcf_install');
	}
	*/
	register_activation_hook( __FILE__, 'fbcf_install' );
	add_action('init', 'fbcf_textdomain');
	add_action('admin_menu', 'fbcf_add_menu');
	add_action('admin_menu', 'fbcf_add_settings_page');
	add_action('in_admin_footer', 'fbcf_add_admin_footer');

	if ( get_option('fbcf_dashboardinfo') == 1 && get_option('fbcf_search_fingerprint') == 1 && strpos($_SERVER['REQUEST_URI'], 'index.php') ) {
		if ( function_exists('wp_admin_css_color') ) {
			add_action('activity_box_end', 'fbcf_admin_dashboard');
		} else {
			add_action('admin_footer', 'fbcf_admin_footer');
		}
	}
}


/**
 * filter-hooks for wordpress
 *
 */
if (function_exists('add_filter') && ! is_admin() ) {

	if ( get_option('rss_use_excerpt') && get_option('fbcf_excerptfeed') ) {
		add_filter('the_excerpt_rss', 'fbcf_add_content_to_feed', 0);
	} else {
		add_filter('the_content', 'fbcf_add_content_to_feed');
	}
	if ( get_option('fbcf_fullfeed') == 1 ) {
		add_filter('the_content', 'fbcf_full_feed', -1, 1);
		//Set RSS options to summary, not full text.
		add_filter( 'option_rss_use_excerpt', 'fbcf_rss_use_excerpt' );
	}
	/**
	 * @return int
	 */
	function fbcf_rss_use_excerpt() {
		return 1;
	}
}
