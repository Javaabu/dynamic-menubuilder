/**
 * The Blade menu builder (resources/views/stencil/menu-items.blade.php): drag and drop, the
 * move buttons, keyboard shortcuts on the drag handle, adding items and searching records.
 * The list stays flat in tree order; this keeps each item's depth, parent input and input
 * names (`items[0][title]`) in step with the order on the page.
 */
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
  type TreeItem,
} from "../tree"

type Strings = Record<string, string>

interface SearchResult {
  id: number | string
  label: string
}

/** How far a pointer has to move sideways, in pixels, to change an item's depth while dragging */
const INDENT_WIDTH = 24

const ACTIONS = ["move-up", "move-down", "indent", "outdent"] as const

export class BladeMenuBuilder {
  readonly root: HTMLElement
  readonly list: HTMLOListElement
  readonly name: string
  readonly strings: Strings
  readonly searchUrl: string
  readonly defaultMaxDepth: number
  readonly locationDepths: Record<string, number>
  readonly locationsField: string

  protected dragged: string | null = null
  protected dragStartX = 0
  protected searchTimers = new Map<string, ReturnType<typeof setTimeout>>()

  constructor(root: HTMLElement) {
    this.root = root
    this.list = root.querySelector<HTMLOListElement>("[data-menu-builder-list]")!
    this.name = root.dataset.name ?? "items"
    this.strings = parseJson<Strings>(root.dataset.strings, {})
    this.searchUrl = root.dataset.searchUrl ?? ""
    this.defaultMaxDepth = Number(root.dataset.defaultMaxDepth ?? 3) || 3
    this.locationDepths = parseJson<Record<string, number>>(root.dataset.locationDepths, {})
    this.locationsField = root.dataset.locationsField ?? ""

    root.dataset.menuBuilderReady = "true"

    this.list.addEventListener("click", (event) => this.onListClick(event))
    this.list.addEventListener("keydown", (event) => this.onListKeydown(event))
    this.list.addEventListener("input", (event) => this.onListInput(event))
    this.list.addEventListener("dragstart", (event) => this.onDragStart(event))
    this.list.addEventListener("dragover", (event) => this.onDragOver(event))
    this.list.addEventListener("drop", (event) => this.onDrop(event))
    this.list.addEventListener("dragend", () => this.onDragEnd())

    root.querySelector("[data-add-link]")?.addEventListener("click", () => this.addLinkFromInputs())

    root.querySelectorAll<HTMLElement>("[data-menu-builder-source]").forEach((panel) => {
      panel
        .querySelector<HTMLInputElement>("[data-source-search]")
        ?.addEventListener("input", () => this.queueSearch(panel))
      panel.addEventListener("toggle", () => {
        if ((panel as HTMLDetailsElement).open && !panel.dataset.searched) this.search(panel)
      })
      panel.querySelector("[data-add-index]")?.addEventListener("click", (event) => {
        const button = event.currentTarget as HTMLElement
        this.addItem({
          type: "index",
          source: panel.dataset.menuBuilderSource ?? "",
          label: button.dataset.title ?? panel.dataset.label ?? "",
          typeLabel: panel.dataset.label ?? "",
        })
      })
    })

    this.form()?.addEventListener("change", (event) => {
      const target = event.target as HTMLInputElement | null
      if (target?.name === `${this.locationsField}[]`) this.refresh()
    })
    this.form()?.addEventListener("submit", () => this.refresh())

    this.refresh()
  }

  /** The items on the page, in order */
  elements(): HTMLElement[] {
    return Array.from(this.list.querySelectorAll<HTMLElement>(":scope > [data-menu-item]"))
  }

  /** The tree as the shared operations take it */
  tree(): TreeItem[] {
    return this.elements().map((element) => ({
      key: element.dataset.key ?? "",
      depth: Number(element.dataset.depth ?? 1),
    }))
  }

