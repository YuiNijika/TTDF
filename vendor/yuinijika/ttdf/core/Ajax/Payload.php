<?php

declare(strict_types=1);

namespace TTDF\Ajax;

use TTDF\Http\Request;

final class Payload
{
    /**
     * @return array<string, mixed>
     */
    public static function fromRequest(): array
    {
        $data = array_replace($_GET, $_POST);
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

        if (str_contains($contentType, 'application/json')) {
            $data = array_replace($data, Request::json());
        }

        return $data;
    }
}
