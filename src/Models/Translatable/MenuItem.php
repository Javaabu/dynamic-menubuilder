<?php

namespace Javaabu\DynamicMenubuilder\Models\Translatable;

use Javaabu\DynamicMenubuilder\Models\MenuItem as BaseMenuItem;
use Javaabu\Translatable\Contracts\JsonTranslatable;
use Javaabu\Translatable\JsonTranslatable\IsJsonTranslatable;

/**
 * The menu item model when javaabu/translatable is enabled: the title and the custom link
 * are translated, and the structure (parents, order, links to records) is shared by every
 * locale, so moving, adding or removing an item changes every translation
 */
class MenuItem extends BaseMenuItem implements JsonTranslatable
{
    use IsJsonTranslatable;

    protected array $translatable = ['title', 'url'];
}
