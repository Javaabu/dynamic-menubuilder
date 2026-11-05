import {
  splitFieldProps,
  useField,
  useFieldState,
  useFieldValue,
  useFormState,
  usePublishField,
  type FieldProps,
} from "@javaabu/forms"
import { Button, Checkbox, Label, SortableList, TextInput } from "@javaabu/onu"
import * as React from "react"
import {
  canIndent,
  canMoveDown,
  canMoveUp,
  canOutdent,
  format,
  indent,
  moveDown,
  moveTo,
  moveUp,
  newKey,
  outdent,
  parents,
  remove,
  siblingPosition,
  treeDepth,
  withDepths,
  type TreeItem,
} from "../tree"
import type { BuilderItem, BuilderStrings, LinkableSourceOption, MenuLocale, MenuLocationOption } from "../types"

export interface MenuItemsFieldProps extends FieldProps {
  /** The linkable sources admins can add records (and index pages) from */
  sources?: LinkableSourceOption[]
  /** Every location, so the depth can follow the ticked ones */
  locations?: MenuLocationOption[]
  /** The name of the location checkboxes (`locations` for `locations[]`); null to ignore them */
  locationsField?: string | null
  /** The depth when no location is ticked */
  defaultMaxDepth?: number
  /** A fixed maximum depth, over the locations' */
  maxDepth?: number
  /** The record search, with `{source}` for the source key */
  searchUrl?: string | null
  /** The active languages (javaabu/translatable); null or empty for one title and URL */
  locales?: MenuLocale[] | null
  visibilities?: Array<{ value: string; label: string }>
  strings: BuilderStrings
}

type Item = BuilderItem & TreeItem

/** The fields each item posts, besides the per-locale title and URL */
const POSTED_FIELDS = [
  "key",
  "id",
  "parent",
  "type",
  "source",
  "linkable_id",
  "new_tab",
  "rel",
  "css_class",
  "icon",
  "visibility",
] as const

function stripDepths(items: Item[]): BuilderItem[] {
  const parentKeys = parents(items)

  return items.map(({ depth: _depth, ...item }) => ({ ...item, parent: parentKeys[item.key] ?? null }))
}

/** The location depths ticked in the form, or the default */
function useMaxDepth(
  locations: MenuLocationOption[],
  locationsField: string | null | undefined,
  fallback: number,
  fixed?: number,
): number {
  const formRef = useFormState()?.formRef
  const [ticked, setTicked] = React.useState<string[] | null>(null)

  React.useEffect(() => {
    const form = formRef?.current
    if (!form || !locationsField) return

    const read = () =>
      setTicked(
        Array.from(form.querySelectorAll<HTMLInputElement>(`input[name="${locationsField}[]"]:checked`)).map(
          (input) => input.value,
        ),
      )

    // Onu's checkboxes update after the click that toggles them, so read once it has settled
    const timers = new Set<ReturnType<typeof setTimeout>>()
    const readSoon = () => {
      read()
      const timer = setTimeout(() => {
        timers.delete(timer)
        read()
      }, 0)
      timers.add(timer)
    }

    read()
    form.addEventListener("change", readSoon)
    form.addEventListener("click", readSoon)

    return () => {
      form.removeEventListener("change", readSoon)
      form.removeEventListener("click", readSoon)
      timers.forEach((timer) => clearTimeout(timer))
    }
  }, [formRef, locationsField])

  if (fixed) return fixed

  const depths = (ticked ?? [])
    .map((key) => locations.find((location) => location.key === key)?.max_depth)
    .filter((depth): depth is number => typeof depth === "number")

  return depths.length ? Math.min(...depths) : fallback
}

/**
 * The drag-and-drop menu builder as a @javaabu/forms field: its value is the flat list of
 * items (in tree order, each naming its parent), read and written with `useFieldState`, so it
 * works with `Form`, Inertia `useForm()`, `resetOnSuccess` and errors like any built-in field.
 *
 * Items move by dragging the handle (mouse, touch, or Space and the arrow keys), with
 * Alt + arrow keys on the handle, or with the Move up / Move down / Indent / Outdent buttons.
 */
