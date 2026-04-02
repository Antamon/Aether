<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/companyUtils.php';

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Geen geldig bedrijf ID.']);
    exit;
}

if (!isset($_FILES['logo']) || !is_array($_FILES['logo'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Geen logo ontvangen.']);
    exit;
}

$upload = $_FILES['logo'];
if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Het opladen van het logo is mislukt.']);
    exit;
}

try {
    $pdo = getPDO();
    requirePrivilegedCompanyAccess($pdo);

    $company = dbOne(
        $pdo,
        'SELECT id
           FROM tblCompany
          WHERE id = :id',
        ['id' => $id]
    );

    if ($company === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Bedrijf niet gevonden.']);
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

    $maxDimension = 800;
    $scale = min(1, $maxDimension / max($sourceWidth, $sourceHeight));
    $targetWidth = max(1, (int) round($sourceWidth * $scale));
    $targetHeight = max(1, (int) round($sourceHeight * $scale));

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
        0,
        0,
        $targetWidth,
        $targetHeight,
        $sourceWidth,
        $sourceHeight
    )) {
        imagedestroy($sourceImage);
        imagedestroy($targetImage);
        throw new RuntimeException('Kon afbeelding niet herschalen.');
    }

    $logoDirectory = getCompanyLogoDirectory();
    if (!is_dir($logoDirectory) && !mkdir($logoDirectory, 0775, true) && !is_dir($logoDirectory)) {
        imagedestroy($sourceImage);
        imagedestroy($targetImage);
        throw new RuntimeException('Kon logomap niet aanmaken.');
    }

    $targetPath = getCompanyLogoAbsolutePath($id);
    if (!imagepng($targetImage, $targetPath)) {
        imagedestroy($sourceImage);
        imagedestroy($targetImage);
        throw new RuntimeException('Kon logo niet bewaren.');
    }

    imagedestroy($sourceImage);
    imagedestroy($targetImage);

    echo json_encode([
        'status' => 'ok',
        'logoUrl' => getCompanyLogoUrl($id),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Kon bedrijfslogo niet bewaren.']);
}
