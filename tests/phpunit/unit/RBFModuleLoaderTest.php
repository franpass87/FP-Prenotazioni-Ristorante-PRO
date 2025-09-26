<?php
/**
 * Unit tests for the contextual module loader.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/includes/core/class-rbf-module-loader.php';

/**
 * Helper loader exposing protected logic for assertions.
 */
class Instrumented_Module_Loader extends RBF_Module_Loader {
    public bool $should_admin = false;
    public bool $should_frontend = true;
    public bool $should_cli = false;

    /**
     * Reset the runtime include log.
     */
    public function resetIncludeLog(): void {
        $GLOBALS['rbf_dummy_includes'] = [];
    }

    /**
     * Configure which module groups should load during the next call.
     *
     * @param bool $admin    Whether admin modules should load.
     * @param bool $frontend Whether frontend modules should load.
     * @param bool $cli      Whether CLI modules should load.
     */
    public function setContext(bool $admin, bool $frontend, bool $cli): void {
        $this->should_admin    = $admin;
        $this->should_frontend = $frontend;
        $this->should_cli      = $cli;
    }

    /**
     * Public proxy for the protected normalizer method.
     *
     * @param string $module Module path.
     * @return string
     */
    public function normalize(string $module): string
    {
        return $this->normalize_module_path($module);
    }

    /** @inheritDoc */
    protected function should_load_admin_modules() {
        return $this->should_admin;
    }

    /** @inheritDoc */
    protected function should_load_frontend_modules() {
        return $this->should_frontend;
    }

    /** @inheritDoc */
    protected function should_load_cli_modules() {
        return $this->should_cli;
    }
}

/**
 * @covers RBF_Module_Loader
 */
class RBFModuleLoaderTest extends TestCase
{
    private string $fixtureSourceDir;
    private string $tempFixtureDir;
    /**
     * @var string[]
     */
    private array $fixtureFiles = [
        'module-shared.php',
        'module-admin.php',
        'module-frontend.php',
        'module-cli.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureSourceDir = dirname(__DIR__) . '/fixtures/';
        $this->tempFixtureDir   = sys_get_temp_dir() . '/rbf-loader-fixtures-' . uniqid('', true);

        if (! mkdir($this->tempFixtureDir) && ! is_dir($this->tempFixtureDir)) {
            $this->fail('Unable to create temporary fixture directory.');
        }

        foreach ($this->fixtureFiles as $file) {
            copy($this->fixtureSourceDir . $file, $this->tempFixtureDir . DIRECTORY_SEPARATOR . $file);
        }

        $this->resetLog();
    }

    private function resetLog(): void
    {
        $GLOBALS['rbf_dummy_includes'] = [];
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->fixtureFiles as $file) {
            $path = $this->tempFixtureDir . DIRECTORY_SEPARATOR . $file;
            if (file_exists($path)) {
                unlink($path);
            }
        }

        if (is_dir($this->tempFixtureDir)) {
            rmdir($this->tempFixtureDir);
        }
    }

    public function test_loads_expected_groups_based_on_context(): void
    {
        $loader = new Instrumented_Module_Loader($this->tempFixtureDir);
        $loader->register_modules([
            'shared'   => ['module-shared.php'],
            'admin'    => ['module-admin.php'],
            'frontend' => ['module-frontend.php'],
            'cli'      => ['module-cli.php'],
        ]);

        $loader->setContext(true, true, true);
        $loader->load_registered_modules();

        sort($GLOBALS['rbf_dummy_includes']);

        $this->assertSame(
            ['module-admin.php', 'module-cli.php', 'module-frontend.php', 'module-shared.php'],
            $GLOBALS['rbf_dummy_includes']
        );
    }

    public function test_duplicate_modules_are_ignored_and_groups_only_load_once(): void
    {
        $loader = new Instrumented_Module_Loader($this->tempFixtureDir);
        $loader->register_group('shared', ['module-shared.php', 'module-shared.php']);

        $loader->load_group('shared');
        $loader->load_group('shared');

        $this->assertSame(['module-shared.php'], $GLOBALS['rbf_dummy_includes']);
    }

    public function test_context_flags_prevent_unneeded_groups_from_loading(): void
    {
        $loader = new Instrumented_Module_Loader($this->tempFixtureDir);
        $loader->register_modules([
            'shared'   => ['module-shared.php'],
            'admin'    => ['module-admin.php'],
            'frontend' => ['module-frontend.php'],
            'cli'      => ['module-cli.php'],
        ]);

        $loader->setContext(false, true, false);
        $loader->load_registered_modules();

        sort($GLOBALS['rbf_dummy_includes']);

        $this->assertSame(
            ['module-frontend.php', 'module-shared.php'],
            $GLOBALS['rbf_dummy_includes']
        );
    }

    public function test_normalize_converts_relative_paths_to_absolute(): void
    {
        $loader = new Instrumented_Module_Loader($this->tempFixtureDir);

        $this->assertSame(
            $this->tempFixtureDir . DIRECTORY_SEPARATOR . 'module-shared.php',
            $loader->normalize('module-shared.php')
        );
    }
}
