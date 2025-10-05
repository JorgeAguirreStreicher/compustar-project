<?php
if (!defined('ABSPATH')) { exit; }
function compu_apply_pricing($price_usd,$fx,$margin_pct=0.20,$iva_pct=0.16){ $base=floatval($price_usd)*floatval($fx); $margin=$base*(1.0+floatval($margin_pct)); $iva=$margin*(1.0+floatval($iva_pct)); $rounded=compu_round_059($iva); return [$base,$margin,$iva,$rounded]; }
function compu_round_059($n){ $n=floor($n); $last=$n%10; if(in_array($last,[0,5,9])) return $n; $cand=[($n-$last),($n-$last+5),($n-$last+9)]; $best=$cand[0]; $bestd=abs($n-$best); foreach($cand as $c){ $d=abs($n-$c); if($d<$bestd){$best=$c;$bestd=$d;} } return $best; }
