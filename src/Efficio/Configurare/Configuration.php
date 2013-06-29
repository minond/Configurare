<?php

namespace Efficio\Configurare;

use Efficio\Cache\Caching;
use Symfony\Component\Yaml\Yaml;
use InvalidArgumentException;
use Exception;

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
     * configuration files directory
     * @var string
     */
    private $dir = '';

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
     * @param string $dir
     */
    public function setDirectory($dir)
    {
        $this->dir = $dir;
    }

    /**
     * @return string
     */
    public function getDirectory()
    {
        return $this->dir;
    }

    /**
     * load a configuration file
     * @param string $path
     * @throws Exception
     * @return array
     */
    public function load($path)
    {
        $file = $this->getFilePath($path);
        $hash = self::getFileName($path);

        if ($this->cache && $this->cache->has($hash)) {
            $data = $this->cache->get($hash);
        } else if (is_readable($file)) {
            $rstr = file_get_contents($file);
            $data = $this->decode($rstr);
        } else {
            throw new Exception('Invalid file: ' . $file);
        }

        if ($this->cache) {
            $this->cache->set($hash, $data);
        }

        return $data;
    }

    /**
     * retrieve a configuration value
     * @param string $path
     * @throws Exception
     * @return string
     */
    public function get($path)
    {
        $conf = $this->load($path);
        $keys = static::getConfPath($path);

        foreach ($keys as $key) {
            if (isset($conf[ $key ])) {
                $conf = $conf[ $key ];
            } else {
                throw new Exception('Invalid configuration path: ' . $path);
            }
        }

        return $conf;
    }

    /**
     * decode a configuration string
     * @param string $raw
     * @return array
     */
    private function decode($raw)
    {
        $obj = null;

        switch ($this->format) {
            case self::JSON:
                $obj = json_decode($raw, true);
                break;

            case self::YAML:
            default:
                $obj = Yaml::parse($raw);
                break;
        }

        return $obj;
    }

    /**
     * encode a configuration object
     *
     * TODO remove magic numbers from Yaml::dump call
     *
     * @param mixed $obj
     * @return string
     */
    private function encode($obj)
    {
        $raw = null;

        switch ($this->format) {
            case self::JSON:
                $raw = json_encode($obj);
                break;

            case self::YAML:
            default:
                $raw = Yaml::dump($obj, 100, 2);
                break;
        }

        return $raw;
    }

    /**
     * extract file name from configuration path and return file path
     * @param string $path
     * @return string
     */
    private function getFilePath($path)
    {
        return $this->dir . DIRECTORY_SEPARATOR .
            static::getFileName($path) . $this->format;
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
