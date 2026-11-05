<nav {{ $attributes->merge(['class' => 'dynamic-menu']) }} aria-label="{{ $label }}" data-menu-location="{{ $location }}">
    @include('dynamic-menubuilder::components.partials.menu-items', ['items' => $items, 'depth' => 1])
</nav>
