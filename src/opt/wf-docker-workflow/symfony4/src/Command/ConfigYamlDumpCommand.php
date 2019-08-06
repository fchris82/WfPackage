<?php declare(strict_types=1);

namespace App\Command;

use Wf\DockerWorkflowBundle\Configuration\Configuration;
use Wf\DockerWorkflowBundle\Configuration\RecipeManager;
use Wf\DockerWorkflowBundle\Environment\IoManager;
use Wf\DockerWorkflowBundle\Recipes\BaseRecipe;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\Dumper\YamlReferenceDumper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ConfigYamlDumpCommand.
 */
class ConfigYamlDumpCommand extends Command
{
    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @var RecipeManager
     */
    protected $recipeManager;

    /**
     * @var IoManager
     */
    protected $ioManager;

    /**
     * ConfigYamlDumpCommand constructor.
     *
     * @param Configuration $configuration
     * @param RecipeManager $recipeManager
     * @param IoManager     $ioManager
     */
    public function __construct(Configuration $configuration, RecipeManager $recipeManager, IoManager $ioManager)
    {
        $this->configuration = $configuration;
        $this->recipeManager = $recipeManager;
        $this->ioManager = $ioManager;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('app:config-dump')
            ->setDescription('Project config dump. Use the <comment>--no-ansi</comment> argument if you want to put it into a file!')
            ->setHelp('Use the <info>--no-ansi</info> argument if you want to put it into a file!')
            ->addOption('recipe', null, InputOption::VALUE_OPTIONAL, 'You have to chose a recipe', null)
            ->addOption('only-recipes', null, InputOption::VALUE_NONE, 'List only recipes in a table. It isn\'t compatible with --recipe="[recipe name]" option!')
        ;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Symfony\Component\Console\Exception\RuntimeException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = $this->ioManager->getIo();
        // We show it if the user don't want to put it into a file!
        if ($io->isDecorated() && !$input->getOption('only-recipes')) {
            $io->title('All available parameters');
            $io->writeln('  Use the <info>--no-ansi</info> argument if you want to put it into a file!');
        }

        $dumper = new YamlReferenceDumper();

        if ($recipeNameOrClass = $input->getOption('recipe')) {
            $recipe = $this->getRecipeByNameOrClass($recipeNameOrClass);
            /** @var ArrayNode $rootNode */
            $rootNode = $recipe->getConfig();
            $ymlTree = $dumper->dumpNode($rootNode->getNode(true));
            // Add indent if we want to use this: wf --config-dump --recipe=php --no-ansi >> .wf.yml
            if (!$io->isDecorated()) {
                $ymlTree = preg_replace('/^[^\n]/m', '    $0', $ymlTree);
            }
            $io->write($ymlTree);
        } elseif ($input->getOption('only-recipes')) {
            /** @var ArrayNode $rootNode */
            $rootNode = $this->configuration->getConfigTreeBuilder()->buildTree();
            $recipeNodes = $rootNode->getChildren()['recipes']->getChildren();
            $headers = [
                'Root',
                'Info',
            ];
            $rows = [];
            /** @var ArrayNode $node */
            foreach ($recipeNodes as $node) {
                $rows[] = [
                    $node->getName(),
                    $node->getInfo(),
                ];
            }
            // Sort by root node name!
            usort($rows, function ($a, $b) {
                if ($a[0] == $b[0]) {
                    return 0;
                }

                return $a[0] < $b[0] ? -1 : 1;
            });
            $io->table($headers, $rows);
        } else {
            /** @var ArrayNode $rootNode */
            $rootNode = $this->configuration->getConfigTreeBuilder()->buildTree();
            // Show only the children
            foreach ($rootNode->getChildren() as $node) {
                $io->write($dumper->dumpNode($node));
            }
        }
    }

    /**
     * @param string $nameOrClass
     *
     * @return BaseRecipe
     */
    protected function getRecipeByNameOrClass($nameOrClass): BaseRecipe
    {
        foreach ($this->recipeManager->getRecipes() as $name => $recipe) {
            if (\get_class($recipe) == $nameOrClass
                || $recipe->getName() == $nameOrClass
            ) {
                return $recipe;
            }
        }

        throw new InvalidArgumentException(sprintf('Missing or wrong recipe: `%s`', $nameOrClass));
    }
}
