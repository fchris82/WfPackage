<?php declare(strict_types=1);

namespace App\Command;

use Wf\DockerWorkflowBundle\Configuration\Builder;
use Wf\DockerWorkflowBundle\Configuration\Configuration;
use Wf\DockerWorkflowBundle\Configuration\RecipeManager;
use Wf\DockerWorkflowBundle\Environment\IoManager;
use Wf\DockerWorkflowBundle\Event\Configuration\BuildInitEvent;
use Wf\DockerWorkflowBundle\Event\Configuration\VerboseInfoEvent;
use Wf\DockerWorkflowBundle\Event\ConfigurationEvents;
use Wf\DockerWorkflowBundle\Event\SkeletonBuild\DumpFileEvent;
use Wf\DockerWorkflowBundle\Event\SkeletonBuildBaseEvents;
use Wf\DockerWorkflowBundle\Exception\InvalidWfVersionException;
use Wf\DockerWorkflowBundle\Exception\MissingRecipeException;
use Wf\DockerWorkflowBundle\Recipes\BaseRecipe;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Class ConfigYamlReaderCommand.
 */
class ReconfigureCommand extends Command
{
    const DEFAULT_CONFIG_FILE = '.wf.yml';
    const DEFAULT_TARGET_DIRECTORY = '.wf';

    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @var Builder
     */
    protected $builder;

    /**
     * @var RecipeManager
     */
    protected $recipeManager;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var IoManager
     */
    protected $ioManager;

    /**
     * ReconfigureCommand constructor.
     *
     * @param Configuration            $configuration
     * @param Builder                  $builder
     * @param RecipeManager            $recipeManager
     * @param EventDispatcherInterface $eventDispatcher
     * @param IoManager                $ioManager
     */
    public function __construct(
        Configuration $configuration,
        Builder $builder,
        RecipeManager $recipeManager,
        EventDispatcherInterface $eventDispatcher,
        IoManager $ioManager
    ) {
        $this->configuration = $configuration;
        $this->builder = $builder;
        $this->recipeManager = $recipeManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->ioManager = $ioManager;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('app:config')
            ->setDescription('Project config init. Generate the "cache" files.')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Set config file name.', self::DEFAULT_CONFIG_FILE)
            ->addOption('target-directory', null, InputOption::VALUE_REQUIRED, 'Set the build target.', self::DEFAULT_TARGET_DIRECTORY)
            ->addOption('config-hash', null, InputOption::VALUE_REQUIRED, 'Set the config hash')
            ->addOption('wf-version', null, InputOption::VALUE_REQUIRED, 'Set the current WF version')
            ->addArgument('base', InputArgument::OPTIONAL, 'The working directory', $_SERVER['PWD'])
        ;
    }

    /**
     * {@inheritdoc}
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @throws \ReflectionException
     * @throws \Symfony\Component\Config\Exception\FileLoaderImportCircularReferenceException
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $baseDirectory = $input->getArgument('base');
            $config = $this->configuration->loadConfig($input->getOption('file'), $baseDirectory, $input->getOption('wf-version'));

            $this->registerEventListeners();

            try {
                $this->builder
                    ->setTargetDirectoryName($input->getOption('target-directory'))
                    ->build($config, $baseDirectory, $input->getOption('config-hash'))
                ;

                $output->writeln('<info>The (new) docker environment was build!</info>');
            } catch (MissingRecipeException $e) {
                // It is maybe an impossible exception, but it will throw we catch it.
                $output->writeln('<comment>' . $e->getMessage() . '</comment>');
                $output->writeln('The available recipes:');
                /** @var BaseRecipe $recipe */
                foreach ($this->recipeManager->getRecipes() as $recipe) {
                    $output->writeln(sprintf('  - <info>%s</info> @%s', $recipe->getName(), \get_class($recipe)));
                }
            }
        } catch (InvalidWfVersionException $e) {
            // We write it formatted
            $output->writeln('');
            $output->writeln($e->getMessage());
            $output->writeln('');
        }
    }

    protected function writeTitle(OutputInterface $output, string $title, string $colorStyle = 'fg=white'): void
    {
        // 2 sort kihagyunk
        $output->writeln("\n");
        $output->writeln(sprintf('<%1$s>%2$s</%1$s>', $colorStyle, $title));
        $output->writeln(sprintf('<%1$s>%2$s</%1$s>', $colorStyle, str_repeat('=', \strlen(strip_tags($title)))));
        $output->writeln('');
    }

    /**
     * Registering event listeners.
     */
    protected function registerEventListeners(): void
    {
        if ($this->ioManager->getOutput()->isVerbose()) {
            $this->eventDispatcher->addListener(
                ConfigurationEvents::VERBOSE_INFO,
                [$this, 'verboseInfo']
            );
            $this->eventDispatcher->addListener(
                ConfigurationEvents::BUILD_INIT,
                [$this, 'parametersInfo'],
                -1000
            );
        }
        $this->eventDispatcher->addListener(
            SkeletonBuildBaseEvents::BEFORE_DUMP_FILE,
            [$this, 'insertGeneratedFileWarning']
        );
    }

    /**
     * Print verbose informations
     *
     * @param VerboseInfoEvent $event
     */
    public function verboseInfo(VerboseInfoEvent $event): void
    {
        $info = $event->getInfo();
        if (\is_array($info)) {
            $info = Yaml::dump($info, 4);
        }
        $this->ioManager->writeln($info);
    }

    public function parametersInfo(BuildInitEvent $event): void
    {
        $this->ioManager->getIo()->title('Replacing placeholders');
        $parameters = [];
        foreach ($event->getParameters() as $key => $value) {
            $parameters[] = ["<comment>$key</comment>", $value];
        }
        $this->ioManager->getIo()->table([
            'Parameter',
            'Value',
        ], $parameters);

        $this->ioManager->getIo()->block('Full configuration:');
        $baseConfig = Yaml::dump($event->getConfig(), 4);
        $this->ioManager->writeln($baseConfig);
    }

    /**
     * Add warnings to almost all configured file.
     *
     * @param DumpFileEvent $event
     */
    public function insertGeneratedFileWarning(DumpFileEvent $event): void
    {
        $skeletonFile = $event->getSkeletonFile();
        $warning = sprintf(
            'This is an auto generated file from `%s` config file! You shouldn\'t edit this.',
            $this->ioManager->getInput()->getOption('file')
        );
        $ext = pathinfo($skeletonFile->getFullTargetPathname(), PATHINFO_EXTENSION);

        $commentPattern = "# %s\n\n";
        switch ($ext) {
            case 'css':
                $commentPattern = "/* %s */\n\n";
                // no break
            case '':
            case 'yaml':
            case 'yml':
            case 'zsh':
            case 'bash':
                $newContents = sprintf($commentPattern, $warning) . $skeletonFile->getContents();
                $skeletonFile->setContents($newContents);
        }
    }
}
