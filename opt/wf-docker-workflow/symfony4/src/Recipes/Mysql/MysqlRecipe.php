<?php declare(strict_types=1);
/**
 * Created by IntelliJ IDEA.
 * User: chris
 * Date: 2018.03.14.
 * Time: 22:24
 */

namespace App\Recipes\Mysql;

use Wf\DockerWorkflowBundle\Exception\SkipSkeletonFileException;
use Wf\DockerWorkflowBundle\Recipes\BaseRecipe;
use Wf\DockerWorkflowBundle\Skeleton\FileType\SkeletonFile;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Finder\SplFileInfo;

class MysqlRecipe extends BaseRecipe
{
    const NAME = 'mysql';

    public function getName(): string
    {
        return static::NAME;
    }

    public function getConfig(): NodeDefinition
    {
        $rootNode = parent::getConfig();

        $rootNode
            ->info('<comment>Include a MySQL service</comment>')
        ;
        $this->configureDbConnection($rootNode);

        return $rootNode;
    }

    protected function configureDbConnection(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->scalarNode('version')
                    ->info('<comment>Docker image tag</comment>')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('database')
                    ->info('<comment>Database name</comment>')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('password')
                    ->info('<comment>The <info>root</info> password.</comment>')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->booleanNode('local_volume')
                    ->info('<comment>You can switch the using local volume.</comment>')
                    ->defaultTrue()
                ->end()
                ->variableNode('port')
                    ->info("<comment>If you want to enable this container from outside set the port number. You can use these values:</comment>\n" .
                        " <info>false</info>        <comment>no create opened port number</comment>\n" .
                        " <info>0</info>            <comment>create a custom, random port number</comment>\n" .
                        ' <info>[1-65535]</info>    <comment>use this port number</comment>')
                    ->defaultFalse()
                    // Available parameters: false, [0-65536]
                    ->beforeNormalization()
                        ->always(function ($v) {
                            if (false === $v || \is_int($v)) {
                                return $v;
                            }
                            if (!\is_string($v)) {
                                throw new InvalidConfigurationException(sprintf(
                                    'The `%s` needs to be false or integer instead of %s!',
                                    'port',
                                    \gettype($v)
                                ));
                            }
                            if (\in_array(strtolower(trim($v)), ['false', 'off', 'no'])) {
                                return false;
                            }
                            if (preg_match('^\d+$', trim($v))) {
                                $v = (int) $v;
                                if ($v > 65535) {
                                    throw new InvalidConfigurationException(sprintf(
                                        'The `%s` needs to be less than 65536! The `%d` is an invalid port number',
                                        'port',
                                        $v
                                    ));
                                }

                                return (int) $v;
                            }

                            throw new InvalidConfigurationException(sprintf(
                                'The `%s` needs to be false or integer. The `%s` value is invalid!',
                                'port',
                                $v
                            ));
                        })
                    ->end()
                ->end()
            ->end()
        ;
    }

    protected function needPortSkeletonFile(array $config): bool
    {
        return isset($config['port']) && false !== $config['port'];
    }

    protected function needVolumeSkeletonFile(array $config): bool
    {
        return isset($config['local_volume']) && $config['local_volume'];
    }

    /**
     * @param SplFileInfo $fileInfo
     * @param $recipeConfig
     *
     * @throws SkipSkeletonFileException
     *
     * @return SkeletonFile
     */
    protected function buildSkeletonFile(SplFileInfo $fileInfo, array $recipeConfig): SkeletonFile
    {
        switch ($fileInfo->getFilename()) {
            // Port settings
            case 'docker-compose.port.yml':
                if (!$this->needPortSkeletonFile($recipeConfig)) {
                    throw new SkipSkeletonFileException();
                }
                break;
            // Volume settings
            case 'docker-compose.volume.yml':
                if (!$this->needVolumeSkeletonFile($recipeConfig)) {
                    throw new SkipSkeletonFileException();
                }
                break;
        }

        return parent::buildSkeletonFile($fileInfo, $recipeConfig);
    }
}
