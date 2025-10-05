<?php
// === CompuStar Guards (idempotentes) ===
if (!function_exists('_compu_is_invalid_lvl1')) {
  function _compu_is_invalid_lvl1(array $row): bool {
    $l1 = trim(strval($row['lvl1_id'] ?? ''));
    return ($l1 === '' || $l1 === '---' || $l1 === '25');
  }
}
if (!function_exists('_compu_is_missing_title')) {
  function _compu_is_missing_title(array $row): bool {
    $t = trim(strval($row['title'] ?? ''));
    return ($t === '');
  }
}
if (!function_exists('compu_should_skip_row')) {
  function compu_should_skip_row(array $row, ?string &$reason): bool {
    if (_compu_is_invalid_lvl1($row)) { $reason = 'invalid_lvl1'; return true; }
    if (_compu_is_missing_title($row)) { $reason = 'missing_title_brand'; return true; }
    $reason = null; return false;
  }
}
