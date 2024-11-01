<?php
/*
Plugin Name: WP Atom Importer
Plugin URI: http://pierskarsenbarg.github.com/wp-atom-importer
Description: Tool to import atom feed into wordpress
Version: 0.9
Author: Piers Karsenbarg
Author URI: http://pierskarsenbarg.com
License: GPL2
*/


if ( !defined('WP_LOAD_IMPORTERS') )
	return;

// Load Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( !class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) )
		require_once $class_wp_importer;
}

if(class_exists('WP_IMPORTER'))
{
	class Atom_Importer extends WP_Importer {
		var $file;
		var $posts = array();
		function Atom_Importer(){}
		function run()
		{
			if(empty($_GET['step'])){
				$step = 0;
			}
			else
			{
				$step = (int)$_GET['step'];
			}
			$this->header();
			switch($step){
				case 0: 
					$this->uploadform();
					break;
				case 1:
					check_admin_referer('import-upload');
					$this->runimport();
					break;
			}
			$this->footer();
		}

		function header(){
			echo '<div class="wrap">';
			screen_icon();
			echo '<h2>Import Atom feed</h2>';
		}

		function footer(){
			echo '</div>';
		}

		function uploadform() {
			echo '<div class="narrow">';
			wp_import_upload_form("admin.php?import=wp-atom-importer&step=1");
			echo '<p>If you have a lot of posts, the upload and import may take a while. Don\'t close this browser tab or window until it has finised.</p>';
			echo '</div>';
		}

		function runimport()	{
			$file = wp_import_handle_upload();
			if(isset($file['error'])){
				echo $file['error'];
				return;
			}
			$this->file = $file['file'];
			$this->getposts();
			$result = $this->do_import();
			if(is_wp_error($result))
				return $result;
			// clean up
			wp_import_cleanup($file['id']);
			do_action('import_done','wp-atom-importer');
			echo '<h3>';
			echo '<a href="'.get_option('home').'">All done</a>';
			echo '</h3>';
		}

		function _normalize_tag( $matches ) {
			return '<' . strtolower( $matches[1] );
		}

		function getposts()
		{
			global $wpdb;
			set_magic_quotes_runtime(0);
			$datalines = file($this->file);
			$importdata = implode('',$datalines);
			$importdata = str_replace(array("\r\n","\r"),"\n",$importdata);
			preg_match_all('|<entry>(.*?)</entry>|is', $importdata, $this->posts);
			$this->posts = $this->posts[1];
			$index = 0;
			foreach($this->posts as $post)
			{
				preg_match('|<title>(.*?)</title>|is',$post,$post_title);
				$post_title = str_replace(array('<![CDATA[',']]>'), '', $wpdb->escape(trim($post_title[1])));

				preg_match('|<updated>(.*?)</updated>|is',$post,$post_date_gmt);
				if($post_date_gmt)
				{
					$post_date_gmt = strtotime($post_date_gmt[1]);
				}
				$post_date_gmt = gmdate('Y-m-d H:i:s',$post_date_gmt);
				$post_date = get_date_from_gmt($post_date_gmt);
				preg_match('|<content type="html">(.*?)</content>|is', $post, $post_content);
				$post_content = str_replace(array('<![CDATA[',']]>'), '', $wpdb->escape(trim($post_content[1])));

				$post_content = preg_replace_callback('|<(/?[A-Z]+)|', array( &$this, '_normalize_tag' ), $post_content);
				$post_content = str_replace('<br>', '<br />', $post_content);
				$post_content = str_replace('<hr>', '<hr />', $post_content);
				$post_content = htmlspecialchars_decode($post_content);

				$post_author = 1;
				$post_status = 'publish';
				$this->posts[$index] = compact('post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title', 'post_status', 'guid', 'categories');
				$index++;
			}
		}

		function do_import()
		{
			echo '<ol>';

		foreach ($this->posts as $post) {
			echo "<li>".__('Importing post...', 'rss-importer');

			extract($post);

			if ($post_id = post_exists($post_title, $post_content, $post_date)) {
				_e('Post already imported', 'rss-importer');
			} else {
				$post_id = wp_insert_post($post);
				if ( is_wp_error( $post_id ) )
					return $post_id;
				if (!$post_id) {
					_e('Couldn&#8217;t get post ID', 'rss-importer');
					return;
				}

				if (0 != count($categories))
					wp_create_categories($categories, $post_id);
				_e('Done!', 'rss-importer');
			}
			echo '</li>';
		}

		echo '</ol>';
		}

	}

	$atom_import = new Atom_Importer();
	register_importer('wp-atom-importer','Atom Importer','Import posts from an atom feed',array($atom_import,'run'));

}