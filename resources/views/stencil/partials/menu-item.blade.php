{{-- One item of the Blade menu builder. $index is its position (or __INDEX__ in the template). --}}
@php
    $link_type = \Javaabu\DynamicMenubuilder\Enums\MenuItemType::Link;
    $type = \Javaabu\DynamicMenubuilder\Enums\MenuItemType::tryFrom((string) ($item['type'] ?? 'link')) ?? $link_type;
    $depth = (int) ($item['depth'] ?? 1);
    $field = fn (string $key) => "{$name}[{$index}][{$key}]";
    $error_key = fn (string $key) => "{$name}.{$index}.{$key}";
    $panel_id = "{$id}-item-{$item['key']}";
    $default_locale = collect($locales)->firstWhere('default', true)['code'] ?? null;
    $own_title = $default_locale ? ($item["title_{$default_locale}"] ?? $item['title'] ?? null) : ($item['title'] ?? null);
    $label = filled($own_title) ? $own_title : ($item['linkable_label'] ?? null);
    $label = filled($label) ? $label : __('dynamic-menubuilder::strings.builder.untitled');
    $type_label = $type === $link_type
        ? (filled($item['url'] ?? null) || ($default_locale && filled($item["url_{$default_locale}"] ?? null)) ? $type->label() : __('dynamic-menubuilder::strings.builder.heading'))
        : ($item['source_label'] ?? $type->label());
    $has_error = collect(['title', 'url', 'source', 'linkable_id', 'parent', 'rel', 'css_class', 'icon', 'visibility'])
        ->contains(fn ($key) => $errors->has($error_key($key)));
@endphp

