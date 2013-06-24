<?php

namespace Efficio\Configurare;

use Efficio\Cache\Caching;

/**
 * project configuration reader and writer
 */
class Configuration
{
    use Caching;

    /**
     * configuration file formats. defaults to yaml
     */
    const JSON = '.json';
    const YAML = '.yml';

    /**
     * configuration path delimeter
     */
    const DELIM = ':';

    /**
     * configuration file format
     */
    private $format = self::YAML;

    /**
     * get the file name from a configuration path
     * @param string $path
     * @return string
     */
    public static function getFileName($path)
    {
        return array_shift(explode(self::DELIM, $path, 2));
    }

    /**
     * get the configuration path
     * @param string $path
     * @return array
     */
    public static function getConfPath($path)
    {
        return array_slice(explode(self::DELIM, $path), 1);
    }
}
