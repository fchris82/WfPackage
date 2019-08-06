<?php declare(strict_types=1);
/**
 * Created by IntelliJ IDEA.
 * User: chris
 * Date: 2018.03.14.
 * Time: 21:55
 */

namespace App\Wf\BaseSystemRecipesBundle\SystemRecipes\PostBase;

use Wf\DockerWorkflowBundle\Event\RegisterEventListenersInterface;
use Wf\DockerWorkflowBundle\Event\SkeletonBuild\DumpFileEvent;
use Wf\DockerWorkflowBundle\Event\SkeletonBuildBaseEvents;
use Wf\DockerWorkflowBundle\Recipes\SystemRecipe;
use Wf\DockerWorkflowBundle\Skeleton\FileType\DockerComposeSkeletonFile;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Class Recipe
 *
 * After the all
 */
class PostBaseRecipe extends SystemRecipe implements RegisterEventListenersInterface
{
    const NAME = 'post_base';

    /**
     * @var array|string[]
     */
    protected $dockerComposeFiles = [];

    public function getName(): string
    {
        return static::NAME;
    }

    public function registerEventListeners(EventDispatcherInterface $eventDispatcher): void
    {
        $eventDispatcher->addListener(SkeletonBuildBaseEvents::AFTER_DUMP_FILE, [$this, 'collectFiles']);
    }

    public function collectFiles(DumpFileEvent $event): void
    {
        $skeletonFile = $event->getSkeletonFile();

        if ($skeletonFile instanceof DockerComposeSkeletonFile) {
            $this->dockerComposeFiles[] = $skeletonFile->getRelativePathname();
        }
    }

    public function getSkeletonVars(string $projectPath, array $recipeConfig, array $globalConfig): array
    {
        return array_merge(parent::getSkeletonVars($projectPath, $recipeConfig, $globalConfig), [
            'services' => $this->parseAllDockerServices($projectPath),
        ]);
    }

    /**
     * Find all docker service name through parsing the all included docker-compose.yml file.
     *
     * @return array
     */
    protected function parseAllDockerServices(string $projectPath): array
    {
        $services = [];
        foreach ($this->dockerComposeFiles as $dockerComposeFile) {
            $config = Yaml::parse(file_get_contents(
                $projectPath . '/' . $dockerComposeFile
            ));
            if (isset($config['services'])) {
                $services = array_unique(array_merge($services, array_keys($config['services'])));
            }
        }

        return $services;
    }
}
