<?php
namespace Ascorak\Faker\Model;

use Ascorak\Faker\Api\FakerInterface;
use Ascorak\Faker\Api\FakerProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use InvalidArgumentException;

/**
 * @author Grare Olivier <grare.o@gmail.com>
 */
class FakerProvider implements FakerProviderInterface
{
    /**
     * FakerProvider constructor
     *
     * @param array $fakerList
     */
    public function __construct(
        private readonly array $fakerList = []
    ) {
        foreach ($this->fakerList as $faker) {
            if (!$faker instanceof FakerInterface) {
                throw new InvalidArgumentException(__('The faker %1 must implements %2', get_class($faker), FakerInterface::class));
            }
        }
    }

    /**
     * Retrieve a Faker object by its code
     *
     * @param string $code
     *
     * @return FakerInterface
     * @throws LocalizedException
     */
    public function getFaker(string $code): FakerInterface
    {
        if (!array_key_exists($code, $this->fakerList)) {
            throw new LocalizedException(__('Faker %1 does not exist', $code));
        }

        return $this->fakerList[$code];
    }

    /**
     * Retrieve all Faker objects
     *
     * @return FakerInterface[]
     */
    public function getFakers(): array
    {
        return $this->fakerList;
    }

    /**
     * @return array
     */
    public function getFakerCodes(): array
    {
        return array_keys($this->fakerList);
    }
}
