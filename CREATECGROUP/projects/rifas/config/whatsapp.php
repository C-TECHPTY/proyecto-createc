<?php

declare(strict_types=1);

function whatsapp_detect_intent(string $message): string
{
    $text = mb_strtolower(trim($message));

    if ($text === '') {
        return 'empty';
    }
    if (str_contains($text, 'quiero') || str_contains($text, 'reserv') || preg_match('/\b\d{1,3}\b/', $text)) {
        return 'reserve_request';
    }
    if (str_contains($text, 'comprobante') || str_contains($text, 'pagu') || str_contains($text, 'yappy')) {
        return 'payment_receipt';
    }
    if (str_contains($text, 'disponible') || str_contains($text, 'libre')) {
        return 'availability_check';
    }
    if (str_contains($text, 'gan') || str_contains($text, 'premio')) {
        return 'winner_check';
    }

    return 'general_message';
}

function whatsapp_extract_text(array $payload): string
{
    $cloudText = $payload['entry'][0]['changes'][0]['value']['messages'][0]['text']['body'] ?? null;
    if (is_string($cloudText)) {
        return $cloudText;
    }

    $twilioText = $payload['Body'] ?? null;
    if (is_string($twilioText)) {
        return $twilioText;
    }

    $watiText = $payload['text'] ?? $payload['message'] ?? null;
    return is_string($watiText) ? $watiText : '';
}

function whatsapp_extract_from(array $payload): ?string
{
    $cloudFrom = $payload['entry'][0]['changes'][0]['value']['messages'][0]['from'] ?? null;
    if (is_string($cloudFrom)) {
        return normalize_phone($cloudFrom);
    }

    $from = $payload['From'] ?? $payload['waId'] ?? $payload['from'] ?? null;
    return is_string($from) ? normalize_phone($from) : null;
}

function whatsapp_extract_to(array $payload): ?string
{
    $cloudTo = $payload['entry'][0]['changes'][0]['value']['metadata']['display_phone_number'] ?? null;
    if (is_string($cloudTo)) {
        return normalize_phone($cloudTo);
    }

    $to = $payload['To'] ?? $payload['to'] ?? null;
    return is_string($to) ? normalize_phone($to) : null;
}

