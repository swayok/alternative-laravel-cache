<?php

declare(strict_types=1);

namespace Tests\TestClass;

/**
 * A class used to check `cache.serializable_classes` restrictions.
 */
class SerializableTestClass
{
    public string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }
}
