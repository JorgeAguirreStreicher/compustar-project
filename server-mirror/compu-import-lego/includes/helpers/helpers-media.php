<?php
if (!defined('ABSPATH')) { exit; }
function compu_download_and_attach_media($post_id,$image_url){
  if(!$image_url) return 0;
  require_once ABSPATH.'wp-admin/includes/file.php';
  require_once ABSPATH.'wp-admin/includes/media.php';
  require_once ABSPATH.'wp-admin/includes/image.php';
  $tmp = download_url($image_url, 30);
  if (is_wp_error($tmp)) return 0;
  $file_array = ['name'=>basename(parse_url($image_url, PHP_URL_PATH)),'tmp_name'=>$tmp];
  $id = media_handle_sideload($file_array, $post_id);
  if (is_wp_error($id)) { @unlink($tmp); return 0; }
  set_post_thumbnail($post_id, $id);
  return $id;
}
