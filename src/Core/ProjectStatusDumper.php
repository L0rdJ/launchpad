<?php
/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license   For full copyright and license information view LICENSE file distributed with this source code.
 */

namespace eZ\Launchpad\Core;

use eZ\Launchpad\Configuration\Project as ProjectConfiguration;
use eZ\Launchpad\Core\Client\Docker;
use Novactive\Collection\Collection;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class ProjectStatusDumper.
 */
class ProjectStatusDumper
{
    /**
     * @var ProjectConfiguration
     */
    protected $projectConfiguration;

    /**
     * @var Docker
     */
    protected $dockerClient;

    /**
     * @var SymfonyStyle
     */
    protected $io;

    /**
     * ProjectStatusDumper constructor.
     *
     * @param ProjectConfiguration $projectConfiguration
     */
    public function __construct(ProjectConfiguration $projectConfiguration)
    {
        $this->projectConfiguration = $projectConfiguration;
    }

    /**
     * @param Docker $dockerClient
     */
    public function setDockerClient($dockerClient)
    {
        $this->dockerClient = $dockerClient;
    }

    /**
     * @param SymfonyStyle $io
     */
    public function setIo($io)
    {
        $this->io = $io;
    }

    /**
     * @param Collection|string|array $options
     */
    public function dump($options)
    {
        if (is_string($options)) {
            $options = str_split($options);
        }
        if (is_array($options)) {
            $options = NovaCollection($options);
        }
        if ($options->contains('n')) {
            $this->dumpNetwork();
        }
        if ($options->contains('c')) {
            $this->dumpComposeCommand();
        }
        if ($options->contains('i')) {
            $this->dumpFirstTime();
        }
        if ($options->contains('s')) {
            $this->dumpServiceAccess();
        }
    }

    /**
     * Dump Network.
     */
    protected function dumpNetwork()
    {
        $this->io->title('Docker Network');
        $this->dockerClient->ps();
    }

    /**
     * Dump Compose command.
     */
    protected function dumpComposeCommand()
    {
        $composeCommand = $this->dockerClient->getComposeCommand();
        $this->io->title("\nDocker Compose command");
        $this->io->writeln($composeCommand);
    }

    /**
     * Dump Human service acccess.
     */
    protected function dumpServiceAccess()
    {
        $portPrefix = $this->projectConfiguration->get('docker.network_prefix_port');
        $dump       = function ($title, $port, $suffix = '', $proto = 'http') use ($portPrefix) {
            $this->io->writeln(
                "<fg=white;options=bold>{$title}: </>".
                str_pad('', 1, "\t").
                "{$proto}://localhost:<fg=white;options=bold>{$portPrefix}{$port}</>{$suffix}"
            );
        };
        $this->io->title('Service Access');
        $services = $this->projectConfiguration->getDockerCompose()->getServices();
        if (isset($services['nginx'])) {
            $this->io->section('Project Web Access');
            $dump('Nginx - Front-end (Development Mode)', '080');
            $dump('Nginx - Back-end (Development Mode)', '080', '/ez');

            if (isset($services['varnish'])) {
                $dump('Varnish - Front-end (Production Mode)', '082');
                $dump('Varnish - Back-end (Production Mode)', '082', '/ez');
            } else {
                $dump('Nginx - Front-end (Production Mode)', '081');
                $dump('Nginx - Back-end (Production Mode)', '081', '/ez');
            }
        }

        if (isset($services['db'])) {
            $this->io->section('Database Access');
            $dump('MariaDB', '306', '', 'tcp');
        }

        if (isset($services['solr'])) {
            $this->io->section('Solr Access');
            $dump('Solr', '983');
        }

        if (isset($services['mailcatcher']) || isset($services['adminer']) || isset($services['memcachedadmin'])) {
            $this->io->section('Tools');
            if (isset($services['mailcatcher'])) {
                $dump('Mailcatcher', '180');
            }
            if (isset($services['adminer'])) {
                $dump('Adminer', '084');
            }
            if (isset($services['memcachedadmin'])) {
                $dump('Memcache Admin', '083');
            }
        }
    }

    /**
     * Dump first time stuff.
     */
    protected function dumpFirstTime()
    {
        $this->io->title("\033[2J\033[0;0HWelcome in eZ Launchpad!");
        $this->io->writeln(
            "Your project environment is now up and running. You have eZ Platform installed and running.\n".
            'All the information are define in the section <comment>Service Access</comment> below.'
        );

        $this->io->section('Code and CVS');
        $this->io->writeln(
            "You will find the folder <comment>ezplatform</comment> which contains eZ Platform.\n".
            "The <comment>provisioning</comment> folder contains your local stack specifics.\n".
            "<comment>.gitignore</comment> have been provided then you can commit everything right now.\n"
        );

        $this->io->section('Dump and storage');
        $this->io->writeln(
            "Once intialized you can dump the database and the storage to include it in your repository.\n".
            '<comment>~ez dumpdata</comment> will create a <comment>data</comment> folder containing'.
            ' an archive for each (db.sql.gz and storage.tar.gz).'
        );

        $this->io->section('Sharing');
        $this->io->writeln(
            "Once commited and pushed, your collaborators can just <comment>git pull</comment> to get the project\n".
            "And then run <comment>~/ez create</comment> to duplicate the environment.\n"
        );
    }
}
