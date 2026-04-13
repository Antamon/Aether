<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/characterMediaUtils.php';

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Geen geldig personage ID.']);
    exit;
}

if (!isset($_FILES['portrait']) || !is_array($_FILES['portrait'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Geen portret ontvangen.']);
    exit;
}

$upload = $_FILES['portrait'];
if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Het opladen van het portret is mislukt.']);
    exit;
}

try {
    $pdo = getPDO();
    $currentUserRole = getCurrentUserRole($pdo);
    $currentUserId = getCurrentUserId();

    $character = dbOne(
        $pdo,
        'SELECT id, idUser
           FROM tblCharacter
          WHERE id = :id',
        ['id' => $id]
    );

    if ($character === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Personage niet gevonden.']);
        exit;
    }

    if (!canManageCharacterPortrait($character, $currentUserRole, $currentUserId)) {
        http_response_code(403);
        echo json_encode(['error' => 'Je hebt geen rechten om dit portret te beheren.']);
        exit;
    }

    $tmpPath = (string) ($upload['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        http_response_code(400);
        echo json_encode(['error' => 'Geen geldig uploadbestand ontvangen.']);
        exit;
    }

    $imageData = file_get_contents($tmpPath);
    if ($imageData === false) {
        throw new RuntimeException('Kon uploadbestand niet lezen.');
    }

    $sourceImage = @imagecreatefromstring($imageData);
    if ($sourceImage === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Geef een geldige afbeeldingsfile op.']);
        exit;
    }

    $sourceWidth = imagesx($sourceImage);
    $sourceHeight = imagesy($sourceImage);
    if ($sourceWidth <= 0 || $sourceHeight <= 0) {
        imagedestroy($sourceImage);
        http_response_code(400);
        echo json_encode(['error' => 'De afbeelding heeft geen geldige afmetingen.']);
        exit;
    }

    $targetRatio = 35 / 45;
    $sourceRatio = $sourceWidth / $sourceHeight;

    $cropWidth = $sourceWidth;
    $cropHeight = $sourceHeight;
    $srcX = 0;
    $srcY = 0;

    if ($sourceRatio > $targetRatio) {
        $cropWidth = (int) round($sourceHeight * $targetRatio);
        $srcX = (int) floor(($sourceWidth - $cropWidth) / 2);
    } elseif ($sourceRatio < $targetRatio) {
        $cropHeight = (int) round($sourceWidth / $targetRatio);
        $srcY = (int) floor(($sourceHeight - $cropHeight) / 2);
    }

    $targetHeight = min(1024, $cropHeight);
    $targetWidth = max(1, (int) round($targetHeight * $targetRatio));

    $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);
    if ($targetImage === false) {
        imagedestroy($sourceImage);
        throw new RuntimeException('Kon doelafbeelding niet aanmaken.');
    }

    imagealphablending($targetImage, false);
    imagesavealpha($targetImage, true);
    $transparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
    imagefill($targetImage, 0, 0, $transparent);

    if (!imagecopyresampled(
        $targetImage,
        $sourceImage,
        0,
        0,
        $srcX,
        $srcY,
        $targetWidth,
        $targetHeight,
        $cropWidth,
        $cropHeight
    )) {
        imagedestroy($sourceImage);
        imagedestroy($targetImage);
        throw new RuntimeException('Kon afbeelding niet verwerken.');
    }

    $portraitDirectory = getCharacterPortraitDirectory();
    if (!is_dir($portraitDirectory) && !mkdir($portraitDirectory, 0775, true) && !is_dir($portraitDirectory)) {
        imagedestroy($sourceImage);
        imagedestroy($targetImage);
        throw new RuntimeException('Kon portretmap niet aanmaken.');
    }

    $targetPath = getCharacterPortraitAbsolutePath($id);
    if (!imagepng($targetImage, $targetPath)) {
        imagedestroy($sourceImage);
        imagedestroy($targetImage);
        throw new RuntimeException('Kon portret niet bewaren.');
    }

    imagedestroy($sourceImage);
    imagedestroy($targetImage);

    echo json_encode([
        'status' => 'ok',
        'portraitUrl' => getCharacterPortraitUrl($id),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Kon portret niet bewaren.']);
}
