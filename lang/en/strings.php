<?php

return [
    'menus' => 'Menus',
    'search' => 'Search menus',

    'types' => [
        'link' => 'Link',
        'model' => 'Record',
        'index' => 'Index page',
    ],

    'visibility' => [
        'everyone' => 'Everyone',
        'guests' => 'Signed-out visitors',
        'users' => 'Signed-in users',
    ],

    'fields' => [
        'name' => 'Name',
        'locations' => 'Show in',
        'location' => 'Location',
        'menu' => 'Menu',
        'items' => 'Menu items',
    ],

    'help' => [
        'locations' => 'A location shows one menu: ticking one takes it from the menu it shows now.',
    ],

    'sections' => [
        'details' => 'Menu',
        'items' => 'Items',
        'locations' => 'Menu locations',
    ],

    'actions' => [
        'new' => 'New menu',
        'create' => 'Create menu',
        'save' => 'Save menu',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'back' => 'Back to menus',
        'actions' => 'Actions',
        'search' => 'Search',
    ],

    'messages' => [
        'created' => 'Menu created.',
        'updated' => 'Menu saved.',
        'deleted' => 'Menu deleted.',
        'confirm_delete' => 'Delete this menu? Its items are deleted too.',
        'empty' => 'No menus yet.',
        'no_menu' => 'No menu',
        'no_locations' => 'The app has no menu locations yet.',
    ],

    'validation' => [
        'parent' => 'This item is nested under an item that isn\'t in the menu.',
        'depth' => '{1} The menu\'s locations show only one level: take the items out of their dropdowns.|[2,*] The menu\'s locations show only :max levels.',
        'source' => 'What this item links to can\'t be added to menus any more.',
        'index' => 'This kind of record has no index page.',
        'record' => 'The record this item links to no longer exists. Remove the item or pick another record.',
    ],

    'builder' => [
        'add_items' => 'Add items',
        'custom_link' => 'Link',
        'title' => 'Title',
        'url' => 'URL',
        'url_help' => 'Leave the URL empty for a heading, such as the label of a dropdown.',
        'add_to_menu' => 'Add to menu',
        'search' => 'Search :source',
        'no_results' => 'Nothing found.',
        'searching' => 'Searching…',
        'index_page' => 'All :source',
        'add_index' => 'Add the index page',
        'structure' => 'Menu structure',
        'empty' => 'The menu has no items. Add items from the list.',
        'drag' => 'Drag to reorder :title',
        'move_up' => 'Move up',
        'move_down' => 'Move down',
        'indent' => 'Move under the item above',
        'outdent' => 'Move out of its parent',
        'remove' => 'Remove',
        'edit_item' => 'Edit :title',
        'done' => 'Done',
        'new_tab' => 'Open in a new tab',
        'rel' => 'Link relationship (rel)',
        'css_class' => 'CSS classes',
        'icon' => 'Icon',
        'visibility' => 'Show to',
        'locales' => 'Show in these languages',
        'locales_help' => 'Leave all unticked to show it in every language.',
        'broken' => 'The record this item linked to was deleted. Remove the item.',
        'untranslated' => 'No title in :language: it falls back to the original.',
        'max_depth' => 'This menu\'s locations show up to :max levels.',
        'default_title' => 'Uses the title of :title',
        'moved' => ':title moved to position :position of :total.',
        'indented' => ':title is now under :parent.',
        'outdented' => ':title moved out of :parent.',
        'removed' => ':title removed.',
        'added' => ':title added to the menu.',
        'untitled' => 'Untitled',
        'heading' => 'Heading',
    ],
];
