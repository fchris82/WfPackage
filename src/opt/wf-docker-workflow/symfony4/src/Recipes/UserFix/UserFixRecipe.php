<?php declare(strict_types=1);
/**
 * Created by IntelliJ IDEA.
 * User: chris
 * Date: 2018.03.14.
 * Time: 22:24
 */

namespace App\Recipes\UserFix;

use Wf\DockerWorkflowBundle\Recipes\BaseRecipe;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;

/**
 * Class Recipe
 *
 * You can change existing docker compose services behaviour. You can set the user ID and group ID for the selected user
 * and group.
 *
 * <code>
 *  recipes:
 *      user_fix:
 *          services:
 *              mysql:
 *                  user:   mysql
 *                  group:  mysql
 *                  # You have to set string!!!!
 *                  entrypoint: docker-entrypoint.sh mysqld
 *              nginx-custom:
 *                  user:   www-data
 *                  group:  www-data
 *                  entrypoint: nginx start
 * </code>
 *
 * If you have shared data volume, you will be the owner of the created files!
 */
class UserFixRecipe extends BaseRecipe
{
    const NAME = 'user_fix';

    public function getName(): string
    {
        return static::NAME;
    }

    public function getConfig(): NodeDefinition
    {
        $rootNode = parent::getConfig();

        $rootNode
            ->info('<comment>Run the setted containers with your user permissions.</comment>')
            ->children()
                ->arrayNode('services')
                    ->info('<comment>You have to set the service and its <info>user</info>, <info>group</info> and other settings.</comment>')
                    ->useAttributeAsKey('service')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('user')
                                ->info('<comment>The <info>username</info> what the container is using.</comment>')
                                ->example('mysql')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('group')
                                ->info('<comment>The <info>group name</info> what the container is using.</comment>')
                                ->example('mysql')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('entrypoint')
                                ->info('<comment>The <info>original FULL entrypoint</info> what the container is using.</comment>')
                                ->example('docker-entrypoint.sh mysqld')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('uid')
                                ->info('<comment>The <info>user ID</info> what we want to use. If you don\'t set it, then it uses the current user ID.</comment>')
                                ->example('1000')
                                ->cannotBeEmpty()
                                ->defaultValue('${WWW_DATA_UID}')
                            ->end()
                            ->scalarNode('gid')
                                ->info('<comment>The <info>group ID</info> what we want to use. If you don\'t set it, then it uses the docker group ID.</comment>')
                                ->example('999')
                                ->cannotBeEmpty()
                                ->defaultValue('${WWW_DATA_GID}')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $rootNode;
    }
}
