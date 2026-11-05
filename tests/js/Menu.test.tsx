import { render, screen, within } from "@testing-library/react"
import { describe, expect, it, vi } from "vitest"

import { Menu, useMenu, type MenuNode, type SharedMenus } from "@javaabu/dynamic-menubuilder"

import sharedFixture from "./fixtures/shared.json"

const shared = sharedFixture as unknown as SharedMenus
const page = { props: { menus: shared, nav: shared } as Record<string, unknown> }

vi.mock("@inertiajs/react", () => ({
  usePage: () => {
    if (!page.props) throw new Error("usePage must be used within the Inertia component")
    return page
  },
}))

describe("Menu", () => {
  it("renders a location's shared menu", () => {
    render(<Menu location="header" label="Main" className="main-nav" />)

    const nav = screen.getByRole("navigation", { name: "Main" })
    expect(nav).toHaveClass("dynamic-menu", "main-nav")
    expect(nav).toHaveAttribute("data-menu-location", "header")

    expect(within(nav).getByRole("link", { name: "Home" })).toHaveAttribute("href", "/")
    expect(within(nav).getByText("ޚިދުމަތްތައް").tagName).toBe("SPAN")
    expect(within(nav).getByRole("link", { name: "About us" })).toHaveAttribute("href", "http://localhost/pages/about")
    expect(within(nav).getByRole("link", { name: "News" })).toHaveAttribute("target", "_blank")
    expect(within(nav).getByRole("link", { name: "News" })).toHaveAttribute("rel", "noopener")
    expect(nav.querySelector('[data-menu-depth="2"]')).not.toBeNull()
  })

  it("marks the current page", () => {
    const items: MenuNode[] = [
      { ...shared.header![0]!, current: true, active: true, css_class: "home", icon: "fa-home" },
    ]

    render(<Menu items={items} label="Main" />)

    const link = screen.getByRole("link", { name: "Home" })
    expect(link).toHaveAttribute("aria-current", "page")
    expect(link.querySelector("i.fa-home")).not.toBeNull()
    expect(link.closest("li")).toHaveClass("dynamic-menu__item--current", "dynamic-menu__item--active", "home")
  })

  it("renders nothing without items", () => {
    const { container } = render(<Menu location="footer" />)

    expect(container).toBeEmptyDOMElement()
  })

  it("reads another share key and takes a custom item renderer", () => {
    render(
      <Menu
        location="header"
        shareKey="nav"
        renderItem={({ item, children }) => (
          <li key={item.id} data-custom>
            {item.title.toUpperCase()}
            {children}
          </li>
        )}
        classNames={{ list: "flex" }}
      />,
    )

    expect(screen.getByText("HOME")).toBeInTheDocument()
    expect(screen.getByText("ABOUT US")).toBeInTheDocument()
    expect(document.querySelectorAll("ul.flex")).toHaveLength(2)
  })

  it("works outside an Inertia app", () => {
    const props = page.props
    page.props = undefined as unknown as Record<string, unknown>

    try {
      const { container } = render(<Menu location="header" />)
      expect(container).toBeEmptyDOMElement()

      render(<Menu items={shared.header} label="Main" />)
      expect(screen.getByRole("link", { name: "Home" })).toBeInTheDocument()
    } finally {
      page.props = props
    }
  })

  it("gives a location's items through useMenu", () => {
    let items: MenuNode[] = []

    function Probe() {
      items = useMenu("header")
      return null
    }

    render(<Probe />)

    expect(items.map((item) => item.title)).toEqual(["Home", "ޚިދުމަތްތައް", "News"])
  })
})