export function MenuItemsField(props: MenuItemsFieldProps): React.JSX.Element {
  const { field, rest } = splitFieldProps(props)
  const {
    sources = [],
    locations = [],
    locationsField = "locations",
    defaultMaxDepth = 3,
    maxDepth: fixedMaxDepth,
    searchUrl,
    locales,
    visibilities = [],
    strings,
  } = rest
  const f = useField(field)
  const formState = useFormState()
  const starting = useFieldValue(field.name, field.model, field.default)
  const startingItems = React.useMemo(() => (Array.isArray(starting) ? (starting as BuilderItem[]) : []), [starting])
  const [value, setValue] = useFieldState<BuilderItem[]>(field.name, startingItems)
  const items = React.useMemo<Item[]>(() => withDepths(Array.isArray(value) ? value : []), [value])
  const maxDepth = useMaxDepth(locations, locationsField, defaultMaxDepth, fixedMaxDepth)
  const [expanded, setExpanded] = React.useState<Set<string>>(() => new Set())
  const [announcement, setAnnouncement] = React.useState("")
  const listRef = React.useRef<HTMLDivElement>(null)
  const translated = !!locales?.length
  const defaultLocale = locales?.find((locale) => locale.default)?.code ?? locales?.[0]?.code

  usePublishField({ id: f.id, name: field.name, kind: "value", value, disabled: f.disabled, label: f.labelText })

  const update = (next: Item[]) => setValue(stripDepths(next))

  const titleOf = (item: BuilderItem): string => {
    const own = translated && defaultLocale ? item[`title_${defaultLocale}`] : item.title

    return (own || item.linkable_label || strings.untitled || "").toString()
  }

  const urlOf = (item: BuilderItem): string =>
    ((translated && defaultLocale ? item[`url_${defaultLocale}`] : item.url) || "").toString()

  const typeLabel = (item: BuilderItem): string => {
    if (item.type !== "link") return item.source_label ?? sources.find((s) => s.key === item.source)?.label ?? ""

    return urlOf(item) ? strings.custom_link : strings.heading
  }

  const focusButton = (key: string, action: string) => {
    requestAnimationFrame(() => {
      const row = listRef.current?.querySelector<HTMLElement>(`[data-menu-item="${CSS.escape(key)}"]`)
      const button = row?.querySelector<HTMLButtonElement>(`[data-action="${action}"]:not(:disabled)`)
      ;(button ?? row?.querySelector<HTMLElement>("[data-action='drag'], button"))?.focus()
    })
  }

  const act = (action: "move-up" | "move-down" | "indent" | "outdent" | "remove", index: number) => {
    const item = items[index]
    if (!item) return

    const title = titleOf(item)
    const parentBefore = parents(items)[item.key]
    const operations = {
      "move-up": () => moveUp(items, index),
      "move-down": () => moveDown(items, index),
      indent: () => indent(items, index, maxDepth),
      outdent: () => outdent(items, index),
      remove: () => remove(items, index),
    }
    const next = operations[action]()

    update(next)

    if (action === "remove") {
      setAnnouncement(format(strings.removed, { title }))
      return
    }

    const newIndex = next.findIndex((entry) => entry.key === item.key)
    const parentKey = parents(next)[item.key]

    if (action === "indent") {
      const parent = next.find((entry) => entry.key === parentKey)
      setAnnouncement(format(strings.indented, { title, parent: parent ? titleOf(parent) : "" }))
    } else if (action === "outdent") {
      const parent = items.find((entry) => entry.key === parentBefore)
      setAnnouncement(format(strings.outdented, { title, parent: parent ? titleOf(parent) : "" }))
    } else {
      const { position, total } = siblingPosition(next, newIndex)
      setAnnouncement(format(strings.moved, { title, position, total }))
    }

    focusButton(item.key, action)
  }

  const setItem = (key: string, changes: Partial<BuilderItem>) => {
    update(items.map((item) => (item.key === key ? ({ ...item, ...changes } as Item) : item)))
    formState?.clearErrors(field.name)
  }

  const add = (item: Omit<BuilderItem, "key">, label: string) => {
    const key = newKey()
    update([...items, { visibility: "everyone", new_tab: false, locales: [], ...item, key, depth: 1 } as Item])
    setAnnouncement(format(strings.added, { title: label || strings.untitled }))
  }

  const addLink = (title: string, url: string) => {
    const texts: Partial<BuilderItem> =
      translated && defaultLocale
        ? { [`title_${defaultLocale}`]: title, [`url_${defaultLocale}`]: url }
        : { title, url }

    add({ type: "link", ...texts }, title)
  }

  const toggle = (key: string, open?: boolean) =>
    setExpanded((current) => {
      const next = new Set(current)
      if (open ?? !next.has(key)) next.add(key)
      else next.delete(key)
      return next
    })

  const errors = formState?.errors ?? {}
  const errorsFor = (index: number) =>
    Object.entries(errors)
      .filter(([key]) => key.startsWith(`${field.name}.${index}.`))
      .flatMap(([, messages]) => (Array.isArray(messages) ? messages : [messages]))
      .map(String)

  return f.wrap(
    <div className="dynamic-menubuilder flex flex-col gap-4" id={f.id} data-menu-builder-react>
      <div className="grid gap-6 lg:grid-cols-[minmax(0,20rem)_minmax(0,1fr)]">
        <AddPanel
          sources={sources}
          searchUrl={searchUrl}
          strings={strings}
          disabled={f.disabled}
          onAddLink={addLink}
          onAddRecord={(source, record) =>
            add(
              {
                type: "model",
                source: source.key,
                linkable_id: String(record.id),
                linkable_label: record.label,
                source_label: source.label,
              },
              record.label,
            )
          }
          onAddIndex={(source) =>
            add(
              { type: "index", source: source.key, linkable_label: source.index_title, source_label: source.label },
              source.index_title,
            )
          }
        />

        <div className="flex flex-col gap-3" ref={listRef}>
          <h3 className="font-semibold" id={`${f.baseId}-structure`}>
            {strings.structure}
          </h3>
          <p className="text-sm text-muted-foreground" data-menu-builder-depth-hint>
            {format(strings.max_depth, { max: maxDepth })}
          </p>

          {items.length ? (
            <SortableList
              aria-label={strings.structure}
              items={items}
              getId={(item) => item.key}
              getName={titleOf}
              moveButtons={false}
              disabled={f.disabled}
              classNames={{ item: "list-none" }}
              className="flex flex-col gap-2"
              onReorder={(_next, { from, to }) => {
                const next = moveTo(items, from, to, undefined, maxDepth)
                const index = next.findIndex((entry) => entry.key === items[from]?.key)
                update(next)
                if (index !== -1) {
                  const { position, total } = siblingPosition(next, index)
                  setAnnouncement(format(strings.moved, { title: titleOf(next[index]!), position, total }))
                }
              }}
              renderItem={(item, { handle, index }) => (
                <MenuItemRow
                  item={item}
                  index={index}
                  name={field.name}
                  handle={handle}
                  title={titleOf(item)}
                  typeLabel={typeLabel(item)}
                  strings={strings}
                  locales={locales ?? []}
                  visibilities={visibilities}
                  errors={errorsFor(index)}
                  expanded={expanded.has(item.key) || errorsFor(index).length > 0}
                  disabled={!!f.disabled}
                  can={{
                    "move-up": canMoveUp(items, index),
                    "move-down": canMoveDown(items, index),
                    indent: canIndent(items, index, maxDepth),
                    outdent: canOutdent(items, index),
                  }}
                  onAction={(action) => act(action, index)}
                  onToggle={(open) => toggle(item.key, open)}
                  onChange={(changes) => setItem(item.key, changes)}
                />
              )}
            />
          ) : (
            <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground" data-menu-builder-empty>
              {strings.empty}
            </p>
          )}

          {treeDepth(items) > maxDepth ? (
            <p className="text-sm text-destructive" role="alert" data-menu-builder-too-deep>
              {format(strings.max_depth, { max: maxDepth })}
            </p>
          ) : null}

          <div className="sr-only" role="status" aria-live="polite" data-menu-builder-status>
            {announcement}
          </div>
        </div>
      </div>

      {/* Native and router submits read the form's inputs */}
      <div hidden>
        {stripDepths(items).map((item, index) => (
          <React.Fragment key={item.key}>
            {POSTED_FIELDS.map((key) => (
              <input
                key={key}
                type="hidden"
                name={`${field.name}[${index}][${key}]`}
                value={key === "new_tab" ? (item.new_tab ? "1" : "0") : String(item[key] ?? "")}
                disabled={f.disabled}
              />
            ))}
            {translated
              ? locales!.flatMap((locale) =>
                  (["title", "url"] as const).map((key) => (
                    <input
                      key={`${key}_${locale.code}`}
                      type="hidden"
                      name={`${field.name}[${index}][${key}_${locale.code}]`}
                      value={String(item[`${key}_${locale.code}`] ?? "")}
                      disabled={f.disabled}
                    />
                  )),
                )
              : (["title", "url"] as const).map((key) => (
                  <input
                    key={key}
                    type="hidden"
                    name={`${field.name}[${index}][${key}]`}
                    value={String(item[key] ?? "")}
                    disabled={f.disabled}
                  />
                ))}
            {(item.locales ?? []).map((locale) => (
              <input
                key={`locale-${locale}`}
                type="hidden"
                name={`${field.name}[${index}][locales][]`}
                value={locale}
                disabled={f.disabled}
              />
            ))}
          </React.Fragment>
        ))}
      </div>
    </div>,
  )
}

