<?php
if (!defined('ABSPATH')) { exit; }

require_once dirname(__DIR__) . '/helpers/helpers-common.php';
require_once dirname(__DIR__) . '/helpers/helpers-db.php';

/**
 * Stage 02 - NORMALIZE
 *
 * Lee el CSV crudo del run y construye un diccionario canónico por fila.
 * El diccionario mantiene alias temporales para compatibilidad mientras
 * el resto del pipeline migra a los nuevos nombres (`cost_usd`,
 * `exchange_rate`, `stock_total`, etc.).
 */
class Compu_Stage_Normalize {
  /** @var array<string,array<int,string>> */
  private $headerSynonyms = [];
  /** @var array<string,array<int,string>> */
  private $branchSynonyms = [];

  public function __construct(){
    $this->bootstrapSynonyms();
  }

  /**
   * Punto de entrada desde WP-CLI (`wp compu stage normalize`).
   * @param array<string,mixed> $args
   */
  public function run($args){
    $run_id = compu_import_run_id_from_arg($args['run-id'] ?? 'last');
    $base   = compu_import_get_base_dir();
    $dir    = trailingslashit($base).'run-'.$run_id;
    $src    = $dir.'/source.csv';
    $srcEnv = getenv('CSV_SRC');
    if ($srcEnv && file_exists($srcEnv)) { $src = $srcEnv; }
    if (!file_exists($src)) { \WP_CLI::error('Falta source.csv; ejecuta fetch antes de normalize.'); }

    $normalized = $dir.'/normalized.jsonl';
    $headerMap  = $dir.'/header-map.json';
    $dupesCsv   = $dir.'/duplicates.csv';
    @unlink($normalized);
    @unlink($headerMap);
    @unlink($dupesCsv);
    touch($normalized);

    $fh = fopen($src, 'r');
    if (!$fh) { \WP_CLI::error('No se pudo abrir source.csv para lectura'); }

    $delimiter   = $this->detect_delimiter($src);
    $header_raw  = fgetcsv($fh, 0, $delimiter, '"', '\\');
    if ($header_raw === false) {
      fclose($fh);
      \WP_CLI::error('El CSV no tiene cabecera legible.');
    }

    $header_norm = array_map(function($h){ return $this->norm_header($h); }, $header_raw);
    $columnIndex = $this->build_column_index($header_norm);
    $branchIndex = $this->build_branch_index($header_norm);

    $hdr_meta = [
      'header'            => $header_raw,
      'normalized'        => $header_norm,
      'column_index'      => $columnIndex,
      'branch_index'      => $branchIndex,
      'detected_delimiter'=> $delimiter,
    ];
    file_put_contents(
      $headerMap,
      wp_json_encode($hdr_meta, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
    );

    $exclusions_csv = '/home/compustar/syscom_l1_exclusions.csv';
    $exclude_l1     = $this->load_l1_exclusions($exclusions_csv);

    $seenSkus   = [];
    $dupes      = [];
    $written    = 0;
    $skippedSku = 0;

    while (($row = fgetcsv($fh, 0, $delimiter, '"', '\\')) !== false) {
      $canon = $this->build_canonical_row($row, $columnIndex, $branchIndex);
      if ($canon === null) {
        $skippedSku++;
        compu_import_log($run_id,'normalize','error','Fila omitida: sin SKU detectable');
        continue;
      }

      // Exclusión por L1 si aplica
      $lvl1_id = $canon['lvl1_id'] ?? null;
      if ($lvl1_id && isset($exclude_l1[$lvl1_id])) {
        compu_import_log($run_id, 'normalize', 'info', 'Fila excluida por L1', [
          'lvl1_id' => $lvl1_id,
          'sku'     => $canon['sku'],
        ]);
        continue;
      }

      $key = mb_strtolower($canon['sku'], 'UTF-8');
      if (isset($seenSkus[$key])) {
        $dupes[$canon['sku']] = ($dupes[$canon['sku']] ?? 1) + 1;
      }
      $seenSkus[$key] = true;

      compu_import_append_jsonl($normalized, $canon);
      $written++;
    }
    fclose($fh);

    if (!empty($dupes)) {
      $fhDupes = fopen($dupesCsv, 'w');
      fputcsv($fhDupes, ['sku','duplicate_count']);
      foreach ($dupes as $sku => $count) {
        fputcsv($fhDupes, [$sku, $count]);
        compu_import_log($run_id,'normalize','warning','SKU duplicado detectado',[
          'sku' => $sku,
          'count' => $count,
        ]);
      }
      fclose($fhDupes);
    }

    compu_import_log($run_id,'normalize','info','Stage 02 completado',[
      'rows_written'   => $written,
      'skipped_no_sku' => $skippedSku,
    ]);
  }

  private function bootstrapSynonyms(): void {
    $aliasFile = dirname(__DIR__, 2).'/aliases.json';
    $raw       = [];
    if (file_exists($aliasFile)) {
      $json = json_decode(file_get_contents($aliasFile), true);
      if (is_array($json)) { $raw = $json; }
    }

    $map = [
      'sku'             => ['sku','modelo','model','mpn','codigo','code','clave','product_code'],
      'supplier_sku'    => ['supplier_sku','sku_proveedor'],
      'brand'           => ['brand','marca'],
      'model'           => ['model','modelo','mpn'],
      'title'           => ['title','titulo','título','nombre','product_title'],
      'description_html'=> ['description','descripcion','descripción','desc','detalle','html','content'],
      'image_url'       => ['image_url','imagen','imagen_principal','image','img','picture','url_imagen'],
      'cost_usd'        => ['cost_usd','su_precio','su precio','precio_usd','precio usd','precio','price_customer'],
      'list_price_usd'  => ['list_price','price_list','precio_lista','lista'],
      'special_price_usd'=> ['special_price','precio_especial'],
      'exchange_rate'   => ['exchange_rate','tipo_cambio','tipo de cambio','tc','exchange'],
      'weight_kg'       => ['weight_kg','peso','peso_kg','peso kg','weight'],
      'tax_code'        => ['tax_code','codigo_fiscal','código fiscal','clave fiscal','cfdi'],
      'lvl1'            => ['lvl1','menu_n1','menu 1','nivel 1','categoria','categoría'],
      'lvl2'            => ['lvl2','menu_n2','menu 2','nivel 2','subcategoria','sub-categoria'],
      'lvl3'            => ['lvl3','menu_n3','menu 3','nivel 3','subsubcategoria'],
      'lvl1_id'         => ['lvl1_id','id_n1','id menu nvl 1','id_menu_nvl_1'],
      'lvl2_id'         => ['lvl2_id','id_n2','id menu nvl 2','id_menu_nvl_2'],
      'lvl3_id'         => ['lvl3_id','id_n3','id menu nvl 3','id_menu_nvl_3'],
      'stock_total'     => ['stock_total','stock','existencias','existencia','inventario'],
      'stock_tijuana'   => ['stock_tijuana','tijuana','stock tj'],
      'stock_main'      => ['stock_main','stock_otros','stock_others','stock_a15'],
    ];

    foreach ($raw as $aliasKey => $variants) {
      if (!is_array($variants)) { continue; }
      $canonical = $this->mapAliasKeyToCanonical($aliasKey);
      if (!$canonical) { continue; }
      foreach ($variants as $variant) {
        $map[$canonical][] = $variant;
      }
    }

    foreach ($map as $canonical => $variants) {
      $norm = [];
      foreach ($variants as $variant) {
        $v = $this->norm_header($variant);
        if ($v !== '') { $norm[$v] = true; }
      }
      $this->headerSynonyms[$canonical] = array_keys($norm);
    }

    // Branch aliases (solo guardamos normalizados)
    $branchMap = [];
    foreach ($raw as $aliasKey => $variants) {
      if (strpos($aliasKey, 'stock_') !== 0) { continue; }
      if (!is_array($variants)) { continue; }
      foreach ($variants as $variant) {
        $branchMap[$aliasKey][] = $this->norm_header($variant);
      }
    }
    $this->branchSynonyms = $branchMap;
  }

  private function mapAliasKeyToCanonical(string $aliasKey): ?string {
    $map = [
      'sku'           => 'sku',
      'brand'         => 'brand',
      'title'         => 'title',
      'price_customer'=> 'cost_usd',
      'price_list'    => 'list_price_usd',
      'price_special' => 'special_price_usd',
      'exchange_rate' => 'exchange_rate',
      'weight_kg'     => 'weight_kg',
      'tax_code'      => 'tax_code',
      'description'   => 'description_html',
      'image_url'     => 'image_url',
      'cat_lvl1'      => 'lvl1',
      'cat_lvl2'      => 'lvl2',
      'cat_lvl3'      => 'lvl3',
      'cat_id_lvl1'   => 'lvl1_id',
      'cat_id_lvl2'   => 'lvl2_id',
      'cat_id_lvl3'   => 'lvl3_id',
    ];
    return $map[$aliasKey] ?? null;
  }

  /**
   * @param array<int,string> $headerNorm
   * @return array<string,array<int,int>> canonical => list of column indexes
   */
  private function build_column_index(array $headerNorm): array {
    $index = [];
    foreach ($this->headerSynonyms as $canonical => $variants) {
      foreach ($variants as $variant) {
        foreach ($headerNorm as $i => $header) {
          if ($header === $variant) {
            $index[$canonical][] = $i;
          }
        }
      }
    }
    return $index;
  }

  /**
   * @param array<int,string> $headerNorm
   * @return array<string,int> branch alias => column index
   */
  private function build_branch_index(array $headerNorm): array {
    $branchIndex = [];
    foreach ($this->branchSynonyms as $aliasKey => $variants) {
      foreach ($variants as $variant) {
        foreach ($headerNorm as $i => $header) {
          if ($header === $variant) {
            $branchIndex[$aliasKey] = $i;
          }
        }
      }
    }
    return $branchIndex;
  }

  private function detect_delimiter(string $file): string {
    $sample = file_get_contents($file, false, null, 0, 2048) ?: '';
    $candidates = [',',';','\t','|'];
    $best = ','; $bestCount = -1;
    foreach ($candidates as $cand) {
      $count = substr_count($sample, $cand);
      if ($count > $bestCount) { $best = $cand; $bestCount = $count; }
    }
    return $best;
  }

  private function norm_header($header): string {
    $h = (string)$header;
    $h = preg_replace('/^\xEF\xBB\xBF/', '', $h); // BOM
    if (preg_match('/M-CM-|Ã|Â/u', $h)) {
      $try = @iconv('WINDOWS-1252', 'UTF-8//TRANSLIT//IGNORE', $h);
      if ($try !== false) { $h = $try; }
      $h = str_replace(['M-CM-','Â','Ã'], '', $h);
    }
    if (class_exists('Transliterator')) {
      $t = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC;');
      if ($t) { $h = $t->transliterate($h); }
    } else {
      $h = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$h);
    }
    $h = mb_strtolower($h ?: '', 'UTF-8');
    $h = preg_replace('/[^a-z0-9]+/u','_', $h);
    $h = preg_replace('/_+/', '_', $h);
    return trim($h, '_');
  }

