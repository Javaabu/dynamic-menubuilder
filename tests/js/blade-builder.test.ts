import { readFileSync } from "node:fs"
import { resolve } from "node:path"

import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"

import { BladeMenuBuilder, initMenuBuilders } from "@javaabu/dynamic-menubuilder/blade/menu-builder"

// Written by tests/Http/BladePagesTest.php: Home, ޚިދުމަތްތައް > About us, Contact; locations header (2) and footer (1)
const html = readFileSync(resolve(__dirname, "fixtures/blade-builder.html"), "utf8")

let builder: BladeMenuBuilder

const labels = () =>
  builder.elements().map((element) => element.querySelector("[data-item-label]")!.textContent!.trim())
const depths = () => builder.elements().map((element) => Number(element.dataset.depth))
const item = (label: string) => builder.elements().find((element) => element.textContent!.includes(label))!
const click = (label: string, action: string) =>
  item(label).querySelector<HTMLButtonElement>(`:scope > div [data-action="${action}"]`)!.click()
const status = () => document.querySelector("[data-menu-builder-status]")!.textContent
const formData = () => Object.fromEntries(new FormData(document.querySelector("form")!).entries())

beforeEach(() => {
  document.body.innerHTML = html
  builder = initMenuBuilders()[0]!
})

afterEach(() => {
  vi.unstubAllGlobals()
})

