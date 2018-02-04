<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\Traits\GeneratesTestDirectory;

class InitScriptTest extends TestCase
{
    use GeneratesTestDirectory;

    /**
     * Copies init.sh and resources directory to the temporal directory.
     */
    public function setUp()
    {
        $projectDirectory = __DIR__.'/..';

        exec("cp {$projectDirectory}/init.sh ".self::$testDirectory);
        exec("cp -r {$projectDirectory}/resources ".self::$testDirectory);
    }

    /** @test */
    public function it_displays_a_success_message()
    {
        $output = exec('bash init.sh');

        $this->assertEquals('Lemp initialized!', $output);
    }

    /** @test */
    public function it_creates_a_server_json_file()
    {
        exec('bash init.sh json');

        $this->assertFileExists(self::$testDirectory.'/Server.json');
    }
}
