import { Form } from "@javaabu/forms"
import { act, fireEvent, render, screen, within } from "@testing-library/react"
import { afterEach, describe, expect, it, vi } from "vitest"

import { MenuItemsField, type BuilderItem, type MenuItemsFieldProps } from "@javaabu/dynamic-menubuilder"

import formFixture from "./fixtures/form.json"

interface Node {
  type: string
  props?: Record<string, unknown>
  children?: Node[]
}

function findNode(nodes: Node[], type: string): Node | undefined {
  for (const node of nodes) {
    if (node.type === type) return node
    const found = findNode(node.children ?? [], type)
    if (found) return found
  }
}

const node = findNode((formFixture as unknown as { form: { components: Node[] } }).form.components, "menu-items")!
const fieldProps = node.props as unknown as Omit<MenuItemsFieldProps, "name">
const state = (formFixture as unknown as { form: { state: { items: BuilderItem[] } } }).form.state

function renderField(props: Partial<MenuItemsFieldProps> = {}, items: BuilderItem[] = state.items) {
  const result = render(
    <Form model={{ items, locations: ["header"] }}>
      <input type="checkbox" name="locations[]" value="header" defaultChecked />
      <input type="checkbox" name="locations[]" value="footer" />
      <MenuItemsField name="items" {...fieldProps} {...props} />
    </Form>,
  )

  return { ...result, form: result.container.querySelector("form")! }
}

const labels = () => Array.from(document.querySelectorAll("[data-item-label]")).map((label) => label.textContent)
const depths = () =>
  Array.from(document.querySelectorAll<HTMLElement>("[data-menu-item]")).map((row) => Number(row.dataset.depth))
const row = (label: string) =>
  Array.from(document.querySelectorAll<HTMLElement>("[data-menu-item]")).find(
    (element) => element.querySelector("[data-item-label]")?.textContent === label,
  )!
const button = (label: string, action: string) =>
  row(label).querySelector<HTMLButtonElement>(`[data-action="${action}"]`)!
const data = (form: HTMLFormElement) => Object.fromEntries(new FormData(form).entries())
const status = () => document.querySelector("[data-menu-builder-status]")!.textContent

afterEach(() => {
  vi.unstubAllGlobals()
  vi.useRealTimers()
})