type RowAction = "move-up" | "move-down" | "indent" | "outdent"

interface MenuItemRowProps {
  item: Item
  index: number
  name: string
  handle: React.ReactNode
  title: string
  typeLabel: string
  strings: BuilderStrings
  locales: MenuLocale[]
  visibilities: Array<{ value: string; label: string }>
  errors: string[]
  expanded: boolean
  disabled: boolean
  can: Record<RowAction, boolean>
  onAction: (action: RowAction | "remove") => void
  onToggle: (open?: boolean) => void
  onChange: (changes: Partial<BuilderItem>) => void
}

function MenuItemRow({
  item,
  index,
  name,
  handle,
  title,
  typeLabel,
  strings,
  locales,
  visibilities,
  errors,
  expanded,
  disabled,
  can,
  onAction,
  onToggle,
  onChange,
}: MenuItemRowProps) {
  const panelId = `${name}-item-${item.key}`.replace(/[^\w-]/g, "-")
  const isLink = item.type === "link"
  const rtl = (event: React.KeyboardEvent) =>
    (event.currentTarget as HTMLElement).closest("[dir]")?.getAttribute("dir") === "rtl"

  const onKeyDown = (event: React.KeyboardEvent<HTMLDivElement>) => {
    if (!event.altKey || !(event.target as HTMLElement).closest("[data-slot='sortable-handle'], [data-action='drag']"))
      return

    const actions: Record<string, RowAction> = {
      ArrowRight: rtl(event) ? "outdent" : "indent",
      ArrowLeft: rtl(event) ? "indent" : "outdent",
    }
    const action = actions[event.key]

    if (!action) return

    event.preventDefault()
    event.stopPropagation()
    if (can[action]) onAction(action)
  }

  const button = (action: RowAction, label: string, symbol: string, mirrored = false) => (
    <Button
      type="button"
      variant="outline"
      size="sm"
      data-action={action}
      disabled={disabled || !can[action]}
      title={label}
      aria-label={`${label}: ${title}`}
      onClick={() => onAction(action)}
    >
      <span aria-hidden="true" className={mirrored ? "inline-block rtl:-scale-x-100" : undefined}>
        {symbol}
      </span>
    </Button>
  )

  return (
    <div
      data-menu-item={item.key}
      data-depth={item.depth}
      data-type={item.type}
      aria-level={item.depth}
      style={{ marginInlineStart: `${(item.depth - 1) * 1.5}rem` }}
      onKeyDown={onKeyDown}
    >
      <div
        className={`flex flex-wrap items-center gap-2 rounded-lg border bg-card p-2 ${item.broken ? "border-destructive" : ""}`}
      >
        <span data-action="drag">{handle}</span>
        <span className="min-w-0 flex-1 truncate font-medium" dir="auto" data-item-label>
          {title}
        </span>
        <span className="rounded bg-muted px-2 py-0.5 text-xs text-muted-foreground" dir="auto" data-item-type>
          {typeLabel}
        </span>
        <span className="flex flex-wrap items-center gap-1">
          {button("move-up", strings.move_up, "↑")}
          {button("move-down", strings.move_down, "↓")}
          {button("outdent", strings.outdent, "⇤", true)}
          {button("indent", strings.indent, "⇥", true)}
          <Button
            type="button"
            variant="outline"
            size="sm"
            data-action="toggle"
            aria-expanded={expanded}
            aria-controls={panelId}
            onClick={() => onToggle()}
          >
            {format(strings.edit_item, { title })}
          </Button>
          <Button
            type="button"
            variant="destructive"
            size="sm"
            data-action="remove"
            disabled={disabled}
            aria-label={`${strings.remove}: ${title}`}
            onClick={() => onAction("remove")}
          >
            {strings.remove}
          </Button>
        </span>
      </div>

      {item.broken ? (
        <p className="mt-1 text-sm text-destructive" data-item-broken>
          {strings.broken}
        </p>
      ) : null}

      {expanded ? (
        <div id={panelId} className="mt-2 grid gap-3 rounded-lg border p-3 md:grid-cols-2" data-item-panel>
          {errors.map((error) => (
            <p key={error} className="text-sm text-destructive md:col-span-2" role="alert">
              {error}
            </p>
          ))}

          {locales.length
            ? locales.map((locale) => (
                <React.Fragment key={locale.code}>
                  <TextField
                    id={`${panelId}-title-${locale.code}`}
                    label={`${strings.title} (${locale.name})`}
                    value={String(item[`title_${locale.code}`] ?? "")}
                    placeholder={item.linkable_label ?? undefined}
                    dir={locale.direction}
                    lang={locale.code}
                    thaana={locale.code === "dv"}
                    disabled={disabled}
                    onChange={(value) => onChange({ [`title_${locale.code}`]: value })}
                  />
                  {isLink ? (
                    <TextField
                      id={`${panelId}-url-${locale.code}`}
                      label={`${strings.url} (${locale.name})`}
                      value={String(item[`url_${locale.code}`] ?? "")}
                      dir="ltr"
                      disabled={disabled}
                      onChange={(value) => onChange({ [`url_${locale.code}`]: value })}
                    />
                  ) : null}
                </React.Fragment>
              ))
            : [
                <TextField
                  key="title"
                  id={`${panelId}-title`}
                  label={strings.title}
                  value={item.title ?? ""}
                  placeholder={item.linkable_label ?? undefined}
                  dir="auto"
                  disabled={disabled}
                  onChange={(value) => onChange({ title: value })}
                />,
                isLink ? (
                  <TextField
                    key="url"
                    id={`${panelId}-url`}
                    label={strings.url}
                    help={strings.url_help}
                    value={item.url ?? ""}
                    dir="ltr"
                    disabled={disabled}
                    onChange={(value) => onChange({ url: value })}
                  />
                ) : null,
              ]}

          <div className="flex flex-col gap-1 text-sm">
            <Label htmlFor={`${panelId}-visibility`}>{strings.visibility}</Label>
            <select
              id={`${panelId}-visibility`}
              className="h-8 rounded-md border bg-background px-2"
              value={item.visibility ?? "everyone"}
              disabled={disabled}
              onChange={(event) => onChange({ visibility: event.target.value as BuilderItem["visibility"] })}
            >
              {visibilities.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>

          <TextField
            id={`${panelId}-icon`}
            label={strings.icon}
            value={item.icon ?? ""}
            dir="ltr"
            disabled={disabled}
            onChange={(value) => onChange({ icon: value })}
          />
          <TextField
            id={`${panelId}-css-class`}
            label={strings.css_class}
            value={item.css_class ?? ""}
            dir="ltr"
            disabled={disabled}
            onChange={(value) => onChange({ css_class: value })}
          />
          <TextField
            id={`${panelId}-rel`}
            label={strings.rel}
            value={item.rel ?? ""}
            dir="ltr"
            disabled={disabled}
            onChange={(value) => onChange({ rel: value })}
          />

          <Checkbox
            id={`${panelId}-new-tab`}
            label={strings.new_tab}
            checked={!!item.new_tab}
            disabled={disabled}
            onCheckedChange={(checked) => onChange({ new_tab: checked === true })}
          />

          {locales.length ? (
            <fieldset className="flex flex-col gap-1 text-sm md:col-span-2">
              <legend>{strings.locales}</legend>
              <div className="flex flex-wrap gap-3">
                {locales.map((locale) => (
                  <Checkbox
                    key={locale.code}
                    id={`${panelId}-locale-${locale.code}`}
                    label={<span lang={locale.code}>{locale.name}</span>}
                    checked={(item.locales ?? []).includes(locale.code)}
                    disabled={disabled}
                    onCheckedChange={(checked) =>
                      onChange({
                        locales:
                          checked === true
                            ? [...(item.locales ?? []), locale.code]
                            : (item.locales ?? []).filter((code) => code !== locale.code),
                      })
                    }
                  />
                ))}
              </div>
              <span className="text-xs text-muted-foreground">{strings.locales_help}</span>
            </fieldset>
          ) : null}

          <div className="md:col-span-2">
            <Button type="button" variant="outline" size="sm" data-action="close" onClick={() => onToggle(false)}>
              {strings.done}
            </Button>
          </div>
        </div>
      ) : null}

      <span hidden data-item-index={index} />
    </div>
  )
}

function TextField({
  id,
  label,
  help,
  value,
  onChange,
  ...props
}: {
  id: string
  label: string
  help?: string
  value: string
  onChange: (value: string) => void
} & Omit<React.ComponentProps<typeof TextInput>, "id" | "value" | "onChange">) {
  return (
    <div className="flex flex-col gap-1 text-sm">
      <Label htmlFor={id}>{label}</Label>
      <TextInput {...props} id={id} value={value} onChange={(event) => onChange(event.target.value)} />
      {help ? <span className="text-xs text-muted-foreground">{help}</span> : null}
    </div>
  )
}

interface SearchResult {
  id: number | string
  label: string
}

interface AddPanelProps {
  sources: LinkableSourceOption[]
  searchUrl?: string | null
  strings: BuilderStrings
  disabled?: boolean
  onAddLink: (title: string, url: string) => void
  onAddRecord: (source: LinkableSourceOption, record: SearchResult) => void
  onAddIndex: (source: LinkableSourceOption) => void
}

/** Custom links, and each source's records and index page */
function AddPanel({ sources, searchUrl, strings, disabled, onAddLink, onAddRecord, onAddIndex }: AddPanelProps) {
  const [title, setTitle] = React.useState("")
  const [url, setUrl] = React.useState("")
  const titleRef = React.useRef<HTMLInputElement>(null)

  const addLink = () => {
    if (!title.trim()) {
      titleRef.current?.focus()
      return
    }

    onAddLink(title.trim(), url.trim())
    setTitle("")
    setUrl("")
    titleRef.current?.focus()
  }

  return (
    <div className="flex flex-col gap-3" data-menu-builder-add>
      <h3 className="font-semibold">{strings.add_items}</h3>

      <details className="rounded-lg border p-3" open data-menu-builder-panel="link">
        <summary className="cursor-pointer font-medium">{strings.custom_link}</summary>
        <div className="mt-3 flex flex-col gap-3">
          <div className="flex flex-col gap-1 text-sm">
            <Label htmlFor="menu-builder-new-title">{strings.title}</Label>
            <TextInput
              id="menu-builder-new-title"
              ref={titleRef}
              dir="auto"
              value={title}
              disabled={disabled}
              onChange={(event) => setTitle(event.target.value)}
              onKeyDown={(event) => {
                if (event.key === "Enter") {
                  event.preventDefault()
                  addLink()
                }
              }}
            />
          </div>
          <div className="flex flex-col gap-1 text-sm">
            <Label htmlFor="menu-builder-new-url">{strings.url}</Label>
            <TextInput
              id="menu-builder-new-url"
              dir="ltr"
              inputMode="url"
              value={url}
              disabled={disabled}
              onChange={(event) => setUrl(event.target.value)}
              onKeyDown={(event) => {
                if (event.key === "Enter") {
                  event.preventDefault()
                  addLink()
                }
              }}
            />
            <span className="text-xs text-muted-foreground">{strings.url_help}</span>
          </div>
          <Button
            type="button"
            variant="outline"
            size="sm"
            startIcon="plus"
            disabled={disabled}
            onClick={addLink}
            data-add-link
          >
            {strings.add_to_menu}
          </Button>
        </div>
      </details>

      {sources.map((source) => (
        <SourcePanel
          key={source.key}
          source={source}
          searchUrl={searchUrl}
          strings={strings}
          disabled={disabled}
          onAddRecord={(record) => onAddRecord(source, record)}
          onAddIndex={() => onAddIndex(source)}
        />
      ))}
    </div>
  )
}

function SourcePanel({
  source,
  searchUrl,
  strings,
  disabled,
  onAddRecord,
  onAddIndex,
}: {
  source: LinkableSourceOption
  searchUrl?: string | null
  strings: BuilderStrings
  disabled?: boolean
  onAddRecord: (record: SearchResult) => void
  onAddIndex: () => void
}) {
  const [open, setOpen] = React.useState(false)
  const [query, setQuery] = React.useState("")
  const [state, setState] = React.useState<{ query: string; results: SearchResult[]; failed: boolean } | null>(null)
  const id = `menu-builder-source-${source.key}`.replace(/[^\w-]/g, "-")

  React.useEffect(() => {
    if (!open || !searchUrl) return

    const controller = new AbortController()
    const timer = setTimeout(() => {
      const url = new URL(searchUrl.replace("{source}", encodeURIComponent(source.key)), window.location.href)
      if (query.trim()) url.searchParams.set("search", query.trim())

      fetch(url.toString(), {
        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
        credentials: "same-origin",
        signal: controller.signal,
      })
        .then((response) => {
          if (!response.ok) throw new Error(`HTTP ${response.status}`)
          return response.json() as Promise<{ data?: SearchResult[] }>
        })
        .then((body) => setState({ query, results: body.data ?? [], failed: false }))
        .catch((error: unknown) => {
          if ((error as Error).name === "AbortError") return
          setState({ query, results: [], failed: true })
        })
    }, 250)

    return () => {
      clearTimeout(timer)
      controller.abort()
    }
  }, [open, query, searchUrl, source.key])

  const current = state && state.query === query ? state : null

  return (
    <details
      className="rounded-lg border p-3"
      data-menu-builder-source={source.key}
      onToggle={(event) => setOpen((event.currentTarget as HTMLDetailsElement).open)}
    >
      <summary className="cursor-pointer font-medium" dir="auto">
        {source.label}
      </summary>
      <div className="mt-3 flex flex-col gap-3">
        <div className="flex flex-col gap-1 text-sm">
          <Label htmlFor={id}>{format(strings.search, { source: source.label })}</Label>
          <TextInput
            id={id}
            type="search"
            dir="auto"
            autoComplete="off"
            value={query}
            disabled={disabled}
            onChange={(event) => setQuery(event.target.value)}
          />
        </div>
        <ul className="flex flex-col gap-1" data-source-results>
          {(current?.results ?? []).map((record) => (
            <li key={record.id}>
              <button
                type="button"
                className="w-full rounded-md px-2 py-1 text-start hover:bg-muted"
                dir="auto"
                disabled={disabled}
                data-add-record={record.id}
                onClick={() => onAddRecord(record)}
              >
                {record.label}
              </button>
            </li>
          ))}
        </ul>
        <p className="text-sm text-muted-foreground" aria-live="polite" data-source-status>
          {open && !current ? strings.searching : current && !current.results.length ? strings.no_results : ""}
        </p>
        {source.has_index ? (
          <Button
            type="button"
            variant="outline"
            size="sm"
            startIcon="list"
            disabled={disabled}
            onClick={onAddIndex}
            data-add-index
          >
            {strings.add_index}
          </Button>
        ) : null}
      </div>
    </details>
  )
}
