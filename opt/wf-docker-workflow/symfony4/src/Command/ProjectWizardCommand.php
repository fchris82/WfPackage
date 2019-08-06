<?php declare(strict_types=1);

namespace App\Command;

use App\Helper\WordWrapper;
use Wf\DockerWorkflowBundle\Environment\IoManager;
use Wf\DockerWorkflowBundle\Exception\WizardSomethingIsRequiredException;
use Wf\DockerWorkflowBundle\Wizard\Manager;
use Wf\DockerWorkflowBundle\Wizards\BaseWizard;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Class ProjectWizardCommand.
 */
class ProjectWizardCommand extends Command
{
    /**
     * @var Manager
     */
    protected $wizardManager;

    /**
     * @var IoManager
     */
    protected $ioManager;

    /**
     * ProjectWizardCommand constructor.
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
            ->setName('app:wizard')
            ->addOption('wf-version', null, InputOption::VALUE_REQUIRED, 'Set the current WF version')
            ->addOption('target-dir', null, InputOption::VALUE_OPTIONAL, 'The working directory.', $_SERVER['PWD'])
            ->addOption('force', null, InputOption::VALUE_NONE, 'If it is set, the program won\'t check the requires, and you can use all available wizards.')
            ->addOption('full', null, InputOption::VALUE_NONE, 'If it is set, the program will list all installed wizards! Include disableds too.')
            ->setDescription('Wizard collection handler command.')
            ->setHelp(<<<EOS
You can run wizards. You can enable or disable wizards with the <comment>wizard --config</comment> command. The <comment>wizard</comment> and
the <comment>wizard --config</comment> command are aliases:
    <info>wizard</info>             <comment>php bin/console app:wizard</comment> 
    <info>wizard --config</info>    <comment>php bin/console app:wizard:config</comment>    See: <comment>wizard --config --help</comment>

Examples:
    <comment>wizard --full</comment>
    List all installed wizards, the disabled wizards too.

    <comment>wizard --force</comment>
    List all <info>enabled</info> wizards without requires or built check.

    <comment>wizard --force --full</comment>
    List all installed wizards. 

    <comment>wizard --config</comment>
    You can configure your installed wizards. You can change the visible <info>names</info>, <info>groups</info>, <info>priority</info>
    and <info>availability</info> (enabled/disabled). 

    <comment>wizard <fg=cyan>--dev</></comment>
    The <fg=cyan>--dev</> switch develop debug mode on. 

    <comment>wizard <fg=cyan>--dev</> --config</comment>
    Run config command with develop debug mode.
EOS
        );
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Symfony\Component\Console\Exception\RuntimeException
     * @throws \Wf\DockerWorkflowBundle\Exception\WizardHasAlreadyBuiltException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // ---------------------------------------- DEFAULT INFORMATION ------------------------------------------------

        $output->writeln('');
        $output->writeln(' <comment>!> If the <question>CTRL-C</question> doesn\'t work, you can use the <question>^P + ^Q + ^C</question> (^ == CTRL).</comment>');
        $output->writeln(' <comment>!> You can edit the enabled wizards and sort order with the <info>wizard --config</info> command.</comment>');

        $targetProjectDirectory = $input->getOption('target-dir');
        $isForce = $input->getOption('force');
        $isFull = $input->getOption('full');

        $enabledWizards = $isFull
            ? $this->wizardManager->getAllAvailableWizardItems()
            : $this->wizardManager->getAllEnabledWizardItems();

        if (0 == \count($enabledWizards)) {
            $this->writeNote('There isn\'t any installed/enabled wizard! The program exited.');

            return;
        }

        $this->ioManager->writeln($this->createBlock(
            $output,
            'You can see information about all available wizards with <info>h</info>. If the name is <comment>yellow</comment> the program is unavailable here. The reason would be there are missing requires OR it has already built/run. Use the <comment>--force</comment> option to disable this check. You can read more information with <comment>wizard --help</comment> command.',
            null,
            null,
            ' ',
            true,
            false
        ));

        // --------------------------------------------- BUILD LIST ----------------------------------------------------

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $choices = [];
        $wizardChoices = [];
        $information = [];
        foreach ($enabledWizards as $configurationItem) {
            $wizardHelp = [];
            /** @var BaseWizard $wizard */
            $wizard = $this->wizardManager->getWizard($configurationItem->getClass());
            $missingRequires = false;
            $built = false;
            if (!$isForce) {
                try {
                    if (!$wizard->checkRequires($targetProjectDirectory)) {
                        throw new WizardSomethingIsRequiredException('Some requires missing');
                    }
                    if ($wizard->isBuilt($targetProjectDirectory)) {
                        $built = true;
                    }
                } catch (WizardSomethingIsRequiredException $e) {
                    $missingRequires = $e->getMessage();
                }
            }
            $groupPrefix = $configurationItem->getGroup() ?
                sprintf('<comment>[%s]</comment> ', $configurationItem->getGroup()) :
                '';
            $wizardHelp[] = sprintf(
                '  <%1$s>%2$s%3$s</%1$s>',
                $built || $missingRequires ? 'comment' : 'fg=green;options=bold',
                $groupPrefix,
                $configurationItem->getName()
            );
            $wizardHelp[] = '  ' . str_repeat('-', mb_strlen(strip_tags($groupPrefix . $configurationItem->getName())));
            if ($missingRequires) {
                $wizardHelp[] = implode(PHP_EOL, $this->createBlock($output, $missingRequires, null, 'fg=blue', '    <fg=yellow;options=bold>[!]</> '));
            }
            if ($built) {
                $wizardHelp[] = implode(PHP_EOL, $this->createBlock($output, 'This command was used here! You can\'t run again.', null, 'fg=blue', '    <comment>[!]</comment> '));
            }
            $wizardHelp[] = implode(PHP_EOL, $this->createBlock($output, $wizard->getInfo(), null, null, '    '));
            $information[] = implode(PHP_EOL, $wizardHelp);

