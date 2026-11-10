<?php

namespace Javaabu\DynamicMenubuilder\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Javaabu\DynamicMenubuilder\DynamicMenubuilderServiceProvider;
use Javaabu\DynamicMenubuilder\Facades\Menus;
use Javaabu\DynamicMenubuilder\Tests\Fixtures\Models\Admin;
use Javaabu\DynamicMenubuilder\Tests\Fixtures\Models\Page;
use Javaabu\DynamicMenubuilder\Tests\Fixtures\Models\Post;
use Javaabu\DynamicMenubuilder\Tests\Fixtures\Models\User;
use Javaabu\Forms\FormsServiceProvider;
use Javaabu\Manifest\ManifestServiceProvider;
use Javaabu\Permissions\Permissions;
use Javaabu\Permissions\PermissionsServiceProvider;
use Javaabu\Stencil\StencilServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Spatie\Permission\PermissionServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Menus::reset();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ManifestServiceProvider::class,
            FormsServiceProvider::class,
            StencilServiceProvider::class,
            PermissionServiceProvider::class,
            PermissionsServiceProvider::class,
            DynamicMenubuilderServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return ['Menus' => Menus::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:yWa/ByhLC/GUvfToOuaPD7zDwB64qkc/QkaQOrT5IpE=');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('view.paths', [__DIR__ . '/views', ...$app['config']->get('view.paths', [])]);

        $app['config']->set('auth.guards', [
            'web' => ['driver' => 'session', 'provider' => 'users'],
            'web_admin' => ['driver' => 'session', 'provider' => 'admins'],
        ]);

        $app['config']->set('auth.providers', [
            'users' => ['driver' => 'eloquent', 'model' => User::class],
            'admins' => ['driver' => 'eloquent', 'model' => Admin::class],
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        foreach (['users', 'admins'] as $table) {
            Schema::create($table, function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('password')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        }

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug');
            $table->boolean('published')->default(true);
            $table->json('translations')->nullable();
            $table->string('lang')->nullable();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * The locations and sources most tests use
     */
    protected function registerMenus(): void
    {
        Menus::location('header', 'Header')->maxDepth(2);
        Menus::location('footer', 'Footer')->maxDepth(1);

        Menus::linkable(Page::class)->label('Pages');
        Menus::linkable(Post::class, 'news')
            ->label('News')
            ->query(fn ($query) => $query->where('type', 'news'))
            ->indexUrl(fn (string $locale) => url($locale === 'dv' ? 'dv/news' : 'news'));
        Menus::linkable(Post::class, 'events')
            ->label('Events')
            ->query(fn ($query) => $query->where('type', 'events'));
    }

    /**
     * A user of a guard with a role that has the given permissions
     *
     * @param  array<int, string>  $permissions
     */
    protected function userWith(array $permissions, string $guard = 'web'): User|Admin
    {
        config(['permissions.guards' => ['web', 'web_admin']]);

        Permissions::sync();

        $model = $guard === 'web_admin' ? Admin::class : User::class;
        $user = $model::query()->create(['name' => 'އަޙްމަދު', 'email' => uniqid() . '@example.com']);

        if ($permissions === []) {
            return $user;
        }

        $role_class = config('permission.models.role');
        $role = $role_class::findOrCreate('role_' . uniqid(), $guard);
        $role->givePermissionTo($permissions);

        $user->assignRole($role);

        return $user;
    }

    /**
     * A user who may do everything with menus
     */
    protected function menusAdmin(string $guard = 'web'): User|Admin
    {
        return $this->userWith(['view_menus', 'edit_menus', 'delete_menus'], $guard);
    }
}
