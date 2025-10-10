<?php
require_once dirname(__DIR__) . "/helpers/helpers-common.php";

require_once dirname(__DIR__) . '/helpers/helpers-media.php';

if (!defined('COMP_RUN_STAGE')) { define('COMP_RUN_STAGE', 1); }

// Guard: solo ejecuta en WP-CLI (no en web)
if (php_sapi_name() !== 'cli' && (!defined('WP_CLI') || !WP_CLI)) {
  return;
}

if (!function_exists('wp_remote_head') || !function_exists('wp_remote_get')) {
  require_once ABSPATH . WPINC . '/http.php';
}

/**
 * Stage 07: Media manifest
 *
 * Lee resolved.jsonl (o validated.jsonl), valida URLs de imagen y genera
 * RUN/media.jsonl con el estado de cada producto.
 */

// -------------------------------------------------------------------------
// Utilidades básicas
// -------------------------------------------------------------------------

/**
 * Normaliza una llave para búsquedas tolerantes (snake_case + lowercase).
 */
function compu_stage07_normalize_key(string $key): string {
  return strtolower(preg_replace('/[^a-z0-9]+/i', '_', $key));
}

/**
 * Obtiene el primer valor coincidente dentro de un registro usando llaves
 * alternativas (insensible a mayúsculas, espacios y guiones).
 *
 * @param array<int|string, mixed> $row
 * @param string[]                 $candidates
 * @return mixed|null
 */
function compu_stage07_get(array $row, array $candidates)
{
  $normalized = [];
  foreach ($row as $key => $value) {
    if (is_string($key)) {
      $normalized[compu_stage07_normalize_key($key)] = $value;
    }
  }

  foreach ($candidates as $candidate) {
    if (array_key_exists($candidate, $row)) {
      return $row[$candidate];
    }
    $norm = compu_stage07_normalize_key($candidate);
    if (array_key_exists($norm, $normalized)) {
      return $normalized[$norm];
    }
  }

  return null;
}

/**
 * Convierte galerías (string/lista) en un arreglo de URLs limpias.
 *
 * @param mixed $raw
 * @return string[]
 */
function compu_stage07_normalize_gallery($raw): array
{
  $candidates = [];
  if (is_array($raw)) {
    $candidates = $raw;
  } elseif (is_string($raw)) {
    $parts = preg_split('/[\s,;|]+/', $raw) ?: [];
    $candidates = $parts;
  }

  $urls = [];
  foreach ($candidates as $candidate) {
    if (!is_string($candidate)) {
      continue;
    }
    $url = trim($candidate);
    if ($url === '') {
      continue;
    }
    if (!preg_match('~^https?://~i', $url)) {
      continue;
    }
    $urls[$url] = true; // mantiene únicos
  }

  return array_keys($urls);
}

/**
 * Determina si un WP_Error representa un timeout.
 */
function compu_stage07_is_timeout($error): bool
{
  if (!is_wp_error($error)) {
    return false;
  }

  $timeoutCodes = [
    'connect_timeout',
    'http_request_timeout',
    'timeout',
    'request_timeout',
  ];

  if (in_array($error->get_error_code(), $timeoutCodes, true)) {
    return true;
  }

  $message = strtolower($error->get_error_message() ?? '');
  return strpos($message, 'timed out') !== false
    || strpos($message, 'timeout') !== false;
}

/**
 * Valida una URL (HEAD → GET) y retorna estado + nota.
 *
 * @param string $url
 * @return array{status: string, note: string|null, http_code: int|null}
 */
