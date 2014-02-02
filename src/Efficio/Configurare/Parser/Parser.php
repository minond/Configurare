<?php

namespace Efficio\Configurare\Parser;

interface Parser
{
    /**
     * takes a raw string, parses it, and returns the array representing the
     * data
     * @param string $raw
     * @return array
     */
    public function decode($raw);

    /**
     * takes an array or an object and converts it into a string that can be
     * saved in a file
     * @param mixed $obj
     * @return string
     */
    public function encode($obj);
}
