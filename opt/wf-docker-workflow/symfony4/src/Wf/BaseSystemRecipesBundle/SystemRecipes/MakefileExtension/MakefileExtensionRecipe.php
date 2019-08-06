<?php declare(strict_types=1);
/**
 * Created by IntelliJ IDEA.
 * User: chris
 * Date: 2018.03.27.
 * Time: 11:31
 */

namespace App\Wf\BaseSystemRecipesBundle\SystemRecipes\MakefileExtension;

use Wf\DockerWorkflowBundle\Event\SkeletonBuild\PostBuildSkeletonFilesEvent;
use Wf\DockerWorkflowBundle\Recipes\BaseRecipe;
use Wf\DockerWorkflowBundle\Recipes\SystemRecipe;
use Wf\DockerWorkflowBundle\Skeleton\FileType\MakefileSkeletonFile;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Class Recipe
 *
 * You can insert Docker Compose configuration in the project config file:
 * <code>
 *  makefile:
 *      - ~/dev.mk
 * <code>
 */
class MakefileExtensionRecipe extends SystemRecipe
{
    const NAME = 'makefile';

    public function getName(): string
    {
        return static::NAME;
    }

    public function getConfig(): NodeDefinition
    {
        $rootNode = BaseRecipe::getConfig();

        $rootNode
            ->info('<comment>You can add extra <info>makefile files</info>. You have to set absolute path, and you can use the <info>%wf.project_path%</info> placeholder or <info>~</info> (your home directory). You can use only these two path!</comment>')
            ->example('~/dev.mk')
            ->scalarPrototype()->end()
        ;

        return $rootNode;
    }

    /**
     * Register extra makefiles contents
     *
     * @param PostBuildSkeletonFilesEvent $event
     */
    protected function eventAfterBuildFiles(PostBuildSkeletonFilesEvent $event): void
    {
        $buildConfig = $event->getBuildConfig();

        foreach ($buildConfig[static::NAME] as $n => $makefile) {
            $filename = sprintf('%d.makefile', $n);
            $fileInfo = new SplFileInfo($filename, '', $filename);

            $skeletonFile = new MakefileSkeletonFile($fileInfo);
            $skeletonFile->setContents(file_get_contents($makefile));
            $event->addSkeletonFile($skeletonFile);
        }
    }
}