  /** The deepest the menu may go: the smallest depth of the ticked locations, or the default */
  maxDepth(): number {
    const form = this.form()

    if (!form || !this.locationsField) return this.defaultMaxDepth

    const ticked = Array.from(form.querySelectorAll<HTMLInputElement>(`input[name="${this.locationsField}[]"]:checked`))
      .map((input) => this.locationDepths[input.value])
      .filter((depth): depth is number => typeof depth === "number")

    return ticked.length > 0 ? Math.min(...ticked) : this.defaultMaxDepth
  }

  /** Put the items in the order and depths of `next`; items not in it are removed */
  apply(next: TreeItem[]): void {
    const elements = new Map(this.elements().map((element) => [element.dataset.key ?? "", element]))
    const keep = new Set(next.map((item) => item.key))

    for (const [key, element] of elements) {
      if (!keep.has(key)) element.remove()
    }

    for (const item of next) {
      const element = elements.get(item.key)
      if (!element) continue
      element.dataset.depth = String(item.depth)
      this.list.appendChild(element)
    }

    this.refresh()
  }

  /** Bring depths, parents, input names and buttons in line with the page */
  refresh(): void {
    const tree = this.tree()
    const parentKeys = parents(tree)
    const max = this.maxDepth()
    const pattern = new RegExp(`^${escapeRegExp(this.name)}\\[[^\\]]*\\]`)

    this.elements().forEach((element, index) => {
      const depth = tree[index]!.depth

      element.style.marginInlineStart = `${(depth - 1) * 1.5}rem`
      element.setAttribute("aria-level", String(depth))

      const parent = element.querySelector<HTMLInputElement>('[data-input="parent"]')
      if (parent) parent.value = parentKeys[element.dataset.key ?? ""] ?? ""

      element.querySelectorAll<HTMLInputElement | HTMLSelectElement>("[name]").forEach((input) => {
        input.name = input.name.replace(pattern, `${this.name}[${index}]`)
      })

      const enabled: Record<(typeof ACTIONS)[number], boolean> = {
        "move-up": canMoveUp(tree, index),
        "move-down": canMoveDown(tree, index),
        indent: canIndent(tree, index, max),
        outdent: canOutdent(tree, index),
      }

      for (const action of ACTIONS) {
        const button = element.querySelector<HTMLButtonElement>(`:scope > div [data-action="${action}"]`)
        if (button) button.disabled = !enabled[action]
      }
    })

    const empty = this.root.querySelector<HTMLElement>("[data-menu-builder-empty]")
    if (empty) empty.hidden = tree.length > 0

    const hint = this.root.querySelector<HTMLElement>("[data-menu-builder-depth-hint]")
    if (hint && this.strings.max_depth) hint.textContent = format(this.strings.max_depth, { max })

    this.root.toggleAttribute("data-too-deep", treeDepth(tree) > max)
  }

  /** Run one of the move operations on the item at `index` and announce the result */
  act(action: string, index: number): void {
    const tree = this.tree()
    const item = tree[index]
    if (!item) return

    const title = this.title(item.key)
    const parentBefore = this.parentTitle(tree, index)
    let next: TreeItem[]

    switch (action) {
      case "move-up":
        next = moveUp(tree, index)
        break
      case "move-down":
        next = moveDown(tree, index)
        break
      case "indent":
        next = indent(tree, index, this.maxDepth())
        break
      case "outdent":
        next = outdent(tree, index)
        break
      case "remove":
        next = remove(tree, index)
        break
      default:
        return
    }

    if (action !== "remove" && next.every((entry, i) => entry.key === tree[i]?.key && entry.depth === tree[i]?.depth))
      return

    this.apply(next)

    if (action === "remove") {
      this.announce(format(this.strings.removed ?? "", { title }))
      this.list.querySelector<HTMLElement>("[data-action='drag']")?.focus()
      return
    }

    const newIndex = next.findIndex((entry) => entry.key === item.key)

    if (action === "indent") {
      this.announce(format(this.strings.indented ?? "", { title, parent: this.parentTitle(next, newIndex) }))
    } else if (action === "outdent") {
      this.announce(format(this.strings.outdented ?? "", { title, parent: parentBefore }))
    } else {
      this.announceMove(next, newIndex)
    }

    this.focusAction(item.key, action)
  }

