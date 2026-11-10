<?php

namespace Javaabu\DynamicMenubuilder\Tests\Unit;

use Illuminate\Support\Arr;
use Javaabu\DynamicMenubuilder\Enums\MenuItemType;
use Javaabu\DynamicMenubuilder\Enums\Visibility;
use Javaabu\DynamicMenubuilder\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class LocalizationTest extends TestCase
{
    #[Test]
    public function it_has_a_dhivehi_line_for_every_english_line(): void
    {
        $en = Arr::dot(require __DIR__ . '/../../lang/en/strings.php');
        $dv = Arr::dot(require __DIR__ . '/../../lang/dv/strings.php');

        $this->assertEquals(array_keys($en), array_keys($dv));

        foreach ($dv as $key => $line) {
            $this->assertMatchesRegularExpression('/\p{Thaana}/u', $line, "lang/dv/strings.php [{$key}] isn't in Dhivehi.");

            preg_match_all('/:[a-z_]+/', $en[$key], $placeholders);

            foreach ($placeholders[0] as $placeholder) {
                $this->assertStringContainsString($placeholder, $line, "lang/dv/strings.php [{$key}] is missing {$placeholder}.");
            }
        }
    }

    #[Test]
    public function it_translates_the_permission_descriptions(): void
    {
        $dv = json_decode(file_get_contents(__DIR__ . '/../../lang/dv.json'), true);

        foreach (['Menus', 'View menus', 'Edit menus', 'Delete menus'] as $line) {
            $this->assertArrayHasKey($line, $dv);
        }

        $this->app->setLocale('dv');

        $this->assertEquals('މެނޫތައް ބަދަލުކުރޭ', __('Edit menus'));
    }

    #[Test]
    public function it_translates_the_enums_and_validation_messages(): void
    {
        $this->app->setLocale('dv');

        $this->assertEquals('ލިސްޓު ސަފްހާ', MenuItemType::Index->label());
        $this->assertEquals('ލޮގިން ވެފައިވާ ބޭނުންކުރާ މީހުންނަށް', Visibility::Users->label());
        $this->assertEquals('ލޮގިން ނުވާ ޒިޔާރަތްކުރާ މީހުންނަށް', Visibility::options()['guests']);
        $this->assertStringContainsString('2 ފިޔަވަޅު', trans_choice('dynamic-menubuilder::strings.validation.depth', 2, ['max' => 2]));
        $this->assertStringContainsString('އެއް ފިޔަވަޅު', trans_choice('dynamic-menubuilder::strings.validation.depth', 1, ['max' => 1]));

        $this->app->setLocale('en');

        $this->assertStringContainsString('only one level', trans_choice('dynamic-menubuilder::strings.validation.depth', 1, ['max' => 1]));
        $this->assertStringContainsString('only 3 levels', trans_choice('dynamic-menubuilder::strings.validation.depth', 3, ['max' => 3]));
    }

    #[Test]
    public function it_only_uses_logical_directions_in_views_and_components(): void
    {
        $files = [];

        foreach (['resources/views', 'resources/js'] as $directory) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . "/../../{$directory}")) as $file) {
                if (preg_match('/\.(blade\.php|tsx|ts)$/', $file->getFilename())) {
                    $files[] = $file->getPathname();
                }
            }
        }

        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $contents = file_get_contents($file);

            $this->assertDoesNotMatchRegularExpression(
                '/\b(ml|mr|pl|pr|left|right|text-left|text-right|border-l|border-r|rounded-l|rounded-r)-[0-9a-z]/',
                $contents,
                basename($file) . ' uses a physical direction; use ms-, me-, ps-, pe-, start-, end-, text-start or text-end.',
            );

            $this->assertDoesNotMatchRegularExpression(
                '/(margin|padding)-(left|right)|(margin|padding)(Left|Right)/',
                $contents,
                basename($file) . ' uses a physical margin or padding; use the inline-start / inline-end properties.',
            );
        }
    }
}
