<?php
declare(strict_types=1);

// --- WordPress stubs ----------------------------------------------------
if (!defined('COMP_RUN_STAGE')) { define('COMP_RUN_STAGE', true); }
if (!defined('ABSPATH')) { define('ABSPATH', __DIR__.'/..'); }
if (!defined('WPINC')) { define('WPINC', 'wp-includes'); }
if (!defined('COMPU_IMPORT_UPLOAD_SUBDIR')) { define('COMPU_IMPORT_UPLOAD_SUBDIR', 'runs'); }
if (!defined('ARRAY_A')) { define('ARRAY_A', 'ARRAY_A'); }

function trailingslashit($path){ return rtrim($path, '/').'/' ; }
function wp_json_encode($data, $options = 0){ return json_encode($data, $options); }
function wp_upload_dir(){ global $COMP_TEST_BASEDIR; return ['basedir'=>$COMP_TEST_BASEDIR, 'baseurl'=>'http://example.com/uploads']; }
function wp_mkdir_p($dir){ if (!is_dir($dir)) { mkdir($dir, 0775, true); } }
function current_time($type='mysql'){ return $type === 'mysql' ? '2025-10-05 00:00:00' : time(); }
function remove_accents($string){ return $string; }
function sanitize_title($title){ return preg_replace('/[^a-z0-9]+/i','-', strtolower($title)); }
function wp_strip_all_tags($text){ return strip_tags($text); }
class WP_CLI { public static function error($msg){ throw new RuntimeException($msg); } public static function log($msg){} }

// --- WooCommerce stubs ---------------------------------------------------
class FakeProduct {
  private $sku;
  private $regular;
  private $sale;
  private $stockQty;
  private $stockStatus;
  private $manageStock = false;

  public function __construct(string $sku, float $regular = 1000.0, ?float $sale = null){
    $this->sku = $sku;
    $this->regular = $regular;
    $this->sale = $sale;
    $this->stockQty = 0;
    $this->stockStatus = 'instock';
  }
  public function get_regular_price(){ return $this->regular; }
  public function get_sale_price(){ return $this->sale === null ? '' : (string)$this->sale; }
  public function set_regular_price($price){ $this->regular = (float)$price; }
  public function set_sale_price($price){ $this->sale = ($price === '' ? null : (float)$price); }
  public function set_manage_stock($flag){ $this->manageStock = (bool)$flag; }
  public function set_stock_quantity($qty){ $this->stockQty = (int)$qty; }
  public function set_stock_status($status){ $this->stockStatus = $status; }
  public function save(){ /* no-op */ }
  public function snapshot(): array {
    return [
      'regular_price' => $this->regular,
      'sale_price'    => $this->sale,
      'stock_qty'     => $this->stockQty,
      'stock_status'  => $this->stockStatus,
      'manage_stock'  => $this->manageStock,
    ];
  }
}

$FAKE_PRODUCTS = [];
$FAKE_SKU_TO_ID = [];
function wc_get_product_id_by_sku($sku){
  global $FAKE_SKU_TO_ID;
  return $FAKE_SKU_TO_ID[$sku] ?? 0;
}
function wc_get_product($id){
  global $FAKE_PRODUCTS;
  return $FAKE_PRODUCTS[$id] ?? null;
}

// --- Fake wpdb -----------------------------------------------------------
class FakeWpdb {
  public $prefix = 'wp_';
  public $last_error = '';
  public $insert_id = 0;
  private $offersBySource = [];
  private $offersById = [];

  public function query($sql){ return true; }
  public function prepare($query, ...$args){ return ['query'=>$query,'args'=>$args]; }

  public function get_var($prepared){
    if (is_array($prepared) && strpos($prepared['query'],'compu_offers') !== false) {
      $args = $prepared['args'];
      $sku = $args[0] ?? null;
      $source = $args[1] ?? 'syscom';
      if ($sku && isset($this->offersBySource[$source][$sku])) {
        return $this->offersBySource[$source][$sku]['id'];
      }
    }
    return null;
  }

  public function get_row($prepared, $output=ARRAY_A){
    if (is_array($prepared) && strpos($prepared['query'],'compu_offers') !== false) {
      $args = $prepared['args'];
      $sku = $args[0] ?? null;
      $source = $args[1] ?? 'syscom';
      if ($sku && isset($this->offersBySource[$source][$sku])) {
        return $this->offersBySource[$source][$sku];
      }
    }
    return null;
  }

  public function update($table, $data, $where){
    if ($table !== $this->prefix.'compu_offers') { return true; }
    $id = $where['id'] ?? null;
    if (!$id || !isset($this->offersById[$id])) { return false; }
    $record =& $this->offersById[$id];
    foreach ($data as $k => $v) { $record[$k] = $v; }
    return true;
  }

  public function insert($table, $data){
    if ($table !== $this->prefix.'compu_offers') { return true; }
    $this->insert_id++;
    $data['id'] = $this->insert_id;
    $source = $data['source'] ?? 'syscom';
    $sku    = $data['supplier_sku'];
    $this->offersBySource[$source][$sku] = $data;
    $this->offersById[$this->insert_id]  =& $this->offersBySource[$source][$sku];
    return true;
  }

