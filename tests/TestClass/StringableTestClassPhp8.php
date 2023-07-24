<?php

/** @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection */

declare(strict_types=1);

namespace Tests\TestClass;

/**
 * A class that implements the Stringable interface, to be used as cache tags.
 */
class StringableTestClassPhp8 implements \Stringable
{
    private $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}