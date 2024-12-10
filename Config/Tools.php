<?php
/**
 * Tools Functions
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
class Tools {
    /**
     * 获取类的详细反射信息并格式化输出
     * 通过反射机制获取指定函数的全面详细属性和元数据
     * 
     * @param string|object $class 类名或类对象
     * @param bool $returnArray 是否返回数组（默认为false，直接输出）
     * @return array|void 类信息数组（当$returnArray为true时）
     * @throws \ReflectionException
     */
    public static function ClassDetails($class, ?bool $returnArray = false) {
        try {
            $reflector = new \ReflectionClass($class);
            
            // 安全地获取默认值的函数
            $getSafeDefaultValue = function($value) {
                try {
                    if (is_object($value)) {
                        return get_class($value);
                    }
                    if (is_array($value)) {
                        return array_map(function($item) {
                            return is_object($item) ? get_class($item) : $item;
                        }, $value);
                    }
                    return $value;
                } catch (\Throwable $e) {
                    return '无法获取默认值';
                }
            };
            
            // 递归获取父类继承链
            $getParentChain = function($reflector) use (&$getParentChain) {
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
            $properties = array_map(function($prop) use ($getSafeDefaultValue) {
                try {
                    return [
                        'name' => $prop->getName(),
                        'type' => $prop->getType() ? $prop->getType()->getName() : 'mixed',
                        // 替换 match 表达式兼容php7
                        'visibility' => (function() use ($prop) {
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
            $methods = array_map(function($method) use ($getSafeDefaultValue) {
                try {
                    return [
                        'name' => $method->getName(),
                        // 替换 match 表达式兼容php7
                        'visibility' => (function() use ($method) {
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
                        'parameters' => array_map(function($param) use ($getSafeDefaultValue) {
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
            
            // 根据参数决定返回或输出
            if ($returnArray) {
                return $classInfo;
            }
            
            // 文本输出
            echo "完整类名: {$className}\n";
            echo "短类名: {$shortClassName}\n";
            echo "命名空间: {$namespace}\n";
            echo "文件位置: " . ($reflector->getFileName() ?: '未知') . "\n";
            
            // 输出父类继承链
            echo "父类继承链: \n";
            if (empty($parentChain)) {
                echo "  无父类\n";
            } else {
                foreach ($parentChain as $index => $parent) {
                    echo "  " . str_repeat("└── ", $index) . 
                         "父类 " . ($index + 1) . ": {$parent['className']} (命名空间: {$parent['namespace']})\n";
                }
            }
            
            // 输出接口信息
            echo "实现的接口: \n";
            if (empty($interfaces)) {
                echo "  无接口\n";
            } else {
                foreach ($interfaces as $interface) {
                    echo "  - {$interface}\n";
                }
            }
            
            // 输出常量信息
            $constants = $reflector->getConstants();
            echo "类常量: \n";
            if (empty($constants)) {
                echo "  无常量\n";
            } else {
                foreach ($constants as $name => $value) {
                    echo "  - {$name}: " . (is_array($value) ? json_encode($value) : $value) . "\n";
                }
            }
            
            // 输出属性信息
            echo "类属性: \n";
            if (empty($properties)) {
                echo "  无属性\n";
            } else {
                foreach ($properties as $prop) {
                    $defaultValue = $prop['hasDefaultValue'] 
                        ? ' (默认值: ' . (is_array($prop['defaultValue']) ? json_encode($prop['defaultValue']) : $prop['defaultValue']) . ')' 
                        : '';
                    echo "  - {$prop['visibility']} " . 
                         ($prop['static'] ? 'static ' : '') . 
                         "{$prop['type']} \${$prop['name']}{$defaultValue}\n";
                }
            }
            
            // 输出方法信息
            echo "类方法: \n";
            if (empty($methods)) {
                echo "  无方法\n";
            } else {
                foreach ($methods as $method) {
                    $params = implode(', ', array_map(function($param) {
                        $optional = $param['optional'] ? ' = ' . 
                            (is_array($param['defaultValue']) ? json_encode($param['defaultValue']) : $param['defaultValue']) 
                            : '';
                        return "{$param['type']} \${$param['name']}{$optional}";
                    }, $method['parameters']));
                    
                    echo "  - {$method['visibility']} " . 
                         ($method['static'] ? 'static ' : '') . 
                         ($method['abstract'] ? 'abstract ' : '') . 
                         ($method['final'] ? 'final ' : '') . 
                         "function {$method['name']}({$params})\n";
                }
            }
            
            // 额外类型信息
            echo "\n类型信息:\n";
            echo "  抽象类: " . ($reflector->isAbstract() ? '是' : '否') . "\n";
            echo "  Final类: " . ($reflector->isFinal() ? '是' : '否') . "\n";
            echo "  接口: " . ($reflector->isInterface() ? '是' : '否') . "\n";
            echo "  Trait: " . ($reflector->isTrait() ? '是' : '否') . "\n";
            
        } catch (\ReflectionException $e) {
            echo "错误：无法获取类信息 - " . $e->getMessage() . "\n";
        }
    }    
}