describe("MenuItemsField", () => {
  it("renders the items in tree order with their depths", () => {
    renderField()

    expect(labels()).toEqual(["Home", "ޚިދުމަތްތައް", "About us", "News"])
    expect(depths()).toEqual([1, 1, 2, 1])
    expect(row("ޚިދުމަތްތައް").querySelector("[data-item-type]")!.textContent).toBe("Heading")
    expect(row("About us").querySelector("[data-item-type]")!.textContent).toBe("Pages")
    expect(screen.getByText("This menu's locations show up to 2 levels.")).toBeInTheDocument()
  })

  it("posts every item with its parent", () => {
    const { form } = renderField()
    const values = data(form)

    expect(values["items[0][title]"]).toBe("Home")
    expect(values["items[2][parent]"]).toBe(state.items[1]!.key)
    expect(values["items[2][type]"]).toBe("model")
    expect(values["items[3][new_tab]"]).toBe("1")
  })

  it("moves items with the buttons and announces it", () => {
    const { form } = renderField()

    fireEvent.click(button("News", "move-up"))

    expect(labels()).toEqual(["Home", "News", "ޚިދުމަތްތައް", "About us"])
    expect(status()).toBe("News moved to position 2 of 3.")
    expect(data(form)["items[1][type]"]).toBe("index")
    expect(data(form)["items[3][parent]"]).toBe(state.items[1]!.key)

    fireEvent.click(button("News", "move-down"))
    expect(labels()).toEqual(["Home", "ޚިދުމަތްތައް", "About us", "News"])
  })

  it("indents and outdents within the depth of the ticked locations", () => {
    const { form } = renderField()

    expect(button("Home", "indent").disabled).toBe(true)
    expect(button("About us", "indent").disabled).toBe(true)

    fireEvent.click(button("News", "indent"))
    expect(depths()).toEqual([1, 1, 2, 2])
    expect(status()).toBe("News is now under ޚިދުމަތްތައް.")
    expect(data(form)["items[3][parent]"]).toBe(state.items[1]!.key)

    fireEvent.click(button("About us", "outdent"))
    expect(labels()).toEqual(["Home", "ޚިދުމަތްތައް", "News", "About us"])
    expect(status()).toBe("About us moved out of ޚިދުމަތްތައް.")

    const footer = form.querySelector<HTMLInputElement>('input[value="footer"]')!
    fireEvent.click(footer)

    expect(screen.getAllByText("This menu's locations show up to 1 levels.").length).toBeGreaterThan(0)
    expect(document.querySelector("[data-menu-builder-too-deep]")).not.toBeNull()
  })

  it("moves in and out with alt and the arrow keys on the handle", () => {
    renderField()

    const handle = row("News").querySelector<HTMLElement>("[data-action='drag'] button")!
    fireEvent.keyDown(handle, { key: "ArrowRight", altKey: true })
    expect(depths()).toEqual([1, 1, 2, 2])

    fireEvent.keyDown(row("News").querySelector<HTMLElement>("[data-action='drag'] button")!, {
      key: "ArrowLeft",
      altKey: true,
    })
    expect(depths()).toEqual([1, 1, 2, 1])
  })

  it("moves by one with alt and up or down on the handle, keeping the tree valid", () => {
    renderField()

    fireEvent.keyDown(row("News").querySelector<HTMLElement>("[data-action='drag'] button")!, {
      key: "ArrowUp",
      altKey: true,
    })

    expect(labels()).toEqual(["Home", "ޚިދުމަތްތައް", "News", "About us"])
    expect(depths()).toEqual([1, 1, 2, 2])
    expect(status()).toBe("News moved to position 1 of 2.")
  })

  it("removes an item and lifts its children", () => {
    renderField()

    fireEvent.click(button("ޚިދުމަތްތައް", "remove"))

    expect(labels()).toEqual(["Home", "About us", "News"])
    expect(depths()).toEqual([1, 1, 1])
    expect(status()).toBe("ޚިދުމަތްތައް removed.")
  })

  it("edits an item's fields", () => {
    const { form } = renderField()

    fireEvent.click(button("Home", "toggle"))
    const panel = row("Home").querySelector<HTMLElement>("[data-item-panel]")!

    fireEvent.change(within(panel).getByLabelText("Title"), { target: { value: "ފުރަތަމަ ސަފްހާ" } })
    expect(labels()[0]).toBe("ފުރަތަމަ ސަފްހާ")

    fireEvent.change(within(panel).getByLabelText("Link relationship (rel)"), { target: { value: "nofollow" } })
    fireEvent.change(within(panel).getByLabelText("Show to"), { target: { value: "guests" } })
    fireEvent.click(within(panel).getByRole("checkbox", { name: "Open in a new tab" }))

    const values = data(form)
    expect(values["items[0][title]"]).toBe("ފުރަތަމަ ސަފްހާ")
    expect(values["items[0][rel]"]).toBe("nofollow")
    expect(values["items[0][visibility]"]).toBe("guests")
    expect(values["items[0][new_tab]"]).toBe("1")

    fireEvent.click(within(panel).getByRole("button", { name: "Done" }))
    expect(row("ފުރަތަމަ ސަފްހާ").querySelector("[data-item-panel]")).toBeNull()
  })

  it("hides the URL of records and index pages", () => {
    renderField()

    fireEvent.click(button("About us", "toggle"))
    const panel = row("About us").querySelector<HTMLElement>("[data-item-panel]")!

    expect(within(panel).queryByLabelText("URL")).toBeNull()
    expect(within(panel).getByLabelText<HTMLInputElement>("Title").placeholder).toBe("About us")
  })

  it("adds links and headings", () => {
    const { form } = renderField({}, [])

    expect(screen.getByText("The menu has no items. Add items from the list.")).toBeInTheDocument()

    fireEvent.click(screen.getByRole("button", { name: "Add to menu" }))
    expect(document.activeElement).toBe(document.getElementById("menu-builder-new-title"))
    expect(labels()).toEqual([])

    fireEvent.change(document.getElementById("menu-builder-new-title")!, { target: { value: "ގުޅުއްވުމަށް" } })
    fireEvent.change(document.getElementById("menu-builder-new-url")!, { target: { value: "/contact" } })
    fireEvent.click(screen.getByRole("button", { name: "Add to menu" }))

    fireEvent.change(document.getElementById("menu-builder-new-title")!, { target: { value: "More" } })
    fireEvent.keyDown(document.getElementById("menu-builder-new-title")!, { key: "Enter" })

    expect(labels()).toEqual(["ގުޅުއްވުމަށް", "More"])
    expect(row("More").querySelector("[data-item-type]")!.textContent).toBe("Heading")
    expect(status()).toBe("More added to the menu.")
    expect(data(form)["items[0][url]"]).toBe("/contact")
    expect(String(data(form)["items[0][key]"])).toMatch(/^new-/)
  })

  it("searches a source and adds a record or the index page", async () => {
    vi.useFakeTimers()
    const fetch = vi
      .fn()
      .mockResolvedValue({ ok: true, json: async () => ({ data: [{ id: 9, label: "ގުޅުއްވުމަށް" }] }) })
    vi.stubGlobal("fetch", fetch)

    const { form } = renderField({}, [])
    const pages = document.querySelector<HTMLDetailsElement>('[data-menu-builder-source="pages"]')!

    pages.open = true
    fireEvent(pages, new Event("toggle"))
    fireEvent.change(within(pages).getByLabelText("Search Pages"), { target: { value: "ގުޅު" } })

    await act(async () => {
      await vi.advanceTimersByTimeAsync(300)
    })

    expect(fetch).toHaveBeenCalledTimes(1)
    expect(String(fetch.mock.calls[0]![0])).toContain("/admin/menus/linkables/pages?search=")

    fireEvent.click(within(pages).getByRole("button", { name: "ގުޅުއްވުމަށް" }))

    expect(labels()).toEqual(["ގުޅުއްވުމަށް"])
    expect(data(form)["items[0][linkable_id]"]).toBe("9")
    expect(data(form)["items[0][source]"]).toBe("pages")

    fireEvent.click(within(pages).getByRole("button", { name: "Add the index page" }))
    expect(labels()).toEqual(["ގުޅުއްވުމަށް", "Pages"])
    expect(data(form)["items[1][type]"]).toBe("index")
  })

  it("says when a search finds nothing", async () => {
    vi.useFakeTimers()
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({ ok: false }))

    renderField({}, [])
    const news = document.querySelector<HTMLDetailsElement>('[data-menu-builder-source="news"]')!

    news.open = true
    fireEvent(news, new Event("toggle"))
    expect(within(news).getByText("Searching…")).toBeInTheDocument()

    await act(async () => {
      await vi.advanceTimersByTimeAsync(300)
    })

    expect(within(news).getByText("Nothing found.")).toBeInTheDocument()
  })

  it("shows the errors of an item and of the whole list", () => {
    render(
      <Form
        model={{ items: state.items }}
        errors={{
          "items.0.title": ["The title field is required."],
          items: ["The menu's locations show only 2 levels."],
        }}
      >
        <MenuItemsField name="items" {...fieldProps} />
      </Form>,
    )

    const panel = row("Home").querySelector<HTMLElement>("[data-item-panel]")!
    expect(within(panel).getByRole("alert")).toHaveTextContent("The title field is required.")
    expect(screen.getAllByText("The menu's locations show only 2 levels.").length).toBeGreaterThan(0)
  })

  it("shows broken items", () => {
    renderField({}, [{ ...state.items[2]!, parent: null, broken: true }])

    expect(screen.getByText("The record this item linked to was deleted. Remove the item.")).toBeInTheDocument()
  })

  it("has a title and URL per language with javaabu/translatable", () => {
    const { form } = renderField(
      {
        locales: [
          { code: "en", name: "English", direction: "ltr", default: true },
          { code: "dv", name: "ދިވެހި", direction: "rtl", default: false },
        ],
      },
      [
        {
          key: "a",
          type: "link",
          title_en: "Contact",
          title_dv: "ގުޅުއްވުމަށް",
          url_en: "/contact",
          url_dv: "/dv/contact",
        },
      ],
    )

    expect(labels()).toEqual(["Contact"])

    fireEvent.click(button("Contact", "toggle"))
    const panel = row("Contact").querySelector<HTMLElement>("[data-item-panel]")!
    const dhivehi = within(panel).getByLabelText<HTMLInputElement>("Title (ދިވެހި)")

    expect(dhivehi.value).toBe("ގުޅުއްވުމަށް")
    expect(dhivehi).toHaveAttribute("dir", "rtl")
    expect(dhivehi).toHaveAttribute("lang", "dv")

    fireEvent.click(within(panel).getByRole("checkbox", { name: "ދިވެހި" }))

    const values = data(form)
    expect(values["items[0][title_dv]"]).toBe("ގުޅުއްވުމަށް")
    expect(values["items[0][url_en]"]).toBe("/contact")
    expect(values["items[0][locales][]"]).toBe("dv")
    expect(values["items[0][title]"]).toBeUndefined()
  })

  it("can be disabled", () => {
    render(
      <Form model={{ items: state.items }} disabled>
        <MenuItemsField name="items" {...fieldProps} />
      </Form>,
    )

    expect(button("News", "move-up").disabled).toBe(true)
    expect(button("News", "remove").disabled).toBe(true)
  })

  it("uses a fixed maximum depth", () => {
    renderField({ maxDepth: 1, locationsField: null })

    expect(screen.getAllByText("This menu's locations show up to 1 levels.").length).toBeGreaterThan(0)
    expect(button("News", "indent").disabled).toBe(true)
  })
})
