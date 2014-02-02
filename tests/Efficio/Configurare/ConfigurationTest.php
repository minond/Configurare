<?php

namespace Efficio\Tests\Configurare;

use Efficio\Configurare\Configuration;
use Efficio\Configurare\Parser\Yaml;
use Efficio\Configurare\Parser\Json;
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
        $this->conf = new ConfigurationMock;
    }

    public function tearDown()
    {
        $path = 'writetest:one:two:three';

        $yaml = new Configuration;
        $yaml->setExtension(Configuration::YAML);
        $yaml->setParser(new Yaml);
        $yaml->setDirectory(__dir__);
        $yaml->set($path, 1);

        $json = new Configuration;
        $json->setExtension(Configuration::JSON);
        $json->setParser(new Json);
        $json->setDirectory(__dir__);
        $json->set($path, 1);
    }

    /**
     * data provider
     * supported configuration extensions
     * @return array
     */
    public function configurationExtensions()
    {
        return [
            [Configuration::YAML, new Yaml],
            [Configuration::JSON, new Json],
        ];
    }

    /**
     * data provider
     * supported configuration extensions and a sample string
     * @return array
     */
    public function configurationExtensionsAndString()
    {
        return [
            [Configuration::YAML, new Yaml, 'test: fail'],
            [Configuration::JSON, new Json, '{ "test": "fail" }'],
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

    public function testEnvironmentGetterAndSetterUsingArgs()
    {
        $this->conf->setEnvironments('123');
        $this->assertEquals(['123'], $this->conf->getEnvironments());
    }

    public function testEnvironmentGetterAndSetterUsingArray()
    {
        $this->conf->setEnvironments(['123']);
        $this->assertEquals(['123'], $this->conf->getEnvironments());
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

    public function testSetExtensionsCanBeSetAndRetrieved()
    {
        $this->conf->setExtension(Configuration::JSON);
        $this->assertEquals(Configuration::JSON, $this->conf->getExtension());
    }

    public function testSetParsersCanBeSetAndRetrieved()
    {
        $json = new Json;
        $this->conf->setParser($json);
        $this->assertEquals($json, $this->conf->getParser());
    }

    public function testYamlIsTheDefaultExtension()
    {
        $this->assertEquals(Configuration::YAML, $this->conf->getExtension());
    }

    public function testYamlIsTheDefaultParser()
    {
        $this->assertEquals(get_class(new Yaml), get_class($this->conf->getParser()));
    }

    public function testDirectoryGetterAndSetter()
    {
        $this->conf->setDirectory('test');
        $this->assertEquals('test', $this->conf->getDirectory());
    }

    /**
     * @dataProvider configurationExtensions
     */
    public function testConfigurationFilesCanBeLoaded($extension, $parser)
    {
        $this->conf->setDirectory(__dir__);
        $this->conf->setExtension($extension);
        $this->conf->setParser($parser);
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
     * @dataProvider configurationExtensions
     */
    public function testSettingValues($extension, $parser)
    {
        $val = mt_rand();
        $path = 'writetest:one:two:three';
        $this->conf->setDirectory(__dir__);
        $this->conf->setExtension($extension);
        $this->conf->setParser($parser);
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
     * @dataProvider configurationExtensions
     */
    public function testSettingValuesUpdatesTheConfigurationFiles($extension, $parser)
    {
        $val = mt_rand();
        $path = 'writetest:one:two:three';
        $cache = new RuntimeCache;
        $this->conf->setCache($cache);
        $this->conf->setExtension($extension);
        $this->conf->setParser($parser);
        $this->conf->setDirectory(__dir__);
        $this->conf->set($path, $val);

        $data = file_get_contents(__dir__ . DIRECTORY_SEPARATOR . 'writetest' . $extension);
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

    public function testSettingInvalidConfigurationPathsDoNotTriggerAnExceptionWhenForcePlaceIsSet()
    {
        $val = uniqid();
        $this->conf->setDirectory(__dir__);
        $this->conf->set('configuration6:path', []);
        $this->conf->set('configuration6:path:invalid', $val, true);
        $this->assertEquals($val, $this->conf->get('configuration6:path:invalid'));
        $this->conf->set('configuration6:path', []);
    }

    public function testSettingInvalidConfigurationPathsDoNotTriggerAnExceptionWhenForcePlaceIsSetEvenForDeepPaths()
    {
        $val = uniqid();
        $this->conf->setDirectory(__dir__);
        $this->conf->set('configuration6:path', []);
        $this->conf->set('configuration6:path:one:two:three:four:invalid', $val, true);
        $this->assertEquals($val, $this->conf->get('configuration6:path:one:two:three:four:invalid'));
        $this->conf->set('configuration6:path', []);
    }

    public function testEnvironmentIsNotLoadedByDefault()
    {
        $this->conf->setDirectory(__dir__);
        $this->assertEquals('My App', $this->conf->get('app:name'));
        $this->assertTrue($this->conf->get('app:level:one:two:four'));
    }

    public function testEnvironmentChecksCorrectFileNames()
    {
        $this->conf->setDirectory(__dir__);
        $this->conf->setEnvironments('env123');
        $this->assertEquals('My App', $this->conf->get('app:name'));
        $this->assertTrue($this->conf->get('app:level:one:two:four'));
    }

    public function testEnvironmentFilesOverwriteBaseConfiguration()
    {
        $this->conf->setDirectory(__dir__);
        $this->conf->setEnvironments('env');
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
        $this->conf->setEnvironments('env');
        $this->assertEquals('new', $this->conf->get('app:something'));
    }

    public function testEnvironmentFilesCanAddNewItemsAtAnyLevel()
    {
        $this->conf->setDirectory(__dir__);
        $this->conf->setEnvironments('env');
        $this->assertEquals('here', $this->conf->get('app:level:starting:from'));
    }

    /**
     * @dataProvider configurationExtensionsAndString
     */
    public function testMacroPreParsers($extension, $parser, $string)
    {
        $this->conf->setExtension($extension);
        $this->conf->setParser($parser);
        $this->conf->registerMacroPreParser('/(fail)/i', function($matches, $raw) {
            return str_replace('fail', 'pass', $raw);
        });

        $decoded = $this->conf->callDecode($string);
        $this->assertEquals('pass', $decoded['test']);
    }

    /**
     * @dataProvider configurationExtensionsAndString
     */
    public function testMacroPostParsers($extension, $parser, $string)
    {
        $this->conf->setExtension($extension);
        $this->conf->setParser($parser);
        $this->conf->registerMacroPostParser(function(& $obj) {
            $obj['test'] = 'pass';
            return $obj;
        });

        $decoded = $this->conf->callDecode($string);
        $this->assertEquals('pass', $decoded['test']);
    }

    /**
     * @dataProvider configurationExtensions
     */
    public function testMergeFieldsAreMergedWhenGettingWholeFile($extension, $parser)
    {
        $this->conf->setDirectory(__dir__);
        $this->conf->setExtension($extension);
        $this->conf->setParser($parser);
        $all = $this->conf->load('configuration3', [
            'name' => 'Marcos'
        ]);
        $this->assertEquals([
            'name' => 'my name is Marcos.',
        ], $all);
    }

    /**
     * @dataProvider configurationExtensions
     */
    public function testMergeFieldsAreMergedWhenGettingSingleProperty($extension, $parser)
    {
        $this->conf->setDirectory(__dir__);
        $this->conf->setExtension($extension);
        $this->conf->setParser($parser);
        $str = $this->conf->get('configuration3:name', [
            'name' => 'Marcos'
        ]);
        $this->assertEquals('my name is Marcos.', $str);
    }

    public function testMergeFieldsAreMergedIntoEnvironmentConfiguration()
    {
        $this->conf->setDirectory(__dir__);
        $this->conf->setEnvironments('env');
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

    public function testMultipleEnvironments()
    {
        $this->conf->setDirectory(__dir__);
        $this->conf->setEnvironments('one', 'two', 'three');
        $this->assertTrue($this->conf->get('configuration7:settings:base'));
        $this->assertTrue($this->conf->get('configuration7:settings:one'));
        $this->assertTrue($this->conf->get('configuration7:settings:two'));
        $this->assertTrue($this->conf->get('configuration7:settings:three'));
    }

    public function testMultipleEnvironmentsInOrder()
    {
        $this->conf->setDirectory(__dir__);
        $this->conf->setEnvironments('one', 'two', 'three', 'ignored');
        $this->assertEquals(1, $this->conf->get('configuration7:settings:base'));
        $this->assertEquals(1, $this->conf->get('configuration7:settings:one'));
        $this->assertEquals(1, $this->conf->get('configuration7:settings:two'));
        $this->assertEquals(1, $this->conf->get('configuration7:settings:three'));
    }
}

