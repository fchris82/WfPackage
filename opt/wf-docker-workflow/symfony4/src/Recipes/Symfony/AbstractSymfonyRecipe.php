<?php declare(strict_types=1);
/**
 * Created by IntelliJ IDEA.
 * User: chris
 * Date: 2018.03.14.
 * Time: 22:24
 */

namespace App\Recipes\Symfony;

use Wf\DockerWorkflowBundle\Event\Configuration\PreProcessConfigurationEvent;
use Wf\DockerWorkflowBundle\Event\ConfigurationEvents;
use Wf\DockerWorkflowBundle\Exception\SkipSkeletonFileException;
use Wf\DockerWorkflowBundle\Recipes\AbstractTemplateRecipe;
use Wf\DockerWorkflowBundle\Recipes\BaseRecipe;
use Wf\DockerWorkflowBundle\Skeleton\FileType\SkeletonFile;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Class AbstractRecipe
 *
 * Symfony friendly environment
 */
class AbstractSymfonyRecipe extends BaseRecipe implements AbstractTemplateRecipe, EventSubscriberInterface
{
    const NAME = 'abstract_symfony_dont_use';
    const SF_CONSOLE_COMMAND = 'bin/console';
    const SF_BIN_DIR = 'vendor/bin';
    const DEFAULT_VERSION = 'php7.2';

    /**
     * @var string
     */
    protected $projectPath;

    public function getName(): string
    {
        return static::NAME;
    }