  /** Add an item at the end of the menu */
  addItem(options: {
    type: "link" | "model" | "index"
    label: string
    typeLabel: string
    title?: string
    url?: string
    source?: string
    linkableId?: string
  }): HTMLElement | null {
    const template = this.root.querySelector<HTMLTemplateElement>("[data-menu-builder-template]")
    if (!template) return null

    const key = newKey()
    const holder = document.createElement("template")
    holder.innerHTML = template.innerHTML
      .split("__KEY__")
      .join(key)
      .split("__INDEX__")
      .join(String(this.elements().length))

    const element = holder.content.querySelector<HTMLElement>("[data-menu-item]")
    if (!element) return null

    element.dataset.type = options.type
    setInput(element, "type", options.type)
    setInput(element, "source", options.source ?? "")
    setInput(element, "linkable_id", options.linkableId ?? "")

    const titles = element.querySelectorAll<HTMLInputElement>('[data-input^="title"]')
    const urls = element.querySelectorAll<HTMLInputElement>('[data-input^="url"]')

    if (options.type === "link") {
      if (titles[0]) titles[0].value = options.title ?? ""
      if (urls[0]) urls[0].value = options.url ?? ""
    } else {
      titles.forEach((input) => (input.placeholder = options.label))
      element.querySelectorAll<HTMLElement>("[data-link-only]").forEach((field) => (field.hidden = true))
    }

    const label = element.querySelector("[data-item-label]")
    if (label) label.textContent = options.label || this.strings.untitled || ""

    const typeLabel = element.querySelector("[data-item-type]")
    if (typeLabel) typeLabel.textContent = options.typeLabel

    this.updateItemText(element, options.label)

    this.list.appendChild(element)
    this.refresh()
    this.announce(format(this.strings.added ?? "", { title: options.label || this.strings.untitled || "" }))

    return element
  }

  addLinkFromInputs(): void {
    const title = this.root.querySelector<HTMLInputElement>("[data-new-link-title]")
    const url = this.root.querySelector<HTMLInputElement>("[data-new-link-url]")
    const text = title?.value.trim() ?? ""

    if (!text) {
      title?.focus()
      return
    }

    const href = url?.value.trim() ?? ""

    this.addItem({
      type: "link",
      label: text,
      title: text,
      url: href,
      typeLabel: href ? (this.strings.custom_link ?? "") : (this.strings.heading ?? ""),
    })

    if (title) title.value = ""
    if (url) url.value = ""
    title?.focus()
  }

  queueSearch(panel: HTMLElement): void {
    const key = panel.dataset.menuBuilderSource ?? ""

    clearTimeout(this.searchTimers.get(key))
    this.searchTimers.set(
      key,
      setTimeout(() => this.search(panel), 250),
    )
  }

  /** Search a source's records and list them as buttons that add them */
  async search(panel: HTMLElement): Promise<void> {
    const source = panel.dataset.menuBuilderSource ?? ""
    const query = panel.querySelector<HTMLInputElement>("[data-source-search]")?.value.trim() ?? ""
    const results = panel.querySelector<HTMLElement>("[data-source-results]")
    const status = panel.querySelector<HTMLElement>("[data-source-status]")

    if (!this.searchUrl || !results) return

    panel.dataset.searched = "true"
    if (status) status.textContent = this.strings.searching ?? ""

    const url = new URL(this.searchUrl.replace("{source}", encodeURIComponent(source)), window.location.href)
    if (query) url.searchParams.set("search", query)

    try {
      const response = await fetch(url.toString(), {
        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
        credentials: "same-origin",
      })

      if (!response.ok) throw new Error(`HTTP ${response.status}`)

      const body = (await response.json()) as { data?: SearchResult[] }
      const records = body.data ?? []

      results.replaceChildren(
        ...records.map((record) => {
          const li = document.createElement("li")
          const button = document.createElement("button")
          button.type = "button"
          button.className = "w-full rounded-md px-2 py-1 text-start hover:bg-muted"
          button.dir = "auto"
          button.dataset.addRecord = String(record.id)
          button.textContent = record.label
          button.addEventListener("click", () =>
            this.addItem({
              type: "model",
              source,
              linkableId: String(record.id),
              label: record.label,
              typeLabel: panel.dataset.label ?? "",
            }),
          )
          li.appendChild(button)
          return li
        }),
      )

      if (status) status.textContent = records.length === 0 ? (this.strings.no_results ?? "") : ""
    } catch {
      results.replaceChildren()
      if (status) status.textContent = this.strings.no_results ?? ""
    }
  }

