<?php

namespace Yiisoft\Arrays\Modifier;

/**
 * Removes array keys.
 */
class RemoveKeys implements ModifierInterface
{
    public function apply(array $data, string $key): array
    {
        return array_values($data);
    }
}
