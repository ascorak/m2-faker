<?php

namespace Ascorak\Faker\Model\Faker;

use Ascorak\Faker\Api\Command\ConfigProviderInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractConfigProvider implements ConfigProviderInterface
{
    /** @var OutputInterface|null $output */
    protected ?OutputInterface $output = null;

    /** @var InputInterface|null $input */
    protected ?InputInterface $input = null;

    /**
     * @inheritDoc
     */
    public function setOutput(OutputInterface $output): ConfigProviderInterface
    {
        $this->output = $output;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function setInput(InputInterface $input): ConfigProviderInterface
    {
        $this->input = $input;
        return $this;
    }

    /**
     * @inheritDoc
     */
    abstract public function getConfig(string $code): array;
}