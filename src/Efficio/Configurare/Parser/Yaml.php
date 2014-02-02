<?php

namespace Efficio\Configurare\Parser;

use Symfony\Component\Yaml\Yaml as YamlParser;

class Yaml implements Parser
{
    /**
     * json decode/encode configuration
     */
    const YAML_INLINE_LEVEL = 100;
    const YAML_INDENT = 2;

    /**
     * {@inheritDoc}
     */
    public function decode($raw)
    {
        return YamlParser::parse($raw);
    }

    /**
     * {@inheritDoc}
     */
    public function encode($obj)
    {
        return YamlParser::dump($obj,
            self::YAML_INLINE_LEVEL, self::YAML_INDENT);
    }
}
