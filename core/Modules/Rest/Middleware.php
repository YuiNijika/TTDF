<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once 'Enums.php';

final class TokenValidator
{
    public static function validate(): void
    {
        $tokenConfig = TTDF_CONFIG['REST_API']['TOKEN'] ?? [];
        if (!($tokenConfig['ENABLED'] ?? false)) {
            return;
        }

        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $tokenValue = $tokenConfig['VALUE'] ?? '';
        $tokenFormat = $tokenConfig['FORMAT'] ?? 'Bearer';

        switch ($tokenFormat) {
            case 'Bearer':
                if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                    self::sendErrorResponse('Missing or invalid Bearer token', HttpCode::UNAUTHORIZED);
                }
                if (trim($matches[1]) !== $tokenValue) {
                    self::sendErrorResponse('Invalid token', HttpCode::FORBIDDEN);
                }
                break;
                
            case 'Token':
                if (trim($authHeader) !== $tokenValue) {
                    self::sendErrorResponse('Invalid token', HttpCode::FORBIDDEN);
                }
                break;
                
            default:
                self::sendErrorResponse('Unsupported token format', HttpCode::BAD_REQUEST);
        }
    }

    private static function sendErrorResponse(string $message, HttpCode $code): never
    {
        if (!headers_sent()) {
            \Typecho\Response::getInstance()->setStatus($code->value);
            header('Content-Type: application/json; charset=UTF-8');
        }

        echo json_encode([
            'code' => $code->value,
            'message' => $message,
            'timestamp' => time()
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}