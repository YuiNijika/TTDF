<?php

declare(strict_types=1);

namespace TTDF\Theme;

final class Fields
{
    /**
     * @param object $layout Typecho layout instance
     * @param array<string, array<string, mixed>> $fields
     */
    public static function addToLayout(object $layout, array $fields): void
    {
        foreach ($fields as $field) {
            $type = (string) ($field['type'] ?? 'Text');
            $class = '\\Typecho\\Widget\\Helper\\Form\\Element\\' . $type;
            if (!class_exists($class)) {
                continue;
            }

            $name = (string) ($field['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $options = $field['options'] ?? null;
            $value = $field['value'] ?? null;
            $label = (string) ($field['label'] ?? '');
            $description = (string) ($field['description'] ?? '');

            $element = in_array($type, ['Radio', 'Select', 'Checkbox'], true)
                ? new $class($name, is_array($options) ? $options : [], $value, $label, $description)
                : new $class($name, null, $value, $label, $description);

            if (isset($field['attributes']) && is_array($field['attributes'])) {
                foreach ($field['attributes'] as $attribute => $attributeValue) {
                    if (isset($element->input) && is_object($element->input)) {
                        $setter = [$element->input, 'setAttribute'];
                        if (is_callable($setter)) {
                            $setter((string) $attribute, (string) $attributeValue);
                        }
                    }
                }
            }

            if (is_callable([$layout, 'addItem'])) {
                call_user_func([$layout, 'addItem'], $element);
            }
        }
    }
}
