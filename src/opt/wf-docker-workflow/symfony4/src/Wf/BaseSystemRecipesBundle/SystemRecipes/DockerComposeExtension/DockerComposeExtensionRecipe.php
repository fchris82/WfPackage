<?php declare(strict_types=1);
/**
 * Created by IntelliJ IDEA.
 * User: chris
 * Date: 2018.03.27.
 * Time: 11:31
 */

namespace App\Wf\BaseSystemRecipesBundle\SystemRecipes\DockerComposeExtension;

use Wf\DockerWorkflowBundle\Configuration\Builder;
use Wf\DockerWorkflowBundle\Event\SkeletonBuild\PostBuildSkeletonFilesEvent;
use Wf\DockerWorkflowBundle\Exception\SkipRecipeException;
use Wf\DockerWorkflowBundle\Recipes\BaseRecipe;
use Wf\DockerWorkflowBundle\Recipes\SystemRecipe;
use Wf\DockerWorkflowBundle\Skeleton\FileType\DockerComposeSkeletonFile;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Yaml;

/**
 * Class Recipe
 *
 * You can insert Docker Compose configuration in the project config file:
 * <code>
 *  docker_compose:
 *      include:
 *          # Project file
 *          - mongo.docker-compose.yml
 *          # User file
 *          - /home/user/.wf/elasticsearch.docker-compose.yml
 *      extension:
 *          # Here start the 'docker-compose.yml'. The `version` will be automated there, you mustn't use it!
 *          services:
 *              web:
 *                  environment:
 *                      TEST: test
 * <code>
 */
class DockerComposeExtensionRecipe extends SystemRecipe
{
    const NAME = 'docker_compose';

    const DEFAULT_VERSION = '3.4';

    public function getName(): string
    {
        return static::NAME;
    }

    public function getConfig(): NodeDefinition
    {
        $rootNode = BaseRecipe::getConfig();

        $rootNode
            ->info('<comment>Config the docker compose data.</comment>')
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('version')
                    ->info('<comment>You can change the docker compose file version.</comment>')
                    ->cannotBeEmpty()
                    ->defaultValue(static::DEFAULT_VERSION)
                ->end()
                ->arrayNode('include')
                    ->info('<comment>You can add extra <info>docker-compose.yml files</info>.</comment>')
                    ->example('/home/user/dev.docker-compose.yml')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('extension')
                    ->info('<comment>Docker Compose yaml configuration. You mustn\'t use the <info>version</info> parameter, it will be automatically.</comment>')
                    ->example([
                        'services' => [
                            'web' => [
                                'volumes' => ['~/dev/nginx.conf:/etc/nginx/conf.d/custom.conf'],
                                'environment' => ['TEST' => '1'],
                            ],
                        ],
                    ])
                    ->variablePrototype()->end()
                    ->defaultValue([])
                ->end()
            ->end()
        ;

        return $rootNode;
    }

    /**
     * @param string $targetPath
     * @param array  $recipeConfig Here it is the `$globalConfig`
     * @param array  $globalConfig
     *
     * @throws SkipRecipeException
     *
     * @return array
     *
     * @see Builder::build()
     */
    public function getSkeletonVars(string $targetPath, array $recipeConfig, array $globalConfig): array
    {
        if (empty($globalConfig['docker_compose']['extension'])) {
            throw new SkipRecipeException();
        }

        $composeConfig = $globalConfig['docker_compose']['extension'];
        $recipeConfig = array_merge(
            $recipeConfig,
            [
                'yaml_dump' => Yaml::dump($composeConfig, 4),
            ]
        );

        return parent::getSkeletonVars($targetPath, $recipeConfig, $globalConfig);
    }

    /**
     * Handle the docker_compose.include parameter. Register the extra config files.
     *
     * @param PostBuildSkeletonFilesEvent $event
     */
    protected function eventAfterBuildFiles(PostBuildSkeletonFilesEvent $event): void
    {
        $buildConfig = $event->getBuildConfig();

        foreach ($buildConfig[static::NAME]['include'] as $n => $dockerComposeFilePath) {
            $filename = sprintf('%d.docker-compose.yml', $n);
            $fileInfo = new SplFileInfo($filename, '', $filename);

            $config = Yaml::parse(file_get_contents($dockerComposeFilePath));
            // Version fix
            $config['version'] = (string) $buildConfig[static::NAME]['version'];
            $skeletonFile = new DockerComposeSkeletonFile($fileInfo);
            $skeletonFile->setContents(Yaml::dump($config, 4));
            $event->addSkeletonFile($skeletonFile);
        }
    }
}
