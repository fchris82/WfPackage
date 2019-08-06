<?php

declare(strict_types=1);
/**
 * Created by IntelliJ IDEA.
 * User: chris
 * Date: 2017.08.13.
 * Time: 21:46
 */

namespace App\Tests\Tool;

use Wf\DockerWorkflowBundle\Tests\Dummy\Command;
use Wf\DockerWorkflowBundle\Tests\Dummy\Filesystem;
use Wf\DockerWorkflowBundle\Tests\Dummy\Input;
use Wf\DockerWorkflowBundle\Tests\TestCase;
use Wf\DockerWorkflowBundle\Wizard\WizardInterface;
use Symfony\Component\Console\Tests\Fixtures\DummyOutput;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class BaseSkeletonTestCase extends TestCase
{
    protected function initSkeleton(WizardInterface $skeleton, array $responses = [])
    {
        $command = new Command(\get_class($skeleton));
        $command->setQuetionResponses($responses);
        $skeleton
            ->setCommand($command)
            ->setInput(new Input())
            ->setOutput(new DummyOutput())
        ;
    }

    protected function compareResults($resultDir, $alias, Filesystem $filesystem)
    {
        $resultFilesystem = new Filesystem($resultDir, $alias);
        $results = $filesystem->getContents();
        ksort($results);
        $responses = $resultFilesystem->getContents();
        ksort($responses);

        $this->assertEquals(
            $responses,
            $results,
            "\e[31mThere are some differencis between directories. The \e[1;97m+\e[0;31m sign is a unnecessary file, the \e[1;97m-\e[0;31m sign is a missing file.\e[0m"
        );

        foreach ($resultFilesystem->getContents() as $file => $content) {
            $this->assertEquals($results[$file], $content, sprintf('The `%s` file contents are different!', $file));
        }
    }

    protected function getTwig($path = null)
    {
        if (null === $path) {
            $path = $this->getBaseDir();
        }

        $loader = new FilesystemLoader($path);
        $loader->addPath($this->getBaseDir(), TwigExtendingPass::WIZARD_TWIG_NAMESPACE);
        $twig = new Environment($loader);

        return $twig;
    }

    protected function getBaseDir()
    {
        return __DIR__ . '/../../../skeletons';
    }
}
