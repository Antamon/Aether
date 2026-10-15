<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/events/eventMediaService.php';
if (!function_exists('imagecreatetruecolor')) throw new RuntimeException('Deze test vereist GD.');
$directory = sys_get_temp_dir() . '/aether-event-image-' . bin2hex(random_bytes(6));
mkdir($directory, 0700);
$source = $directory . '/source.jpg';
$target = $directory . '/7.png';
try {
    $image = imagecreatetruecolor(800, 400);
    imagejpeg($image, $source); unset($image);
    $GLOBALS['aetherEventImageUploadVerifier'] = static fn(string $path): bool => $path === $source;
    aetherUploadEventImage(7, ['tmp_name' => $source, 'error' => UPLOAD_ERR_OK], $directory);
    $info = getimagesize($target);
    if ($info['mime'] !== 'image/png' || abs($info[0] / $info[1] - 4 / 3) > 0.01) {
        throw new RuntimeException('Eventbeeld is geen veilig liggend PNG van 4:3.');
    }
    $hash = hash_file('sha256', $target);
    file_put_contents($source, '<?php echo "invalid";');
    try {
        aetherUploadEventImage(7, ['tmp_name' => $source, 'error' => UPLOAD_ERR_OK], $directory);
        throw new RuntimeException('Ongeldige afbeelding aanvaard.');
    } catch (AetherEventException $e) {
        if ($e->getHttpStatus() !== 422 || hash_file('sha256', $target) !== $hash) {
            throw new RuntimeException('Mislukte upload heeft de bestaande afbeelding gewijzigd.');
        }
    }
    echo "Event image checks passed.\n";
} finally {
    unset($GLOBALS['aetherEventImageUploadVerifier']);
    foreach (glob($directory . '/*') as $path) unlink($path);
    foreach (glob($directory . '/.incoming-*') as $path) unlink($path);
    rmdir($directory);
}
