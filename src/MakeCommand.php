<?php

namespace StarCorp\Lemp;

use StarCorp\Lemp\Settings\JsonSettings;
use StarCorp\Lemp\Traits\GeneratesSlugs;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeCommand extends Command
{
    use GeneratesSlugs;

    /**
     * The base path of the StarCorp installation.
     *
     * @var string
     */
    protected $basePath;

    /**
     * The name of the project folder.
     *
     * @var string
     */
    protected $projectName;

    /**
     * Sluggified Project Name.
     *
     * @var string
     */
    protected $defaultProjectName;

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this->basePath = getcwd();
        $this->projectName = basename($this->basePath);
        $this->defaultProjectName = $this->slug($this->projectName);

        $this
            ->setName('make')
            ->setDescription('Install Server into the current project')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'The name of the virtual machine.', $this->defaultProjectName)
            ->addOption('hostname', null, InputOption::VALUE_OPTIONAL, 'The hostname of the virtual machine.', $this->defaultProjectName)
            ->addOption('ip', null, InputOption::VALUE_OPTIONAL, 'The IP address of the virtual machine.')
            ->addOption('example', null, InputOption::VALUE_NONE, 'Determines if a Server example file is created.')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Determines if the Server settings file will be in json format.');
    }

    /**
     * Execute the command.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        if (! $this->vagrantfileExists()) {
            $this->createVagrantfile();
        }

        $format = 'json';

        if (! $this->settingsFileExists($format)) {
            $this->createSettingsFile($format, [
                'name' => $input->getOption('name'),
                'hostname' => $input->getOption('hostname'),
                'ip' => $input->getOption('ip'),
            ]);
        }

        if ($input->getOption('example') && ! $this->exampleSettingsExists($format)) {
            $this->createExampleSettingsFile($format);
        }

        $output->writeln('Lemp Installed!');
    }

    /**
     * Determines if Server has been installed "per project".
     *
     * @return bool
     */
    protected function isPerProjectInstallation()
    {
        return (bool) preg_match('/vendor\/starcorp\/lemp/', __DIR__);
    }

    /**
     * Determine if the Vagrantfile exists.
     *
     * @return bool
     */
    protected function vagrantfileExists()
    {
        return file_exists("{$this->basePath}/Vagrantfile");
    }

    /**
     * Create a Vagrantfile.
     *
     * @return void
     */
    protected function createVagrantfile()
    {
        copy(__DIR__.'/../resources/localized/Vagrantfile', "{$this->basePath}/Vagrantfile");
    }

    /**
     * Determine if the settings file exists.
     *
     * @param  string  $format
     * @return bool
     */
    protected function settingsFileExists($format)
    {
        return file_exists("{$this->basePath}/Server.{$format}");
    }

    /**
     * Create the server settings file.
     *
     * @param  string  $format
     * @param  array  $options
     * @return void
     */
    protected function createSettingsFile($format, $options)
    {
        $SettingsClass = JsonSettings::class;

        $filename = $this->exampleSettingsExists($format) ?
            "{$this->basePath}/Server.{$format}.example" :
            __DIR__."/../resources/Server.{$format}";

        $settings = $SettingsClass::fromFile($filename);

        if (! $this->exampleSettingsExists($format)) {
            $settings->updateName($options['name'])
                ->updateHostname($options['hostname']);
        }

        $settings->updateIpAddress($options['ip'])
            ->configureSites($this->projectName, $this->defaultProjectName)
            ->configureSharedFolders($this->basePath, $this->defaultProjectName)
            ->save("{$this->basePath}/Server.{$format}");
    }

    /**
     * Determine if the example settings file exists.
     *
     * @param  string  $format
     * @return bool
     */
    protected function exampleSettingsExists($format)
    {
        return file_exists("{$this->basePath}/Server.{$format}.example");
    }

    /**
     * Create the server settings example file.
     *
     * @param  string  $format
     * @return void
     */
    protected function createExampleSettingsFile($format)
    {
        copy("{$this->basePath}/Server.{$format}", "{$this->basePath}/Server.{$format}.example");
    }
}
