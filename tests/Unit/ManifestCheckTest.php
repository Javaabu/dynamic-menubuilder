<?php

namespace Javaabu\DynamicMenubuilder\Tests\Unit;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Javaabu\DynamicMenubuilder\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * What `php artisan manifest:check` asks apps to install for this package: Blade apps get the
 * builder's script prebuilt or through manifest's Vite plugin, so only Inertia apps need React,
 *
 * @javaabu/forms and Onu.
 */
class ManifestCheckTest extends TestCase
{
    protected const PAGES_ONLY = ['@javaabu/forms', '@javaabu/onu', 'react', 'react-dom', '@inertiajs/react'];

    protected function tearDown(): void
    {
        File::delete(base_path('package.json'));
        File::deleteDirectory(base_path('node_modules'));

        parent::tearDown();
    }

    /**
     * @param  array<string, string>  $installed
     */
    protected function app(array $installed): void
    {
        File::put(base_path('package.json'), json_encode([
            'devDependencies' => array_map(fn (string $version) => "^{$version}", $installed),
        ]));

        foreach ($installed as $name => $version) {
            File::ensureDirectoryExists(base_path("node_modules/{$name}"));
            File::put(base_path("node_modules/{$name}/package.json"), json_encode(['name' => $name, 'version' => $version]));
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function check(): array
    {
        Artisan::call('manifest:check', ['--json' => true]);

        return collect(json_decode(Artisan::output(), true))->keyBy('name')->all();
    }

    /**
     * The rows that list this package among the ones that need them
     *
     * @param  array<string, array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    protected function ours(array $rows): array
    {
        return array_filter($rows, fn (array $row) => in_array('dynamic-menubuilder', $row['needed_by']));
    }

    #[Test]
    public function it_does_not_ask_blade_apps_for_the_react_packages(): void
    {
        $this->app(['laravel-vite-plugin' => '2.0.1', 'vite' => '7.1.3']);

        $ours = $this->ours($this->check());

        // Other packages' assets may still need some of these; this package's don't
        foreach (self::PAGES_ONLY as $name) {
            $this->assertArrayNotHasKey($name, $ours, "Blade apps were asked for {$name}");
        }

        $this->assertEquals(['ok'], array_values(array_unique(array_column($ours, 'status'))));
    }

    #[Test]
    public function it_asks_inertia_apps_for_what_the_pages_need(): void
    {
        $this->app(['@inertiajs/react' => '2.1.0', 'laravel-vite-plugin' => '2.0.1', 'vite' => '7.1.3']);

        $ours = $this->ours($this->check());

        foreach (self::PAGES_ONLY as $name) {
            $this->assertArrayHasKey($name, $ours, "Inertia apps weren't asked for {$name}");
            $this->assertContains('pages', $ours[$name]['scopes']);
        }

        $this->assertEquals('ok', $ours['@inertiajs/react']['status']);
        $this->assertEquals('missing', $ours['@javaabu/onu']['status']);
        $this->artisan('manifest:check')->assertFailed();
    }
}
