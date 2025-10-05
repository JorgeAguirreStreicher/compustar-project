<?php if (!defined('ABSPATH')) { exit; }

/** ===== Imagen destacada (segura) ===== */
function compu_lego_download_and_set_thumbnail($post_id, $image_url){
  $image_url = trim((string)$image_url);
  if ($image_url === '') return 0;
  $image_url = preg_replace('#\s+#', '%20', $image_url);
  if (!preg_match('#^https?://#i', $image_url)) {
    if (strpos($image_url, '//') === 0) $image_url = 'https:' . $image_url; else return 0;
  }
  $thumb_id = get_post_thumbnail_id($post_id); if ($thumb_id) return (int)$thumb_id;
  require_once ABSPATH.'wp-admin/includes/file.php';
  require_once ABSPATH.'wp-admin/includes/media.php';
  require_once ABSPATH.'wp-admin/includes/image.php';
  $att_id = media_sideload_image($image_url, $post_id, null, 'id');
  if (!is_wp_error($att_id) && $att_id) { set_post_thumbnail($post_id, $att_id); return (int)$att_id; }
  $tmp = download_url($image_url, 60); if (is_wp_error($tmp)) return 0;
  $file = ['name'=>basename(parse_url($image_url, PHP_URL_PATH)),'tmp_name'=>$tmp];
  $att_id = media_handle_sideload($file, $post_id);
  if (is_wp_error($att_id)) { @unlink($tmp); return 0; }
  set_post_thumbnail($post_id, $att_id); return (int)$att_id;
}

/** ===== Categorías por NOMBRE (jerarquía) ===== */
function compu_lego_term_ensure($name, $parent = 0){
  $tx='product_cat'; $slug = sanitize_title((string)$name);
  $term = get_term_by('slug',$slug,$tx);
  if (!$term){
    $res = wp_insert_term((string)$name,$tx,['slug'=>$slug,'parent'=>(int)$parent]);
    if (is_wp_error($res)){ $term = get_term_by('name',(string)$name,$tx); if(!$term) return 0; return (int)$term->term_id; }
    return (int)$res['term_id'];
  }
  if ($parent && (int)$term->parent !== (int)$parent) wp_update_term($term->term_id,$tx,['parent'=>(int)$parent]);
  return (int)$term->term_id;
}
function compu_lego_assign_categories($post_id,$lvl1,$lvl2,$lvl3){
  $p1 = $lvl1 ? compu_lego_term_ensure($lvl1,0) : 0;
  $p2 = $lvl2 ? compu_lego_term_ensure($lvl2,$p1) : 0;
  $p3 = $lvl3 ? compu_lego_term_ensure($lvl3,$p2 ?: $p1) : 0;
  $assign = array_filter([$p1,$p2,$p3]); if ($assign){ wp_set_object_terms($post_id,$assign,'product_cat'); return true; }
  return false;
}

/** ===== Marca nativa: product_brand ===== */
function compu_lego_assign_brand_tax($post_id,$brand){
  $brand = trim((string)$brand); if ($brand==='') return false;
  $tax = 'product_brand';
  if (!taxonomy_exists($tax)){
    register_taxonomy($tax,['product'],['hierarchical'=>true,'label'=>'Brands','show_ui'=>true,'show_in_quick_edit'=>true]);
  }
  $term = get_term_by('name',$brand,$tax);
  if (!$term){ $res = wp_insert_term($brand,$tax); if (is_wp_error($res)) return false; $term_id=(int)$res['term_id']; }
  else { $term_id=(int)$term->term_id; }
  wp_set_object_terms($post_id,[$term_id],$tax,false); return true;
}

/** ===== Márgenes / IVA / Redondeo (0/5/9) ===== */
function compu_lego_round_059($price){
  // redondea al entero más cercano con terminación 0, 5 o 9 (sin centavos)
  $p = (int)round($price);
  $last = $p % 10; $targets=[0,5,9]; $best=$p; $bestDiff=PHP_INT_MAX;
  foreach($targets as $t){ $cand=$p - $last + $t; $d=abs($cand-$price); if($d < $bestDiff){ $best=$cand; $bestDiff=$d; } }
  return max(1,$best);
}
function compu_lego_apply_margin($mxn_base, $rule){
  // $rule = ['type'=>'PERCENT'|'FIXED', 'value'=>float, 'rounding'=>'059'|null]
  $type = strtoupper($rule['type'] ?? 'PERCENT');
  $val  = (float)($rule['value'] ?? 0);
  if ($type==='FIXED') $net = $mxn_base + $val;
  else                 $net = $mxn_base * (1.0 + $val);
  $gross16 = $net * 1.16; $gross8 = $net * 1.08; // IVA cacheado por oferta
  if (($rule['rounding'] ?? '059') === '059'){
    $gross16 = compu_lego_round_059($gross16);
    $gross8  = compu_lego_round_059($gross8);
  }
  return [$net, $gross16, $gross8];
}