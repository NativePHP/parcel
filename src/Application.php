<?php

declare(strict_types=1);

namespace Parcel;

use Parcel\Command\CompileCommand;
use Parcel\Command\PushStubsCommand;
use Symfony\Component\Console\Application as SymfonyApplication;

class Application extends SymfonyApplication
{
    public function __construct()
    {
        parent::__construct('Parcel', '1.0.0');

        $this->add(new PushStubsCommand());
        $this->add(new CompileCommand());
    }

    public function getLongVersion(): string
    {
        return sprintf(
            '<info>%s</info> version <comment>%s</comment> - Laravel PHAR bundler for NativePHP',
            $this->getName(),
            $this->getVersion()
        );
    }
}