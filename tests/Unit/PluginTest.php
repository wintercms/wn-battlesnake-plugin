<?php

namespace Winter\Battlesnake\Tests\Unit;

use Winter\Battlesnake\Plugin;
use System\Classes\PluginBase;
use System\Tests\Bootstrap\PluginTestCase;

class PluginTest extends PluginTestCase
{
    protected PluginBase $plugin;

    public function setUp(): void
    {
        $this->plugin = new Plugin($this->createApplication());
    }

    public function testPluginDetails()
    {
        $details = $this->plugin->pluginDetails();

        $this->assertIsArray($details);
        $this->assertArrayHasKey('name', $details);
        $this->assertArrayHasKey('description', $details);
        $this->assertArrayHasKey('icon', $details);
        $this->assertArrayHasKey('author', $details);

        $this->assertEquals('Winter', $details['author']);
    }

    public function testRegisterPermissions()
    {
        $permissions = $this->plugin->registerPermissions();

        $this->assertIsArray($permissions);
    }
}
