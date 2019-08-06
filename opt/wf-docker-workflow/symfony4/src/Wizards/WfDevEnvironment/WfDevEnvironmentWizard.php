<?php declare(strict_types=1);
/**
 * Created by IntelliJ IDEA.
 * User: chris
 * Date: 2018.11.15.
 * Time: 11:46
 */

namespace App\Wizards\WfDevEnvironment;

use Wf\DockerWorkflowBundle\Environment\Commander;
use Wf\DockerWorkflowBundle\Environment\IoManager;
use Wf\DockerWorkflowBundle\Environment\WfEnvironmentParser;
use Wf\DockerWorkflowBundle\Event\Wizard\BuildWizardEvent;
use Wf\DockerWorkflowBundle\Wizards\BaseSkeletonWizard;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;

/**
 * Class DevEnvironment.
 *
 * Add "hidden" and simple WF einvironment to a project to work.
 *
 * <code>
 *  DockerProject
 *      ├── [...]
 *      ├── .wf.yml     <-- gitignored configuration file
 *      └── [...]
 * </code>
 */
class WfDevEnvironmentWizard extends BaseSkeletonWizard
{
    /**
     * @var WfEnvironmentParser
     */
    protected $wfEnvironmentParser;

    public function __construct(
        WfEnvironmentParser $wfEnvironmentParser,
        IoManager $ioManager,
        Commander $commander,
        EventDispatcherInterface $eventDispatcher,
        Environment $twig,
        Filesystem $filesystem
    ) {
        parent::__construct($ioManager, $commander, $eventDispatcher, $twig, $filesystem);
        $this->wfEnvironmentParser = $wfEnvironmentParser;
    }

    public function getDefaultName(): string
    {
        return 'WF Environment - Git ignored/outside';
    }

    public function getInfo(): string
    {
        return 'Create a WF environment for a project, hidden from git. You have to register the <info>/.wf.yml</info>' .
            ' in your <comment>global .gitignore</comment> file! You must use this with third party bundles or other components.';
    }

    public function getDefaultGroup(): string
    {
        return 'WF';
    }

    public function isBuilt(string $targetProjectDirectory): bool
    {
        return $this->wfEnvironmentParser->wfIsInitialized($targetProjectDirectory);
    }

    protected function readSkeletonVars(BuildWizardEvent $event): array
    {
        return [
            'project_name' => basename($event->getWorkingDirectory()),
        ];
    }

    protected function build(BuildWizardEvent $event): void
    {
        $this->ioManager->writeln('<comment>We created a simple and "empty" <info>.wf.yml</info> file, you have to edit it!</comment>');
        $this->ioManager->writeln('');
        $this->ioManager->writeln(file_get_contents($event->getWorkingDirectory() . \DIRECTORY_SEPARATOR . '.wf.yml'));
        $this->ioManager->writeln('');
        $this->ioManager->writeln('<question>List available recipes:</question>');
        $this->ioManager->writeln('<info>wf --config-dump --only-recipes</info>');
        $this->ioManager->writeln('<question>Add full recipe config with defaults:</question>');
        $this->ioManager->writeln('<info>wf --config-dump --recipe=<comment>[recipe_name]</comment> --no-ansi >> .wf.yml</info>');
    }
}
