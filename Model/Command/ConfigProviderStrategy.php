<?php

namespace Ascorak\Faker\Model\Command;

use Ascorak\Faker\Api\Command\ConfigProviderInterface;
use Ascorak\Faker\Console\Command\Fake;
use InvalidArgumentException;

class ConfigProviderStrategy
{

    /**
     * @param ConfigProviderInterface[] $configProviders
     */
    public function __construct(
        private array $configProviders
    ) {
        foreach ($this->configProviders as $provider) {
            if (!($provider instanceof ConfigProviderInterface)) {
                throw new InvalidArgumentException(sprintf('The config provider "%s" must implement "%s"', get_class($provider), ConfigProviderInterface::class));
            }
        }
    }

    /**
     * @param string $code
     * @return ConfigProviderInterface
     */
    public function getConfigProvider(string $code): ConfigProviderInterface
    {
        if (!isset($this->configProviders[$code])) {
            throw new InvalidArgumentException(sprintf('The config provider "%s" does not exist', $code));
        }

        return $this->configProviders[$code];
    }
}
