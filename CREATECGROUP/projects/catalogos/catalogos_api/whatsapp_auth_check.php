<?php
declare(strict_types=1);

require __DIR__ . '/ai_helpers.php';

$payload = ai_request_payload();
$sender = ai_normalize_phone((string) ($payload['sender'] ?? ''));
$seller = ai_find_seller_by_sender($sender);

ai_log('auth.checkSeller', [
    'sender' => $sender,
    'authorized' => (bool) $seller,
    'seller_id' => $seller['id'] ?? null,
]);

json_response([
    'ok' => true,
    'authorized' => (bool) $seller,
    'seller' => $seller ? [
        'id' => (int) $seller['id'],
        'code' => $seller['code'],
        'name' => $seller['name'],
        'email' => $seller['email'],
        'phone' => $seller['phone'],
        'has_public_token' => trim((string) ($seller['public_token'] ?? '')) !== '',
    ] : null,
]);
