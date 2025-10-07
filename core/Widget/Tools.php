<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 确保错误处理系统已加载
if (!class_exists('TTDF_ErrorHandler')) {
    require_once dirname(__DIR__) . '/Modules/ErrorHandler.php';
}

/**
 * TTDF工具类
 * 提供类反射、调试工具和实用方法
 */
class TTDF_Tools extends Typecho_Widget
{
    /** @var TTDF_ErrorHandler 错误处理器实例 */
    protected static $errorHandler;

    /** @var array 缓存反射结果 */
    private static $reflectionCache = [];

    /** @var int 缓存最大数量 */
    private const MAX_CACHE_SIZE = 50;

    /**
     * 构造函数
     */
    public function __construct($request, $response, $params = null)
    {
        parent::__construct($request, $response, $params);

        // 初始化错误处理器
        if (!self::$errorHandler) {
            self::$errorHandler = TTDF_ErrorHandler::getInstance();
            self::$errorHandler->init();
        }
    }

    /**
     * 清理缓存
     */
    public static function clearCache(): void
    {
        self::$reflectionCache = [];
    }

    /**
     * 获取缓存统计
     */
    public static function getCacheStats(): array
    {
        return [
            'size' => count(self::$reflectionCache),
            'max_size' => self::MAX_CACHE_SIZE,
            'keys' => array_keys(self::$reflectionCache)
        ];
    }
    /**
     * 获取类的详细反射信息并格式化输出
     * 通过反射机制获取指定函数的全面详细属性和元数据，支持缓存
     * 
     * @param string|object $class 类名或类对象
     * @param bool $returnArray 是否返回数组（默认为false，直接输出）
     * @param bool $useCache 是否使用缓存（默认为true）
     * @return array|void 类信息数组（当$returnArray为true时）
     * @throws \ReflectionException
     */
    public static function ClassDetails($class, ?bool $returnArray = false, bool $useCache = true)
    {
        try {
            // 生成缓存键
            $className = is_object($class) ? get_class($class) : $class;
            $cacheKey = md5($className . '_' . ($returnArray ? '1' : '0'));

            // 检查缓存
            if ($useCache && isset(self::$reflectionCache[$cacheKey])) {
                $classInfo = self::$reflectionCache[$cacheKey];

                if (!$returnArray) {
                    self::outputClassInfo($classInfo);
                    return;
                }
                return $classInfo;
            }

            $reflector = new \ReflectionClass($class);

            // 安全地获取默认值的函数
            $getSafeDefaultValue = function ($value) {
                try {
                    if (is_object($value)) {
                        return get_class($value);
                    }
                    if (is_array($value)) {
                        return array_map(function ($item) {
                            return is_object($item) ? get_class($item) : $item;
                        }, $value);
                    }
                    return $value;
                } catch (\Throwable $e) {
                    return '无法获取默认值';
                }
            };

            // 递归获取父类继承链
            $getParentChain = function ($reflector) use (&$getParentChain) {
                $parentChain = [];
                $currentParent = $reflector->getParentClass();

                while ($currentParent) {
                    $parentChain[] = [
                        'className' => $currentParent->getName(),
                        'namespace' => $currentParent->getNamespaceName(),
                        'shortName' => $currentParent->getShortName()
                    ];
                    $currentParent = $currentParent->getParentClass();
                }

                return $parentChain;
            };

            // 基本类信息
            $namespace = $reflector->getNamespaceName();
            $className = $reflector->getName();
            $shortClassName = $reflector->getShortName();

            // 获取完整父类继承链
            $parentChain = $getParentChain($reflector);

            // 接口信息
            $interfaces = $reflector->getInterfaceNames();

            // 属性信息
            $properties = array_map(function ($prop) use ($getSafeDefaultValue) {
                try {
                    return [
                        'name' => $prop->getName(),
                        'type' => $prop->getType() ? $prop->getType()->getName() : 'mixed',
                        // 替换 match 表达式兼容php7
                        'visibility' => (function () use ($prop) {
                            if ($prop->isPublic()) {
                                return 'public';
                            } elseif ($prop->isProtected()) {
                                return 'protected';
                            } elseif ($prop->isPrivate()) {
                                return 'private';
                            } else {
                                return 'unknown';
                            }
                        })(),
                        'static' => $prop->isStatic(),
                        'hasDefaultValue' => $prop->hasDefaultValue(),
                        'defaultValue' => $prop->hasDefaultValue()
                            ? $getSafeDefaultValue($prop->getDefaultValue())
                            : null
                    ];
                } catch (\Throwable $e) {
                    return [
                        'name' => $prop->getName(),
                        'error' => '无法获取属性详情：' . $e->getMessage()
                    ];
                }
            }, $reflector->getProperties());

            // 方法信息
            $methods = array_map(function ($method) use ($getSafeDefaultValue) {
                try {
                    return [
                        'name' => $method->getName(),
                        // 替换 match 表达式兼容php7
                        'visibility' => (function () use ($method) {
                            if ($method->isPublic()) {
                                return 'public';
                            } elseif ($method->isProtected()) {
                                return 'protected';
                            } elseif ($method->isPrivate()) {
                                return 'private';
                            } else {
                                return 'unknown';
                            }
                        })(),
                        'static' => $method->isStatic(),
                        'abstract' => $method->isAbstract(),
                        'final' => $method->isFinal(),
                        'parameters' => array_map(function ($param) use ($getSafeDefaultValue) {
                            return [
                                'name' => $param->getName(),
                                'type' => $param->hasType() ? $param->getType()->getName() : 'mixed',
                                'optional' => $param->isOptional() ?? false,
                                'defaultValue' => $param->isOptional()
                                    ? ($param->isDefaultValueAvailable()
                                        ? $getSafeDefaultValue($param->getDefaultValue())
                                        : null)
                                    : null
                            ];
                        }, $method->getParameters())
                    ];
                } catch (\Throwable $e) {
                    return [
                        'name' => $method->getName(),
                        'error' => '无法获取方法详情：' . $e->getMessage()
                    ];
                }
            }, $reflector->getMethods());

            // 准备返回的数组
            $classInfo = [
                'fullClassName' => $className,
                'shortClassName' => $shortClassName,
                'namespace' => $namespace,
                'parentChain' => $parentChain,
                'interfaces' => $interfaces,
                'properties' => $properties,
                'methods' => $methods,
                'isAbstract' => $reflector->isAbstract(),
                'isFinal' => $reflector->isFinal(),
                'isInterface' => $reflector->isInterface(),
                'isTrait' => $reflector->isTrait(),
                'fileName' => $reflector->getFileName(),
                'constants' => $reflector->getConstants()
            ];

            // 缓存结果
            if ($useCache) {
                // 如果缓存已满，清理最老的条目
                if (count(self::$reflectionCache) >= self::MAX_CACHE_SIZE) {
                    $oldestKey = array_key_first(self::$reflectionCache);
                    unset(self::$reflectionCache[$oldestKey]);
                }
                self::$reflectionCache[$cacheKey] = $classInfo;
            }

            // 根据参数决定返回或输出
            if ($returnArray) {
                return $classInfo;
            }

            self::outputClassInfo($classInfo);
        } catch (\ReflectionException $e) {
            self::$errorHandler->error('反射错误: ' . $e->getMessage(), ['class' => $className], $e);
            if (!$returnArray) {
                echo "反射错误: " . $e->getMessage() . "\n";
            }
        } catch (\Throwable $e) {
            self::$errorHandler->error('ClassDetails未知错误: ' . $e->getMessage(), ['class' => $className], $e);
            if (!$returnArray) {
                echo "未知错误: " . $e->getMessage() . "\n";
            }
        }

        if ($returnArray) {
            return $classInfo ?? [];
        }
    }

