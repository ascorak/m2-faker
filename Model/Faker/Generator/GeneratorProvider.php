<?php

namespace Ascorak\Faker\Model\Faker\Generator;

use Ascorak\Faker\Api\Generator\EntityGeneratorInterface;
use Ascorak\Faker\Api\Generator\GeneratorProviderInterface;
use InvalidArgumentException;
use Magento\Framework\Exception\NoSuchEntityException;

class GeneratorProvider implements GeneratorProviderInterface
{
    /** @var EntityGeneratorInterface[] $generators */
    private array $generators;

    /**
     * @param EntityGeneratorInterface[] $generators
     * @throws InvalidArgumentException
     */
    public function __construct(array $generators = [])
    {
        foreach ($generators as $code => $generator) {
            if (!$generator instanceof EntityGeneratorInterface) {
                throw new InvalidArgumentException(
                    __('Generator with code "%1" must implement %2', $code, EntityGeneratorInterface::class)
                );
            }
        }
        $this->generators = $generators;
    }

    /**
     * @inheritDoc
     */
    public function getGenerator(string $code): EntityGeneratorInterface
    {
        if (!isset($this->generators[$code])) {
            throw new InvalidArgumentException(
                __('Generator with code "%1" does not exist.', $code)
            );
        }

        return $this->generators[$code];
    }
}
