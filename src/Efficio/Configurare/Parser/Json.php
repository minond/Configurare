<?php

namespace Efficio\Configurare\Parser;

class Json implements Parser
{
    /**
     * {@inheritDoc}
     */
    public function decode($raw)
    {
        return json_decode($raw, true);
    }

    /**
     * {@inheritDoc}
     */
    public function encode($obj)
    {
        return json_encode($obj, JSON_PRETTY_PRINT);
    }
}