  announce(text: string): void {
    const status = this.root.querySelector<HTMLElement>("[data-menu-builder-status]")
    if (status) status.textContent = text
  }

  protected announceMove(tree: TreeItem[], index: number): void {
    const { position, total } = siblingPosition(tree, index)
    this.announce(format(this.strings.moved ?? "", { title: this.title(tree[index]!.key), position, total }))
  }

  protected title(key: string): string {
    return this.element(key)?.querySelector("[data-item-label]")?.textContent?.trim() ?? ""
  }

  protected parentTitle(tree: TreeItem[], index: number): string {
    const parent = parents(tree)[tree[index]?.key ?? ""]

    return parent ? this.title(parent) : ""
  }

  protected element(key: string): HTMLElement | undefined {
    return this.elements().find((element) => element.dataset.key === key)
  }

  protected form(): HTMLFormElement | null {
    return this.root.closest("form")
  }

  protected focusAction(key: string, action: string): void {
    const element = this.element(key)
    const button = element?.querySelector<HTMLButtonElement>(`:scope > div [data-action="${action}"]`)

    if (button && !button.disabled) {
      button.focus()
      return
    }

    element?.querySelector<HTMLElement>("[data-action='drag']")?.focus()
  }

  protected updateItemText(element: HTMLElement, label: string): void {
    const drag = element.querySelector<HTMLElement>("[data-action='drag']")
    if (drag && this.strings.drag) drag.setAttribute("aria-label", format(this.strings.drag, { title: label }))

    const toggle = element.querySelector<HTMLElement>("[data-toggle-label]")
    if (toggle && this.strings.edit_item) toggle.textContent = format(this.strings.edit_item, { title: label })
  }

  protected onListClick(event: MouseEvent): void {
    const button = (event.target as HTMLElement).closest<HTMLElement>("[data-action]")
    const element = button?.closest<HTMLElement>("[data-menu-item]")

    if (!button || !element || button.dataset.action === "drag") return

    const action = button.dataset.action ?? ""

    if (action === "toggle" || action === "close") {
      const panel = element.querySelector<HTMLElement>("[data-item-panel]")
      const toggle = element.querySelector<HTMLElement>("[data-action='toggle']")
      if (!panel || !toggle) return

      const open = action === "toggle" ? panel.hidden : false
      panel.hidden = !open
      toggle.setAttribute("aria-expanded", String(open))

      if (open) panel.querySelector<HTMLElement>("input:not([type=hidden]), select")?.focus()
      else toggle.focus()

      return
    }

    this.act(action, this.elements().indexOf(element))
  }

  /** Alt + arrow keys on the drag handle: up, down, in and out (mirrored in RTL) */
  protected onListKeydown(event: KeyboardEvent): void {
    const handle = (event.target as HTMLElement).closest<HTMLElement>("[data-action='drag']")
    const element = handle?.closest<HTMLElement>("[data-menu-item]")

    if (!handle || !element || !event.altKey) return

    const rtl = getComputedStyle(this.root).direction === "rtl" || this.root.closest("[dir=rtl]") !== null
    const actions: Record<string, string> = {
      ArrowUp: "move-up",
      ArrowDown: "move-down",
      ArrowRight: rtl ? "outdent" : "indent",
      ArrowLeft: rtl ? "indent" : "outdent",
    }
    const action = actions[event.key]

    if (!action) return

    event.preventDefault()

    const key = element.dataset.key ?? ""
    this.act(action, this.elements().indexOf(element))
    this.element(key)?.querySelector<HTMLElement>("[data-action='drag']")?.focus()
  }

