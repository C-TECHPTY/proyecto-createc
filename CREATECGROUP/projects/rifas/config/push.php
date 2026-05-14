<?php
declare(strict_types=1);

function push_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function push_openssl_config(): array
{
    if (getenv('OPENSSL_CONF')) {
        return [];
    }

    $candidates = [
        'C:\\xampp\\php\\extras\\openssl\\openssl.cnf',
        'C:\\xampp\\apache\\conf\\openssl.cnf',
    ];
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return ['config' => $candidate];
        }
    }

    return [];
}

function push_base64url_decode(string $value): string
{
    $remainder = strlen($value) % 4;
    if ($remainder) {
        $value .= str_repeat('=', 4 - $remainder);
    }

    $decoded = base64_decode(strtr($value, '-_', '+/'), true);
    if ($decoded === false) {
        throw new RuntimeException('Valor base64url inválido.');
    }

    return $decoded;
}

function push_ensure_subscriptions_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS push_subscriptions (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          admin_id INT UNSIGNED NULL,
          endpoint TEXT NOT NULL,
          p256dh_key VARCHAR(255) NULL,
          auth_token VARCHAR(255) NULL,
          user_agent VARCHAR(255) NULL,
          active TINYINT(1) NOT NULL DEFAULT 1,
          status ENUM('active','disabled','error') NOT NULL DEFAULT 'active',
          last_success_at DATETIME NULL,
          last_error TEXT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_push_admin_active (admin_id, active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM push_subscriptions')->fetchAll() as $column) {
        $columns[$column['Field']] = true;
    }

    if (empty($columns['status'])) {
        $pdo->exec("ALTER TABLE push_subscriptions ADD status ENUM('active','disabled','error') NOT NULL DEFAULT 'active' AFTER active");
    }
    if (empty($columns['last_success_at'])) {
        $pdo->exec('ALTER TABLE push_subscriptions ADD last_success_at DATETIME NULL AFTER status');
    }
    if (empty($columns['last_error'])) {
        $pdo->exec('ALTER TABLE push_subscriptions ADD last_error TEXT NULL AFTER last_success_at');
    }
}