function compu_stage07_check_url(string $url): array
{
  $args = [
    'timeout'      => 8,
    'redirection'  => 3,
    'headers'      => [
      'User-Agent' => 'compu-import-stage07/1.0',
    ],
  ];

  $response = wp_remote_head($url, $args);

  if (is_wp_error($response)) {
    if (compu_stage07_is_timeout($response)) {
      return [
        'status'    => 'timeout',
        'note'      => $response->get_error_message(),
        'http_code' => null,
      ];
    }
    // Intento con GET para diferenciar errores reales de falta de soporte HEAD
    $response = null;
  }

  $httpCode = null;
  if (is_array($response)) {
    $httpCode = wp_remote_retrieve_response_code($response);
    if ($httpCode >= 200 && $httpCode < 300) {
      return [
        'status'    => 'ok',
        'note'      => null,
        'http_code' => $httpCode,
      ];
    }

    if ($httpCode === 405 || $httpCode === 403 || $httpCode === 0) {
      // Algunos servidores no permiten HEAD → se intenta GET
      $response = null;
    } elseif ($httpCode >= 400) {
      return [
        'status'    => 'http_error',
        'note'      => 'HTTP ' . $httpCode,
        'http_code' => $httpCode,
      ];
    }
  }

  if ($response === null) {
    $getArgs = $args;
    $getArgs['limit_response_size'] = 32768;
    $getArgs['method'] = 'GET';
    $getArgs['headers']['Range'] = 'bytes=0-4096';

    $response = wp_remote_get($url, $getArgs);

    if (is_wp_error($response)) {
      if (compu_stage07_is_timeout($response)) {
        return [
          'status'    => 'timeout',
          'note'      => $response->get_error_message(),
          'http_code' => null,
        ];
      }

      return [
        'status'    => 'http_error',
        'note'      => $response->get_error_message(),
        'http_code' => null,
      ];
    }

    $httpCode = wp_remote_retrieve_response_code($response);
    if ($httpCode >= 200 && $httpCode < 300) {
      return [
        'status'    => 'ok',
        'note'      => null,
        'http_code' => $httpCode,
      ];
    }

    if ($httpCode >= 400) {
      return [
        'status'    => 'http_error',
        'note'      => 'HTTP ' . $httpCode,
        'http_code' => $httpCode,
      ];
    }
  }

  return [
    'status'    => 'http_error',
    'note'      => $httpCode ? 'HTTP ' . $httpCode : 'Respuesta inesperada',
    'http_code' => $httpCode,
  ];
}

// -------------------------------------------------------------------------
// Resolución de RUN / archivos de entrada
// -------------------------------------------------------------------------

$run = rtrim(getenv('RUN_DIR') ?: getenv('RUN_PATH') ?: '', '/');
if ($run === '' || !is_dir($run)) {
  fwrite(STDERR, "[07] RUN_DIR/RUN_PATH vacío o inválido\n");
  exit(1);
}

if (!is_writable($run)) {
  fwrite(STDERR, "[07] RUN_DIR no es escribible: $run\n");
  exit(1);
}

$logsDir = $run . '/logs';
if (!is_dir($logsDir) && !@mkdir($logsDir, 0775, true) && !is_dir($logsDir)) {
  fwrite(STDERR, "[07] No se pudo crear el directorio de logs: $logsDir\n");
  exit(1);
}

$logPath = $logsDir . '/stage-07.log';
$logHandle = @fopen($logPath, 'a');
if (!$logHandle) {
  fwrite(STDERR, "[07] No se pudo abrir el log: $logPath\n");
  exit(1);
}

/* === init stage-07 log handle (global) === */
$__run = rtrim(getenv('RUN_DIR') ?: getenv('RUN_PATH') ?: '', '/');
$__logDir = $__run !== '' ? ($__run . '/logs') : '';
if ($__logDir !== '' && !is_dir($__logDir)) { @mkdir($__logDir, 0775, true); }
$__logPath = $__logDir !== '' ? ($__logDir . '/stage-07.log') : '';
$logHandle = ($__logPath !== '' ? @fopen($__logPath, 'ab') : false);
if (!$logHandle) { $logHandle = fopen('php://stdout', 'w'); }
/* === end init === */
function compu_stage07_log(string $message): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @fwrite(STDERR, "[07] " . $line);
}

$resolvedPath = $run . '/resolved.jsonl';
$validatedPath = $run . '/validated.jsonl';
$inputPath = null;

if (is_readable($resolvedPath)) {
  $inputPath = $resolvedPath;
} elseif (is_readable($validatedPath)) {
  $inputPath = $validatedPath;
  compu_stage07_log('WARN: No se encontró resolved.jsonl, usando validated.jsonl');
} else {
  fwrite(STDERR, "[07] No existe resolved.jsonl ni validated.jsonl en $run\n");
  if (is_resource($logHandle)) { if (is_resource($logHandle)) { fclose($logHandle); } }
  exit(1);
}

$inputHandle = @fopen($inputPath, 'r');
if (!$inputHandle) {
  fwrite(STDERR, "[07] No se pudo abrir el origen: $inputPath\n");
  if (is_resource($logHandle)) { if (is_resource($logHandle)) { fclose($logHandle); } }
  exit(1);
}

$outputPath = $run . '/media.jsonl';
$outputHandle = @fopen($outputPath, 'w');
if (!$outputHandle) {
  fwrite(STDERR, "[07] No se pudo crear el archivo de salida: $outputPath\n");
  fclose($inputHandle);
  if (is_resource($logHandle)) { if (is_resource($logHandle)) { fclose($logHandle); } }
  exit(1);
}

