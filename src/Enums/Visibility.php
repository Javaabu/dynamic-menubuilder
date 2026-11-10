<?php

namespace Javaabu\DynamicMenubuilder\Enums;

/**
 * Who sees a menu item
 */
enum Visibility: string
{
    case Everyone = 'everyone';

    /** Visitors who aren't signed in to the location's guard */
    case Guests = 'guests';

    /** Visitors signed in to the location's guard */
    case Users = 'users';

    public function label(): string
    {
        return __("dynamic-menubuilder::strings.visibility.{$this->value}");
    }

    public function allows(bool $signed_in): bool
    {
        return match ($this) {
            self::Everyone => true,
            self::Guests => ! $signed_in,
            self::Users => $signed_in,
        };
    }

    /**
     * value => label, for selects
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
