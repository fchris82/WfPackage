<?php declare(strict_types=1);
/**
 * Created by IntelliJ IDEA.
 * User: chris
 * Date: 2018.03.14.
 * Time: 22:23
 */

namespace App\Recipes\MailHog;

use App\Recipes\NginxReverseProxy\NginxReverseProxyRecipe;
use Wf\DockerWorkflowBundle\Configuration\Environment;
use Wf\DockerWorkflowBundle\Exception\SkipSkeletonFileException;
use Wf\DockerWorkflowBundle\Recipes\BaseRecipe;
use Wf\DockerWorkflowBundle\Skeleton\FileType\SkeletonFile;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment as TwigEnvironment;

/**
 * Class Recipe
 *
 * E-mail sender.
 */
class MailHogRecipe extends BaseRecipe
{
    const NAME = 'mailhog';

    /**
     * @var Environment
     */
    protected $environment;

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

        $defaultTld = trim(
            $this->environment->getConfigValue(Environment::CONFIG_DEFAULT_LOCAL_TLD, '.loc'),
            '.'
        );
        $defaultHost = sprintf(
            '%s.%s.%s',
            static::NAME,
            NginxReverseProxyRecipe::PROJECT_NAME_PARAMETER_NAME,
            $defaultTld
        );

        $rootNode
            ->info('<comment>MailHog e-mail catcher. You can use the ports 1025 and 80.</comment>')
            ->children()
                ->variableNode('nginx_reverse_proxy_host')
                    ->info('You can set a custom domain that you can use to allow the webpage. Set false if you don\'t want to use it.')
                    ->defaultValue($defaultHost)
                    ->example('mailhog.custom.loc')
                ->end()
            ->end()
        ;

        return $rootNode;
    }

    /**
     * {@inheritdoc}
     */
    protected function buildSkeletonFile(SplFileInfo $fileInfo, array $recipeConfig): SkeletonFile
    {
        switch ($fileInfo->getFilename()) {
            case 'docker-compose.nginx-reverse-proxy.yml':
                if (!isset($recipeConfig['nginx_reverse_proxy_host']) || !$recipeConfig['nginx_reverse_proxy_host']) {
                    throw new SkipSkeletonFileException();
                }
                break;
        }

        return parent::buildSkeletonFile($fileInfo, $recipeConfig);
    }
}