<li class="dynamic-menubuilder__item"
    style="margin-inline-start: {{ max(0, $depth - 1) * 1.5 }}rem"
    data-menu-item
    data-key="{{ $item['key'] }}"
    data-depth="{{ $depth }}"
    data-type="{{ $type->value }}">
    <div class="flex flex-wrap items-center gap-2 rounded-lg border bg-card p-2 @if(! empty($item['broken'])) border-destructive @endif">
        <button type="button" class="cursor-grab rounded p-1 text-muted-foreground" draggable="true"
                data-action="drag"
                aria-label="{{ __('dynamic-menubuilder::strings.builder.drag', ['title' => $label]) }}"
                aria-keyshortcuts="Alt+ArrowUp Alt+ArrowDown Alt+ArrowRight Alt+ArrowLeft">
            <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="5" r="1.5"/><circle cx="15" cy="5" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="19" r="1.5"/><circle cx="15" cy="19" r="1.5"/></svg>
        </button>

        <span class="min-w-0 flex-1 truncate font-medium" dir="auto" data-item-label>{{ $label }}</span>
        <span class="rounded bg-muted px-2 py-0.5 text-xs text-muted-foreground" dir="auto" data-item-type>{{ $type_label }}</span>

        <span class="flex items-center gap-1">
            <x-forms::button type="button" color="secondary" size="sm" data-action="move-up" :title="__('dynamic-menubuilder::strings.builder.move_up')">
                <span aria-hidden="true">↑</span><span class="sr-only">{{ __('dynamic-menubuilder::strings.builder.move_up') }}</span>
            </x-forms::button>
            <x-forms::button type="button" color="secondary" size="sm" data-action="move-down" :title="__('dynamic-menubuilder::strings.builder.move_down')">
                <span aria-hidden="true">↓</span><span class="sr-only">{{ __('dynamic-menubuilder::strings.builder.move_down') }}</span>
            </x-forms::button>
            <x-forms::button type="button" color="secondary" size="sm" data-action="outdent" :title="__('dynamic-menubuilder::strings.builder.outdent')">
                <span aria-hidden="true" class="rtl:-scale-x-100 inline-block">⇤</span><span class="sr-only">{{ __('dynamic-menubuilder::strings.builder.outdent') }}</span>
            </x-forms::button>
            <x-forms::button type="button" color="secondary" size="sm" data-action="indent" :title="__('dynamic-menubuilder::strings.builder.indent')">
                <span aria-hidden="true" class="rtl:-scale-x-100 inline-block">⇥</span><span class="sr-only">{{ __('dynamic-menubuilder::strings.builder.indent') }}</span>
            </x-forms::button>
            <x-forms::button type="button" color="secondary" size="sm" data-action="toggle"
                             aria-expanded="{{ $has_error ? 'true' : 'false' }}" aria-controls="{{ $panel_id }}">
                <span data-toggle-label>{{ __('dynamic-menubuilder::strings.builder.edit_item', ['title' => $label]) }}</span>
            </x-forms::button>
            <x-forms::button type="button" color="danger" size="sm" data-action="remove">
                {{ __('dynamic-menubuilder::strings.builder.remove') }}
            </x-forms::button>
        </span>
    </div>

    @if(! empty($item['broken']))
        <p class="mt-1 text-sm text-destructive" data-item-broken>{{ __('dynamic-menubuilder::strings.builder.broken') }}</p>
    @endif

    <input type="hidden" name="{{ $field('key') }}" value="{{ $item['key'] }}" data-input="key">
    <input type="hidden" name="{{ $field('id') }}" value="{{ $item['id'] ?? '' }}" data-input="id">
    <input type="hidden" name="{{ $field('parent') }}" value="{{ $item['parent'] ?? '' }}" data-input="parent">
    <input type="hidden" name="{{ $field('type') }}" value="{{ $type->value }}" data-input="type">
    <input type="hidden" name="{{ $field('source') }}" value="{{ $item['source'] ?? '' }}" data-input="source">
    <input type="hidden" name="{{ $field('linkable_id') }}" value="{{ $item['linkable_id'] ?? '' }}" data-input="linkable_id">

    <div id="{{ $panel_id }}" class="mt-2 grid gap-3 rounded-lg border p-3 md:grid-cols-2" data-item-panel @unless($has_error) hidden @endunless>
        @foreach(['source', 'linkable_id', 'parent'] as $key)
            @error($error_key($key))
                <p class="text-sm text-destructive md:col-span-2" role="alert">{{ $message }}</p>
            @enderror
        @endforeach

        @if($locales === [])
            <label class="flex flex-col gap-1 text-sm">
                <span>{{ __('dynamic-menubuilder::strings.builder.title') }}</span>
                <input type="text" class="h-8 rounded-md border px-2" dir="auto" name="{{ $field('title') }}" value="{{ $item['title'] ?? '' }}" maxlength="255" data-input="title"
                       @if(filled($item['linkable_label'] ?? null)) placeholder="{{ $item['linkable_label'] }}" @endif>
                @error($error_key('title'))<span class="text-destructive" role="alert">{{ $message }}</span>@enderror
            </label>
            <label class="flex flex-col gap-1 text-sm" data-link-only @if($type !== $link_type) hidden @endif>
                <span>{{ __('dynamic-menubuilder::strings.builder.url') }}</span>
                <input type="text" class="h-8 rounded-md border px-2" dir="ltr" name="{{ $field('url') }}" value="{{ $item['url'] ?? '' }}" maxlength="2048" data-input="url">
                @error($error_key('url'))<span class="text-destructive" role="alert">{{ $message }}</span>@enderror
            </label>
        @else
            @foreach($locales as $locale)
                <label class="flex flex-col gap-1 text-sm">
                    <span>{{ __('dynamic-menubuilder::strings.builder.title') }} ({{ $locale['name'] }})</span>
                    <input type="text" class="h-8 rounded-md border px-2" dir="{{ $locale['direction'] }}" lang="{{ $locale['code'] }}"
                           name="{{ $field('title_' . $locale['code']) }}" value="{{ $item['title_' . $locale['code']] ?? '' }}" maxlength="255" data-input="title_{{ $locale['code'] }}"
                           @if(filled($item['linkable_label'] ?? null)) placeholder="{{ $item['linkable_label'] }}" @endif>
                    @error($error_key('title_' . $locale['code']))<span class="text-destructive" role="alert">{{ $message }}</span>@enderror
                </label>
                <label class="flex flex-col gap-1 text-sm" data-link-only @if($type !== $link_type) hidden @endif>
                    <span>{{ __('dynamic-menubuilder::strings.builder.url') }} ({{ $locale['name'] }})</span>
                    <input type="text" class="h-8 rounded-md border px-2" dir="ltr" name="{{ $field('url_' . $locale['code']) }}" value="{{ $item['url_' . $locale['code']] ?? '' }}" maxlength="2048" data-input="url_{{ $locale['code'] }}">
                </label>
            @endforeach
        @endif

        <label class="flex flex-col gap-1 text-sm">
            <span>{{ __('dynamic-menubuilder::strings.builder.visibility') }}</span>
            <select class="h-8 rounded-md border px-2" name="{{ $field('visibility') }}" data-input="visibility">
                @foreach($visibilities as $option)
                    <option value="{{ $option['value'] }}" @selected(($item['visibility'] ?? 'everyone') === $option['value'])>{{ $option['label'] }}</option>
                @endforeach
            </select>
        </label>

        <label class="flex flex-col gap-1 text-sm">
            <span>{{ __('dynamic-menubuilder::strings.builder.icon') }}</span>
            <input type="text" class="h-8 rounded-md border px-2" dir="ltr" name="{{ $field('icon') }}" value="{{ $item['icon'] ?? '' }}" maxlength="255" data-input="icon">
        </label>

        <label class="flex flex-col gap-1 text-sm">
            <span>{{ __('dynamic-menubuilder::strings.builder.css_class') }}</span>
            <input type="text" class="h-8 rounded-md border px-2" dir="ltr" name="{{ $field('css_class') }}" value="{{ $item['css_class'] ?? '' }}" maxlength="255" data-input="css_class">
        </label>

        <label class="flex flex-col gap-1 text-sm">
            <span>{{ __('dynamic-menubuilder::strings.builder.rel') }}</span>
            <input type="text" class="h-8 rounded-md border px-2" dir="ltr" name="{{ $field('rel') }}" value="{{ $item['rel'] ?? '' }}" maxlength="255" data-input="rel">
        </label>

        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="{{ $field('new_tab') }}" value="0" data-input="new_tab_off">
            <input type="checkbox" name="{{ $field('new_tab') }}" value="1" @checked(filter_var($item['new_tab'] ?? false, FILTER_VALIDATE_BOOL)) data-input="new_tab">
            <span>{{ __('dynamic-menubuilder::strings.builder.new_tab') }}</span>
        </label>

        @if($locales !== [])
            <fieldset class="flex flex-col gap-1 text-sm md:col-span-2">
                <legend>{{ __('dynamic-menubuilder::strings.builder.locales') }}</legend>
                <div class="flex flex-wrap gap-3">
                    @foreach($locales as $locale)
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="{{ $field('locales') }}[]" value="{{ $locale['code'] }}" @checked(in_array($locale['code'], (array) ($item['locales'] ?? []), true)) data-input="locales">
                            <span lang="{{ $locale['code'] }}">{{ $locale['name'] }}</span>
                        </label>
                    @endforeach
                </div>
                <span class="text-xs text-muted-foreground">{{ __('dynamic-menubuilder::strings.builder.locales_help') }}</span>
            </fieldset>
        @endif

        <div class="md:col-span-2">
            <x-forms::button type="button" color="secondary" size="sm" data-action="close">{{ __('dynamic-menubuilder::strings.builder.done') }}</x-forms::button>
        </div>
    </div>
</li>
