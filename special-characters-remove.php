<?php
/**
 * Plugin Name: Special Characters Remove
 * Plugin URI: https://wordpress.org/plugins/special-characters-remove/
 * Description: Simply Remove Special Characters from WordPress attachments, slugs, permalinks, post and pages.
 * Version: 1.0
 * Author: Sirius Pro
 * Author URI: https://siriuspro.pl
 * License: GNU General Public License v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

 add_action('wp_dashboard_setup', 'scr_admin_widget_button');

 function scr_admin_widget_button()
 {
     global $wp_meta_boxes;

     wp_add_dashboard_widget('custom_help_widget', 'Special Characters Remove', 'scr_admin_button');
 }

 function scr_admin_button()
 {
 ?>
     <input type="button" name="custom_db_update" class="btn button-primary" value="Run now">
     <span class="spinner"></span>
     <script>
         jQuery(document).ready(function() {
             jQuery('input[name=custom_db_update]').click(function(e) {
                 e.preventDefault();
                 jQuery('.spinner').addClass('is-active');
                 let data = {
                     action: 'custom_db_update'
                 };
                 jQuery.ajax({
                     type: "post",
                     url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
                     data: data,
                     dataType: "json",
                     success: function(response) {
                         jQuery('.spinner').removeClass('is-active');
                         // window.location = response.data.redirect;
                     }
                 });
             });
         });
     </script>
 <?php
 }


 add_action('wp_ajax_custom_db_update', 'scr_button_ajax_run');
 function scr_button_ajax_run()
 {
     /* if (!(defined('DOING_CRON') && DOING_CRON))
     {
         return;
     } */

     $posts = get_posts(array(
         'post_type' => 'attachment',
         'post_status' => 'inherit',
         'nopaging' => 1,
         'meta_query' => array(
             'relation' => 'AND',
             array(
                 'key'     => 'sp_updated',
                 'compare' => 'NOT EXISTS'
             ),
         ),
     ));
     $counter = 0;
     global $wpdb;
     foreach ($posts as $post)
     {
         $file = get_post_meta($post->ID, '_wp_attached_file', true);

         $fullPath = ABSPATH . 'wp-content/uploads/' . $file;
         $directoryPath = str_replace(basename($fullPath), '', $fullPath);

         $ext = pathinfo($fullPath, PATHINFO_EXTENSION);
         $basename = basename($fullPath, '.' . $ext);

         if (!scr_check_string(basename($fullPath)))
         {
             $newPath =  $directoryPath . sanitize_title($basename) . '.' . $ext;
             $newPathServer = str_replace(ABSPATH . 'wp-content/uploads/', '', $newPath);

             $resRename = rename($fullPath,  $newPath);
             if (!file_exists($newPath))
             {
                 update_post_meta($post->ID, 'sp_updated', 0);
                 continue;
             }
             update_post_meta($post->ID, '_wp_attached_file', $newPathServer);
             $meta = get_post_meta($post->ID, '_wp_attachment_metadata', true);
             wp_update_post(array(
                 'ID' => $post->ID,
                 'guid' => str_replace(ABSPATH, home_url('/'), $newPath)
             ));

             $oldLink = $file;
             $newLink = $newPathServer;

             $wpdb->query($wpdb->prepare("UPDATE {wp_posts} SET post_content = replace(post_content, $oldLink, $newLink);",));

             if (is_array($meta['sizes']) && !empty($meta['sizes']))
             {

                 foreach ($meta['sizes'] as $ind => $data)
                 {
                     $oldSubFilePath  = $directoryPath . $data['file'];
                     $basenameSubFile = basename($oldSubFilePath, '.' . $ext);

                     $newSubFilePath = $directoryPath . sanitize_title($basenameSubFile) . '.' . $ext;
                     rename($oldSubFilePath, $newSubFilePath);
                     $meta['sizes'][$ind]['file'] = sanitize_title($basenameSubFile) . '.' . $ext;
                 }
                 update_post_meta($post->ID, '_wp_attachment_metadata', $meta);
             }

             $counter++;
         }
         update_post_meta($post->ID, 'sp_updated', 1);
     }
     wp_send_json_success();
     exit();
 }
 function scr_check_string($string = '')
 {
     return (bool) !preg_match('/[\\x80-\\xff]+/', $string);
 }