    /**
     * 输出类信息的私有方法
     * 
     * @param array $classInfo 类信息数组
     */
    private static function outputClassInfo(array $classInfo): void
    {
        // 文本输出
        echo "完整类名: {$classInfo['fullClassName']}\n";
        echo "短类名: {$classInfo['shortClassName']}\n";
        echo "命名空间: {$classInfo['namespace']}\n";
        echo "文件位置: " . ($classInfo['fileName'] ?: '未知') . "\n";

        // 输出父类继承链
        echo "父类继承链: \n";
        if (empty($classInfo['parentChain'])) {
            echo "  无父类\n";
        } else {
            foreach ($classInfo['parentChain'] as $index => $parent) {
                echo "  " . str_repeat("└── ", $index) .
                    "父类 " . ($index + 1) . ": {$parent['className']} (命名空间: {$parent['namespace']})\n";
            }
        }

        // 输出接口信息
        echo "实现的接口: \n";
        if (empty($classInfo['interfaces'])) {
            echo "  无接口\n";
        } else {
            foreach ($classInfo['interfaces'] as $interface) {
                echo "  - {$interface}\n";
            }
        }

        // 输出常量信息
        echo "类常量: \n";
        if (empty($classInfo['constants'])) {
            echo "  无常量\n";
        } else {
            foreach ($classInfo['constants'] as $name => $value) {
                echo "  - {$name}: " . (is_array($value) ? json_encode($value) : $value) . "\n";
            }
        }

        // 输出属性信息
        echo "类属性: \n";
        if (empty($classInfo['properties'])) {
            echo "  无属性\n";
        } else {
            foreach ($classInfo['properties'] as $prop) {
                if (isset($prop['error'])) {
                    echo "  - 错误: {$prop['error']}\n";
                    continue;
                }

                $defaultValue = $prop['hasDefaultValue']
                    ? ' (默认值: ' . (is_array($prop['defaultValue']) ? json_encode($prop['defaultValue']) : $prop['defaultValue']) . ')'
                    : '';
                echo "  - {$prop['visibility']} " . ($prop['static'] ? 'static ' : '') .
                    "{$prop['type']} \${$prop['name']}{$defaultValue}\n";
            }
        }

        // 输出方法信息
        echo "类方法: \n";
        if (empty($classInfo['methods'])) {
            echo "  无方法\n";
        } else {
            foreach ($classInfo['methods'] as $method) {
                if (isset($method['error'])) {
                    echo "  - 错误: {$method['error']}\n";
                    continue;
                }

                $params = array_map(function ($param) {
                    $defaultValue = $param['optional'] && $param['defaultValue'] !== null
                        ? ' = ' . (is_array($param['defaultValue']) ? json_encode($param['defaultValue']) : $param['defaultValue'])
                        : '';
                    return "{$param['type']} \${$param['name']}{$defaultValue}";
                }, $method['parameters']);

                $modifiers = [];
                if ($method['abstract']) $modifiers[] = 'abstract';
                if ($method['final']) $modifiers[] = 'final';
                if ($method['static']) $modifiers[] = 'static';

                $modifierStr = !empty($modifiers) ? implode(' ', $modifiers) . ' ' : '';

                echo "  - {$method['visibility']} {$modifierStr}{$method['name']}(" . implode(', ', $params) . ")\n";
            }
        }

        // 额外类型信息
        echo "\n类型信息:\n";
        echo "  抽象类: " . ($classInfo['isAbstract'] ? '是' : '否') . "\n";
        echo "  Final类: " . ($classInfo['isFinal'] ? '是' : '否') . "\n";
        echo "  接口: " . ($classInfo['isInterface'] ? '是' : '否') . "\n";
        echo "  Trait: " . ($classInfo['isTrait'] ? '是' : '否') . "\n";
    }
}
