<?php
/** Local-only image processing. Photos are never sent to an external service. */
function caz_process_image(string $path, bool $checkIdQuality = true): array {
    if (function_exists('imagecreatefromstring')) {
        require_once __DIR__.'/image_worker.php';
        return caz_normalize_image($path, $checkIdQuality);
    }
    // XAMPP may leave GD disabled in Apache while shipping the GD extension.
    $candidates = [dirname((string)ini_get('extension_dir')).'/php.exe', PHP_BINDIR.'/php.exe', PHP_BINDIR.'/php'];
    if (PHP_SAPI === 'cli') array_unshift($candidates, PHP_BINARY);
    $binary = null;
    foreach ($candidates as $candidate) if (is_file($candidate)) { $binary=$candidate; break; }
    if (!$binary || !function_exists('proc_open')) throw new RuntimeException('Local image processing is unavailable.');
    $output = tempnam(sys_get_temp_dir(), 'caz_photo_');
    if ($output === false) throw new RuntimeException('Cannot create image processing workspace.');
    $process = null;
    try {
        $process = proc_open([$binary,'-d','extension=gd','-d','display_errors=0','-d','log_errors=0','-d','memory_limit=256M',__DIR__.'/image_worker.php',$path,$output,$checkIdQuality?'identity':'product'], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, null, null, ['bypass_shell'=>true]);
        if (!is_resource($process)) throw new RuntimeException('Cannot start local image processing.');
        fclose($pipes[0]); stream_set_blocking($pipes[1],false); stream_set_blocking($pipes[2],false);
        $message=''; $started=microtime(true); $timedOut=false;
        do {
            $message.=stream_get_contents($pipes[1]); stream_get_contents($pipes[2]);
            $status=proc_get_status($process);
            if (!$status['running']) break;
            if (microtime(true)-$started > 15) { proc_terminate($process); $timedOut=true; break; }
            usleep(10000);
        } while (true);
        $message.=stream_get_contents($pipes[1]);
        fclose($pipes[1]); fclose($pipes[2]); proc_close($process); $process=null;
        $result=json_decode($message,true);
        if (!$timedOut && isset($result['error'])) throw new InvalidArgumentException($result['error']);
        if ($timedOut || !is_array($result) || empty($result['ok']) || !filesize($output)) throw new RuntimeException('Image processing could not finish.');
        return ['bytes'=>file_get_contents($output),'mime'=>'image/jpeg','quality'=>$result['quality']??[]];
    } finally {
        if (is_resource($process)) { proc_terminate($process); proc_close($process); }
        if (is_file($output)) unlink($output);
    }
}
