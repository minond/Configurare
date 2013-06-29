<?php

namespace Efficio\Configurare;

use Efficio\Cache\Caching;
use InvalidArgumentException;

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
     * @param string $format
     * @throws InvalidArgumentException
     */
    public function setFormat($format)
    {
        $formats = [ self::JSON, self::YAML ];

        if (!in_array($format, $formats)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid format: %s, following formats are are supported: %s',
                $format, implode(', ', $formats)
            ));
        }

        $this->format = $format;
    }

    /**
     * @return string
     */
    public function getFormat()
    {
        return $this->format;
    }

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
