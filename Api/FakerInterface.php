<?php
namespace Ascorak\Faker\Api;

use Ascorak\Faker\Api\Command\ConfigProviderInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @author Grare Olivier <grare.o@gmail.com>
 */
interface FakerInterface
{
    /**
     * @param ConfigProviderInterface $configProvider
     * @param SymfonyStyle $io
     * @return void
     */
    public function generateFakeData(ConfigProviderInterface $configProvider, SymfonyStyle $io): void;
}