            if (!$missingRequires && !$built) {
                $key = sprintf('%s%s', $groupPrefix, $configurationItem->getName());
                $choices[] = $key;
                $wizardChoices[] = $wizard;
            }
        }

        // ----------------------------------------- PRINT CHOICES -----------------------------------------------------

        if (\count($choices) > 0) {
            $countUnavailable = \count($enabledWizards) - \count($choices);
            if ($countUnavailable > 0) {
                $pattern = 1 == $countUnavailable
                    ? ' - <options=bold>There is <comment>%d</comment> not available command</>'
                    : ' - <options=bold>There are <comment>%d</comment> not available commands</>'
                ;
                $listSuffix = sprintf($pattern, $countUnavailable);
            } else {
                $listSuffix = '';
            }
            $question = new ChoiceQuestion('Select wizard (multiselect!)', array_merge(
                ['q' => 'Quit', 'h' => 'List commands information' . $listSuffix],
                $choices
            ), 'q');
            // @todo (Chris) Ezt lehet, hogy törölni kellene, mivel néhány Wizard ütközhet, ha egymás után hívjuk.
            $question->setMultiselect(true);
            // @todo (Chris) Szar a defaultValidator, az ugyanis összecsukja a szóközöket, azonban ellenőrzésnél nem tesz így, így a szóköz nélküli értéket nem találja a tömbben.
            $question->setAutocompleterValues(null);
            $io = $this->ioManager->getIo();
            while (['h'] == $selected = $helper->ask($input, $output, $question)) {
                $io->newLine();
                $io->title('Command list & information');
                $io->writeln(implode("\n\n", $information));
                $io->newLine();
                $io->newLine();
            }

            // -------------------------------------- RUN SELECTED -----------------------------------------------------
            if (['q'] != $selected) {
                // BUILDS
                foreach ($selected as $key) {
                    /** @var BaseWizard $wizard */
                    $wizard = $wizardChoices[(int) $key];

                    $io->title($choices[(int) $key]);
                    $output->writeln($wizard->getInfo());

                    $targetProjectDirectory = $wizard->runBuild($targetProjectDirectory);
                }
            } else {
                $io->writeln('Quit');
            }
        } else {
            $this->writeNote('There isn\'t any callable wizard! The program exited. You can use the `--force` or `--full` arguments.');
        }
    }

    protected function writeNote(string $note, string $colorStyle = 'fg=white;bg=yellow;options=bold'): void
    {
        $this->ioManager->getIo()->block($note, 'NOTE', $colorStyle, ' ', true);
    }

    /**
     * @param OutputInterface $output
     * @param string          $message
     * @param string|null     $type
     * @param string|null     $style
     * @param string          $prefix
     * @param bool            $padding
     * @param bool            $escape
     *
     * @return array|string[]
     *
     * @todo (Chris) Az itt megvalósított, WordWrapper-rel kivitelezett megoldást implementálni a Symfony repo-ba. Itt: \Symfony\Component\Console\Style\SymfonyStyle::createBlock() kell a wordwrap()-ot lecserélni.
     */
    protected function createBlock(
        OutputInterface $output,
        string $message,
        string $type = null,
        string $style = null,
        string $prefix = ' ',
        bool $padding = false,
        bool $escape = false
    ): array {
        $indentLength = 0;
        $prefixLength = Helper::strlenWithoutDecoration($output->getFormatter(), $prefix);

        if (null !== $type) {
            $type = sprintf('[%s] ', $type);
            $indentLength = mb_strlen($type);
            $lineIndentation = str_repeat(' ', $indentLength);
        }
        if ($escape) {
            $message = OutputFormatter::escape($message);
        }

        $ww = new WordWrapper(120 - $prefixLength - $indentLength, PHP_EOL);
        $lines = explode(PHP_EOL, $ww->formattedStringWordwrap($message, true));

        $firstLineIndex = 0;
        if ($padding) {
            $firstLineIndex = 1;
            array_unshift($lines, '');
            $lines[] = '';
        }

        foreach ($lines as $i => &$line) {
            if (null !== $type) {
                $line = $firstLineIndex === $i ? $type . $line : $lineIndentation . $line;
            }

            $line = $prefix . $line;
            $line .= str_repeat(' ', 120 - Helper::strlenWithoutDecoration($output->getFormatter(), $line));

            if ($style) {
                $line = sprintf('<%s>%s</>', $style, $line);
            }
        }

        return $lines;
    }
}
