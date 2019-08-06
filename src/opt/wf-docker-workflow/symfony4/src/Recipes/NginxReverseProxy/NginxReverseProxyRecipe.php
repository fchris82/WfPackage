<?php declare(strict_types=1);
/**
 * Created by IntelliJ IDEA.
 * User: chris
 * Date: 2018.03.14.
 * Time: 21:55
 */

namespace App\Recipes\NginxReverseProxy;

use Wf\DockerWorkflowBundle\Configuration\Environment;
use Wf\DockerWorkflowBundle\Event\Configuration\BuildInitEvent;
use Wf\DockerWorkflowBundle\Event\ConfigurationEvents;
use Wf\DockerWorkflowBundle\Event\RegisterEventListenersInterface;
use Wf\DockerWorkflowBundle\Event\SkeletonBuild\PreBuildSkeletonFilesEvent;
use Wf\DockerWorkflowBundle\Recipes\BaseRecipe;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment as TwigEnvironment;

/**
 * Class Recipe
 *
 * Allow nginx-reverse-proxy config.
 */
class NginxReverseProxyRecipe extends BaseRecipe implements RegisterEventListenersInterface
{
    const NAME = 'nginx_reverse_proxy';

    const SERVICE_NAME_PARAMETER_NAME = '%service%';
    // We try to give a lazy solution with default host settings
    const PROJECT_NAME_PARAMETER_NAME = '%config.name%';

    /**
     * @var Environment
     */
    protected $environment;

    /**
     * @var string
     */
    protected $projectName;

    /**
     * Recipe constructor.
     *
     * @param TwigEnvironment          $twig
     * @param EventDispatcherInterface $eventDispatcher
     * @param Environment              $environment
     */
    public function __construct(TwigEnvironment $twig, EventDispatcherInterface $eventDispatcher, Environment $environment)
    {
        parent::__construct($twig, $eventDispatcher);
        $this->environment = $environment;
    }

    public function getName(): string
    {
        return static::NAME;
    }

    public function getConfig(): NodeDefinition
    {
        $rootNode = parent::getConfig();

        $rootNode
            ->info('<comment>You can enable the nginx-reverse-proxy.</comment>')
            ->children()
                ->scalarNode('network_name')
                    ->info('<comment>The nginx-reverse-proxy network name.</comment>')
                    ->cannotBeEmpty()
                    ->defaultValue('reverse-proxy')
                ->end()
                ->arrayNode('settings')
                    ->info('<comment>You have to set the service and its <info>host</info> and <info>port</info> settings.</comment>')
                    ->useAttributeAsKey('service')
                    ->variablePrototype()
                        ->beforeNormalization()
                            ->always(function ($v) {
                                $defaultTld = trim(
                                    $this->environment->getConfigValue(Environment::CONFIG_DEFAULT_LOCAL_TLD, '.loc'),
                                    '.'
                                );
                                $defaultHost = sprintf(
                                    '%s.%s.%s',
                                    static::SERVICE_NAME_PARAMETER_NAME,
                                    static::PROJECT_NAME_PARAMETER_NAME,
                                    $defaultTld
                                );
                                $defaultPort = 80;

                                return [
                                    // If the project name: `project` --> `project.loc`
                                    'host' => \is_array($v) && \array_key_exists('host', $v) ? $v['host'] : $defaultHost,
                                    'port' => (int) (\is_array($v) && \array_key_exists('port', $v) ? $v['port'] : (!\is_array($v) && $v ? $v : $defaultPort)),
                                ];
                            })
                            ->end()
                        ->end()
                        // Replace the service names in domains
                        ->validate()
                            ->always(function ($v) {
                                foreach ($v as $serviceName => $settings) {
                                    $settings['host'] = strtr($settings['host'], [static::SERVICE_NAME_PARAMETER_NAME => $serviceName]);
                                    $v[$serviceName] = $settings;
                                }

                                return $v;
                            })
                        ->end()
                        ->example([
                            'service1' => '~',
                            'service2' => ['host' => 'phpmyadmin.project.loc', 'port' => 81],
                            'service3' => 82,
                        ])
                    ->end()
                ->end()
            ->end()
        ;

        return $rootNode;
    }