    public function getSkeletonVars(string $projectPath, array $recipeConfig, array $globalConfig): array
    {
        return array_merge(
            [
                'sf_console_command' => static::SF_CONSOLE_COMMAND,
                'sf_bin_dir' => static::SF_BIN_DIR,
            ],
            parent::getSkeletonVars($projectPath, $recipeConfig, $globalConfig)
        );
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2')))
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ConfigurationEvents::PRE_PROCESS_CONFIGURATION => 'preProcessConfiguration',
        ];
    }

    public function preProcessConfiguration(PreProcessConfigurationEvent $event): void
    {
        $this->projectPath = $event->getProjectPath();
    }

    public function getConfig(): NodeDefinition
    {
        $rootNode = parent::getConfig();
        // The default locale
        $defaultLocale = $_ENV['WF_HOST_LOCALE'] ?: $_ENV['LOCALE'] ?: 'en_US';
        if ($dotPos = strpos($defaultLocale, '.')) {
            $defaultLocale = substr($defaultLocale, 0, $dotPos);
        }

        $rootNode
            ->info('<comment>Symfony recipe</comment>')
            ->children()
                ->scalarNode('version')
                    ->info('<comment>Docker image tag. If you want to change image too, use the <info>image</info> option.</comment>')
                    ->cannotBeEmpty()
                    ->defaultValue(static::DEFAULT_VERSION)
                ->end()
                ->scalarNode('env')
                    ->info('<comment>Symfony environment.</comment>')
                    ->example('dev')
                    ->cannotBeEmpty()
                    ->defaultValue('prod')
                ->end()
                ->arrayNode('http_auth')
                    ->addDefaultsIfNotSet()
                    ->info('<comment>You can generate a user-password string here: http://www.htaccesstools.com/htpasswd-generator/ ( --> <info>htpasswd</info>).</comment>')
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                        ->end()
                        ->scalarNode('title')
                            ->defaultValue('Private zone')
                        ->end()
                        ->scalarNode('htpasswd')
                            ->cannotBeEmpty()
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('nginx')
                    ->addDefaultsIfNotSet()
                    ->info('<comment>You can register an nginx config file that will be inculded into the <info>server</info> block.</comment>')
                    ->children()
                        ->booleanNode('use_defaults')
                            ->defaultTrue()
                            ->info('<comment>If you want to use custom locals or anything else, you can switch off to use default "local settings" in the <info>server</info> block.</comment>')
                        ->end()
                        ->scalarNode('include_file')
                            ->cannotBeEmpty()
                            ->defaultNull()
                            ->info('<comment>You have to use a custom <info>server</info> block. You have to use docker <info>volumes</info> format!</comment>')
                            ->example('%wf.project_path%/.docker/web/error_pages.conf:/etc/nginx/error_pages.conf')
                        ->end()
                    ->end()
                    ->validate()
                        ->always(function ($v) {
                            if (!$v['use_defaults'] && !$v['include_file']) {
                                throw new InvalidConfigurationException('If you disable the `nginx.use defaults` option you have to set an `nginx.include_file`!');
                            }

                            return $v;
                        })
                    ->end()
                ->end()
                ->booleanNode('share_base_user_configs')
                    ->info('<comment>Here you can switch off or on to use user\'s .gitconfig, .ssh and .composer configs. Maybe you should switch off on CI.</comment>')
                    ->defaultTrue()
                ->end()
                ->arrayNode('names')
                    ->info('<comment>You can change the service names</comment>')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('engine')
                            ->info('<comment>The name of engine/default cli service</comment>')
                            ->cannotBeEmpty()
                            ->defaultValue('engine')
                            ->validate()
                                ->always(function ($v) {
                                    $v = trim($v);
                                    if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $v)) {
                                        throw new InvalidConfigurationException(sprintf(
                                            'The `%s` service name is invalid! You have to use only `[a-zA-Z0-9_.-]` characters.',
                                            $v
                                        ));
                                    }

                                    return $v;
                                })
                            ->end()
                        ->end()
                        ->scalarNode('web')
                            ->info('<comment>The name of web service</comment>')
                            ->cannotBeEmpty()
                            ->defaultValue('web')
                            ->validate()
                                ->always(function ($v) {
                                    $v = trim($v);
                                    if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $v)) {
                                        throw new InvalidConfigurationException(sprintf(
                                            'The `%s` service name is invalid! You have to use only `[a-zA-Z0-9_.-]` characters.',
                                            $v
                                        ));
                                    }

                                    return $v;
                                })
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('server')
                    ->info('<comment>Server configuration</comment>')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('host')
                            ->info('<comment>Only for nginx. Here you can set the host. See: etc/vhost.conf file</comment>')
                            ->setDeprecated('We are using the `_` special settings so it may be redundant!')
                            ->example('project.docker.company.com')
                            ->cannotBeEmpty()
                            ->defaultValue('localhost')
                        ->end()
                        ->booleanNode('xdebug')
                            ->defaultFalse()
                            ->info('<comment>You can switch on and off the xdebug.</comment>')
                        ->end()
                        ->scalarNode('xdebug_ide_server_name')
                            ->cannotBeEmpty()
                            ->defaultValue('Docker')
                        ->end()
                        ->booleanNode('error_log')
                            ->defaultTrue()
                            ->info('<comment>You can switch on and off the PHP error log. (default is ON!)</comment>')
                        ->end()
                        ->booleanNode('nginx_debug')
                            ->defaultFalse()
                            ->info('<comment>You can switch on and off debug mode. IMPORTANT! The debug mode makes lot of logs!</comment>')
                        ->end()
                        ->scalarNode('max_post_size')
                            ->defaultValue('10M')
                            ->info('<comment>You can set the nginx <info>client_max_body_size</info> and php <info>max_post</info> and <info>max_file_upload</info>.</comment>')
                        ->end()
                        ->scalarNode('timeout')
                            ->defaultValue('30')
                            ->info('<comment>You can set the nginx <info>fastcgi_read_timeout</info> and php <info>max_execution_time</info>.</comment>')
                        ->end()
                        ->scalarNode('timezone')
                            ->defaultValue($_ENV['WF_HOST_TIMEZONE'] ?: 'UTC')
                            ->info('<comment>You can set the server timezone. The default is your/host machine system setting from the <info>/etc/timezone</info> file.</comment>')
                        ->end()
                        ->scalarNode('locale')
                            ->defaultValue($defaultLocale)
                            ->info('<comment>You can set the server locale. The default is your/host machine system setting from the <info>$_ENV[LOCALE]</info></comment>')
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('project_dir')
                    ->info('<comment>You can set a subdirectory where the doc root is.</comment>')
                    ->cannotBeEmpty()
                    ->defaultValue('.')
                    ->validate()
                        ->always(function ($v) {
                            $v = trim($v);
                            $path = \DIRECTORY_SEPARATOR == $v[0]
                                ? $v
                                : rtrim($this->projectPath, '\\/') . \DIRECTORY_SEPARATOR . $v;
                            if (!file_exists($path)) {
                                throw new InvalidConfigurationException(sprintf(
                                    'The `%s` project dir is invalid, because it doesn\'t exist! (Full path where we tried to find: `%s`)',
                                    $v,
                                    $path
                                ));
                            }

                            return $v;
                        })
                    ->end()
                ->end()
            ->end()
        ;

        return $rootNode;
    }

    protected function buildSkeletonFile(SplFileInfo $fileInfo, array $recipeConfig): SkeletonFile
    {
        switch ($fileInfo->getFilename()) {
            // Volume settings
            case 'docker-compose.user-volumes.yml':
                if (!isset($recipeConfig['share_base_user_configs']) || !$recipeConfig['share_base_user_configs']) {
                    throw new SkipSkeletonFileException();
                }
                break;
        }

        return parent::buildSkeletonFile($fileInfo, $recipeConfig);
    }
}
