@extends($layout)

@section($layout_section)
    <div class="flex flex-col gap-6" data-menus-index>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold">{{ $title }}</h1>

            @if($can['create'])
                <x-forms::link-button :url="$urls['create']" start-icon="plus">
                    {{ __('dynamic-menubuilder::strings.actions.new') }}
                </x-forms::link-button>
            @endif
        </div>

        @include('dynamic-menubuilder::menus.partials.status')

        <section class="flex flex-col gap-3" data-menu-locations aria-labelledby="menu-locations-heading">
            <h2 id="menu-locations-heading" class="text-lg font-semibold">{{ __('dynamic-menubuilder::strings.sections.locations') }}</h2>

            @if($locations === [])
                <p class="text-muted-foreground">{{ __('dynamic-menubuilder::strings.messages.no_locations') }}</p>
            @else
                <x-forms::table model="menu-locations" no-checkbox>
                    <x-slot:headers>
                        <x-forms::table.heading :label="__('dynamic-menubuilder::strings.fields.location')" />
                        <x-forms::table.heading :label="__('dynamic-menubuilder::strings.fields.menu')" />
                    </x-slot:headers>

                    <x-slot:rows>
                        @foreach($locations as $location)
                            <x-forms::table.row :model="$location['key']" name="menu-locations" no-checkbox data-menu-location-row="{{ $location['key'] }}">
                                <x-forms::table.cell name="location" :label="__('dynamic-menubuilder::strings.fields.location')">
                                    <span class="font-medium" dir="auto">{{ $location['label'] }}</span>
                                    @if($location['description'])
                                        <span class="block text-sm text-muted-foreground" dir="auto">{{ $location['description'] }}</span>
                                    @endif
                                </x-forms::table.cell>
                                <x-forms::table.cell name="menu" :label="__('dynamic-menubuilder::strings.fields.menu')">
                                    @if($location['menu'] && $location['menu']['edit_url'])
                                        <a href="{{ $location['menu']['edit_url'] }}" class="hover:underline" dir="auto">{{ $location['menu']['name'] }}</a>
                                    @elseif($location['menu'])
                                        <span dir="auto">{{ $location['menu']['name'] }}</span>
                                    @else
                                        <span class="text-muted-foreground">{{ __('dynamic-menubuilder::strings.messages.no_menu') }}</span>
                                    @endif
                                </x-forms::table.cell>
                            </x-forms::table.row>
                        @endforeach
                    </x-slot:rows>
                </x-forms::table>
            @endif
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="menus-heading">
            <h2 id="menus-heading" class="text-lg font-semibold">{{ __('dynamic-menubuilder::strings.menus') }}</h2>

            <x-forms::form method="GET" :action="$urls['index']" class="flex flex-wrap items-end gap-2" data-menus-search>
                <x-forms::text name="search" :label="__('dynamic-menubuilder::strings.actions.search')" :placeholder="__('dynamic-menubuilder::strings.search')" :value="$search" show-placeholder />
                <x-forms::submit color="secondary" :animate="false">{{ __('dynamic-menubuilder::strings.actions.search') }}</x-forms::submit>
            </x-forms::form>

            <x-forms::table model="menus" no-checkbox>
                <x-slot:headers>
                    <x-forms::table.heading :label="__('dynamic-menubuilder::strings.fields.name')" />
                    <x-forms::table.heading :label="__('dynamic-menubuilder::strings.fields.locations')" />
                    <x-forms::table.heading :label="__('dynamic-menubuilder::strings.fields.items')" />
                    <x-forms::table.heading :label="__('dynamic-menubuilder::strings.actions.actions')" />
                </x-slot:headers>

                <x-slot:rows>
                    @forelse($rows as $row)
                        <x-forms::table.row :model="$row['id']" name="menus" no-checkbox data-menu-row="{{ $row['id'] }}">
                            <x-forms::table.cell name="name" :label="__('dynamic-menubuilder::strings.fields.name')">
                                @if($row['can']['update'])
                                    <a href="{{ $row['links']['edit'] }}" class="font-medium hover:underline" dir="auto">{{ $row['name'] }}</a>
                                @else
                                    <span dir="auto">{{ $row['name'] }}</span>
                                @endif
                            </x-forms::table.cell>
                            <x-forms::table.cell name="locations" :label="__('dynamic-menubuilder::strings.fields.locations')">
                                <span dir="auto">{{ implode('، ', $row['locations']) ?: '—' }}</span>
                            </x-forms::table.cell>
                            <x-forms::table.cell name="items" :label="__('dynamic-menubuilder::strings.fields.items')">
                                <span dir="ltr">{{ $row['items_count'] }}</span>
                            </x-forms::table.cell>
                            <x-forms::table.cell name="actions" :label="__('dynamic-menubuilder::strings.actions.actions')">
                                @include('dynamic-menubuilder::menus.partials.actions', ['links' => $row['links'], 'abilities' => $row['can']])
                            </x-forms::table.cell>
                        </x-forms::table.row>
                    @empty
                        <x-forms::table.empty-row :columns="4" no-checkbox>
                            <p class="font-medium">{{ __('dynamic-menubuilder::strings.messages.empty') }}</p>
                        </x-forms::table.empty-row>
                    @endforelse
                </x-slot:rows>

                @if($menus->hasPages())
                    <x-slot:pagination>
                        <x-forms::pagination :paginator="$menus" />
                    </x-slot:pagination>
                @endif
            </x-forms::table>
        </section>
    </div>
@endsection