    public function registerEventListeners(EventDispatcherInterface $eventDispatcher): void
    {
        $eventDispatcher->addListener(ConfigurationEvents::BUILD_INIT, [$this, 'findProjectName']);
    }

    public function findProjectName(BuildInitEvent $buildInitEvent): void
    {
        $config = $buildInitEvent->getConfig();
        $buildInitEvent->setParameter(static::PROJECT_NAME_PARAMETER_NAME, $config['name']);
        $this->projectName = $config['name'];
    }

    /**
     * Create default host (only [project_name].[default_tld] for the FIRST service)
     *
     * @param PreBuildSkeletonFilesEvent $event
     */
    protected function eventBeforeBuildFiles(PreBuildSkeletonFilesEvent $event): void
    {
        parent::eventBeforeBuildFiles($event);

        $recipeConfig = $event->getBuildConfig();
        $templateVariables = $event->getSkeletonVars();

        $defaultTld = trim(
            $this->environment->getConfigValue(Environment::CONFIG_DEFAULT_LOCAL_TLD, '.loc'),
            '.'
        );
        $defaultHost = sprintf('%s.%s', $this->projectName, $defaultTld);

        if (!$this->defaultHostIsSet($recipeConfig, $defaultHost)) {
            foreach ($recipeConfig['settings'] as $serviceName => $settings) {
                // Only the default host name exists: [service_name].[project_name].[tld]
                if (0 === strpos($settings['host'], $serviceName)) {
                    $settings['host'] = $defaultHost . ' ' . $settings['host'];
                    $recipeConfig['settings'][$serviceName] = $settings;
                }
                break;
            }
            foreach ($templateVariables['settings'] as $serviceName => $settings) {
                // Only the default host name exists: [service_name].[project_name].[tld]
                if (0 === strpos($settings['host'], $serviceName)) {
                    $settings['host'] = $defaultHost . ' ' . $settings['host'];
                    $templateVariables['settings'][$serviceName] = $settings;
                }
                break;
            }
        }

        $event->setBuildConfig($recipeConfig);
        $event->setSkeletonVars($templateVariables);
    }

    /**
     * Set a default host (only the [project_name].[tld] format) for the first service if there isn't set it anywhere.
     *
     * @param string $projectPath
     * @param array  $recipeConfig
     * @param array  $globalConfig
     *
     * @return array
     */
    protected function setTheDefaultHostIfNotSet(string $projectPath, array $recipeConfig, array $globalConfig): array
    {
        $defaultTld = trim(
            $this->environment->getConfigValue(Environment::CONFIG_DEFAULT_LOCAL_TLD, '.loc'),
            '.'
        );
        $defaultHost = sprintf('%s.%s', $globalConfig['name'], $defaultTld);

        if (!$this->defaultHostIsSet($recipeConfig, $defaultHost)) {
            foreach ($recipeConfig['settings'] as $serviceName => $settings) {
                // Only the default host name exists: [service_name].[project_name].[tld]
                if (0 === strpos($settings['host'], $serviceName)) {
                    $settings['host'] = $defaultHost . ' ' . $settings['host'];
                    $recipeConfig['settings'][$serviceName] = $settings;
                }
                break;
            }
        }

        return $recipeConfig;
    }

    /**
     * It tries to find to project default host name (format: [project_name].[tld] ), and if it is set somewhere it will
     * return true.
     *
     * @param array  $recipeConfig
     * @param string $defaultHost
     *
     * @return bool
     */
    protected function defaultHostIsSet(array $recipeConfig, string $defaultHost): bool
    {
        foreach ($recipeConfig['settings'] as $serviceName => $settings) {
            $hosts = explode(' ', $settings['host']);
            if (\in_array($defaultHost, $hosts)) {
                return true;
            }
        }

        return false;
    }
}
