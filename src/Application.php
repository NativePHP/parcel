<?php

declare(strict_types=1);

namespace Parcel;

use Parcel\Command\CompileCommand;
use Parcel\Command\PatchComposerCommand;
use Parcel\Command\PrepareCommand;
use Parcel\Command\PushStubsCommand;
use Symfony\Component\Console\Application as SymfonyApplication;

class Application extends SymfonyApplication
{
    public function __construct()
    {
        parent::__construct('Parcel');

        $this->add(new PushStubsCommand);
        $this->add(new PatchComposerCommand);
        $this->add(new PrepareCommand);
        $this->add(new CompileCommand);
    }
}
