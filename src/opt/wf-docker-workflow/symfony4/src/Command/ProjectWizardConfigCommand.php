<?php declare(strict_types=1);
/**
 * Created by IntelliJ IDEA.
 * User: chris
 * Date: 2018.11.13.
 * Time: 16:15
 */

namespace App\Command;

use Wf\DockerWorkflowBundle\Environment\IoManager;
use Wf\DockerWorkflowBundle\Wizard\Configuration;
use Wf\DockerWorkflowBundle\Wizard\ConfigurationItem;
use Wf\DockerWorkflowBundle\Wizard\Manager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class ProjectWizardConfigCommand extends Command
{
    const EXIT_SIGN = '🢤';
    const ENABLED_SIGN = '✓';
    const DISABLED_SIGN = '∅';

    /**
     * @var Manager
     */
    protected $wizardManager;

    /**
     * @var IoManager
     */
    protected $ioManager;

    /**
     * ProjectWizardConfigCommand constructor.
     *
     * @param Manager $wizardManager
     */
    public function __construct(Manager $wizardManager, IoManager $ioManager)
    {
        $this->wizardManager = $wizardManager;
        $this->ioManager = $ioManager;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('app:wizard:config')
            ->setDescription('Wizard collection configuration.');
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Symfony\Component\Console\Exception\RuntimeException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('');
        $output->writeln(' <comment>!> If the <question>CTRL-C</question> doesn\'t work, you can use the <question>^P + ^Q + ^C</question> (^ == CTRL).</comment>');
        $output->writeln('');

        $this->wizardManager->syncConfiguration();

        do {
            $this->renderSummaryTable();
            $summaryQuestion = $this->getSummaryQuestion();
            if ($selectedClass = $this->ioManager->ask($summaryQuestion)) {
                $this->editConfigItem($selectedClass);
            }
        } while ($selectedClass);

        if ($this->wizardManager->getConfiguration()->hasChanges()) {
            $doYouWantToSaveQuestion = new ConfirmationQuestion('There are some changes. Do you want to save them?', true);
            $wantToSave = $this->ioManager->ioAsk($doYouWantToSaveQuestion);
            if ($wantToSave) {
                $this->wizardManager->getConfiguration()->saveConfigurationList();
                $this->ioManager->getIo()->success('Wizard configuration is updated! (Default location on host: ~/.wf-docker-workflow/config/wizards.yml)');
            } else {
                $this->ioManager->getIo()->writeln('Nothing changed');
            }
        }
    }

    protected function getIcon(ConfigurationItem $configurationItem): string
    {
        if ($configurationItem->isEnabled()) {
            return static::ENABLED_SIGN;
        }

        return static::DISABLED_SIGN;
    }

    protected function getStyle(ConfigurationItem $configurationItem): ?string
    {
        if ($this->wizardManager->wizardIsNew($configurationItem)) {
            return 'info';
        }
        if ($this->wizardManager->wizardIsUpdated($configurationItem)) {
            return 'comment';
        }

        return null;
    }

    protected function renderSummaryTable(): void
    {
        $table = new Table($this->ioManager->getIo());
        $table->setHeaders([
            'Name',
            'Group',
            'Priority',
        ]);
        foreach ($this->wizardManager->getAllAvailableWizardItems() as $configurationItem) {
            $name = $configurationItem->getName();
            $icon = $this->getIcon($configurationItem);
            $style = $this->getStyle($configurationItem);
            $table->addRow([
                $style ? sprintf('<%1$s>%2$s %3$s</%1s>', $style, $icon, $name) : "$icon $name",
                $configurationItem->getGroup(),
                $configurationItem->getPriority(),
            ]);
        }

        // Missing/deleted, but has been configured wizards
        /** @var ConfigurationItem $configurationItem */
        foreach ($this->wizardManager->getConfiguration()->getChanges(Configuration::CHANGES_REMOVED) as $configurationItem) {
            $table->addRow([
                sprintf('<error>❌ %s</error>', $configurationItem->getClass()),
                $configurationItem->getGroup(),
                $configurationItem->getPriority(),
            ]);
        }
        $table->render();
    }

    protected function getSummaryQuestion(): ChoiceQuestion
    {
        $choices = [
            '' => '<comment>Exit</comment>',
        ];
        foreach ($this->wizardManager->getAllAvailableWizardItems() as $configurationItem) {
            $choices[$configurationItem->getClass()] = $configurationItem->getName();
        }
        $question = new ChoiceQuestion('Which one do you want to edit?', $choices, '');

        return $question;
    }

    protected function renderItemSummaryTable($class, OutputInterface $output): void
    {
        $table = new Table($output);
        $table->setHeaders([
            'Property',
            'Value',
        ]);
        $configurationItem = $this->wizardManager->getConfiguration()->get($class);
        $table->addRows([
            ['name', $configurationItem->getName()],
            ['class', $configurationItem->getClass()],
            ['group', $configurationItem->getGroup()],
            ['priority', $configurationItem->getPriority()],
        ]);
        $table->render();
    }

    protected function editConfigItem($wizardClass): void
    {
        $this->ioManager->clearScreen();
        $configurationItem = $this->wizardManager->getConfiguration()->get($wizardClass);
        $this->ioManager->getIo()->title($wizardClass);
        $originalContent = serialize($configurationItem);

        do {
            $priorityQuestion = new Question('Priority: ', $configurationItem->getPriority());
            $priorityQuestion->setValidator(function ($value) {
                if (!preg_match('/^\d*$/', $value)) {
                    throw new InvalidArgumentException(sprintf('The `%s` value is invalid at priority! You have to use only numbers!', $value));
                }

                return (int) $value;
            });
            $groupQuestion = new Question('Group: ', $configurationItem->getGroup());
            $groupQuestion->setAutocompleterValues($this->getAllExistingGroups());

            $config = [
                'name' => [
                    'question' => new Question('Name: ', $configurationItem->getName()),
                    'handle' => function (ConfigurationItem $configurationItem, $name) {
                        $configurationItem->setName($name);
                    },
                ],
                'group' => [
                    'question' => $groupQuestion,
                    'handle' => function (ConfigurationItem $configurationItem, $group) {
                        $configurationItem->setGroup($group);
                    },
                ],
                'priority' => [
                    'question' => $priorityQuestion,
                    'handle' => function (ConfigurationItem $configurationItem, $priority) {
                        $configurationItem->setPriority($priority);
                    },
                ],
                'enabled' => [
                    'question' => new ConfirmationQuestion('Wizard is enabled? ', $configurationItem->isEnabled()),
                    'handle' => function (ConfigurationItem $configurationItem, $enabled) {
                        $configurationItem->setEnabled($enabled);
                    },
                ],
            ];
            $questions = [
                static::EXIT_SIGN => '<comment>Go back</comment>',
            ];
            foreach ($config as $n => $item) {
                /** @var Question $itemQuestion */
                $itemQuestion = $item['question'];
                $label = $itemQuestion->getDefault();
                if (\is_bool($label)) {
                    $label = $label ? static::ENABLED_SIGN : static::DISABLED_SIGN;
                }
                $questions[$n] = (string) $label;
            }
            $question = new ChoiceQuestion('What do you want to change?', $questions, static::EXIT_SIGN);

            if ('🢤' != $change = $this->ioManager->ask($question)) {
                /** @var Question $question */
                $subQuestion = $config[$change]['question'];
                $newValue = $this->ioManager->ask($subQuestion);
                $config[$change]['handle']($configurationItem, $newValue);
            }
        } while ($change != static::EXIT_SIGN);

        // If something was changed
        if ($originalContent != serialize($configurationItem)) {
            $this->wizardManager->getConfiguration()->set($configurationItem);
        }

        $this->ioManager->clearScreen();
    }

    protected function getAllExistingGroups(): array
    {
        $existingGroups = [];
        foreach ($this->wizardManager->getConfiguration()->getConfigurationList() as $configurationItem) {
            $existingGroups[] = $configurationItem->getGroup();
        }
        array_unique($existingGroups);

        return $existingGroups;
    }
}