  /**
   * @param array<int,string> $row
   * @param array<string,array<int,int>> $columnIndex
   * @param array<string,int> $branchIndex
   * @return array<string,mixed>|null
   */
  private function build_canonical_row(array $row, array $columnIndex, array $branchIndex): ?array {
    $sku = $this->first_non_empty($row, $columnIndex['sku'] ?? []);
    if ($sku === null || $sku === '') {
      $skuAlt = $this->first_non_empty($row, $columnIndex['model'] ?? []);
      $sku = $skuAlt !== null ? $skuAlt : null;
    }
    if ($sku === null || $sku === '') {
      return null;
    }

    $brand   = $this->first_non_empty($row, $columnIndex['brand'] ?? []);
    $model   = $this->first_non_empty($row, $columnIndex['model'] ?? []);
    $title   = $this->first_non_empty($row, $columnIndex['title'] ?? []);
    $desc    = $this->first_non_empty($row, $columnIndex['description_html'] ?? []);
    $image   = $this->first_non_empty($row, $columnIndex['image_url'] ?? []);

    $cost    = $this->to_float($this->first_non_empty($row, $columnIndex['cost_usd'] ?? []));
    $list    = $this->to_float($this->first_non_empty($row, $columnIndex['list_price_usd'] ?? []));
    $special = $this->to_float($this->first_non_empty($row, $columnIndex['special_price_usd'] ?? []));
    $fx      = $this->to_float($this->first_non_empty($row, $columnIndex['exchange_rate'] ?? []));
    $weight  = $this->to_float($this->first_non_empty($row, $columnIndex['weight_kg'] ?? []));
    $taxCode = $this->first_non_empty($row, $columnIndex['tax_code'] ?? []);

    $lvl1    = $this->first_non_empty($row, $columnIndex['lvl1'] ?? []);
    $lvl2    = $this->first_non_empty($row, $columnIndex['lvl2'] ?? []);
    $lvl3    = $this->first_non_empty($row, $columnIndex['lvl3'] ?? []);
    $lvl1_id = $this->first_non_empty($row, $columnIndex['lvl1_id'] ?? []);
    $lvl2_id = $this->first_non_empty($row, $columnIndex['lvl2_id'] ?? []);
    $lvl3_id = $this->first_non_empty($row, $columnIndex['lvl3_id'] ?? []);

    $stockFromColumn = $this->to_int($this->first_non_empty($row, $columnIndex['stock_total'] ?? []));
    $stockMainColumn = $this->to_int($this->first_non_empty($row, $columnIndex['stock_main'] ?? []));
    $stockTjColumn   = $this->to_int($this->first_non_empty($row, $columnIndex['stock_tijuana'] ?? []));

    $branchStocks = $this->extract_branch_stocks($row, $branchIndex);
    if (!empty($branchStocks)) {
      $stockTotal = array_sum($branchStocks);
      $stockTj    = 0;
      foreach ($branchStocks as $key => $qty) {
        if (stripos($key, 'tijuana') !== false || stripos($key, 'tj') !== false) {
          $stockTj += $qty;
        }
      }
      $stockMain = max(0, $stockTotal - $stockTj);
    } else {
      $stockTotal = $stockFromColumn ?? 0;
      $stockTj    = $stockTjColumn ?? 0;
      $stockMain  = $stockMainColumn ?? max(0, $stockTotal - $stockTj);
      if ($stockTotal === 0 && $stockFromColumn === null) {
        $stockTotal = $stockMain + $stockTj;
      }
    }

    $nameParts = [];
    if ($brand) { $nameParts[] = $this->trim_text($brand); }
    if ($model && strtolower($model) !== strtolower($sku)) { $nameParts[] = $this->trim_text($model); }
    if ($title) { $nameParts[] = $this->trim_text($title); }
    $name = trim(implode(' ', array_filter($nameParts)));
    if ($name === '' && $sku) { $name = (string)$sku; }

    $canonical = [
      'sku'                => (string)$sku,
      'supplier_sku'       => (string)$sku,
      'brand'              => $brand !== null ? $this->trim_text($brand) : null,
      'model'              => $model !== null ? $this->trim_text($model) : null,
      'title'              => $title !== null ? $this->trim_text($title) : null,
      'name'               => $name,
      'description_html'   => $desc !== null ? (string)$desc : null,
      'image_url'          => $image !== null ? (string)$image : null,
      'cost_usd'           => $cost,
      'list_price_usd'     => $list,
      'special_price_usd'  => $special,
      'exchange_rate'      => $fx,
      'weight_kg'          => $weight,
      'tax_code'           => $taxCode !== null ? (string)$taxCode : null,
      'lvl1'               => $lvl1,
      'lvl2'               => $lvl2,
      'lvl3'               => $lvl3,
      'lvl1_id'            => $lvl1_id,
      'lvl2_id'            => $lvl2_id,
      'lvl3_id'            => $lvl3_id,
      'stock_total'        => $stockTotal,
      'stock_main'         => $stockMain,
      'stock_tijuana'      => $stockTj,
      'stock_by_branch'    => !empty($branchStocks) ? $branchStocks : null,
    ];

    // Aliases temporales (mantener mientras se migra el pipeline)
    $canonical['description']  = $canonical['description_html'];
    $canonical['desc_html']    = $canonical['description_html'];
    $canonical['image']        = $canonical['image_url'];
    $canonical['img']          = $canonical['image_url'];
    $canonical['price_usd']    = $canonical['cost_usd'];
    $canonical['su_precio']    = $canonical['cost_usd'];
    $canonical['price_list']   = $canonical['list_price_usd'];
    $canonical['price_special']= $canonical['special_price_usd'];
    $canonical['fx']           = $canonical['exchange_rate'];
    $canonical['stock']        = $canonical['stock_total'];
    $canonical['stock_a15']    = $canonical['stock_main'];
    $canonical['stock_a15_tj'] = $canonical['stock_tijuana'];
    $canonical['stock_others'] = $canonical['stock_main'];
    if (!empty($branchStocks)) {
      $canonical['stocks_by_branch'] = $branchStocks;
      $canonical['stock_by_wh']      = $branchStocks;
    }

    return $canonical;
  }

