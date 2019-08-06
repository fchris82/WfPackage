<?php declare(strict_types=1);
/**
 * Created by IntelliJ IDEA.
 * User: chris
 * Date: 2018.03.27.
 * Time: 16:55
 */

namespace App\Wf\BaseSystemRecipesBundle\SystemRecipes\Commands;

use Wf\DockerWorkflowBundle\Event\Configuration\BuildInitEvent;
use Wf\DockerWorkflowBundle\Event\ConfigurationEvents;
use Wf\DockerWorkflowBundle\Event\RegisterEventListenersInterface;
use Wf\DockerWorkflowBundle\Recipes\BaseRecipe;
use Wf\DockerWorkflowBundle\Recipes\SystemRecipe;
use Wf\DockerWorkflowBundle\Skeleton\FileType\ExecutableSkeletonFile;
use Wf\DockerWorkflowBundle\Skeleton\FileType\SkeletonFile;
use Wf\DockerWorkflowBundle\Skeleton\SkeletonHelper;
use Wf\DockerWorkflowBundle\Skeleton\TemplateTwigFileInfo;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CommandsRecipe extends SystemRecipe implements RegisterEventListenersInterface
{
    const NAME = 'commands';

    /**
     * @var array
     */
    protected $globalConfig;

    public function getName(): string
    {
        return static::NAME;
    }

    public function getConfig(): NodeDefinition
    {
        $rootNode = BaseRecipe::getConfig();

        $rootNode
            ->info('<comment>You can add extra <info>commands</info>.</comment>')
            ->useAttributeAsKey('command')
            ->variablePrototype()->end()
        ;

        return $rootNode;
    }

    public function registerEventListeners(EventDispatcherInterface $eventDispatcher): void
    {
        $eventDispatcher->addListener(ConfigurationEvents::BUILD_INIT, [$this, 'init']);
    }

    public function init(BuildInitEvent $event): void
    {
        $this->globalConfig = $event->getConfig();
    }

    /**
     * @param $templateVars
     * @param array $buildConfig
     *
     * @throws \Exception
     * @throws \ReflectionException
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     *
     * @return SkeletonFile[]|array
     */
    protected function buildSkeletonFiles(array $templateVars, array $buildConfig = []): array
    {
        // Start creating .sh files
        $tmpSkeletonFileInfo = $this->getTempSkeletonFileInfo('bin.sh');

        // Collect the skeleton files
        $skeletonFiles = [];
        // Collect the targets for makefile
        $makefileTargets = [];
        foreach ($this->globalConfig[static::NAME] as $commandName => $commands) {
            $commandTemplateVars = $templateVars;
            $commandTemplateVars[static::NAME] = $commands;
            $skeletonFile = $this->createSkeletonFile($tmpSkeletonFileInfo, $commandName, $commandTemplateVars);
            $skeletonFiles[] = $skeletonFile;
            $makefileTargets[$commandName] = $skeletonFile->getRelativePathname();
        }

        // Create makefile
        $templateVars['makefileTargets'] = $makefileTargets;
        $skeletonFiles = array_merge($skeletonFiles, parent::buildSkeletonFiles($templateVars, $buildConfig));

        return $skeletonFiles;
    }

    /**
     * Create an ExecutableSkeletonFile from a template FileInfo and other parameters.
     *
     * @param TemplateTwigFileInfo $tmpFileInfo
     * @param string               $commandName
     * @param array                $templateVars
     *
     * @throws \Exception
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     *
     * @return ExecutableSkeletonFile
     */
    protected function createSkeletonFile(TemplateTwigFileInfo $tmpFileInfo, string $commandName, array $templateVars): ExecutableSkeletonFile
    {
        $fileName = $commandName . '.sh';
        $newSplFileInfo = new SplFileInfo($fileName, '', $fileName);
        $templateContent = $this->parseTemplateFile(
            $tmpFileInfo,
            $templateVars
        );
        $outputFormatter = new OutputFormatter(true);
        $skeletonFile = new ExecutableSkeletonFile($newSplFileInfo);
        $skeletonFile->setContents($outputFormatter->format($templateContent));

        return $skeletonFile;
    }

    /**
     * @param string $tempFile the template filename
     *
     * @throws \ReflectionException
     *
     * @return TemplateTwigFileInfo
     */
    protected function getTempSkeletonFileInfo($tempFile): TemplateTwigFileInfo
    {
        $refClass = new \ReflectionClass($this);
        $skeletonsPath = \dirname($refClass->getFileName()) . \DIRECTORY_SEPARATOR . SkeletonHelper::TEMPLATES_DIR;
        $tmpFileInfo = new TemplateTwigFileInfo(
            $skeletonsPath . \DIRECTORY_SEPARATOR . $tempFile,
            '',
            $tempFile,
            SkeletonHelper::generateTwigNamespace(new \ReflectionClass($this))
        );

        return $tmpFileInfo;
    }
}