function push_save_subscription(PDO $pdo, int $adminId, array $payload, string $userAgent): void
{
    push_ensure_subscriptions_table($pdo);

    $endpoint = trim((string) ($payload['endpoint'] ?? ''));
    $p256dh = trim((string) ($payload['keys']['p256dh'] ?? ''));
    $auth = trim((string) ($payload['keys']['auth'] ?? ''));

    if ($endpoint === '' || $p256dh === '' || $auth === '') {
        throw new RuntimeException('Suscripción push incompleta.');
    }

    $find = $pdo->prepare('SELECT id FROM push_subscriptions WHERE endpoint = ? LIMIT 1');
    $find->execute([$endpoint]);
    $existingId = (int) ($find->fetchColumn() ?: 0);

    if ($existingId) {
        $stmt = $pdo->prepare("
            UPDATE push_subscriptions
            SET admin_id = ?, p256dh_key = ?, auth_token = ?, user_agent = ?, active = 1, status = 'active', last_error = NULL
            WHERE id = ?
        ");
        $stmt->execute([$adminId, $p256dh, $auth, substr($userAgent, 0, 255), $existingId]);
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO push_subscriptions (admin_id, endpoint, p256dh_key, auth_token, user_agent, active, status)
        VALUES (?, ?, ?, ?, ?, 1, 'active')
    ");
    $stmt->execute([$adminId, $endpoint, $p256dh, $auth, substr($userAgent, 0, 255)]);
}

function push_is_configured(): bool
{
    return (string) config_value('WEB_PUSH_PUBLIC_KEY', '') !== ''
        && (string) config_value('WEB_PUSH_PRIVATE_KEY', '') !== ''
        && (string) config_value('WEB_PUSH_SUBJECT', '') !== '';
}

function push_vapid_private_pem(string $privateKey, string $publicKey): string
{
    $private = str_contains($privateKey, 'BEGIN') ? $privateKey : push_base64url_decode($privateKey);
    if (str_contains($privateKey, 'BEGIN')) {
        return $privateKey;
    }

    $public = push_base64url_decode($publicKey);
    if (strlen($private) !== 32 || strlen($public) !== 65) {
        throw new RuntimeException('Las claves VAPID deben ser P-256 en base64url.');
    }

    $der = hex2bin('30770201010420') . $private
        . hex2bin('a00a06082a8648ce3d030107a144034200') . $public;

    return "-----BEGIN EC PRIVATE KEY-----\n"
        . chunk_split(base64_encode($der), 64, "\n")
        . "-----END EC PRIVATE KEY-----\n";
}

function push_public_key_pem(string $rawPublicKey): string
{
    if (strlen($rawPublicKey) !== 65 || $rawPublicKey[0] !== "\x04") {
        throw new RuntimeException('Clave pública P-256 inválida.');
    }

    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $rawPublicKey;

    return "-----BEGIN PUBLIC KEY-----\n"
        . chunk_split(base64_encode($der), 64, "\n")
        . "-----END PUBLIC KEY-----\n";
}

function push_ec_public_raw_from_details(array $details): string
{
    $x = $details['ec']['x'] ?? null;
    $y = $details['ec']['y'] ?? null;
    if (!is_string($x) || !is_string($y)) {
        throw new RuntimeException('OpenSSL no devolvió la clave pública ECDH.');
    }

    return "\x04" . str_pad($x, 32, "\0", STR_PAD_LEFT) . str_pad($y, 32, "\0", STR_PAD_LEFT);
}

function push_hkdf_expand(string $prk, string $info, int $length): string
{
    $output = '';
    $last = '';
    for ($i = 1; strlen($output) < $length; $i++) {
        $last = hash_hmac('sha256', $last . $info . chr($i), $prk, true);
        $output .= $last;
    }

    return substr($output, 0, $length);
}

function push_der_signature_to_jose(string $der): string
{
    $offset = 0;
    if (ord($der[$offset++]) !== 0x30) {
        throw new RuntimeException('Firma VAPID inválida.');
    }
    $length = ord($der[$offset++]);
    if ($length & 0x80) {
        $bytes = $length & 0x7f;
        $length = 0;
        for ($i = 0; $i < $bytes; $i++) {
            $length = ($length << 8) + ord($der[$offset++]);
        }
    }

    $parts = [];
    for ($i = 0; $i < 2; $i++) {
        if (ord($der[$offset++]) !== 0x02) {
            throw new RuntimeException('Firma VAPID inválida.');
        }
        $partLength = ord($der[$offset++]);
        $part = substr($der, $offset, $partLength);
        $offset += $partLength;
        $parts[] = str_pad(ltrim($part, "\0"), 32, "\0", STR_PAD_LEFT);
    }

    return $parts[0] . $parts[1];
}

function push_vapid_jwt(string $audience): string
{
    $publicKey = (string) config_value('WEB_PUSH_PUBLIC_KEY', '');
    $privateKey = (string) config_value('WEB_PUSH_PRIVATE_KEY', '');
    $subject = (string) config_value('WEB_PUSH_SUBJECT', '');

    $header = push_base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_THROW_ON_ERROR));
    $payload = push_base64url_encode(json_encode([
        'aud' => $audience,
        'exp' => time() + 3600,
        'sub' => $subject,
    ], JSON_THROW_ON_ERROR));

    $pem = push_vapid_private_pem($privateKey, $publicKey);
    if (!openssl_sign($header . '.' . $payload, $signature, $pem, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('No se pudo firmar VAPID.');
    }

    return $header . '.' . $payload . '.' . push_base64url_encode(push_der_signature_to_jose($signature));
}

function push_encrypt_payload(array $subscription, array $payload): string
{
    $clientPublic = push_base64url_decode((string) $subscription['p256dh_key']);
    $authSecret = push_base64url_decode((string) $subscription['auth_token']);

    $localKey = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1',
    ] + push_openssl_config());
    if (!$localKey) {
        throw new RuntimeException('No se pudo crear clave ECDH.');
    }

    $details = openssl_pkey_get_details($localKey);
    if (!$details) {
        throw new RuntimeException('No se pudo leer clave ECDH.');
    }
    $serverPublic = push_ec_public_raw_from_details($details);
    $clientPem = push_public_key_pem($clientPublic);
    $clientKey = openssl_pkey_get_public($clientPem);
    if (!$clientKey) {
        throw new RuntimeException('No se pudo leer clave pública del dispositivo.');
    }

    $sharedSecret = openssl_pkey_derive($clientKey, $localKey, 32);
    if (!is_string($sharedSecret)) {
        throw new RuntimeException('No se pudo derivar secreto ECDH.');
    }

    $salt = random_bytes(16);
    $prkKey = hash_hmac('sha256', $sharedSecret, $authSecret, true);
    $context = 'WebPush: info' . "\0" . $clientPublic . $serverPublic;
    $ikm = push_hkdf_expand($prkKey, $context, 32);
    $prk = hash_hmac('sha256', $ikm, $salt, true);
    $cek = push_hkdf_expand($prk, 'Content-Encoding: aes128gcm' . "\0", 16);
    $nonce = push_hkdf_expand($prk, 'Content-Encoding: nonce' . "\0", 12);
    $content = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\x02";
    $cipherText = openssl_encrypt($content, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if (!is_string($cipherText)) {
        throw new RuntimeException('No se pudo cifrar la notificación push.');
    }

    return $salt . pack('N', 4096) . chr(strlen($serverPublic)) . $serverPublic . $cipherText . $tag;
}

