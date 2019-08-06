<?php declare(strict_types=1);
/**
 * Created by IntelliJ IDEA.
 * User: chris
 * Date: 2018.03.14.
 * Time: 22:24
 */

namespace App\Recipes\MysqlMultiple;

use App\Recipes\Mysql\MysqlRecipe;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class MysqlMultipleRecipe extends MysqlRecipe
{
    const NAME = 'mysql_multiple';

    public function getName(): string
    {
        return static::NAME;
    }

    /**
     * @throws \ReflectionException
     *
     * @return ArrayNodeDefinition|NodeDefinition
     */
    public function getConfig(): NodeDefinition
    {
        $parentReflection = new \ReflectionClass(parent::class);
        $grandparentClass = $parentReflection->getParentClass()->getName();
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $grandparentClass::getConfig();

        $rootNode
            ->info('<comment>Include multiple MySQL services</comment>')
            ->children()
                ->arrayNode('defaults')
                    ->info('<comment>You can set some defaults for all containers!</comment>')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('version')
                            ->info('<comment>Docker image tag</comment>')
                        ->end()
                        ->scalarNode('password')
                            ->info('<comment>The <info>root</info> password.</comment>')
                        ->end()
                        ->booleanNode('local_volume')
                            ->info('<comment>You can switch the using local volume.</comment>')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        // Database prototype configuration
        $databasePrototypeNode = $rootNode
            ->children()
                ->arrayNode('databases')
                    ->info('<comment>Configuration of the MySql containers.</comment>')
                    ->useAttributeAsKey('docker_container_name')
                    ->prototype('array')
                        ->info('<comment>The docker container name. You have to link through this! Eg: <info>mysql -u root -p -h [docker_container_name]</info>.</comment>')
                        ->addDefaultsIfNotSet();
        $this->configureDbConnection($databasePrototypeNode);

        $rootNode
            ->beforeNormalization()
                ->always(function ($v) {
                    if (\is_array($v)) {
                        // Handle defaults
                        if (\array_key_exists('defaults', $v) && \is_array($v['defaults'])) {
                            foreach ($v['defaults'] as $key => $defaultValue) {
                                foreach ($v['databases'] as $dockerContainerName => $config) {
                                    // If the config empty, then we use only defaults
                                    if (!$config) {
                                        $config = [
                                            'database' => $dockerContainerName . '_db',
                                        ];
                                    } elseif (!\is_array($config)) {
                                        throw new InvalidConfigurationException(sprintf(
                                            'Invalid configuration value in the <info>mysql.databases.%s</info> place. You have to use array or null instead of %s',
                                            $dockerContainerName,
                                            \gettype($config)
                                        ));
                                    }
                                    if (!\array_key_exists($key, $config) || null === $config[$key]) {
                                        $v['databases'][$dockerContainerName][$key] = $defaultValue;
                                    }
                                }
                            }
                        }
                    }

                    return $v;
                })
                ->end()
            ->end()
        ;

        return $rootNode;
    }

    protected function needPortSkeletonFile(array $config): bool
    {
        foreach ($config['databases'] as $name => $dbConfig) {
            if (isset($dbConfig['port']) && false !== $dbConfig['port']) {
                return true;
            }
        }

        return false;
    }

    protected function needVolumeSkeletonFile(array $config): bool
    {
        foreach ($config['databases'] as $name => $dbConfig) {
            if (isset($dbConfig['local_volume']) && $dbConfig['local_volume']) {
                return true;
            }
        }

        return false;
    }

    // @todo (Chris) Regisztrálni kellene az elérhető parancsokat és azok paramétereit, hogy a `help` szépen kiírja azokat!
}
