<?php

namespace Efficio\Tests\Configurare;

use Efficio\Configurare\Configuration;
use Efficio\Cache\RuntimeCache;
use PHPUnit_Framework_TestCase;

require_once __dir__ . '/ConfigurationMock.php';

class ConfigurationTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Configuration
     */
    public $conf;

    public function setUp()
    {
        $this->conf = new ConfigurationMock;
    }

    public function tearDown()
    {
        $path = 'writetest:one:two:three';

        $yaml = new Configuration;
        $yaml->setFormat(Configuration::YAML);
        $yaml->setDirectory(__dir__);
        $yaml->set($path, 1);

        $json = new Configuration;
        $json->setFormat(Configuration::JSON);
        $json->setDirectory(__dir__);
        $json->set($path, 1);
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

    /**
     * data provider
     * supported configuration formats and a sample string
     * @return array
     */
    public function configurationFormatsAndString()
    {
        return [
            [Configuration::YAML, 'test: fail'],
            [Configuration::JSON, '{ "test": "fail" }'],
         ];
    }

    public function testNewConfigurationClassesCanBeCreated()
    {
        $this->assertTrue($this->conf instanceof Configuration);
    }

    public function testFileNamesCanBeParsedFromConfigurationPath()
    {
        $this->assertEquals('config/project',
            $this->conf->callGetFileName('config/project:test:users'));
    }

    public function testFileNamesCanStillBeParsedWhenThereIsNoConfigurationPath()
    {
        $this->assertEquals('config/project',
            $this->conf->callGetFileName('config/project'));
    }

    public function testPathParser()
    {
        $this->conf->registerPathParser('/^@(\w+)/', function($match, $path) {
            return str_replace($match[0], strrev($match[1]), $path);
        });

        $this->assertEquals('gifnoc/project',
            $this->conf->callGetFileName('@config/project'));
    }

    public function testPathParsersAreOverwritten()
    {
        $this->conf->registerPathParser('/^@(\w+)/', function($match, $path) {
            return str_replace($match[0], strrev($match[1]), $path);
        });

        $this->conf->registerPathParser('/^@(\w+)/', function($match, $path) {
            return str_replace($match[0], strrev($match[1]), $path) . '/more';
        });

        $this->assertEquals('gifnoc/project/more',
            $this->conf->callGetFileName('@config/project'));
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

    public function testEnvironmentFilesOverwriteBaseConfiguration()
    {
        $this->conf->setDirectory(__dir__);
        $this->assertEquals('Your App', $this->conf->get('app:name'));
        $this->assertFalse($this->conf->get('app:level:one:two:four'));
    }

    public function testEnvironmentFilesOnlyOverwriteWhatTheySpecify()
    {
        $this->conf->setDirectory(__dir__);
        $this->assertEquals(['red', 'blue'], $this->conf->get('app:colors'));
        $this->assertTrue($this->conf->get('app:level:one:two:three'));
    }

    public function testEnvironmentFilesCanAddNewItemsAtTheBaseLevel()
    {
        $this->conf->setDirectory(__dir__);
        $this->assertEquals('new', $this->conf->get('app:something'));
    }

    public function testEnvironmentFilesCanAddNewItemsAtAnyLevel()
    {
        $this->conf->setDirectory(__dir__);
        $this->assertEquals('here', $this->conf->get('app:level:starting:from'));
    }

    /**
     * @dataProvider configurationFormatsAndString
     */
    public function testMacroPreParsers($format, $string)
    {
        $this->conf->setFormat($format);
        $this->conf->registerMacroPreParser('/(fail)/i', function($matches, $raw) {
            return str_replace('fail', 'pass', $raw);
        });

        $decoded = $this->conf->callDecode($string);
        $this->assertEquals('pass', $decoded['test']);
    }

    /**
     * @dataProvider configurationFormatsAndString
     */
    public function testMacroPostParsers($format, $string)
    {
        $this->conf->setFormat($format);
        $this->conf->registerMacroPostParser(function(& $obj) {
            $obj['test'] = 'pass';
            return $obj;
        });

        $decoded = $this->conf->callDecode($string);
        $this->assertEquals('pass', $decoded['test']);
    }

    /**
     * @dataProvider configurationFormats
     */
    public function testMergeFieldsAreMergedWhenGettingWholeFile($format)
    {
        $this->conf->setDirectory(__dir__);
        $this->conf->setFormat($format);
        $all = $this->conf->load('configuration3', [
            'name' => 'Marcos'
        ]);
        $this->assertEquals([
            'name' => 'my name is Marcos.',
        ], $all);
    }

    /**
     * @dataProvider configurationFormats
     */
    public function testMergeFieldsAreMergedWhenGettingSingleProperty($format)
    {
        $this->conf->setDirectory(__dir__);
        $this->conf->setFormat($format);
        $str = $this->conf->get('configuration3:name', [
            'name' => 'Marcos'
        ]);
        $this->assertEquals('my name is Marcos.', $str);
    }

    public function testMergeFieldsAreMergedIntoEnviromentConfiguration()
    {
        $this->conf->setDirectory(__dir__);
        $four = $this->conf->get('configuration4:three', [
            'four' => '4'
        ]);
        $this->assertEquals(4, $four);
    }

    public function testOnlyMergeFieldsAreReplaced()
    {
        $this->conf->setDirectory(__dir__);
        $list = $this->conf->get('configuration5:list', [
            'two' => '2'
        ]);
        $this->assertEquals('{one}', $list['one']);
        $this->assertEquals('2', $list['two']);
    }
}
