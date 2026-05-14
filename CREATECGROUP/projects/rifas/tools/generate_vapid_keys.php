<?php
declare(strict_types=1);

$opensslConfig = [];
foreach (['C:\\xampp\\php\\extras\\openssl\\openssl.cnf', 'C:\\xampp\\apache\\conf\\openssl.cnf'] as $candidate) {
    if (is_file($candidate)) {
        $opensslConfig = ['config' => $candidate];
        break;
    }
}

$key = openssl_pkey_new([
    'private_key_type' => OPENSSL_KEYTYPE_EC,
    'curve_name' => 'prime256v1',
] + $opensslConfig);

if (!$key) {
    fwrite(STDERR, "No se pudo generar el par VAPID.\n");
    exit(1);
}

$details = openssl_pkey_get_details($key);
$private = $details['ec']['d'] ?? null;
$x = $details['ec']['x'] ?? null;
$y = $details['ec']['y'] ?? null;

if (!is_string($private) || !is_string($x) || !is_string($y)) {
    fwrite(STDERR, "OpenSSL no devolvio las claves EC esperadas.\n");
    exit(1);
}

$public = "\x04" . str_pad($x, 32, "\0", STR_PAD_LEFT) . str_pad($y, 32, "\0", STR_PAD_LEFT);
$encode = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');

echo "WEB_PUSH_PUBLIC_KEY=" . $encode($public) . PHP_EOL;
echo "WEB_PUSH_PRIVATE_KEY=" . $encode(str_pad($private, 32, "\0", STR_PAD_LEFT)) . PHP_EOL;
