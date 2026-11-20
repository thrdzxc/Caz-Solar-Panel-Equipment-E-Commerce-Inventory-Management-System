<?php
/** Product-only normalization. Transparent photos keep their alpha channel. */
function caz_normalize_gallery_image(string $path): array {
    if (!function_exists('imagecreatefromstring')) throw new RuntimeException('Image processing is unavailable.');
    $info = @getimagesize($path);
    if (!$info || !in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)) throw new InvalidArgumentException('Only real JPG, PNG or WebP images are accepted.');
    [$width, $height] = $info;
    if ($width * $height > 24000000) throw new InvalidArgumentException('The image is too large. Use a photo below 24 megapixels.');
    $source = @imagecreatefromstring(file_get_contents($path));
    if (!$source) throw new InvalidArgumentException('This image cannot be opened. Please upload another photo.');
    try {
        $transparent = $info['mime'] !== 'image/jpeg';
        if (!$transparent && function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            $orientation = (int)($exif['Orientation'] ?? 1);
            if (in_array($orientation, [2, 4, 5, 7], true)) imageflip($source, IMG_FLIP_HORIZONTAL);
            $angle = match ($orientation) { 3, 4 => 180, 5, 6 => -90, 7, 8 => 90, default => 0 };
            if ($angle) {
                $rotated = imagerotate($source, $angle, 0);
                if ($rotated) { imagedestroy($source); $source = $rotated; }
            }
        }
        $width = imagesx($source); $height = imagesy($source);
        foreach ([1600, 1280, 1024, 800] as $maximum) {
            $scale = min(1, $maximum / max($width, $height));
            $w = max(1, (int)round($width * $scale)); $h = max(1, (int)round($height * $scale));
            $normalized = imagecreatetruecolor($w, $h);
            try {
                if ($transparent) {
                    imagealphablending($normalized, false);
                    imagesavealpha($normalized, true);
                    imagefill($normalized, 0, 0, imagecolorallocatealpha($normalized, 0, 0, 0, 127));
                } else { imagefill($normalized, 0, 0, 0xFFFFFF); }
                imagecopyresampled($normalized, $source, 0, 0, 0, 0, $w, $h, $width, $height);
                ob_start();
                $encoded = $transparent ? imagepng($normalized, null, 8) : imagejpeg($normalized, null, 85);
                $bytes = ob_get_clean();
                if ($encoded && is_string($bytes) && strlen($bytes) <= 1500000) {
                    return ['bytes' => $bytes, 'mime' => $transparent ? 'image/png' : 'image/jpeg'];
                }
            } finally { imagedestroy($normalized); }
        }
        throw new InvalidArgumentException('The photo has too much detail. Crop the surrounding background or upload a smaller image.');
    } finally { imagedestroy($source); }
}

function caz_process_gallery_image(string $path): array {
    if (function_exists('imagecreatefromstring')) return caz_normalize_gallery_image($path);
    $candidates = [dirname((string)ini_get('extension_dir')) . '/php.exe', PHP_BINDIR . '/php.exe', PHP_BINDIR . '/php'];
    if (PHP_SAPI === 'cli') array_unshift($candidates, PHP_BINARY);
    $binary = null;
    foreach ($candidates as $candidate) if (is_file($candidate)) { $binary = $candidate; break; }
    if (!$binary || !function_exists('proc_open')) throw new RuntimeException('Local image processing is unavailable.');
    $output = tempnam(sys_get_temp_dir(), 'caz_gallery_');
    if ($output === false) throw new RuntimeException('Cannot create image processing workspace.');
    $process = null;
    try {
        $process = proc_open([$binary, '-d', 'extension=gd', '-d', 'display_errors=0', '-d', 'log_errors=0', '-d', 'memory_limit=256M', __FILE__, $path, $output], [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']], $pipes, null, null, ['bypass_shell'=>true]);
        if (!is_resource($process)) throw new RuntimeException('Cannot start local image processing.');
        fclose($pipes[0]); stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
        $message = ''; $started = microtime(true); $timedOut = false;
        do {
            $message .= stream_get_contents($pipes[1]); stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (!$status['running']) break;
            if (microtime(true) - $started > 15) { proc_terminate($process); $timedOut = true; break; }
            usleep(10000);
        } while (true);
        $message .= stream_get_contents($pipes[1]);
        fclose($pipes[1]); fclose($pipes[2]); proc_close($process); $process = null;
        $result = json_decode($message, true);
        if (!$timedOut && isset($result['error'])) throw new InvalidArgumentException($result['error']);
        if ($timedOut || !is_array($result) || empty($result['ok']) || !in_array($result['mime'] ?? '', ['image/png', 'image/jpeg'], true) || !filesize($output)) throw new RuntimeException('Image processing could not finish.');
        return ['bytes' => file_get_contents($output), 'mime' => $result['mime']];
    } finally {
        if (is_resource($process)) { proc_terminate($process); proc_close($process); }
        if (is_file($output)) unlink($output);
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    try {
        if ($argc !== 3 || !is_file($argv[1]) || !is_file($argv[2])) throw new RuntimeException('Invalid processing request.');
        $result = caz_normalize_gallery_image($argv[1]);
        if (file_put_contents($argv[2], $result['bytes']) === false) throw new RuntimeException('Cannot save processed image.');
        echo json_encode(['ok' => true, 'mime' => $result['mime']]);
    } catch (InvalidArgumentException $error) { echo json_encode(['error' => $error->getMessage()]); exit(2); }
    catch (Throwable $error) { echo json_encode(['ok' => false]); exit(1); }
}
