<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use StarCorp\Lemp\MakeCommand;
use Tests\Traits\GeneratesTestDirectory;
use StarCorp\Lemp\Traits\GeneratesSlugs;
use Symfony\Component\Console\Tester\CommandTester;

class MakeCommandTest extends TestCase
{
    use GeneratesSlugs, GeneratesTestDirectory;

    /** @test */
    public function it_displays_a_success_message()
    {
        $tester = new CommandTester(new MakeCommand());

        $tester->execute([]);

        $this->assertContains('Lemp Installed!', $tester->getDisplay());
    }

    /** @test */
    public function it_returns_a_success_status_code()
    {
        $tester = new CommandTester(new MakeCommand());

        $tester->execute([]);

        $this->assertEquals(0, $tester->getStatusCode());
    }

    /** @test */
    public function a_vagrantfile_is_created_if_it_does_not_exists()
    {
        $tester = new CommandTester(new MakeCommand());

        $tester->execute([]);

        $this->assertFileExists(self::$testDirectory.DIRECTORY_SEPARATOR.'Vagrantfile');

        $this->assertFileEquals(
            self::$testDirectory.DIRECTORY_SEPARATOR.'Vagrantfile',
            __DIR__.'/../resources/localized/Vagrantfile'
        );
    }

    /** @test */
    public function an_existing_vagrantfile_is_not_overwritten()
    {
        file_put_contents(
            self::$testDirectory.DIRECTORY_SEPARATOR.'Vagrantfile',
            'Already existing Vagrantfile'
        );
        $tester = new CommandTester(new MakeCommand());

        $tester->execute([]);

        $this->assertStringEqualsFile(
            self::$testDirectory.DIRECTORY_SEPARATOR.'Vagrantfile',
            'Already existing Vagrantfile'
        );
    }

    /** @test */
    public function an_example_server_json_settings_is_created_if_requested()
    {
        $tester = new CommandTester(new MakeCommand());

        $tester->execute([
            '--example' => true,
            '--json' => true,
        ]);

        $this->assertFileExists(self::$testDirectory.DIRECTORY_SEPARATOR.'Server.json.example');
    }

    /** @test */
    public function an_existing_example_server_json_settings_is_not_overwritten()
    {
        file_put_contents(
            self::$testDirectory.DIRECTORY_SEPARATOR.'Server.json.example',
            '{"name": "Already existing Server.json.example"}'
        );
        $tester = new CommandTester(new MakeCommand());

        $tester->execute([
            '--example' => true,
            '--json' => true,
        ]);

        $this->assertFileExists(self::$testDirectory.DIRECTORY_SEPARATOR.'Server.json.example');

        $this->assertStringEqualsFile(
            self::$testDirectory.DIRECTORY_SEPARATOR.'Server.json.example',
            '{"name": "Already existing Server.json.example"}'
        );
    }

    /** @test */
    public function a_server_json_settings_is_created_if_it_is_requested_and_it_does_not_exists()
    {
        $tester = new CommandTester(new MakeCommand());

        $tester->execute([
            '--json' => true,
        ]);

        $this->assertFileExists(self::$testDirectory.DIRECTORY_SEPARATOR.'Server.json');
    }

    /** @test */
    public function an_existing_server_json_settings_is_not_overwritten()
    {
        file_put_contents(
            self::$testDirectory.DIRECTORY_SEPARATOR.'Server.json',
            '{"message": "Already existing Server.json"}'
        );
        $tester = new CommandTester(new MakeCommand());

        $tester->execute([]);

        $this->assertStringEqualsFile(
            self::$testDirectory.DIRECTORY_SEPARATOR.'Server.json',
            '{"message": "Already existing Server.json"}'
        );
    }

    /** @test */
    public function a_server_json_settings_is_created_from_a_server_json_example_if_is_requested_and_if_it_exists()
    {
        file_put_contents(
            self::$testDirectory.DIRECTORY_SEPARATOR.'Server.json.example',
            '{"message": "Already existing Server.json.example"}'
        );
        $tester = new CommandTester(new MakeCommand());

        $tester->execute([
            '--json' => true,
        ]);

        $this->assertFileExists(self::$testDirectory.DIRECTORY_SEPARATOR.'Server.json');

        $this->assertContains(
            '"message": "Already existing Server.json.example"',
            file_get_contents(self::$testDirectory.DIRECTORY_SEPARATOR.'Server.json')
        );
    }

    /** @test */
    public function a_server_json_settings_created_from_a_server_json_example_can_override_the_ip_address()
    {
        copy(
            __DIR__.'/../resources/Server.json',
            self::$testDirectory.DIRECTORY_SEPARATOR.'Server.json.example'
        );

        $tester = new CommandTester(new MakeCommand());

        $tester->execute([
            '--json' => true,
            '--ip' => '192.168.10.11',
        ]);

        $this->assertFileExists(self::$testDirectory.DIRECTORY_SEPARATOR.'Server.json');

        $settings = json_decode(file_get_contents(self::$testDirectory.DIRECTORY_SEPARATOR.'Server.json'), true);

        $this->assertEquals('192.168.10.11', $settings['ip']);
    }

    /** @test */
    public function a_server_json_settings_can_be_created_with_some_command_options_overrides()
    {
        $tester = new CommandTester(new MakeCommand());

        $tester->execute([
            '--json' => true,
            '--name' => 'test_name',
            '--hostname' => 'test_hostname',
            '--ip' => '127.0.0.1',
        ]);

        $this->assertFileExists(self::$testDirectory.DIRECTORY_SEPARATOR.'Server.json');

        $settings = json_decode(file_get_contents(self::$testDirectory.DIRECTORY_SEPARATOR.'Server.json'), true);

        $this->assertArraySubset([
            'name' => 'test_name',
            'hostname' => 'test_hostname',
            'ip' => '127.0.0.1',
        ], $settings);
    }

    /** @test */
    public function a_server_json_settings_has_preconfigured_sites()
    {
        $tester = new CommandTester(new MakeCommand());

        $tester->execute([
            '--json' => true,
        ]);

        $this->assertFileExists(self::$testDirectory.DIRECTORY_SEPARATOR.'Server.json');

        $settings = json_decode(file_get_contents(self::$testDirectory.DIRECTORY_SEPARATOR.'Server.json'), true);

        $this->assertEquals([
            'map' => 'starcorp.test',
            'to' => '/home/vagrant/code/public',
        ], $settings['sites'][0]);
    }

    /** @test */
    public function a_server_json_settings_has_preconfigured_shared_folders()
    {
        $tester = new CommandTester(new MakeCommand());

        $tester->execute([
            '--json' => true,
        ]);

        $this->assertFileExists(self::$testDirectory.DIRECTORY_SEPARATOR.'Server.json');

        $projectDirectory = basename(getcwd());
        $projectName = $this->slug($projectDirectory);
        $settings = json_decode(file_get_contents(self::$testDirectory.DIRECTORY_SEPARATOR.'Server.json'), true);

        // The "map" is not tested for equality because getcwd() (The method to obtain the project path)
        // returns a directory in a different location that the test directory itself.
        //
        // Example:
        //  - project directory: /private/folders/...
        //  - test directory: /var/folders/...
        //
        // The curious thing is that both directories point to the same location.
        //
        $this->assertRegExp("/{$projectDirectory}/", $settings['folders'][0]['map']);
        $this->assertEquals('/home/vagrant/code', $settings['folders'][0]['to']);
        $this->assertEquals($projectName, $settings['name']);
        $this->assertEquals($projectName, $settings['hostname']);
    }
}