  private function first_non_empty(array $row, array $indexes){
    foreach ($indexes as $idx) {
      if (array_key_exists($idx, $row)) {
        $value = trim((string)$row[$idx]);
        if ($value !== '') { return $value; }
      }
    }
    return null;
  }

  private function to_float($value): ?float {
    if ($value === null) { return null; }
    $s = trim((string)$value);
    if ($s === '') { return null; }
    $s = str_replace(['USD','MXN','$',"\xC2\xA0"],'', $s);
    $s = str_replace(' ', '', $s);
    $s = str_replace(',', '.', $s);
    $s = preg_replace('/[^0-9\.-]/', '', $s);
    if ($s === '' || $s === '-' || $s === '.') { return null; }
    return (float)$s;
  }

  private function to_int($value): ?int {
    if ($value === null) { return null; }
    $s = trim((string)$value);
    if ($s === '') { return null; }
    $s = preg_replace('/[^0-9\-]/','', $s);
    if ($s === '' || $s === '-') { return null; }
    return (int)$s;
  }

  private function trim_text(?string $value): ?string {
    if ($value === null) { return null; }
    $t = trim($value);
    $t = preg_replace('/\s+/u',' ', $t);
    return $t === '' ? null : $t;
  }

  /**
   * @param array<int,string> $row
   * @param array<string,int> $branchIndex
   * @return array<string,int>
   */
  private function extract_branch_stocks(array $row, array $branchIndex): array {
    $out = [];
    foreach ($branchIndex as $aliasKey => $idx) {
      if (!array_key_exists($idx, $row)) { continue; }
      $qty = $this->to_int($row[$idx]);
      if ($qty === null) { continue; }
      $out[$aliasKey] = max(0, $qty);
    }
    return $out;
  }

  /**
   * @return array<string,bool>
   */
  private function load_l1_exclusions(string $path): array {
    if (!file_exists($path)) { return []; }
    $fh = fopen($path, 'r');
    if (!$fh) { return []; }
    $out = [];
    while (($line = fgetcsv($fh)) !== false) {
      $id = trim((string)($line[0] ?? ''));
      if ($id !== '') { $out[$id] = true; }
    }
    fclose($fh);
    return $out;
  }
}
