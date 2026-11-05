{{-- One level of a rendered menu. $items are Javaabu\DynamicMenubuilder\Rendering\MenuNode --}}
<ul class="dynamic-menu__list dynamic-menu__list--depth-{{ $depth }}" data-menu-depth="{{ $depth }}">
    @foreach($items as $item)
        <li @class([
                'dynamic-menu__item',
                'dynamic-menu__item--active' => $item->active,
                'dynamic-menu__item--current' => $item->current,
                'dynamic-menu__item--has-children' => $item->hasChildren(),
                $item->css_class,
            ])
            data-menu-item="{{ $item->id }}">
            @if($item->isHeading())
                <span class="dynamic-menu__heading" dir="auto">
                    @if($item->icon)<i class="{{ $item->icon }}" aria-hidden="true"></i>@endif
                    {{ $item->title }}
                </span>
            @else
                <a href="{{ $item->url }}"
                   class="dynamic-menu__link"
                   dir="auto"
                   @if($item->current) aria-current="page" @endif
                   @if($item->target()) target="{{ $item->target() }}" @endif
                   @if($item->relAttribute()) rel="{{ $item->relAttribute() }}" @endif>
                    @if($item->icon)<i class="{{ $item->icon }}" aria-hidden="true"></i>@endif
                    {{ $item->title }}
                </a>
            @endif

            @if($item->hasChildren())
                @include('dynamic-menubuilder::components.partials.menu-items', ['items' => $item->children, 'depth' => $depth + 1])
            @endif
        </li>
    @endforeach
</ul>
