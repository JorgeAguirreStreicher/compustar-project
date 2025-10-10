<?php

require_once dirname(__DIR__) . '/helpers/helpers-common.php';
require_once dirname(__DIR__) . '/helpers/helpers-media.php';

// Cuando se ejecuta vía `wp eval-file` desde el orquestador puede que la
// constante aún no exista; la definimos para garantizar la ejecución del stage.
if (!defined('COMP_RUN_STAGE')) {
  define('COMP_RUN_STAGE', true);
}

// Guard: solo ejecuta en WP-CLI (no en web)
if (php_sapi_name() !== 'cli' && (!defined('WP_CLI') || !WP_CLI)) {
  return;
}

if (!function_exists('wp_remote_head') || !function_exists('wp_remote_get')) {
  require_once ABSPATH . WPINC . '/http.php';
}

// -------------------------------------------------------------------------
// Logger encapsulado
// -------------------------------------------------------------------------

class CompuStage07Logger
{
  /** @var resource|null */
  private static $handle = null;

  /** @var string */
  private static $destination = 'stdout';

  /** @var bool */
  private static $initialized = false;

  public static function bootstrap(?string $runDir): void
  {
    if (self::$initialized) {
      return;
    }

    self::$initialized = true;
    $targetHandle = null;
    $destination = 'stdout';

    if ($runDir && is_dir($runDir)) {
      $logsDir = rtrim($runDir, '/') . '/logs';
      if (!is_dir($logsDir)) {
        if (!@mkdir($logsDir, 0775, true) && !is_dir($logsDir)) {
          fwrite(STDERR, "[07] WARN: no se pudo crear el directorio de logs ($logsDir); se usará stdout\n");
        }
      }

      if (is_dir($logsDir) && is_writable($logsDir)) {
        $logPath = $logsDir . '/stage-07.log';
        $targetHandle = @fopen($logPath, 'a');
        if (!$targetHandle) {
          fwrite(STDERR, "[07] WARN: no se pudo abrir el log ($logPath); se usará stdout\n");
        } else {
          $destination = 'file';
        }
      } elseif ($runDir) {
        fwrite(STDERR, "[07] WARN: directorio de logs no escribible ($logsDir); se usará stdout\n");
      }
    }

    if (!$targetHandle) {
      $targetHandle = @fopen('php://stdout', 'w');
      if (!$targetHandle) {
        $targetHandle = STDOUT;
      }
    }

    self::$handle = $targetHandle;
    self::$destination = $destination;
  }

  public static function log(string $message): void
  {
    if (!self::$initialized) {
      self::bootstrap(null);
    }

    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    $handle = self::$handle;
    if (!is_resource($handle)) {
      $handle = @fopen('php://stdout', 'w');
      if (!$handle) {
        $handle = STDOUT;
      }
      self::$handle = $handle;
      self::$destination = 'stdout';
    }

    @fwrite($handle, $line);
  }

