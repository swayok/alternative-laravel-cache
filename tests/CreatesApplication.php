<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return Application
     */
    public function createApplication()
    {
        $app = new Application(
            realpath(__DIR__ . '/')
        );

        $app->singleton(
            \Illuminate\Contracts\Console\Kernel::class,
            Kernel::class
        );

        $app
            ->make(\Illuminate\Contracts\Console\Kernel::class)
            ->bootstrap();

        return $app;
    }
}