describe("Blade menu builder", () => {
  it("starts once per builder", () => {
    expect(labels()).toEqual(["Home", "ޚިދުމަތްތައް", "About us", "Contact"])
    expect(depths()).toEqual([1, 1, 2, 1])
    expect(initMenuBuilders()).toEqual([])
  })

  it("moves items with the buttons and renumbers the inputs", () => {
    click("Contact", "move-up")

    expect(labels()).toEqual(["Home", "Contact", "ޚިދުމަތްތައް", "About us"])
    expect(status()).toBe("Contact moved to position 2 of 3.")
    expect(formData()["items[1][title]"]).toBe("Contact")
    expect(formData()["items[3][parent]"]).toBe(item("ޚިދުމަތްތައް").dataset.key)
    expect(document.activeElement).toBe(item("Contact").querySelector('[data-action="move-up"]'))
  })

  it("disables what an item can't do", () => {
    const home = item("Home")

    expect(home.querySelector<HTMLButtonElement>('[data-action="move-up"]')!.disabled).toBe(true)
    expect(home.querySelector<HTMLButtonElement>('[data-action="indent"]')!.disabled).toBe(true)
    expect(home.querySelector<HTMLButtonElement>('[data-action="outdent"]')!.disabled).toBe(true)
    expect(item("About us").querySelector<HTMLButtonElement>('[data-action="indent"]')!.disabled).toBe(true)
    expect(item("About us").querySelector<HTMLButtonElement>('[data-action="outdent"]')!.disabled).toBe(false)
  })

  it("indents and outdents, setting the parent", () => {
    click("Contact", "indent")

    expect(depths()).toEqual([1, 1, 2, 2])
    expect(formData()["items[3][parent]"]).toBe(item("ޚިދުމަތްތައް").dataset.key)
    expect(status()).toBe("Contact is now under ޚިދުމަތްތައް.")

    click("About us", "outdent")

    expect(labels()).toEqual(["Home", "ޚިދުމަތްތައް", "Contact", "About us"])
    expect(depths()).toEqual([1, 1, 2, 1])
    expect(formData()["items[3][parent]"]).toBe("")
    expect(status()).toBe("About us moved out of ޚިދުމަތްތައް.")
  })

  it("limits the depth to the ticked locations", () => {
    const header = document.querySelector<HTMLInputElement>('input[name="locations[]"][value="header"]')!
    const footer = document.querySelector<HTMLInputElement>('input[name="locations[]"][value="footer"]')!

    expect(header.checked).toBe(true)
    expect(builder.maxDepth()).toBe(2)

    footer.checked = true
    footer.dispatchEvent(new Event("change", { bubbles: true }))

    expect(builder.maxDepth()).toBe(1)
    expect(document.querySelector("[data-menu-builder-depth-hint]")!.textContent).toContain("show: 1")
    expect(builder.root.hasAttribute("data-too-deep")).toBe(true)
    expect(item("Contact").querySelector<HTMLButtonElement>('[data-action="indent"]')!.disabled).toBe(true)

    header.checked = false
    footer.checked = false
    footer.dispatchEvent(new Event("change", { bubbles: true }))

    expect(builder.maxDepth()).toBe(3)
  })

  it("moves with alt and the arrow keys on the handle", () => {
    const handle = item("Contact").querySelector<HTMLElement>('[data-action="drag"]')!

    handle.dispatchEvent(new KeyboardEvent("keydown", { key: "ArrowUp", altKey: true, bubbles: true }))
    expect(labels()).toEqual(["Home", "Contact", "ޚިދުމަތްތައް", "About us"])
    expect(document.activeElement).toBe(item("Contact").querySelector('[data-action="drag"]'))

    item("Contact")
      .querySelector('[data-action="drag"]')!
      .dispatchEvent(new KeyboardEvent("keydown", { key: "ArrowRight", altKey: true, bubbles: true }))
    expect(depths()).toEqual([1, 2, 1, 2])

    item("Contact")
      .querySelector('[data-action="drag"]')!
      .dispatchEvent(new KeyboardEvent("keydown", { key: "ArrowLeft", altKey: true, bubbles: true }))
    expect(depths()).toEqual([1, 1, 1, 2])

    item("Contact")
      .querySelector('[data-action="drag"]')!
      .dispatchEvent(new KeyboardEvent("keydown", { key: "ArrowUp", bubbles: true }))
    expect(labels()[1]).toBe("Contact")
  })

  it("mirrors the arrow keys in right-to-left", () => {
    document.documentElement.setAttribute("dir", "rtl")

    try {
      item("Contact")
        .querySelector('[data-action="drag"]')!
        .dispatchEvent(new KeyboardEvent("keydown", { key: "ArrowLeft", altKey: true, bubbles: true }))
      expect(depths()).toEqual([1, 1, 2, 2])
    } finally {
      document.documentElement.removeAttribute("dir")
    }
  })

  it("removes an item and lifts its children", () => {
    click("ޚިދުމަތްތައް", "remove")

    expect(labels()).toEqual(["Home", "About us", "Contact"])
    expect(depths()).toEqual([1, 1, 1])
    expect(status()).toBe("ޚިދުމަތްތައް removed.")
    expect(formData()["items[1][parent]"]).toBe("")
  })

  it("opens and closes an item's fields and follows its title", () => {
    const home = item("Home")
    const toggle = home.querySelector<HTMLElement>('[data-action="toggle"]')!
    const panel = home.querySelector<HTMLElement>("[data-item-panel]")!

    toggle.click()
    expect(panel.hidden).toBe(false)
    expect(toggle.getAttribute("aria-expanded")).toBe("true")

    const title = panel.querySelector<HTMLInputElement>('[data-input="title"]')!
    title.value = "ފުރަތަމަ ސަފްހާ"
    title.dispatchEvent(new Event("input", { bubbles: true }))
    expect(home.querySelector("[data-item-label]")!.textContent).toBe("ފުރަތަމަ ސަފްހާ")
    expect(home.querySelector('[data-action="drag"]')!.getAttribute("aria-label")).toBe(
      "Drag to reorder ފުރަތަމަ ސަފްހާ",
    )

    const url = panel.querySelector<HTMLInputElement>('[data-input="url"]')!
    url.value = ""
    url.dispatchEvent(new Event("input", { bubbles: true }))
    expect(home.querySelector("[data-item-type]")!.textContent).toBe("Heading")

    panel.querySelector<HTMLElement>('[data-action="close"]')!.click()
    expect(panel.hidden).toBe(true)
    expect(document.activeElement).toBe(toggle)
  })

  it("adds a link", () => {
    document.querySelector<HTMLInputElement>("[data-new-link-title]")!.value = "ގުޅުއްވުމަށް"
    document.querySelector<HTMLInputElement>("[data-new-link-url]")!.value = "https://example.mv"
    document.querySelector<HTMLElement>("[data-add-link]")!.click()

    expect(labels()).toEqual(["Home", "ޚިދުމަތްތައް", "About us", "Contact", "ގުޅުއްވުމަށް"])
    expect(formData()["items[4][title]"]).toBe("ގުޅުއްވުމަށް")
    expect(formData()["items[4][url]"]).toBe("https://example.mv")
    expect(formData()["items[4][type]"]).toBe("link")
    expect(String(formData()["items[4][key]"])).toMatch(/^new-/)
    expect(status()).toBe("ގުޅުއްވުމަށް added to the menu.")
    expect(document.querySelector<HTMLInputElement>("[data-new-link-title]")!.value).toBe("")
  })

  it("needs a title to add a link", () => {
    document.querySelector<HTMLElement>("[data-add-link]")!.click()

    expect(labels()).toHaveLength(4)
    expect(document.activeElement).toBe(document.querySelector("[data-new-link-title]"))
  })

  it("adds a heading", () => {
    document.querySelector<HTMLInputElement>("[data-new-link-title]")!.value = "More"
    document.querySelector<HTMLElement>("[data-add-link]")!.click()

    expect(item("More").querySelector("[data-item-type]")!.textContent).toBe("Heading")
  })

  it("adds a source's index page", () => {
    const news = document.querySelector<HTMLElement>('[data-menu-builder-source="news"]')!
    news.querySelector<HTMLElement>("[data-add-index]")!.click()

    const added = builder.elements().at(-1)!
    expect(added.querySelector("[data-item-label]")!.textContent).toBe("News")
    expect(added.querySelector<HTMLElement>("[data-link-only]")!.hidden).toBe(true)
    expect(formData()["items[4][type]"]).toBe("index")
    expect(formData()["items[4][source]"]).toBe("news")
  })

  it("searches a source and adds a record", async () => {
    const fetch = vi
      .fn()
      .mockResolvedValue({ ok: true, json: async () => ({ data: [{ id: 7, label: "ގުޅުއްވުމަށް" }] }) })
    vi.stubGlobal("fetch", fetch)

    const pages = document.querySelector<HTMLElement>('[data-menu-builder-source="pages"]')!
    pages.querySelector<HTMLInputElement>("[data-source-search]")!.value = "ގުޅު"

    await builder.search(pages)

    expect(String(fetch.mock.calls[0]![0])).toBe(
      "http://localhost/admin/menus/linkables/pages?search=%DE%8E%DE%AA%DE%85%DE%AA",
    )

    pages.querySelector<HTMLElement>("[data-add-record]")!.click()

    const added = builder.elements().at(-1)!
    expect(added.querySelector("[data-item-label]")!.textContent).toBe("ގުޅުއްވުމަށް")
    expect(added.querySelector("[data-item-type]")!.textContent).toBe("Pages")
    expect(formData()["items[4][linkable_id]"]).toBe("7")
    expect(formData()["items[4][type]"]).toBe("model")
    expect(added.querySelector<HTMLInputElement>('[data-input="title"]')!.placeholder).toBe("ގުޅުއްވުމަށް")
  })

  it("says when a search finds nothing or fails", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({ ok: true, json: async () => ({ data: [] }) }))
    const pages = document.querySelector<HTMLElement>('[data-menu-builder-source="pages"]')!

    await builder.search(pages)
    expect(pages.querySelector("[data-source-status]")!.textContent).toBe("Nothing found.")

    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({ ok: false }))
    await builder.search(pages)
    expect(pages.querySelector("[data-source-status]")!.textContent).toBe("Nothing found.")
  })

  it("debounces typing in a search", async () => {
    vi.useFakeTimers()
    const fetch = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ data: [] }) })
    vi.stubGlobal("fetch", fetch)

    const input = document.querySelector<HTMLInputElement>('[data-menu-builder-source="pages"] [data-source-search]')!
    input.value = "a"
    input.dispatchEvent(new Event("input"))
    input.value = "ab"
    input.dispatchEvent(new Event("input"))

    await vi.advanceTimersByTimeAsync(300)
    vi.useRealTimers()

    expect(fetch).toHaveBeenCalledTimes(1)
  })

  it("moves by drag and drop", () => {
    const contact = item("Contact")
    const home = item("Home")
    const handle = contact.querySelector<HTMLElement>('[data-action="drag"]')!

    const event = (type: string, target: HTMLElement, clientX = 0) => {
      const dragEvent = new Event(type, { bubbles: true, cancelable: true }) as DragEvent
      Object.defineProperty(dragEvent, "clientX", { value: clientX })
      Object.defineProperty(dragEvent, "dataTransfer", { value: null })
      target.dispatchEvent(dragEvent)
      return dragEvent
    }

    event("dragstart", handle, 100)
    expect(contact.dataset.dragging).toBe("true")
    expect(event("dragover", home).defaultPrevented).toBe(true)
    event("drop", home, 100)

    expect(labels()).toEqual(["Contact", "Home", "ޚިދުމަތްތައް", "About us"])
    expect(contact.dataset.dragging).toBeUndefined()

    // dragged to the inline end: one level in
    event("dragstart", item("Home").querySelector<HTMLElement>('[data-action="drag"]')!, 100)
    event("drop", item("About us"), 130)

    expect(labels()).toEqual(["Contact", "ޚިދުމަތްތައް", "About us", "Home"])
    expect(depths()).toEqual([1, 1, 2, 2])
  })
})