  public static function shutdown(): void
  {
    if (self::$initialized && is_resource(self::$handle) && self::$destination === 'file') {
      @fclose(self::$handle);
    }

    self::$handle = null;
    self::$initialized = false;
    self::$destination = 'stdout';
  }
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
 * Calcula rápidamente la cantidad de líneas de un archivo sin cargarlo a
 * memoria. Usado únicamente para propósitos de logging.
 */
function compu_stage07_count_lines(string $path): int
{
  try {
    $file = new SplFileObject($path, 'r');
    $file->seek(PHP_INT_MAX);
    return $file->key() + 1;
  } catch (Throwable $e) {
    return 0;
  }
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

  $attempts = 0;
  $maxAttempts = 2;
  $delay = 1;

  do {
    $attempts++;
    $response = wp_remote_head($url, $args);

    if (is_wp_error($response) && compu_stage07_is_timeout($response) && $attempts < $maxAttempts) {
      sleep($delay);
      $delay *= 2;
      continue;
    }

    break;
  } while ($attempts < $maxAttempts);

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

    $attempts = 0;
    $delay = 1;

    do {
      $attempts++;
      $response = wp_remote_get($url, $getArgs);

      if (is_wp_error($response) && compu_stage07_is_timeout($response) && $attempts < $maxAttempts) {
        sleep($delay);
        $delay *= 2;
        continue;
      }

      break;
    } while ($attempts < $maxAttempts);

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

$run = rtrim(compu_import_resolve_run_dir(), '/');
if ($run === '' || !is_dir($run)) {
  fwrite(STDERR, "[07] RUN_DIR/RUN_PATH vacío o inválido\n");
  CompuStage07Logger::bootstrap(null);
  CompuStage07Logger::log('RUN_DIR ausente; abortando Stage 07');
  CompuStage07Logger::shutdown();
  exit(1);
}

if (!is_writable($run)) {
  fwrite(STDERR, "[07] RUN_DIR no es escribible: $run\n");
  CompuStage07Logger::bootstrap($run);
  CompuStage07Logger::log('RUN_DIR no escribible; abortando Stage 07');
  CompuStage07Logger::shutdown();
  exit(1);
}

CompuStage07Logger::bootstrap($run);

$resolvedPath = $run . '/resolved.jsonl';
$validatedPath = $run . '/validated.jsonl';
$inputPath = null;

if (is_readable($resolvedPath)) {
  $inputPath = $resolvedPath;
} elseif (is_readable($validatedPath)) {
  $inputPath = $validatedPath;
  CompuStage07Logger::log('WARN: No se encontró resolved.jsonl, usando validated.jsonl');
} else {
  fwrite(STDERR, "[07] No existe resolved.jsonl ni validated.jsonl en $run\n");
  CompuStage07Logger::log('No se encontraron archivos de entrada requeridos');
  CompuStage07Logger::shutdown();
  exit(1);
}

$inputHandle = @fopen($inputPath, 'r');
if (!$inputHandle) {
  fwrite(STDERR, "[07] No se pudo abrir el origen: $inputPath\n");
  CompuStage07Logger::log('No se pudo abrir el archivo de entrada: ' . $inputPath);
  CompuStage07Logger::shutdown();
  exit(1);
}

$outputPath = $run . '/media.jsonl';
$outputHandle = @fopen($outputPath, 'w');
if (!$outputHandle) {
  fwrite(STDERR, "[07] No se pudo crear el archivo de salida: $outputPath\n");
  fclose($inputHandle);
  CompuStage07Logger::log('No se pudo crear el archivo de salida: ' . $outputPath);
  CompuStage07Logger::shutdown();
  exit(1);
}

$expectedLines = compu_stage07_count_lines($inputPath);
$startMessage = sprintf(
  'Inicio Stage 07 - run=%s input=%s expected_lines=%d',
  $run,
  $inputPath,
  $expectedLines
);
CompuStage07Logger::log($startMessage);
echo sprintf(
  '[07] Start media manifest (run=%s input=%s expected_lines=%d)' . "\n",
  $run,
  $inputPath,
  $expectedLines
);

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
    CompuStage07Logger::log("Fila $total: JSON inválido, se omite");
    continue;
  }

  $skuRaw = compu_stage07_get($row, ['sku', 'SKU', 'model', 'modelo', 'Modelo']);
  $sku = is_string($skuRaw) ? trim($skuRaw) : '';
  if ($sku === '') {
    $errorCount++;
    CompuStage07Logger::log("Fila $total: sin SKU, se omite");
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
      $imageStatus = 'error';
      $notes = 'URL inválida';
    } else {
      $check = compu_stage07_check_url($imageUrl);
      $imageStatus = $check['status'] === 'ok' ? 'ok' : 'error';
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
    'sku'          => $sku,
    'image_url'    => $imageUrl,
    'image_status' => $imageStatus,
    'source'       => $imageUrl ? 'url' : 'none',
  ];

  if (!empty($galleryUrls)) {
    $record['gallery_urls'] = array_values($galleryUrls);
  }

  if ($notes !== null && $notes !== '') {
    $record['notes'] = $notes;
  }

  $jsonOptions = JSON_UNESCAPED_SLASHES;
  $encoded = json_encode($record, $jsonOptions);
  if ($encoded === false) {
    $errorCount++;
    CompuStage07Logger::log("Fila $total: no se pudo codificar JSON para SKU $sku");
    continue;
  }

  $bytesWritten = fwrite($outputHandle, $encoded . "\n");
  if ($bytesWritten === false) {
    CompuStage07Logger::log("Fila $total: error al escribir salida para SKU $sku");
    fclose($inputHandle);
    fclose($outputHandle);
    fwrite(STDERR, "[07] Error al escribir media.jsonl (SKU $sku)\n");
    CompuStage07Logger::shutdown();
    exit(1);
  }
  $written++;

  if ($total % 20 === 0) {
    CompuStage07Logger::log(
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
  CompuStage07Logger::log('media.jsonl no existe tras el procesamiento');
  CompuStage07Logger::shutdown();
  exit(1);
}

// Se fuerza la existencia del archivo aún si no se escribió ninguna línea.
if ($written === 0) {
  touch($outputPath);
}

CompuStage07Logger::log(
  sprintf(
    'Fin Stage 07: total=%d ok=%d missing=%d errors=%d salida=%s',
    $total,
    $okCount,
    $missingCount,
    $errorCount,
    $outputPath
  )
);

CompuStage07Logger::shutdown();

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

