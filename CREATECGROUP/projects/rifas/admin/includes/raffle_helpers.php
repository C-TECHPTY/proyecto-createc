<?php

declare(strict_types=1);

function slugify(string $value): string
{
    $value = iconv('UTF-8', 'ASCII//TRANSLIT', $value) ?: $value;
    $value = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $value), '-'));
    return $value !== '' ? $value : 'rifa-' . time();
}

function sync_raffle_numbers(PDO $pdo, int $raffleId, int $min, int $max): void
{
    for ($number = $min; $number <= $max; $number++) {
        $stmt = $pdo->prepare('INSERT IGNORE INTO raffle_numbers (raffle_id, number_value, display_number) VALUES (?, ?, ?)');
        $stmt->execute([$raffleId, $number, str_pad((string) $number, 2, '0', STR_PAD_LEFT)]);
    }

    $delete = $pdo->prepare("DELETE FROM raffle_numbers WHERE raffle_id = ? AND status = 'available' AND (number_value < ? OR number_value > ?)");
    $delete->execute([$raffleId, $min, $max]);
}

function handle_flyer_upload(string $field): ?string
{
    if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo subir el flyer.');
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = mime_content_type($_FILES[$field]['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('El flyer debe ser JPG, PNG o WEBP.');
    }

    $name = 'flyer-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $targetDir = __DIR__ . '/../../public/assets/uploads/flyers';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0775, true);
    }
    $target = $targetDir . '/' . $name;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
        throw new RuntimeException('No se pudo guardar el flyer.');
    }

    return 'assets/uploads/flyers/' . $name;
}

