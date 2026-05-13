<?php

namespace App\Services;

class RedsysValidator
{
    public static function validate(array $params, string $base64, string $signature): array
    {
        $errors = [];

        // 1. Merchant
        if (empty($params['DS_MERCHANT_MERCHANTCODE'])) {
            $errors[] = 'MerchantCode vacío';
        }

        // 2. Terminal
        if (empty($params['DS_MERCHANT_TERMINAL'])) {
            $errors[] = 'Terminal vacío';
        }

        if (!preg_match('/^[0-9]{3}$/', $params['DS_MERCHANT_TERMINAL'])) {
            $errors[] = 'Terminal inválido (debe ser 3 dígitos)';
        }

        // 3. Order (Redsys: 4-12 chars alfanumérico)
        if (!preg_match('/^[A-Z0-9]{4,12}$/', $params['DS_MERCHANT_ORDER'])) {
            $errors[] = 'Order inválido (4-12 chars A-Z0-9)';
        }

        // 4. Amount
        if (!isset($params['DS_MERCHANT_AMOUNT']) || (int)$params['DS_MERCHANT_AMOUNT'] <= 0) {
            $errors[] = 'Amount inválido (debe ser > 0)';
        }

        // 5. Currency
        if (($params['DS_MERCHANT_CURRENCY'] ?? null) !== '978') {
            $errors[] = 'Currency incorrecta (debe ser 978 EUR)';
        }

        // 6. URLs
        foreach (['DS_MERCHANT_MERCHANTURL','DS_MERCHANT_URLOK','DS_MERCHANT_URLKO'] as $urlField) {
            if (empty($params[$urlField]) || !filter_var($params[$urlField], FILTER_VALIDATE_URL)) {
                $errors[] = "URL inválida: $urlField";
            }
        }

        // 7. JSON integrity
        $json = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $reencoded = base64_encode($json);

        if ($reencoded !== $base64) {
            $errors[] = 'Base64 no coincide con el JSON original (payload corrupto)';
        }

        // 8. Signature presence
        if (empty($signature)) {
            $errors[] = 'Firma vacía';
        }

        return [
            'valid' => count($errors) === 0,
            'errors' => $errors
        ];
    }
}
