<?php

namespace Efficio\Tests\Configurare;

use Efficio\Configurare\Configuration;
use PHPUnit_Framework_TestCase;

class ConfigurationTest extends PHPUnit_Framework_TestCase
{
    public function testNewConfigurationClassesCanBeCreated()
    {
        $conf = new Configuration;
        $this->assertTrue($conf instanceof Configuration);
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
}
