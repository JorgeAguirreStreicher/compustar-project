<?php
if (!defined('ABSPATH')) { exit; }
class Compu_Stage_Validate {
  public function run($args){
    $run_id = compu_import_run_id_from_arg(isset($args['run-id']) ? $args['run-id'] : 'last');
    $base = compu_import_get_base_dir();
    $dir  = trailingslashit($base) . 'run-' . $run_id;
    $src  = $dir . '/normalized.jsonl';
    if (!file_exists($src)) \WP_CLI::error('Falta normalized.jsonl; corre normalize.');
    $rows = compu_import_read_jsonl($src);
    $out  = $dir . '/validated.jsonl';
    @unlink($out);
    // Codex audit: aseguramos el artefacto aunque no haya filas válidas.
    if (file_put_contents($out, '') === false) {
      \WP_CLI::error('No se pudo preparar validated.jsonl para escritura.');
    }
    $ok=0; $err=0;
    foreach ($rows as $r) {
      $errors = [];
      $normalizedRow = $this->normalizeKeys($r);

      foreach ($this->requiredFields() as $field => $label) {
        $value = $normalizedRow[$field] ?? null;
        if ($this->isEmpty($value)) {
          $errors[] = "Campo obligatorio faltante: {$label}";
        }
      }

      foreach ($this->stockFields() as $stockField) {
        if (!array_key_exists($stockField, $normalizedRow)) {
          continue;
        }
        $value = $normalizedRow[$stockField];
        if ($this->isEmpty($value)) {
          continue;
        }
        if (!is_numeric($value)) {
          $errors[] = "Stock no numérico en {$stockField}";
          continue;
        }
        if ((float) $value < 0) {
          $errors[] = "Stock negativo en {$stockField}";
        }
      }

      if ($errors) {
        compu_import_log($run_id,'validate','error','Fila inválida: '.implode('; ', $errors), $r, isset($r['row_key'])?$r['row_key']:null);
        $err++;
        continue;
      }
      // CompuStar guards (usa llaves canónicas para título/N1).
      $guardRow = $r;
      if (isset($normalizedRow['id_menu_nvl_1'])) {
        $guardRow['lvl1_id'] = $normalizedRow['id_menu_nvl_1'];
      }
      if (isset($normalizedRow['titulo'])) {
        $guardRow['title'] = $normalizedRow['titulo'];
      }
      if (isset($normalizedRow['marca']) && !isset($guardRow['brand'])) {
        $guardRow['brand'] = $normalizedRow['marca'];
      }

      $reason = null;
      if (compu_should_skip_row($guardRow, $reason)) {
        if (function_exists('compu_import_log')) {
          compu_import_log($run_id, 'validate', 'error', $reason, $r, $r['row_key'] ?? null);
        }
        $err++;
        continue;
      }

      compu_import_append_jsonl($out, $r);
      $ok++;
    }

    if ($ok === 0) {
      compu_import_log($run_id,'validate','warn','Sin filas validadas; revisar condiciones de entrada.');
    }

    compu_import_log($run_id,'validate','info','Validación completa', ['ok'=>$ok,'err'=>$err]);
    \WP_CLI::success("Run {$run_id}: validadas OK={$ok} ERR={$err}");
  }

  /**
   * @param array<string,mixed> $row
   * @return array<string,mixed>
   */
  private function normalizeKeys(array $row): array {
    $normalized = [];
    foreach ($row as $key => $value) {
      $normalized[$this->normalizeKey((string) $key)] = $value;
    }
    return $normalized;
  }

  private function normalizeKey(string $key): string {
    $key = strtolower($key);
    $key = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $key) ?: $key;
    $key = preg_replace('/[^a-z0-9]+/i', '_', $key);
    return trim((string) $key, '_');
  }

  /**
   * @return array<string,string>
   */
  private function requiredFields(): array {
    return [
      'modelo'         => 'Modelo',
      'sku'            => 'SKU',
      'marca'          => 'Marca',
      'titulo'         => 'Título',
      'su_precio'      => 'Su Precio',
      'tipo_de_cambio' => 'Tipo de Cambio',
      'id_menu_nvl_1'  => 'ID Menu Nvl 1',
      'id_menu_nvl_2'  => 'ID Menu Nvl 2',
      'id_menu_nvl_3'  => 'ID Menu Nvl 3',
    ];
  }

// CODex audit: lista explícita de campos de stock relevantes para validar no-negativos.
  /**
   * @return array<int,string>
   */
  private function stockFields(): array {
    return [
      'existencias',
      'stock',
      'stock_total',
      'stock_disponible',
      'stock_queretaro_cedis',
      'stock_veracruz',
      'stock_tepotzotlan',
      'stock_cancun',
      'stock_culiacan',
      'stock_monterrey_centro',
      'queretaro_cedis',
      'chihuahua',
      'cd_juarez',
      'guadalajara',
      'los_mochis',
      'merida',
      'mexico_norte',
      'mexico_sur',
      'monterrey',
      'puebla',
      'queretaro',
      'tijuana',
      'villahermosa',
      'leon',
      'hermosillo',
      'san_luis_potosi',
      'torreon',
      'chihuahua_cedis',
      'toluca',
      'culiacan',
      'cancun',
      'veracruz',
      'tepotzotlan',
      'monterrey_centro',
    ];
  }

  private function isEmpty($value): bool {
    if ($value === null) {
      return true;
    }
    if (is_string($value)) {
      return trim($value) === '';
    }
    return false;
  }
}