compu_stage07_log("Inicio Stage 07 - run=$run input=$inputPath");

$total = 0;
$okCount = 0;
$missingCount = 0;
$errorCount = 0;
$written = 0;

while (($line = fgets($inputHandle)) !== false) {
  $line = trim($line);
  if ($line === '') {
    continue;
  }

  $total++;
  $row = json_decode($line, true);
  if (!is_array($row)) {
    $errorCount++;
    compu_stage07_log("Fila $total: JSON inválido, se omite");
    continue;
  }

  $skuRaw = compu_stage07_get($row, ['sku', 'SKU', 'model', 'modelo', 'Modelo']);
  $sku = is_string($skuRaw) ? trim($skuRaw) : '';
  if ($sku === '') {
    $errorCount++;
    compu_stage07_log("Fila $total: sin SKU, se omite");
    continue;
  }

  $title = compu_stage07_get($row, ['title', 'titulo', 'título']);
  $brand = compu_stage07_get($row, ['brand', 'marca']);

  $imageCandidate = compu_stage07_get($row, [
    'image',
    'image_url',
    'imagen_principal',
    'imagen principal',
    'img',
    'Imagen_Principal',
  ]);
  $imageUrl = is_string($imageCandidate) ? trim($imageCandidate) : '';

  $galleryRaw = compu_stage07_get($row, [
    'gallery',
    'galeria',
    'galería',
    'gallery_urls',
    'imagenes',
    'images',
  ]);
  $galleryUrls = compu_stage07_normalize_gallery($galleryRaw);

  $imageStatus = 'missing';
  $notes = null;

  if ($imageUrl === '') {
    $imageUrl = null;
    if (!empty($galleryUrls)) {
      $notes = 'Sin imagen principal; se usará galería';
    }
  } else {
    if (!preg_match('~^https?://~i', $imageUrl) || !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
      $imageStatus = 'invalid_url';
      $notes = 'URL inválida';
    } else {
      $check = compu_stage07_check_url($imageUrl);
      $imageStatus = $check['status'];
      $notes = $check['note'];
    }
  }

  if ($imageStatus === 'missing' && $imageUrl === null && $notes === null) {
    if (empty($galleryUrls)) {
      $notes = 'Sin imagen principal ni galería';
    }
  }

  if ($imageStatus === 'ok') {
    $okCount++;
  } elseif ($imageStatus === 'missing') {
    $missingCount++;
  } else {
    $errorCount++;
  }

  $record = [
    'sku'           => $sku,
    'image_url'     => $imageUrl,
    'gallery_urls'  => array_values($galleryUrls),
    'image_status'  => $imageStatus,
    'source'        => 'url',
  ];

  if ($notes !== null && $notes !== '') {
    $record['notes'] = $notes;
  }

  $jsonOptions = JSON_UNESCAPED_SLASHES;
  $encoded = json_encode($record, $jsonOptions);
  if ($encoded === false) {
    $errorCount++;
    compu_stage07_log("Fila $total: no se pudo codificar JSON para SKU $sku");
    continue;
  }

  fwrite($outputHandle, $encoded . "\n");
  $written++;

  if ($total % 20 === 0) {
    compu_stage07_log(
      sprintf(
        'Progreso: processed=%d ok=%d missing=%d errors=%d (SKU=%s, Marca=%s, Titulo=%s)',
        $total,
        $okCount,
        $missingCount,
        $errorCount,
        $sku,
        is_string($brand) ? $brand : '-',
        is_string($title) ? $title : '-'
      )
    );
  }
}

fclose($inputHandle);
fclose($outputHandle);

if (!file_exists($outputPath)) {
  fwrite(STDERR, "[07] No se generó media.jsonl en $run\n");
  if (is_resource($logHandle)) { if (is_resource($logHandle)) { fclose($logHandle); } }
  exit(1);
}

// Se fuerza la existencia del archivo aún si no se escribió ninguna línea.
if ($written === 0) {
  touch($outputPath);
}

compu_stage07_log(
  sprintf(
    'Fin Stage 07: total=%d ok=%d missing=%d errors=%d salida=%s',
    $total,
    $okCount,
    $missingCount,
    $errorCount,
    $outputPath
  )
);

if (is_resource($logHandle)) { if (is_resource($logHandle)) { fclose($logHandle); } }

$summary = sprintf(
  '[07] Wrote %s (lines=%d, ok=%d, missing=%d, errors=%d)',
  $outputPath,
  $written,
  $okCount,
  $missingCount,
  $errorCount
);

echo $summary . "\n";

exit(0);

