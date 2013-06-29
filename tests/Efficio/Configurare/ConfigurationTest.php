<?php

namespace Efficio\Tests\Configurare;

use Efficio\Configurare\Configuration;
use Efficio\Cache\RuntimeCache;
use PHPUnit_Framework_TestCase;

class ConfigurationTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Configuration
     */
    public $conf;

    public function setUp()
    {
        $this->conf = new Configuration;
    }

    /**
     * data provider
     * supported configuration formats
     * @return array
     */
    public function configurationFormats()
    {
        return [ [Configuration::YAML], [Configuration::JSON] ];
    }

    public function testNewConfigurationClassesCanBeCreated()
    {
        $this->assertTrue($this->conf instanceof Configuration);
    }

    public function testFileNamesCanBeParsedFromConfigurationPath()
    {
        $this->assertEquals('config/project',
            Configuration::getFileName('config/project:test:users'));
    }

    public function testFileNamesCanStillBeParsedWhenThereIsNoConfigurationPath()
    {
        $this->assertEquals('config/project',
            Configuration::getFileName('config/project'));
    }

    public function testConfigurationPathsCanBeParsed()
    {
        $this->assertEquals(['test', 'users', 'first'],
            Configuration::getConfPath('config/project:test:users:first'));
    }

    public function testPassingNoConfigurationPathReturnsAnEmptyArray()
    {
        $this->assertEquals([],
            Configuration::getConfPath('config/project'));
    }

    public function testSetFormatsCanBeSetAndRetrieved()
    {
        $this->conf->setFormat(Configuration::JSON);
        $this->assertEquals(Configuration::JSON, $this->conf->getFormat());
    }

    public function testYamlIsTheDefaultFormat()
    {
        $this->assertEquals(Configuration::YAML, $this->conf->getFormat());
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Invalid format: .jsoninvalid, following formats are are supported: .json, .yml
     */
    public function testInvalidFormatsThrowException()
    {
        $this->conf->setFormat(Configuration::JSON . 'invalid');
    }

    public function testDirectoryGetterAndSetter()
    {
        $this->conf->setDirectory('test');
        $this->assertEquals('test', $this->conf->getDirectory());
    }

    /**
     * @dataProvider configurationFormats
     */
    public function testConfigurationFilesCanBeLoaded($format)
    {
        $this->conf->setDirectory(__dir__);
        $this->conf->setFormat($format);
        $all = $this->conf->load('configuration1');
        $this->assertEquals([
            'numbers' => [1, 2, 3]
        ], $all);
    }

    public function testConfigurationFilesAreCached()
    {
        $cache = new RuntimeCache;
        $this->conf->setCache($cache);
        $this->conf->setDirectory(__dir__);
        $all = $this->conf->load('configuration1');
        $this->assertEquals($all, $cache->get('configuration1'));
    }

    public function testConfigurationFilesCanBeReadFromTheCache()
    {
        $data = [1, 2, 3];
        $cache = new RuntimeCache;
        $cache->set('configuration_not_exists', $data);
        $this->conf->setCache($cache);
        $all = $this->conf->load('configuration_not_exists');
        $this->assertEquals($all, $data);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Invalid file: /configuration_not_exists.yml
     */
    public function testInvalidConfigurationFilesThrowException()
    {
        $this->conf->load('configuration_not_exists');
    }

    public function testIndividualConfigurationValuesCanBeRetrieved()
    {
        $this->conf->setDirectory(__dir__);
        $numbers = $this->conf->get('configuration1:numbers');
        $this->assertEquals([1, 2, 3], $numbers);
    }

    public function testIndividualArrayConfigurationValuesCanBeRetrieved()
    {
        $this->conf->setDirectory(__dir__);
        $numbers = $this->conf->get('configuration1:numbers:1');
        $this->assertEquals(2, $numbers);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Invalid configuration path: configuration1:invalid
     */
    public function testInvalidConfigurationPathsTriggerAnException()
    {
        $this->conf->setDirectory(__dir__);
        $numbers = $this->conf->get('configuration1:invalid');
    }

    public function testComplexPaths()
    {
        $this->conf->setDirectory(__dir__);
        $this->assertTrue($this->conf->get(
            'configuration2:projects:php:php:0:one'));
    }

    /**
     * @dataProvider configurationFormats
     */
    public function testSettingValues($format)
    {
        $val = mt_rand();
        $path = 'writetest:one:two:three';
        $this->conf->setDirectory(__dir__);
        $this->conf->setFormat($format);
        $this->assertTrue($this->conf->set($path, $val));
        $this->assertEquals($val, $this->conf->get($path));
    }

    public function testSettingValuesUpdatesTheCache()
    {
        $val = mt_rand();
        $path = 'writetest:one:two:three';
        $cache = new RuntimeCache;
        $this->conf->setCache($cache);
        $this->conf->setDirectory(__dir__);
        $this->conf->set($path, $val);
        $this->assertEquals([ 'one' => [ 'two' => [ 'three' => $val] ] ],
            $cache->get('writetest'));
    }

    /**
     * @dataProvider configurationFormats
     */
    public function testSettingValuesUpdatesTheConfigurationFiles($format)
    {
        $val = mt_rand();
        $path = 'writetest:one:two:three';
        $cache = new RuntimeCache;
        $this->conf->setCache($cache);
        $this->conf->setFormat($format);
        $this->conf->setDirectory(__dir__);
        $this->conf->set($path, $val);

        $data = file_get_contents(__dir__ . DIRECTORY_SEPARATOR . 'writetest' . $format);
        $this->assertTrue(strpos($data, (string) $val) !== false);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Invalid configuration path: configuration1:invalid
     */
    public function testSettingInvalidConfigurationPathsTriggerAnException()
    {
        $this->conf->setDirectory(__dir__);
        $numbers = $this->conf->set('configuration1:invalid', false);
    }
}
