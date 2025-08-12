<?php

/**
 * 自动路由
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class TTDF_AutoRouter
{
    private static $pagesDir = __DIR__ . '/../../app/pages';

    public static function init()
    {
        // 判断 pagesDir 是否存在且为目录，并且目录下有文件
        if (!is_dir(self::$pagesDir) || empty(array_diff(scandir(self::$pagesDir), ['.', '..']))) {
            return; // 如果目录不存在或为空，则不启用自定义路由
        }

        $path = self::getRequestPath();
        $matchedFile = self::findMatchingFile($path);

        if ($matchedFile) {
            $response = Typecho_Response::getInstance();
            $response->setStatus(200);
            self::renderMatchedFile($matchedFile, $path);
            exit; // 匹配到路由时终止执行
        }

        // 未匹配到路由时不作任何处理，让Typecho继续
    }

    private static function getRequestPath()
    {
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        return trim($requestUri, '/');
    }

    private static function findMatchingFile($requestPath)
    {
        $requestParts = $requestPath ? explode('/', $requestPath) : [];
        return self::scanDirectory(self::$pagesDir, $requestParts);
    }

    private static function scanDirectory($currentDir, $remainingParts, $index = 0)
    {
        // 确保当前路径是目录
        if (!is_dir($currentDir)) {
            return null;
        }

        $files = scandir($currentDir);
        if ($files === false) {
            return null; // scandir 失败
        }

        // 如果是最后一部分，优先检查精确匹配
        if ($index >= count($remainingParts)) {
            $exactFile = $currentDir . '/index.php';
            if (file_exists($exactFile)) {
                return [
                    'file' => $exactFile,
                    'params' => []
                ];
            }
            return null;
        }

        $currentPart = $remainingParts[$index];

        // 检查精确匹配的目录/文件
        if (in_array($currentPart . '.php', $files)) {
            $filePath = $currentDir . '/' . $currentPart . '.php';
            if ($index === count($remainingParts) - 1) {
                return [
                    'file' => $filePath,
                    'params' => []
                ];
            }
        }

        if (is_dir($currentDir . '/' . $currentPart)) {
            $result = self::scanDirectory(
                $currentDir . '/' . $currentPart,
                $remainingParts,
                $index + 1
            );
            if ($result) return $result;
        }

        // 检查动态路由 [param].php
        foreach ($files as $file) {
            if (preg_match('/^\[(\w+)\]\.php$/', $file, $matches)) {
                $paramName = $matches[1];
                if ($index === count($remainingParts) - 1) {
                    return [
                        'file' => $currentDir . '/' . $file,
                        'params' => [$paramName => $currentPart]
                    ];
                }

                // 确保 $currentDir . '/' . $file 是目录才继续递归
                $nextDir = $currentDir . '/' . $file;
                if (is_dir($nextDir)) {
                    $result = self::scanDirectory(
                        $nextDir,
                        $remainingParts,
                        $index + 1
                    );
                    if ($result) {
                        $result['params'][$paramName] = $currentPart;
                        return $result;
                    }
                }
            }
        }

        // 检查可选动态路由 [[param]].php
        foreach ($files as $file) {
            if (preg_match('/^\[\[(\w+)\]\]\.php$/', $file, $matches)) {
                $paramName = $matches[1];
                $nextDir = $currentDir . '/' . $file;

                // 确保 $nextDir 是目录才继续递归
                if (is_dir($nextDir)) {
                    $result = self::scanDirectory(
                        $nextDir,
                        $remainingParts,
                        $index + 1
                    );
                    if ($result) {
                        if ($index < count($remainingParts)) {
                            $result['params'][$paramName] = $currentPart;
                        }
                        return $result;
                    }
                }

                // 或者作为终止文件
                if ($index === count($remainingParts) - 1) {
                    return [
                        'file' => $currentDir . '/' . $file,
                        'params' => [$paramName => $currentPart]
                    ];
                }
            }
        }

        return null;
    }

    private static function renderMatchedFile($match, $path)
    {
        // 设置参数到$_GET
        foreach ($match['params'] as $key => $value) {
            $_GET[$key] = $value;
        }

        // 设置路由信息
        $GLOBALS['_ttdf_route'] = [
            'path' => $path,
            'params' => $match['params'],
            'file' => str_replace(self::$pagesDir, '', $match['file'])
        ];

        include $match['file'];
    }
}

TTDF_AutoRouter::init();