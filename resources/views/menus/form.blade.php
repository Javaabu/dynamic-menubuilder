@extends($layout)

@section($layout_section)
    <div class="flex flex-col gap-6" data-menus-form>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold" dir="auto">{{ $title }}</h1>
            <x-forms::link-button :url="$urls['index']" color="secondary" size="sm" start-icon="chevron-left">
                {{ __('dynamic-menubuilder::strings.actions.back') }}
            </x-forms::link-button>
        </div>

        @include('dynamic-menubuilder::menus.partials.status')

        {{ $form }}

        @if($menu && $can['delete'])
            <div class="flex justify-end">
                @include('dynamic-menubuilder::menus.partials.actions', ['links' => $urls, 'abilities' => $can, 'show_edit' => false])
            </div>
        @endif
    </div>
@endsection
