<?php

declare(strict_types=1);
/**
 * Created by IntelliJ IDEA.
 * User: chris
 * Date: 2018.05.29.
 * Time: 10:51
 */

namespace App\Tests;

use App\Recipes\NginxReverseProxy\NginxReverseProxyRecipe;
use Wf\DockerWorkflowBundle\Configuration\Environment;
use Wf\DockerWorkflowBundle\Tests\TestCase;
use Mockery as m;
use Symfony\Component\EventDispatcher\EventDispatcher;

class NginxReverseProxyTest extends TestCase
{
    /**
     * @param array  $recipeConfig
     * @param string $defaultHost
     * @param array  $result
     *
     * @dataProvider getHosts
     */
    public function testDefaultHostIsSet($recipeConfig, $defaultHost, $result)
    {
        $twig = m::mock(\Twig\Environment::class);
        $environment = new Environment();
        $recipe = new NginxReverseProxyRecipe($twig, new EventDispatcher(), $environment);

        $response = $this->executeProtectedMethod($recipe, 'defaultHostIsSet', [$recipeConfig, $defaultHost]);
        $this->assertEquals($result, $response);
    }

    public function getHosts()
    {
        return [
            [['settings' => []], 'test.loc', false],
            [
                [
                    'settings' => [
                        'web' =>     ['host' => 'web.test.loc'],
                        'elastic' => ['host' => 'elastic.test.loc'],
                    ],
                ],
                'test.loc',
                false,
            ],
            [
                [
                    'settings' => [
                        'web' =>     ['host' => 'test.loc'],
                        'elastic' => ['host' => 'elastic.test.loc'],
                    ],
                ],
                'test.loc',
                true,
            ],
            [
                [
                    'settings' => [
                        'web' =>     ['host' => 'web.test.loc test.loc'],
                        'elastic' => ['host' => 'elastic.test.loc'],
                    ],
                ],
                'test.loc',
                true,
            ],
            [
                [
                    'settings' => [
                        'web' =>     ['host' => 'test.loc web.test.loc'],
                        'elastic' => ['host' => 'elastic.test.loc'],
                    ],
                ],
                'test.loc',
                true,
            ],
            [
                [
                    'settings' => [
                        'web' =>     ['host' => 'web.test.loc'],
                        'elastic' => ['host' => 'test.loc elastic.test.loc'],
                    ],
                ],
                'test.loc',
                true,
            ],
            [
                [
                    'settings' => [
                        'web' =>     ['host' => 'web.test.loc'],
                        'elastic' => ['host' => 'elastic.test.loc test.loc'],
                    ],
                ],
                'test.loc',
                true,
            ],
        ];
    }

    /**
     * @param array $recipeConfig
     * @param array $globalConfig
     * @param array $result
     *
     * @dataProvider getConfigs
     */
    public function testSetTheDefaultHostIfNotSet($recipeConfig, $globalConfig, $result)
    {
        $twig = m::mock(\Twig\Environment::class);
        $environment = m::mock(Environment::class, [
            'getConfigValue' => '.loc',
        ]);
        $recipe = new NginxReverseProxyRecipe($twig, new EventDispatcher(), $environment);

        $response = $this->executeProtectedMethod($recipe, 'setTheDefaultHostIfNotSet', ['', $recipeConfig, $globalConfig]);
        $this->assertEquals($result, $response);
    }

    public function getConfigs()
    {
        return [
            // all defaults
            [
                [
                    'settings' => [
                        'web' => ['host' => 'web.test.loc'],
                        'elastic' => ['host' => 'elastic.test.loc'],
                    ],
                ],
                ['name' => 'test'],
                [
                    'settings' => [
                        'web' => ['host' => 'test.loc web.test.loc'],
                        'elastic' => ['host' => 'elastic.test.loc'],
                    ],
                ],
            ],
            // all defaults - reverse order
            [
                [
                    'settings' => [
                        'elastic' => ['host' => 'elastic.test.loc'],
                        'web' => ['host' => 'web.test.loc'],
                    ],
                ],
                ['name' => 'test'],
                [
                    'settings' => [
                        'elastic' => ['host' => 'test.loc elastic.test.loc'],
                        'web' => ['host' => 'web.test.loc'],
                    ],
                ],
            ],
            // existing settings 1
            [
                [
                    'settings' => [
                        'web' => ['host' => 'web.test.loc test.loc'],
                        'elastic' => ['host' => 'elastic.test.loc'],
                    ],
                ],
                ['name' => 'test'],
                [
                    'settings' => [
                        'web' => ['host' => 'web.test.loc test.loc'],
                        'elastic' => ['host' => 'elastic.test.loc'],
                    ],
                ],
            ],
            // existing settings 2
            [
                [
                    'settings' => [
                        'web' => ['host' => 'web.test.loc'],
                        'elastic' => ['host' => 'test.loc elastic.test.loc'],
                    ],
                ],
                ['name' => 'test'],
                [
                    'settings' => [
                        'web' => ['host' => 'web.test.loc'],
                        'elastic' => ['host' => 'test.loc elastic.test.loc'],
                    ],
                ],
            ],
            // no default host is used (custom host)
            [
                [
                    'settings' => [
                        'web' => ['host' => 'custom.test.loc'],
                        'elastic' => ['host' => 'elastic.test.loc'],
                    ],
                ],
                ['name' => 'test'],
                [
                    'settings' => [
                        'web' => ['host' => 'custom.test.loc'],
                        'elastic' => ['host' => 'elastic.test.loc'],
                    ],
                ],
            ],
        ];
    }
}
