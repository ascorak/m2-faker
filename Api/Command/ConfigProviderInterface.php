<?php
namespace Ascorak\Faker\Api\Command;

use Ascorak\Faker\Console\Command\Fake;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

interface ConfigProviderInterface
{
    /**
     * @param OutputInterface $output
     * @return ConfigProviderInterface
     */
    public function setOutput(OutputInterface $output): ConfigProviderInterface;

    /**
     * @param InputInterface $input
     * @return ConfigProviderInterface
     */
    public function setInput(InputInterface $input): ConfigProviderInterface;

    /**
     * @return string
     */
    public function getConfigCode(): string;

    /**
     * @return array
     */
    public function getConfig(): array;

    /**
     * @param Fake $command
     * @return void
     */
    public function applyConfig(Fake $command): void;
}
