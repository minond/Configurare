<?php

namespace Efficio\Tests\Configurare;

use Efficio\Configurare\Configuration;

class ConfigurationMock extends Configuration
{
    public function callGetFileName($path)
    {
        return $this->getFileName($path);
    }

    public function callDecode($str)
    {
        return $this->decode($str);
    }
}

