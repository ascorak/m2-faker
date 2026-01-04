<?php
namespace Ascorak\Faker\Api;

interface FakerProviderInterface
{
    /**
     * @param string $code
     * @return FakerInterface
     */
    public function getFaker(string $code): FakerInterface;

    /**
     * @return array
     */
    public function getFakers(): array;

    /**
     * @return array
     */
    public function getFakerCodes(): array;
}
