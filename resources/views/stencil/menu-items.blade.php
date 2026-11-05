{{--
    The menu builder (stencil type `menu-items`). The list is flat, in tree order; each item has
    a depth and posts `{name}[{index}][key|id|parent|type|source|linkable_id|title|url|…]`.
    resources/js/blade/menu-builder.ts adds dragging, the move buttons and adding items, and
    renumbers the inputs. Without the script, the items and their fields still save.
--}}
@php
    $errors ??= new \Illuminate\Support\ViewErrorBag;
    $name = $node->name;
    $items = \Javaabu\DynamicMenubuilder\Support\MenuTree::withDepths(array_values((array) ($stencil->value($node) ?? [])));
    $sources = $node->prop('sources', []);
    $locales = $node->prop('locales') ?? [];
    $visibilities = $node->prop('visibilities', []);
    $location_depths = collect($node->prop('locations', []))->mapWithKeys(fn ($location) => [$location['key'] => $location['max_depth']]);
    $id = 'menu-builder-' . str($name)->slug();
    $strings = $node->prop('strings', []);
@endphp

@manifestRequire('dynamic-menubuilder::builder')

<div class="dynamic-menubuilder flex flex-col gap-4"
     id="{{ $id }}"
     data-menu-builder
     data-name="{{ $name }}"
     data-search-url="{{ $node->prop('searchUrl') }}"
     data-locations-field="{{ $node->prop('locationsField') }}"
     data-default-max-depth="{{ $node->prop('defaultMaxDepth', 3) }}"
     data-location-depths='@json($location_depths, JSON_FORCE_OBJECT)'
     data-strings='@json($strings)'>

    @error($name)
        <x-forms::alert type="danger" role="alert" data-menu-builder-error>{{ $message }}</x-forms::alert>
    @enderror

    <div class="grid gap-6 lg:grid-cols-[minmax(0,20rem)_minmax(0,1fr)]">
        <div class="flex flex-col gap-3" data-menu-builder-add>
            <h3 class="font-semibold">{{ __('dynamic-menubuilder::strings.builder.add_items') }}</h3>

            <details class="rounded-lg border p-3" data-menu-builder-panel="link" open>
                <summary class="cursor-pointer font-medium">{{ __('dynamic-menubuilder::strings.builder.custom_link') }}</summary>
                <div class="mt-3 flex flex-col gap-3">
                    <label class="flex flex-col gap-1 text-sm">
                        <span>{{ __('dynamic-menubuilder::strings.builder.title') }}</span>
                        <input type="text" class="h-8 rounded-md border px-2" dir="auto" data-new-link-title>
                    </label>
                    <label class="flex flex-col gap-1 text-sm">
                        <span>{{ __('dynamic-menubuilder::strings.builder.url') }}</span>
                        <input type="text" class="h-8 rounded-md border px-2" dir="ltr" inputmode="url" data-new-link-url>
                        <span class="text-xs text-muted-foreground">{{ __('dynamic-menubuilder::strings.builder.url_help') }}</span>
                    </label>
                    <x-forms::button type="button" color="secondary" size="sm" start-icon="plus" data-add-link>
                        {{ __('dynamic-menubuilder::strings.builder.add_to_menu') }}
                    </x-forms::button>
                </div>
            </details>

            @foreach($sources as $source)
                <details class="rounded-lg border p-3" data-menu-builder-source="{{ $source['key'] }}" data-label="{{ $source['label'] }}">
                    <summary class="cursor-pointer font-medium" dir="auto">{{ $source['label'] }}</summary>
                    <div class="mt-3 flex flex-col gap-3">
                        <label class="flex flex-col gap-1 text-sm">
                            <span>{{ __('dynamic-menubuilder::strings.builder.search', ['source' => $source['label']]) }}</span>
                            <input type="search" class="h-8 rounded-md border px-2" dir="auto" data-source-search autocomplete="off">
                        </label>
                        <ul class="flex flex-col gap-1" data-source-results></ul>
                        <p class="text-sm text-muted-foreground" data-source-status aria-live="polite"></p>
                        @if($source['has_index'])
                            <x-forms::button type="button" color="secondary" size="sm" start-icon="list" data-add-index data-title="{{ $source['index_title'] }}">
                                {{ __('dynamic-menubuilder::strings.builder.add_index') }}
                            </x-forms::button>
                        @endif
                    </div>
                </details>
            @endforeach
        </div>

        <div class="flex flex-col gap-3">
            <h3 class="font-semibold" id="{{ $id }}-structure">{{ __('dynamic-menubuilder::strings.builder.structure') }}</h3>
            <p class="text-sm text-muted-foreground" data-menu-builder-depth-hint>
                {{ __('dynamic-menubuilder::strings.builder.max_depth', ['max' => $node->prop('defaultMaxDepth', 3)]) }}
            </p>

            <ol class="flex flex-col gap-2" aria-labelledby="{{ $id }}-structure" data-menu-builder-list>
                @foreach($items as $index => $item)
                    @include('dynamic-menubuilder::stencil.partials.menu-item', ['index' => $index, 'item' => $item])
                @endforeach
            </ol>

            <p class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground" data-menu-builder-empty @if($items !== []) hidden @endif>
                {{ __('dynamic-menubuilder::strings.builder.empty') }}
            </p>

            <div class="sr-only" role="status" aria-live="polite" data-menu-builder-status></div>
        </div>
    </div>

    <template data-menu-builder-template>
        @include('dynamic-menubuilder::stencil.partials.menu-item', ['index' => '__INDEX__', 'item' => ['key' => '__KEY__', 'type' => 'link', 'depth' => 1]])
    </template>
</div>
