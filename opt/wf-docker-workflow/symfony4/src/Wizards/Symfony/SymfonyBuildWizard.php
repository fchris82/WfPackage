<?php declare(strict_types=1);
/**
 * Created by IntelliJ IDEA.
 * User: chris
 * Date: 2018.02.19.
 * Time: 11:52
 */

namespace App\Wizards\Symfony;

use Wf\DockerWorkflowBundle\Environment\Commander;
use Wf\DockerWorkflowBundle\Environment\IoManager;
use Wf\DockerWorkflowBundle\Environment\WfEnvironmentParser;
use Wf\DockerWorkflowBundle\Event\SkeletonBuild\PostBuildSkeletonFileEvent;
use Wf\DockerWorkflowBundle\Event\Wizard\BuildWizardEvent;
use Wf\DockerWorkflowBundle\Wizards\BaseSkeletonWizard;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;

class SymfonyBuildWizard extends BaseSkeletonWizard
{
    protected $askDirectory = true;

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

    /**
     * @var array
     */
    protected $config = [];

    /**
     * @var string
     */
    protected $workingDirectory;

    public function getDefaultName(): string
    {
        return 'Symfony builder';
    }

    public function getInfo(): string
    {
        return 'Create a symfony project';
    }

    public function getDefaultGroup(): string
    {
        return 'Builder';
    }

    public function isBuilt(string $targetProjectDirectory): bool
    {
        return $this->wfEnvironmentParser->wfIsInitialized($targetProjectDirectory)
            || $this->fileSystem->exists($targetProjectDirectory . '/.git')
            || $this->fileSystem->exists($targetProjectDirectory . '/composer.json');
    }

    protected function eventAfterBuildFile(PostBuildSkeletonFileEvent $postBuildSkeletonFileEvent): void
    {
        parent::eventAfterBuildFile($postBuildSkeletonFileEvent);

        $postBuildSkeletonFileEvent->getSkeletonFile()->move($this->workingDirectory);
    }

    protected function readSkeletonVars(BuildWizardEvent $event): array
    {
        $directoryQuestion = new Question('Add meg a könyvtárat, ahová szeretnéd telepíteni: [<info>.</info>] ', '.');
        $versionQuestion = new Question('Add meg verziót [Üresen hagyva a legutóbbi stabil verziót szedi le, egyébként: <info>x.x</info>] ');

        $directory = $this->askDirectory
            ? $this->ask($directoryQuestion)
            : '.';
        $this->workingDirectory = $event->getWorkingDirectory() . \DIRECTORY_SEPARATOR . $directory;
        // @todo (Chris) Itt ellenőrizni kellene, hogy a könyvtár létezik-e, és ha igen, akkor üres-e. Ha pedig nem üres, akkor hibát kellene dobni, különben a program elhasal.
        $event->setWorkingDirectory($this->workingDirectory);

        // string!
        $version = $this->ask($versionQuestion);
        // integer!
        $sfVersion = $version ?
            (int) $version[1] :
            4;
        $this->config = [
            'version'    => $version,
            'sf_version' => $sfVersion,
        ];

        return $this->config;
    }

    protected function build(BuildWizardEvent $event): void
    {
        // Alapértelmezett adatok
        $package = 'symfony/website-skeleton';
        // Itt jegyezzük be, ha vmi config-ot módosítani kell. Elérhető configok: `composer config --list`
        $composerConfigChanges = [];
        $version = $this->config['version'];
        if ($version && version_compare($version, '4', '<')) {
            $package = 'symfony/framework-standard-edition';
            // SF3-ban 5.4 van megadva, ami nekünk nagyon nem jó, régi
            $composerConfigChanges = [
                'platform.php' => '7.1',
            ];
        }

        $workDir = $event->getWorkingDirectory();
        $tmpDir = $workDir . '/_tmp';
        $this->commander->run('mkdir -p ' . $tmpDir);

        $this->commander->cd($workDir);
        $this->runCmdInContainer(sprintf(
            'composer create-project %s %s %s',
            $package,
            $tmpDir,
            $version ? '"' . $version . '"' : ''
        ));

        // Composer config upgrade, eg: platform.php --> 7.1
        if (\count($composerConfigChanges) > 0) {
            foreach ($composerConfigChanges as $key => $value) {
                $this->runCmdInContainer(sprintf('composer config %s %s', $key, $value), $tmpDir);
            }
            $this->runCmdInContainer('composer update', $tmpDir);
        }

        $this->commander->run('rm -rf .[^.] .??*');
        $this->commander->run('mv _tmp/* ./');
        $this->commander->run('mv _tmp/.[!.]* ./');
        $this->commander->run('rm -r _tmp');
        $this->commander->run('git init && git add . && git commit -m "Init"');
    }
}
