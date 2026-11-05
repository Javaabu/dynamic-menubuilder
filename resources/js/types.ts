import type { StencilSchema } from "@javaabu/stencil"

/** What a menu item links to */
export type MenuItemType = "link" | "model" | "index"

/** Who sees a menu item */
export type MenuItemVisibility = "everyone" | "guests" | "users"

/** One rendered menu item (PHP `MenuNode`), as Inertia shares it */
export interface MenuNode {
  id: number | string
  title: string
  /** null for a heading (a dropdown's label) */
  url: string | null
  type: MenuItemType
  /** 1 for the top level */
  depth: number
  target: "_blank" | null
  rel: string | null
  css_class: string | null
  icon: string | null
  /** The link is the current page */
  current: boolean
  /** The current page is this item, under it, or one of its children */
  active: boolean
  children: MenuNode[]
}

/** The menus Inertia shares: `config('dynamic-menubuilder.share')`, keyed by location */
export type SharedMenus = Record<string, MenuNode[]>

/** A menu location, as the builder gets it */
export interface MenuLocationOption {
  key: string
  label: string
  description: string | null
  max_depth: number
}

/** A linkable source, as the builder gets it */
export interface LinkableSourceOption {
  key: string
  label: string
  has_index: boolean
  index_title: string
}

/** An active language (javaabu/translatable), as the builder gets it */
export interface MenuLocale {
  code: string
  name: string
  direction: "ltr" | "rtl"
  default: boolean
}

/**
 * One item of the builder's list (PHP `MenuTree::builderItem()`). With javaabu/translatable,
 * the title and URL are `title_{locale}` and `url_{locale}`.
 */
export interface BuilderItem {
  key: string
  id?: number | null
  parent?: string | null
  type: MenuItemType
  source?: string | null
  linkable_id?: string | null
  linkable_label?: string | null
  source_label?: string | null
  broken?: boolean
  title?: string | null
  url?: string | null
  new_tab?: boolean
  rel?: string | null
  css_class?: string | null
  icon?: string | null
  visibility?: MenuItemVisibility
  locales?: string[]
  [translated: `title_${string}` | `url_${string}`]: string | null | undefined
}

/** The builder's text, translated on the server (`lang/*\/strings.php`, `builder`) */
export type BuilderStrings = Record<
  | "add_items"
  | "custom_link"
  | "title"
  | "url"
  | "url_help"
  | "add_to_menu"
  | "search"
  | "no_results"
  | "searching"
  | "index_page"
  | "add_index"
  | "structure"
  | "empty"
  | "drag"
  | "move_up"
  | "move_down"
  | "indent"
  | "outdent"
  | "remove"
  | "edit_item"
  | "done"
  | "new_tab"
  | "rel"
  | "css_class"
  | "icon"
  | "visibility"
  | "locales"
  | "locales_help"
  | "broken"
  | "untranslated"
  | "max_depth"
  | "default_title"
  | "moved"
  | "indented"
  | "outdented"
  | "removed"
  | "added"
  | "untitled"
  | "heading",
  string
>

export interface MenuAbilities {
  view: boolean
  update: boolean
  delete: boolean
}

export interface MenuLinks {
  edit: string
  update: string
  destroy: string
}

/** The text of the Inertia pages */
export interface MenusStrings {
  menus: string
  locations: string
  location: string
  menu: string
  name: string
  items: string
  new: string
  back: string
  edit: string
  delete: string
  actions: string
  search: string
  search_placeholder: string
  confirm_delete: string
  empty: string
  no_menu: string
  no_locations: string
}

/** Props every builder page gets */
export interface MenusPageProps {
  title: string
  guard: string
  urls: { index: string; create: string; store: string; search: string } & Partial<MenuLinks>
  can: { create: boolean } & MenuAbilities
  status: { success: string | null; error: string | null }
  strings: MenusStrings
}

export interface MenuRow {
  id: number
  name: string
  items_count: number
  /** The labels of its locations */
  locations: string[]
  links: MenuLinks
  can: MenuAbilities
}

export interface LocationRow extends MenuLocationOption {
  menu: { id: number; name: string; edit_url: string | null } | null
}

/** A Laravel length-aware paginator as JSON (`$paginator->toArray()`) */
export interface LaravelPaginator<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
  path: string
  first_page_url: string
  last_page_url: string
  next_page_url: string | null
  prev_page_url: string | null
  links: Array<{ url: string | null; label: string; active: boolean }>
}

/** dynamic-menubuilder::Menus/Index */
export interface MenusIndexProps extends MenusPageProps {
  rows: MenuRow[]
  menus: LaravelPaginator<unknown>
  locations: LocationRow[]
  search: string | null
}

/** A menu as JSON */
export interface MenuData {
  id: number
  name: string
  locations: string[]
  items: BuilderItem[]
  links: MenuLinks
  can: MenuAbilities
  created_at: string | null
  updated_at: string | null
}

/** dynamic-menubuilder::Menus/Form, for creating (menu is null) and editing */
export interface MenusFormProps extends MenusPageProps {
  form: StencilSchema
  menu: MenuData | null
}