function push_send_subscription(PDO $pdo, array $subscription, array $payload): bool
{
    if (!push_is_configured()) {
        return false;
    }

    $endpoint = (string) $subscription['endpoint'];
    $port = parse_url($endpoint, PHP_URL_PORT);
    $origin = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST) . ($port ? ':' . $port : '');
    $body = push_encrypt_payload($subscription, $payload);
    $jwt = push_vapid_jwt($origin);
    $publicKey = (string) config_value('WEB_PUSH_PUBLIC_KEY', '');

    $headers = [
        'TTL: 2419200',
        'Urgency: high',
        'Content-Type: application/octet-stream',
        'Content-Encoding: aes128gcm',
        'Authorization: vapid t=' . $jwt . ', k=' . $publicKey,
        'Content-Length: ' . strlen($body),
    ];

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'ignore_errors' => true,
            'timeout' => 8,
        ],
    ]);

    $response = @file_get_contents($endpoint, false, $context);
    $statusLine = $http_response_header[0] ?? '';
    preg_match('/\s(\d{3})\s/', $statusLine, $matches);
    $status = (int) ($matches[1] ?? 0);

    if ($status >= 200 && $status < 300) {
        $stmt = $pdo->prepare("UPDATE push_subscriptions SET active = 1, status = 'active', last_success_at = NOW(), last_error = NULL WHERE id = ?");
        $stmt->execute([(int) $subscription['id']]);
        return true;
    }

    $active = in_array($status, [404, 410], true) ? 0 : 1;
    $state = $active ? 'error' : 'disabled';
    $error = trim($statusLine . ' ' . (is_string($response) ? substr($response, 0, 500) : ''));
    $stmt = $pdo->prepare('UPDATE push_subscriptions SET active = ?, status = ?, last_error = ? WHERE id = ?');
    $stmt->execute([$active, $state, $error ?: 'Error enviando push', (int) $subscription['id']]);

    return false;
}

function push_send_to_admins(PDO $pdo, array $payload): int
{
    try {
        push_ensure_subscriptions_table($pdo);
        $subscriptions = $pdo->query("
            SELECT *
            FROM push_subscriptions
            WHERE active = 1 AND status = 'active'
            ORDER BY updated_at DESC, created_at DESC
        ")->fetchAll();

        $sent = 0;
        foreach ($subscriptions as $subscription) {
            try {
                if (push_send_subscription($pdo, $subscription, $payload)) {
                    $sent++;
                }
            } catch (Throwable $e) {
                $stmt = $pdo->prepare("UPDATE push_subscriptions SET status = 'error', last_error = ? WHERE id = ?");
                $stmt->execute([substr($e->getMessage(), 0, 1000), (int) $subscription['id']]);
            }
        }

        return $sent;
    } catch (Throwable) {
        return 0;
    }
}

function push_notify_admins(PDO $pdo, string $title, string $body, string $url, array $extra = []): void
{
    $payload = array_merge([
        'title' => $title,
        'body' => $body,
        'url' => $url,
        'tag' => $extra['tag'] ?? 'rifagrid',
    ], $extra);

    push_send_to_admins($pdo, $payload);
}
