<?php
if (!function_exists('compu_image_from_row')) {
  function compu_image_from_row(array $r): ?string {
    $cands = [];

    // 1) Llaves directas (prioriza nombre canónico)
    foreach (['image_url','image','img','Imagen Principal','URL_IMAGEN','img_url','picture'] as $k) {
      if (!empty($r[$k])) { $cands[] = (string)$r[$k]; }
    }
    // 2) Arreglos comunes
    if (!empty($r['images']) && is_array($r['images']) && !empty($r['images'][0])) {
      $cands[] = (string)$r['images'][0];
    }
    if (!empty($r['gallery_urls']) && is_array($r['gallery_urls']) && !empty($r['gallery_urls'][0])) {
      $cands[] = (string)$r['gallery_urls'][0];
    }

    // 3) Fallback en HTML/description
    foreach (['description_html','description','Descripción','descripcion','desc','html','content'] as $hk) {
      if (!empty($r[$hk]) && is_string($r[$hk])) {
        $html = $r[$hk];

        if (preg_match('~<img[^>]+src=["\']([^"\']+)["\']~i', $html, $m) && !empty($m[1])) {
          $cands[] = $m[1];
        }
        if (preg_match('~\bhttps?:\/\/\S+\.(?:jpe?g|png|webp|gif)(?:\?\S*)?~i', $html, $m2) && !empty($m2[0])) {
          $cands[] = $m2[0];
        }
        if (preg_match('~\bftp:\/\/\S+\.(?:jpe?g|png|webp|gif)(?:\?\S*)?~i', $html, $m3) && !empty($m3[0])) {
          $cands[] = $m3[0];
        }
      }
    }

    $re = '~^(https?|ftp)://.+\.(jpg|jpeg|png|webp|gif)(\?.*)?$~i';
    foreach ($cands as $u) {
      $u = trim((string)$u);
      if ($u !== '' && preg_match($re, $u)) { return $u; }
    }
    return null;
  }
}
