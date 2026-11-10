<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Javaabu\DynamicMenubuilder\DynamicMenubuilder;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(DynamicMenubuilder::table('menus'), function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create(DynamicMenubuilder::table('menu_items'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained(DynamicMenubuilder::table('menus'))->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained(DynamicMenubuilder::table('menu_items'))->cascadeOnDelete();
            $table->unsignedInteger('order')->default(0);

            // link, model or index
            $table->string('type')->default('link');
            // the key of a registered linkable source, for model and index items
            $table->string('source')->nullable();
            $table->nullableMorphs('linkable');

            $table->string('title')->nullable();
            $table->string('url', 2048)->nullable();
            $table->boolean('new_tab')->default(false);
            $table->string('rel')->nullable();
            $table->string('css_class')->nullable();
            $table->string('icon')->nullable();

            // everyone, guests or users
            $table->string('visibility')->default('everyone');
            // the locales the item shows in; null for every locale
            $table->json('locales')->nullable();

            // Translations (javaabu/translatable). Harmless without it.
            $table->json('translations')->nullable();
            $table->string('lang')->nullable()->index();

            $table->timestamps();

            $table->index(['menu_id', 'parent_id', 'order']);
        });

        Schema::create(DynamicMenubuilder::table('menu_locations'), function (Blueprint $table) {
            $table->id();
            $table->string('location')->unique();
            $table->foreignId('menu_id')->constrained(DynamicMenubuilder::table('menus'))->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(DynamicMenubuilder::table('menu_locations'));
        Schema::dropIfExists(DynamicMenubuilder::table('menu_items'));
        Schema::dropIfExists(DynamicMenubuilder::table('menus'));
    }
};
