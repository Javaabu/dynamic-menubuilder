<?php

namespace Javaabu\DynamicMenubuilder\Tests\Fixtures\Models\Translatable;

use Javaabu\DynamicMenubuilder\Tests\Fixtures\Models\Page as BasePage;
use Javaabu\Translatable\Contracts\JsonTranslatable;
use Javaabu\Translatable\JsonTranslatable\IsJsonTranslatable;

/**
 * A linkable page whose title is translated
 */
class Page extends BasePage implements JsonTranslatable
{
    use IsJsonTranslatable;

    protected $table = 'pages';

    protected array $translatable = ['title'];
}
