<?php

namespace Efficio\Tests\Configurare;

use Efficio\Configurare\Configuration;
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
}
