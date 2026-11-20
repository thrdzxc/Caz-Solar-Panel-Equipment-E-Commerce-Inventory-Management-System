<?php
/** GD implementation shared by the in-process and isolated CLI paths. */
function caz_normalize_image(string $path, bool $checkIdQuality): array {
    if (!function_exists('imagecreatefromstring')) throw new RuntimeException('GD is not available.');
    $info=@getimagesize($path);
    if (!$info || !in_array($info['mime']??'', ['image/jpeg','image/png','image/webp'],true)) throw new InvalidArgumentException('Only real JPG, PNG or WebP images are accepted.');
    [$width,$height]=$info;
    if ($width*$height>24000000) throw new InvalidArgumentException('The image is too large. Use a photo below 24 megapixels.');
    if ($checkIdQuality && (min($width,$height)<500 || max($width,$height)<800)) throw new InvalidArgumentException('This photo is too small to review. Use at least 800 × 500 pixels (either orientation).');
    $source=@imagecreatefromstring(file_get_contents($path));
    if (!$source) throw new InvalidArgumentException('This image cannot be opened. Please upload another photo.');
    try {
        if (($info['mime']??'')==='image/jpeg' && function_exists('exif_read_data')) {
            $exif=@exif_read_data($path); $orientation=(int)($exif['Orientation']??1);
            if (in_array($orientation,[2,4,5,7],true)) imageflip($source, IMG_FLIP_HORIZONTAL);
            $angle=match($orientation) { 3,4=>180,5,6=>-90,7,8=>90,default=>0 };
            if ($angle) { $rotated=imagerotate($source,$angle,0); if ($rotated) { imagedestroy($source); $source=$rotated; } }
        }
        $width=imagesx($source); $height=imagesy($source); $quality=[];
        if ($checkIdQuality) {
            $scale=min(1,800/max($width,$height)); $w=max(3,(int)round($width*$scale)); $h=max(3,(int)round($height*$scale));
            $sample=imagecreatetruecolor($w,$h); imagefill($sample,0,0,0xFFFFFF);
            imagecopyresampled($sample,$source,0,0,0,0,$w,$h,$width,$height);
            imagefilter($sample,IMG_FILTER_GRAYSCALE);
            $sum=0; $square=0; $lapSum=0; $lapSquare=0; $dark=0; $light=0; $n=0;
            for ($y=1;$y<$h-1;$y++) for ($x=1;$x<$w-1;$x++) {
                $v=imagecolorat($sample,$x,$y)&255;
                $lap=4*$v-(imagecolorat($sample,$x-1,$y)&255)-(imagecolorat($sample,$x+1,$y)&255)-(imagecolorat($sample,$x,$y-1)&255)-(imagecolorat($sample,$x,$y+1)&255);
                $sum+=$v; $square+=$v*$v; $lapSum+=$lap; $lapSquare+=$lap*$lap; $dark+=($v<24); $light+=($v>246); $n++;
            }
            imagedestroy($sample);
            $mean=$sum/$n; $contrast=sqrt(max(0,$square/$n-$mean*$mean)); $sharpness=$lapSquare/$n-($lapSum/$n)**2;
            $quality=['brightness'=>round($mean,2),'contrast'=>round($contrast,2),'sharpness'=>round($sharpness,2)];
            if ($mean<38 || $dark/$n>.82) throw new InvalidArgumentException('The ID photo is too dark to read. Retake it in even light without shadows.');
            if ($mean>243 || $light/$n>.92) throw new InvalidArgumentException('The ID photo is washed out or has too much glare. Retake it without flash or reflections.');
            if ($contrast<14) throw new InvalidArgumentException('The ID details are not clear enough. Fill the photo with the whole ID and use even lighting.');
            if ($sharpness<180) throw new InvalidArgumentException('The ID photo appears blurred or out of focus. Hold the camera steady, focus on the printed details and upload a sharper photo.');
        }
        $scale=min(1,1600/max($width,$height)); $w=max(1,(int)round($width*$scale)); $h=max(1,(int)round($height*$scale));
        $normalized=imagecreatetruecolor($w,$h); imagefill($normalized,0,0,0xFFFFFF); imagecopyresampled($normalized,$source,0,0,0,0,$w,$h,$width,$height);
        $bytes='';
        foreach ([90,85,80,75,70] as $compression) { ob_start(); imagejpeg($normalized,null,$compression); $bytes=ob_get_clean(); if (strlen($bytes)<=600000) break; }
        imagedestroy($normalized);
        if (!$bytes || strlen($bytes)>600000) throw new InvalidArgumentException('The image has too much detail to save safely. Crop out the surrounding background and upload it again.');
        return ['bytes'=>$bytes,'mime'=>'image/jpeg','quality'=>$quality];
    } finally { imagedestroy($source); }
}
if (realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__) {
    if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
    try {
        if ($argc!==4 || !is_file($argv[1]) || !is_file($argv[2])) throw new RuntimeException('Invalid processing request.');
        $result=caz_normalize_image($argv[1],$argv[3]==='identity');
        if (file_put_contents($argv[2],$result['bytes'])===false) throw new RuntimeException('Cannot save processed image.');
        echo json_encode(['ok'=>true,'quality'=>$result['quality']]);
    } catch (InvalidArgumentException $error) { echo json_encode(['error'=>$error->getMessage()]); exit(2); }
    catch (Throwable $error) { echo json_encode(['ok'=>false]); exit(1); }
}
