{{-- Links to a menu's pages, for what the user may do. $links and $abilities come from the controller. --}}
<div class="flex flex-wrap items-center gap-2" data-menu-actions>
    @if(($show_edit ?? true) && ($abilities['update'] ?? false))
        <x-forms::link-button :url="$links['edit']" color="secondary" size="sm" start-icon="pencil">
            {{ __('dynamic-menubuilder::strings.actions.edit') }}
        </x-forms::link-button>
    @endif

    @if($abilities['delete'] ?? false)
        <x-forms::form
            method="DELETE"
            :action="$links['destroy']"
            data-confirm="{{ __('dynamic-menubuilder::strings.messages.confirm_delete') }}"
            onsubmit="return window.confirm(this.dataset.confirm)">
            <x-forms::submit color="danger" size="sm" start-icon="trash-2" :animate="false">{{ __('dynamic-menubuilder::strings.actions.delete') }}</x-forms::submit>
        </x-forms::form>
    @endif
</div>
