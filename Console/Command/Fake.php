<?php
namespace Ascorak\Faker\Console\Command;

use Ascorak\Faker\Api\FakerProviderInterface;
use Ascorak\Faker\Model\FakerProvider;
use Ascorak\Faker\Model\Command\ConfigProviderStrategy;
use Exception;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @author Alexandre Granjeon <alexandre.granjeon@gmail.com>
 */
class Fake extends Command
{
    public const COMMAND_NAME = "ascorak:fake:generate";
    public const CODE_ARGUMENT = 'code';
    public const NUMBER_ARGUMENT = 'number';

    /**
     * Fake constructor
     *
     * @param FakerProviderInterface $fakerProvider
     * @param ConfigProviderStrategy $configProviderStrategy
     * @param State $appState
     */
    public function __construct(
        private FakerProviderInterface $fakerProvider,
        private ConfigProviderStrategy $configProviderStrategy,
        private State $appState,
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName(self::COMMAND_NAME);
        $this->setDescription(__('Generate fake data'));
        $this->addArgument(
            self::CODE_ARGUMENT,
            InputArgument::REQUIRED,
            __('Codes of fake data to generated, separated by commas ("all" to generate all types)')
        );
        $this->addArgument(
            self::NUMBER_ARGUMENT,
            InputArgument::REQUIRED,
            __('Number of fake data to generate')
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (Exception $e) {
            $io->error(__('Something went wrong setting area code: %1', $e->getMessage()));
            return Command::FAILURE;
        }

        if ($this->appState->getMode() == State::MODE_PRODUCTION) {
            $io->error(__('You can\'t add fake data while un production mode.'));
            return Command::FAILURE;
        }

        $requestedCodes = $input->getArgument(self::CODE_ARGUMENT);
        if ($requestedCodes === 'all') {
            $requestedCodes = $this->fakerProvider->getFakerCodes();
        } else {
            $requestedCodes = array_intersect(array_map('trim', explode(',', $requestedCodes)), $this->fakerProvider->getFakerCodes());
        }

        if (empty($requestedCodes)) {
            $io->error(__('No code given.'));
        }

        foreach ($requestedCodes as $fakerCode) {
            $fakerConfig = $this->configProviderStrategy->getConfigProvider($fakerCode);
            $fakerConfig->setInput($input)->setOutput($output);
            $faker = $this->fakerProvider->getFaker($fakerCode);
            $errors = $faker->generateFakeData($fakerConfig->getConfig(), $io);
            $io->newLine(2);
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $io->error($error);
                }

                $io->newLine(2);
            }
        }

        $io->newLine(2);
        $io->success('Fake data has been successfully generated');
        return Command::SUCCESS;
    }
}