  public function get_results($query, $output=ARRAY_A){
    if (strpos($query,'compu_offers') === false) { return []; }
    $out = [];
    foreach ($this->offersBySource as $source => $offers) {
      foreach ($offers as $sku => $data) {
        $out[] = [
          'supplier_sku' => $sku,
          'cost_usd' => $data['cost_usd'] ?? null,
          'exchange_rate' => $data['exchange_rate'] ?? null,
          'stock_total' => $data['stock_total'] ?? null,
        ];
      }
    }
    return $out;
  }

  public function get_offer(string $source, string $sku): ?array {
    return $this->offersBySource[$source][$sku] ?? null;
  }
}

// --- Preparar entorno temporal -----------------------------------------
$COMP_TEST_BASEDIR = sys_get_temp_dir().'/compu_stage_pipeline_'.uniqid();
wp_mkdir_p($COMP_TEST_BASEDIR.'/runs');
$runDir = $COMP_TEST_BASEDIR.'/runs/run-1';
wp_mkdir_p($runDir);

$wpdb = new FakeWpdb();

$sourceCsv = $runDir.'/source.csv';
file_put_contents($sourceCsv, implode("\n", [
  'Modelo,Marca,Título,Su Precio,Tipo de Cambio,Existencias,Tijuana,Chihuahua,Descripción,Imagen Principal',
  "ABC123,ACME,\"Producto Demo\",10.00,17.50,5,2,3,\"<p>Excelente equipo</p>\",\"http://example.com/img.jpg\""
]));

// --- Ejecutar Stage 02 ---------------------------------------------------
require_once __DIR__.'/../server-mirror/compu-import-lego/includes/stages/02-normalize.php';

$stage02 = new Compu_Stage_Normalize();
$stage02->run(['run-id' => 1]);

$normalizedPath = $runDir.'/normalized.jsonl';
if (!file_exists($normalizedPath)) { throw new RuntimeException('No se generó normalized.jsonl'); }
$lines = file($normalizedPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (!$lines) { throw new RuntimeException('normalized.jsonl vacío'); }
$row02 = json_decode($lines[0], true);
assert($row02 !== null);

$expectedFields = ['cost_usd','exchange_rate','stock_total','stock_main','stock_tijuana','price_usd','stock'];
foreach ($expectedFields as $field) {
  if (!array_key_exists($field, $row02)) {
    throw new RuntimeException("Campo faltante en Stage02: {$field}");
  }
}

if (abs($row02['cost_usd'] - 10.0) > 0.001) { throw new RuntimeException('cost_usd incorrecto'); }
if (abs($row02['exchange_rate'] - 17.5) > 0.001) { throw new RuntimeException('exchange_rate incorrecto'); }
if ((int)$row02['stock_total'] !== 5) { throw new RuntimeException('stock_total incorrecto'); }
if ((int)$row02['stock_main'] !== 3) { throw new RuntimeException('stock_main incorrecto'); }
if ((int)$row02['stock_tijuana'] !== 2) { throw new RuntimeException('stock_tijuana incorrecto'); }

// Simular que resolved.jsonl es igual al normalized
copy($normalizedPath, $runDir.'/resolved.jsonl');

// --- Ejecutar Stage 08 ---------------------------------------------------
$wpdb = new FakeWpdb();
putenv('RUN_DIR='.$runDir);
putenv('DRY_RUN=0');
putenv('OFFERS_SOURCE=syscom');
require __DIR__.'/../server-mirror/compu-import-lego/includes/stages/08-offers.php';

$offerRow = $wpdb->get_offer('syscom', 'ABC123');
if (!$offerRow) { throw new RuntimeException('Stage08 no generó oferta para ABC123'); }
if (abs($offerRow['cost_usd'] - 10.0) > 0.001) { throw new RuntimeException('Stage08 cost_usd diferente'); }
if (abs($offerRow['exchange_rate'] - 17.5) > 0.001) { throw new RuntimeException('Stage08 exchange_rate diferente'); }
if ((int)$offerRow['stock_total'] !== 5) { throw new RuntimeException('Stage08 stock_total diferente'); }

// --- Preparar Woo stub y ejecutar Stage 09 -------------------------------
$FAKE_SKU_TO_ID['ABC123'] = 101;
$FAKE_PRODUCTS[101] = new FakeProduct('ABC123', 999.0, null);
require __DIR__.'/../server-mirror/compu-import-lego/includes/stages/09-pricing.php';

$snapshot = $FAKE_PRODUCTS[101]->snapshot();
if ($snapshot['regular_price'] >= 999.0) { throw new RuntimeException('Stage09 no actualizó el precio'); }
if ($snapshot['stock_qty'] !== 5) { throw new RuntimeException('Stage09 no propagó stock_total'); }

echo "OK: pipeline canónico operando con cost_usd/exchange_rate/stock_total\n";