  /** Keep the label, the handle's name and the type badge in step with the title and URL */
  protected onListInput(event: Event): void {
    const input = event.target as HTMLInputElement
    const element = input.closest<HTMLElement>("[data-menu-item]")
    const field = input.dataset.input ?? ""

    if (!element) return

    const first = (prefix: string) => element.querySelector<HTMLInputElement>(`[data-input^="${prefix}"]`)

    if (field.startsWith("title") && input === first("title")) {
      const label = input.value.trim() || input.placeholder || this.strings.untitled || ""
      const text = element.querySelector("[data-item-label]")
      if (text) text.textContent = label
      this.updateItemText(element, label)
    }

    if (field.startsWith("url") && input === first("url") && element.dataset.type === "link") {
      const badge = element.querySelector("[data-item-type]")
      if (badge)
        badge.textContent = input.value.trim() ? (this.strings.custom_link ?? "") : (this.strings.heading ?? "")
    }
  }

  protected onDragStart(event: DragEvent): void {
    const handle = (event.target as HTMLElement).closest<HTMLElement>("[data-action='drag']")
    const element = handle?.closest<HTMLElement>("[data-menu-item]")

    if (!handle || !element) return

    this.dragged = element.dataset.key ?? null
    this.dragStartX = event.clientX
    element.dataset.dragging = "true"
    event.dataTransfer?.setData("text/plain", this.dragged ?? "")
    if (event.dataTransfer) event.dataTransfer.effectAllowed = "move"
  }

  protected onDragOver(event: DragEvent): void {
    if (this.dragged === null) return

    event.preventDefault()
    if (event.dataTransfer) event.dataTransfer.dropEffect = "move"
  }

  protected onDrop(event: DragEvent): void {
    if (this.dragged === null) return

    event.preventDefault()

    const target = (event.target as HTMLElement).closest<HTMLElement>("[data-menu-item]")
    const tree = this.tree()
    const from = tree.findIndex((item) => item.key === this.dragged)

    if (!target || from === -1) return this.onDragEnd()

    const to = this.elements().indexOf(target)
    const rtl = this.root.closest("[dir=rtl]") !== null
    const sideways = Math.round(((event.clientX - this.dragStartX) * (rtl ? -1 : 1)) / INDENT_WIDTH)
    const next = moveTo(tree, from, to, tree[from]!.depth + sideways, this.maxDepth())

    this.apply(next)
    this.announceMove(
      next,
      next.findIndex((item) => item.key === this.dragged),
    )
    this.onDragEnd()
  }

  protected onDragEnd(): void {
    this.elements().forEach((element) => delete element.dataset.dragging)
    this.dragged = null
  }
}

function setInput(element: HTMLElement, name: string, value: string): void {
  const input = element.querySelector<HTMLInputElement>(`[data-input="${name}"]`)
  if (input) input.value = value
}

function parseJson<T>(value: string | undefined, fallback: T): T {
  if (!value) return fallback

  try {
    return JSON.parse(value) as T
  } catch {
    return fallback
  }
}

function escapeRegExp(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")
}

/** Start every builder on the page (or in `root`) that hasn't started yet */
export function initMenuBuilders(root: ParentNode = document): BladeMenuBuilder[] {
  return Array.from(root.querySelectorAll<HTMLElement>("[data-menu-builder]:not([data-menu-builder-ready])")).map(
    (element) => new BladeMenuBuilder(element),
  )
}

if (typeof document !== "undefined") {
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => initMenuBuilders())
  } else {
    initMenuBuilders()
  }
}
