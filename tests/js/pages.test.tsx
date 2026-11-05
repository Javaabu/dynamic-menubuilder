import { render, screen, within } from "@testing-library/react"
import { afterEach, describe, expect, it, vi } from "vitest"

import type { MenusFormProps, MenusIndexProps } from "@javaabu/dynamic-menubuilder"
import FormPage from "@javaabu/dynamic-menubuilder/Pages/Menus/Form"
import IndexPage from "@javaabu/dynamic-menubuilder/Pages/Menus/Index"

import formDhivehiFixture from "./fixtures/form.dv.json"
import formFixture from "./fixtures/form.json"
import indexFixture from "./fixtures/index.json"

const index = indexFixture as unknown as MenusIndexProps
const form = formFixture as unknown as MenusFormProps
const formDhivehi = formDhivehiFixture as unknown as MenusFormProps

afterEach(() => {
  vi.restoreAllMocks()
})

describe("dynamic-menubuilder::Menus/Index", () => {
  it("lists the locations and the menus", () => {
    render(<IndexPage {...index} />)

    expect(screen.getByRole("heading", { name: "Menus", level: 1 })).toBeInTheDocument()

    const header = document.querySelector<HTMLElement>('[data-menu-location-row="header"]')!
    expect(within(header).getByText("Header")).toBeInTheDocument()
    expect(within(header).getByRole("link", { name: "މައި މެނޫ" })).toHaveAttribute("href", index.rows[0]?.links.edit)

    const footer = document.querySelector<HTMLElement>('[data-menu-location-row="footer"]')!
    expect(within(footer).getByText("No menu")).toBeInTheDocument()

    const row = document.querySelector<HTMLElement>(`[data-menu-row="${index.rows[0]?.id}"]`)!
    expect(within(row).getByText("4")).toBeInTheDocument()
    expect(within(row).getByRole("link", { name: "Edit" })).toBeInTheDocument()
    expect(screen.getByRole("link", { name: "New menu" })).toHaveAttribute("href", index.urls.create)
  })

  it("hides what the user may not do", () => {
    render(
      <IndexPage
        {...index}
        can={{ ...index.can, create: false }}
        rows={index.rows.map((row) => ({ ...row, can: { view: true, update: false, delete: false } }))}
        locations={index.locations.map((location) => ({
          ...location,
          menu: location.menu ? { ...location.menu, edit_url: null } : null,
        }))}
      />,
    )

    expect(screen.queryByRole("link", { name: "New menu" })).toBeNull()
    expect(screen.queryByRole("link", { name: "Edit" })).toBeNull()
    expect(screen.queryByRole("button", { name: "Delete" })).toBeNull()
    expect(screen.queryByRole("link", { name: "މައި މެނޫ" })).toBeNull()
  })

  it("says when there are no menus or locations", () => {
    render(<IndexPage {...index} rows={[]} locations={[]} />)

    expect(screen.getByText(index.strings.empty)).toBeInTheDocument()
    expect(screen.getByText(index.strings.no_locations)).toBeInTheDocument()
  })

  it("asks before deleting", () => {
    const confirm = vi.spyOn(window, "confirm").mockReturnValue(false)

    render(<IndexPage {...index} />)
    screen.getByRole("button", { name: "Delete" }).click()

    expect(confirm).toHaveBeenCalledWith(index.strings.confirm_delete)
  })
})

describe("dynamic-menubuilder::Menus/Form", () => {
  it("renders the menu form with the builder", () => {
    render(<FormPage {...form} />)

    expect(screen.getByRole("heading", { name: "މައި މެނޫ", level: 1 })).toBeInTheDocument()
    expect(document.querySelector<HTMLInputElement>('input[name="name"]')?.value).toBe("މައި މެނޫ")
    expect(document.querySelector("[data-menu-builder-react]")).not.toBeNull()
    expect(screen.getByRole("button", { name: "Save menu" })).toBeInTheDocument()
    expect(screen.getByRole("link", { name: form.strings.back })).toHaveAttribute("href", form.urls.index)

    const labels = Array.from(document.querySelectorAll("[data-item-label]")).map((label) => label.textContent)
    expect(labels).toEqual(["Home", "ޚިދުމަތްތައް", "About us", "News"])
    expect(document.querySelector("[data-menu-item] [data-item-type]")?.textContent).toBe("Link")
  })

  it("offers to delete an existing menu, not a new one", () => {
    const { unmount } = render(<FormPage {...form} />)
    expect(screen.getByRole("button", { name: "Delete" })).toBeInTheDocument()
    unmount()

    render(<FormPage {...form} menu={null} />)
    expect(screen.queryByRole("button", { name: "Delete" })).toBeNull()
  })

  it("renders in Dhivehi", () => {
    render(<FormPage {...formDhivehi} />)

    expect(screen.getByRole("link", { name: "މެނޫތަކަށް އެނބުރި" })).toBeInTheDocument()
    expect(screen.getByText("އައިޓަމް އިތުރުކުރޭ")).toBeInTheDocument()
    expect(screen.getByText("މެނޫގެ ތަރުތީބު")).toBeInTheDocument()
  })
})
