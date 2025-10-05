<?php
if (!function_exists("compu_norm_row_04")) {
  function compu_norm_row_04(array $r): array {
    if (empty($r["sku"]) && !empty($r["model"])) { $r["sku"] = trim((string)$r["model"]); }
    if (empty($r["image"])) {
      foreach (["image_url","img","URL_IMAGEN","Imagen","img_url"] as $k) {
        if (!empty($r[$k])) { $r["image"] = $r[$k]; break; }
      }
    }
    return $r;
  }
}
