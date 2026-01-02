<?php
namespace Ascorak\Faker\Api\Command;

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
     * @param string $code
     * @return array
     */
    public function getConfig(string $code): array;
}