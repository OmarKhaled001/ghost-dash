<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:service', description: 'Create a new service class')]
final class MakeServiceCommand extends GeneratorCommand
{
    protected $type = 'Service';

    #[Override]
    protected function getStub(): string
    {
        return base_path('stubs/service.stub');
    }

    /**
     * @param  string  $rootNamespace
     */
    #[Override]
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Services';
    }

    /**
     * Accept "Invoice" and "InvoiceService" alike. The suffix is part of
     * the convention (an architecture test checks it), not of what someone
     * should have to remember to type.
     */
    #[Override]
    protected function getNameInput(): string
    {
        return Str::finish(parent::getNameInput(), 'Service');
    }
}
