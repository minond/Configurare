<?php

namespace Efficio\Configurare;

use Efficio\Cache\Caching;
use Efficio\Utilitatis\Merger;
use Symfony\Component\Yaml\Yaml;
use InvalidArgumentException;
use Exception;
use Closure;

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
     * json decode/encode configuration
     */
    const YAML_INLINE_LEVEL = 100;
    const YAML_INDENT = 2;

    /**
     * configuration path delimeter
     */
    const DELIM = ':';

    /**
     * identifier for environment configuration files. ie. project config:
     * app.yml, env config overwrite: app.env.yml
     * @var string[]
     */
    protected $environments = [];

    /**
     * configuration file format
     */
    protected $format = self::YAML;

    /**
     * configuration files directory
     * @var string
     */
    protected $dir = '';

    /**
     * patterns and path reformatters
     * @var array
     */
    protected $path_parsers = [];

    /**
     * ran before decoding a configuration file
     * @var array
     */
    protected $macro_pre_parsers = [];

    /**
     * ran before decoding a configuration file
     * @var array
     */
    protected $macro_post_parsers = [];

    /**
     * add a path parser
     * @param string $pattern
     * @param Callable $formatter
     */
    public function registerPathParser($pattern, Callable $formatter)
    {
        $this->path_parsers[ $pattern ] = $formatter;
    }

    /**
     * add a macro pre parser
     * @param string $pattern
     * @param Callable $formatter
     */
    public function registerMacroPreParser($pattern, Callable $formatter)
    {
        $this->macro_pre_parsers[ $pattern ] = $formatter;
    }

    /**
     * add a macro post parser
     * @param Callable $formatter
     */
    public function registerMacroPostParser(Callable $formatter)
    {
        $this->macro_post_parsers[] = Closure::bind($formatter, $this, get_class($this));
    }

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
     * @param string[] $env
     */
    public function setEnvironments($env)
    {
        $this->environments = is_array($env) ? $eng : func_get_args();
    }

    /**
     * @return string[]
     */
    public function getEnvironments()
    {
        return $this->environments;
    }

    /**
     * load a configuration file
     * @param string $path
     * @param array $mergedata
     * @throws Exception
     * @return array
     */
    public function load($path, array $mergedata = [])
    {
        $merger = new Merger;
        $file = $this->getFilePath($path);
        $hash = $this->getFileName($path);

        if ($this->cache && $this->cache->has($hash)) {
            $data = $this->cache->get($hash);
        } else if (is_readable($file)) {
            $rstr = file_get_contents($file);
            $rstr = $merger->merge($rstr, $mergedata, false);
            $data = $this->decode($rstr);

            if (count($this->environments)) {
                foreach ($this->environments as $env) {
                    $envf = $this->getEnvFilePath($path, $env);

                    if (file_exists($envf)) {
                        $estr = file_get_contents($envf);
                        $estr = $merger->merge($estr, $mergedata, false);
                        $envd = $this->decode($estr);
                        $data = array_replace_recursive($data, $envd);
                    }
                }
            }
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
     * @param array $mergedata
     * @throws Exception
     * @return string
     */
    public function get($path, array $mergedata = [])
    {
        $conf = $this->load($path, $mergedata);
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
     * update a configuration value. returns write success
     * @param string $path
     * @param mixed $value
     * @param boolean $force, update key, even if path/key has to be created
     * @throws Exception
     * @return boolean
     */
    public function set($path, $value, $force = false)
    {
        $keys = static::getConfPath($path);
        $hash = $this->getFileName($path);
        $last = count($keys) - 1;
        $file = $this->getFilePath($path);
        $conf = $this->load($path);
        $find =& $conf;

        foreach ($keys as $index => $key) {
            if (isset($find[ $key ]) || $force) {
                if ($index === $last) {
                    $find[ $key ] = $value;
                } else if ($force) {
                    $find[ $key ] = [];
                    $find =& $find[ $key ];
                } else {
                    $find =& $find[ $key ];
                }
            } else {
                throw new Exception('Invalid configuration path: ' . $path);
            }
        }

        // update cache and write to file
        if ($this->cache) {
            $this->cache->set($hash, $conf);
        }

        return file_put_contents($file, $this->encode($conf)) !== false;
    }

    /**
     * decode a configuration string
     * @param string $raw
     * @return array
     */
    protected function decode($raw)
    {
        $obj = null;

        // pre parsers
        foreach ($this->macro_pre_parsers as $pattern => $formatter) {
            preg_match_all($pattern, $raw, $match);

            if (count($match)) {
                $raw = call_user_func($formatter, $match, $raw);
            }
        }

        switch ($this->format) {
            case self::JSON:
                $obj = json_decode($raw, true);
                break;

            case self::YAML:
            default:
                $obj = Yaml::parse($raw);
                break;
        }

        // post parsers
        foreach ($this->macro_post_parsers as $formatter) {
            $obj = $formatter($obj);
        }

        return $obj;
    }

    /**
     * encode a configuration object
     * @param mixed $obj
     * @return string
     */
    protected function encode($obj)
    {
        $raw = null;

        switch ($this->format) {
            case self::JSON:
                $raw = json_encode($obj, JSON_PRETTY_PRINT);
                break;

            case self::YAML:
            default:
                $raw = Yaml::dump($obj, self::YAML_INLINE_LEVEL, self::YAML_INDENT);
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
            $this->getFileName($path) . $this->format;
    }

    /**
     * @see Configuration::getFilePath
     * @param string $path
     * @param string $env
     * @return string
     */
    private function getEnvFilePath($path, $env)
    {
        return $this->dir . DIRECTORY_SEPARATOR .
            $this->getFileName($path) . '.' . $env . $this->format;
    }

    /**
     * get the file name from a configuration path
     * @param string $path
     * @return string
     */
    protected function getFileName($path)
    {
        $arr = explode(self::DELIM, $path, 2);
        $raw = array_shift($arr);

        foreach ($this->path_parsers as $pattern => $formatter) {
            preg_match($pattern, $raw, $match);

            if (count($match)) {
                $raw = call_user_func($formatter, $match, $raw);
            }
        }

        return $raw;
    }

    /**
     * get the configuration path
     * @param string $path
     * @return array
     */
    public static function getConfPath($path)
    {
        $arr = explode(self::DELIM, $path);
        return array_slice($arr, 1);
    }
}
