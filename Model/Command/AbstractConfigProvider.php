<?php

namespace Ascorak\Faker\Model\Command;

use Ascorak\Faker\Api\Command\ConfigProviderInterface;
use Ascorak\Faker\Console\Command\Fake;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractConfigProvider implements ConfigProviderInterface
{
    const CONFIG_CODE = '';

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

    public function getConfigCode(): string
    {
        return  static::CONFIG_CODE;
    }

    /**
     * @inheritDoc
     */
    abstract public function getConfig(): array;

    /**
     * @param Fake $command
     * @return void
     */
    abstract public function applyConfig(Fake $command): void;
}
