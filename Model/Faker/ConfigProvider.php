<?php

namespace Ascorak\Faker\Model\Faker;

use Ascorak\Faker\Model\Faker\AbstractConfigProvider;

class ConfigProvider extends AbstractConfigProvider
{

    /**
     * @param ConfigProviderInterface[] $configProviders
     */
    public function __construct(
        private array $configProviders
    ) {
        foreach ($this->configProviders as $provider) {
            if (!($provider instanceof ConfigProviderInterface)) {
                throw new \InvalidArgumentException(sprintf('The config provider "%s" must implement "%s"', get_class($provider), ConfigProviderInterface::class));
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function getConfig(string $code): array
    {
        if (!isset($this->configProviders[$code])) {
            throw new \InvalidArgumentException(sprintf('The config provider "%s" does not exist', $code));
        }

        return $this->configProviders[$code]
            ->setOutput($this->output)
            ->setInput($this->input)
            ->getConfig($code);
    }
}