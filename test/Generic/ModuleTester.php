<?php declare(strict_types=1);

namespace Generic;

use Laminas\Config\Reader\Ini as IniReader;

class ModuleTester
{
    use TesterTrait;

    /**
     * @var string
     */
    protected $namespace = '';

    public function __construct($namespace)
    {
        $this->namespace = $namespace;

        // Load composer files of the module if any.
        $composerAutoload = $this->modulePath() . '/vendor/autoload.php';
        if (file_exists($composerAutoload)) {
            require_once $composerAutoload;
        }

        // Set error reporting and bootstrap Omeka S testing with database.
        require_once $this->omekaPath() . '/application/test/bootstrap.php';

        // Init main services.
        $this->application = \Omeka\Test\DbTestCase::getApplication();
        $this->services = $this->application->getServiceManager();

        // Not requierd currently.
        $this->loginAdmin();
    }

    protected function omekaPath(): string
    {
        return dirname(__DIR__, 4);
    }

    protected function modulePath(): string
    {
        return $this->omekaPath() . '/modules/' . $this->namespace;
    }

    /**
     * Init a module with all its dependencies, if any, recursively.
     */
    public function initModule(string $moduleName = null): void
    {
        if (empty($moduleName)) {
            $moduleName = $this->namespace;
        }

        file_put_contents('php://stdout', sprintf("%s: Installing module…\n", $moduleName));

        // Check dependencies from the ini file first (key dependencies).
        $iniReader = new IniReader;
        $configIni = $iniReader->fromFile($this->modulePath() . '/config/module.ini');

        if (!empty($configIni['info']['dependencies'])) {
            $dependencies = $configIni['info']['dependencies'];
            if (!is_array($configIni['info']['dependencies'])) {
                $dependencies = array_map('trim', explode(',', $dependencies));
            }

            $totalDependencies = count($dependencies);
            file_put_contents('php://stdout', $totalDependencies <= 1
                ? sprintf("%s: Installing %d dependency…\n", $moduleName, $totalDependencies)
                : sprintf("%s: Installing %d dependencies…\n", $moduleName, $totalDependencies));
            foreach ($dependencies as $key => $dependency) {
                file_put_contents('php://stdout', sprintf("%s (%d/%d): Installing required module “%s”…\n", $moduleName, $key, $totalDependencies, $dependency));
                $this->installModule($dependency);
            }
        }

        $this->installModule($moduleName);
    }

    protected function installModule(string $moduleName): void
    {
        $moduleManager = $this->services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule($moduleName);
        $state = $module->getState();
        if ($state !== \Omeka\Module\Manager::STATE_ACTIVE) {
            $moduleManager->install($module);
        }
    }
}
