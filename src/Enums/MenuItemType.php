<?php

namespace Javaabu\DynamicMenubuilder\Enums;

/**
 * What a menu item links to
 */
enum MenuItemType: string
{
    /** A URL typed in the builder; without one, the item is a heading (a dropdown's label) */
    case Link = 'link';

    /** A record of a linkable source (a page, a post): its permalink and title */
    case Model = 'model';

    /** A linkable source's index page (all news, all pages) */
    case Index = 'index';

    public function label(): string
    {
        return __("dynamic-menubuilder::strings.types.{$this->value}");
    }
}
