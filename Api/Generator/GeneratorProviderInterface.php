<?php

namespace Ascorak\Faker\Api\Generator;

interface GeneratorProviderInterface
{
    /**
     * @param string $code
     * @return EntityGeneratorInterface|null
     */
    public function getGenerator(string $code): ?EntityGeneratorInterface;
}