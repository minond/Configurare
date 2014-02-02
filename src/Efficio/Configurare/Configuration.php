<?php

namespace Efficio\Configurare;

use Efficio\Cache\Caching;
use Efficio\Utilitatis\Merger;
use Efficio\Configurare\Parser\Parser;
use Efficio\Configurare\Parser\Yaml;
use Efficio\Configurare\Parser\Json;
use Efficio\Configurare\Parser\Ini;
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
     * configuration path delimeter
     */
    const DELIM = ':';

    /**
     * valid formats
     */
    protected static $formats = [ self::JSON, self::YAML ];

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
     * @var Parser
     */
    protected $parser;

    /**
     * sets yaml parser as default parser
     */
    public function __construct()
    {
        $this->parser = new Yaml;
    }

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
        if (!in_array($format, static::$formats)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid format: %s, following formats are are supported: %s',
                $format, implode(', ', static::$formats)
            ));
        }

        switch ($format) {
            case self::JSON:
                $this->parser = new Json;
                break;

            case self::YAML:
                $this->parser = new Yaml;
                break;
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
        $this->environments = is_array($env) ? $env : func_get_args();
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
        $file = $this->getFilePath($path);
        $hash = $this->getFileName($path);

        // cached
        if ($this->cache && $this->cache->has($hash)) {
            return $this->cache->get($hash);
        }

        // configuration file not found
        if (!is_readable($file)) {
            throw new Exception('Invalid file: ' . $file);
        }

        // project config and enviroment config
        $data = $this->loadFile($file, $mergedata);
        $envd = $this->loadEnv($path, $mergedata);
        $data = array_replace_recursive($data, $envd);

        $this->saveToCache($hash, $data);
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
        $file = $this->getFilePath($path);
        $last = count($keys) - 1;

        $conf = $this->load($path);
        $find =& $conf;

        foreach ($keys as $index => $key) {
            // invalid config path and we're not forcing it
            if (!isset($find[ $key ]) && !$force) {
                throw new Exception('Invalid configuration path: ' . $path);
            }

            if ($index === $last) {
                $find[ $key ] = $value;
            } else if ($force) {
                $find[ $key ] = [];
                $find =& $find[ $key ];
            } else {
                $find =& $find[ $key ];
            }
        }

        // update cache and write to file
        $this->saveToCache($hash, $conf);
        return file_put_contents($file, $this->encode($conf)) !== false;
    }

    /**
     * save to cache if there is one
     * @param string $key
     * @param mixed $val
     * @return void
     */
    protected function saveToCache($key, $val)
    {
        if ($this->cache) {
            $this->cache->set($key, $val);
        }
    }

    /**
     * apply all pre parser functions on a raw configuration string
     * @param string & $raw
     * @return void
     */
    protected function applyPreParsers(& $raw)
    {
        foreach ($this->macro_pre_parsers as $pattern => $formatter) {
            preg_match_all($pattern, $raw, $match);

            if (count($match)) {
                $raw = call_user_func($formatter, $match, $raw);
            }
        }
    }

    /**
     * apply all post parsers on a configuration object
     * @param array & $obj
     * @return void
     */
    protected function applyPostParsers(& $arr)
    {
        foreach ($this->macro_post_parsers as $formatter) {
            $arr = $formatter($arr);
        }
    }

    /**
     * decode a configuration string
     * @param string $raw
     * @return array
     */
    protected function decode($raw)
    {
        $arr = null;
        $this->applyPreParsers($raw);
        $arr = $this->parser->decode($raw);
        $this->applyPostParsers($arr);
        return $arr;
    }

    /**
     * encode a configuration object
     * @param mixed $obj
     * @return string
     */
    protected function encode($obj)
    {
        return $this->parser->encode($obj);
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
     * loads and decodes a configuration files
     * @param string $path
     * @param array $mergedata
     * @return array
     */
    protected function loadFile($file, array $mergedata = [])
    {
        $merger = new Merger;
        $str = file_get_contents($file);
        $str = $merger->merge($str, $mergedata, false);
        return $this->decode($str);
    }

    /**
     * loads all enviroment configuration files
     * @param string $path
     * @param array $mergedata
     * @return array
     */
    protected function loadEnv($path, array $mergedata = [])
    {
        $data = [];

        foreach ($this->environments as $env) {
            $envf = $this->getEnvFilePath($path, $env);

            if (file_exists($envf)) {
                $envd = $this->loadFile($envf, $mergedata);
                $data = array_replace_recursive($data, $envd);
            }
        }

        return $data;
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
