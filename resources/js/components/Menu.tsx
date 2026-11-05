import { usePage } from "@inertiajs/react"
import * as React from "react"
import type { MenuNode, SharedMenus } from "../types"

/** The items of a location, from the menus Inertia shares (`config('dynamic-menubuilder.share')`) */
export function useMenu(location: string, shareKey = "menus"): MenuNode[] {
  const menus = (sharedProps()[shareKey] ?? {}) as SharedMenus

  return menus[location] ?? []
}

/** Inertia's page props, or none outside an Inertia app (usePage throws there) */
function sharedProps(): Record<string, unknown> {
  try {
    // eslint-disable-next-line react-hooks/rules-of-hooks
    return usePage().props as Record<string, unknown>
  } catch {
    return {}
  }
}

export interface MenuItemRenderProps {
  item: MenuNode
  /** The item's children, already rendered, or null */
  children: React.ReactNode
}

export interface MenuProps extends Omit<React.ComponentProps<"nav">, "children"> {
  /** The location whose menu to render, from the Inertia shared data */
  location?: string
  /** Render these items instead of the shared ones */
  items?: MenuNode[]
  /** Where the menus are in the shared props. Default `menus` */
  shareKey?: string
  /** The `<nav>`'s accessible name. Default: the location */
  label?: string
  /** Replace how each item renders; return an `<li>` */
  renderItem?: (props: MenuItemRenderProps) => React.ReactNode
  classNames?: { list?: string; item?: string; link?: string; heading?: string }
}

function ItemContent({ item, className }: { item: MenuNode; className?: string }) {
  const icon = item.icon ? <i className={item.icon} aria-hidden="true" /> : null

  if (!item.url) {
    return (
      <span className={className ?? "dynamic-menu__heading"} dir="auto">
        {icon}
        {item.title}
      </span>
    )
  }

  return (
    <a
      href={item.url}
      className={className ?? "dynamic-menu__link"}
      dir="auto"
      aria-current={item.current ? "page" : undefined}
      target={item.target ?? undefined}
      rel={item.rel ?? undefined}
    >
      {icon}
      {item.title}
    </a>
  )
}

function MenuList({
  items,
  depth,
  renderItem,
  classNames,
}: {
  items: MenuNode[]
  depth: number
  renderItem?: MenuProps["renderItem"]
  classNames?: MenuProps["classNames"]
}) {
  return (
    <ul className={classNames?.list ?? `dynamic-menu__list dynamic-menu__list--depth-${depth}`} data-menu-depth={depth}>
      {items.map((item) => {
        const children = item.children.length ? (
          <MenuList items={item.children} depth={depth + 1} renderItem={renderItem} classNames={classNames} />
        ) : null

        if (renderItem) return <React.Fragment key={item.id}>{renderItem({ item, children })}</React.Fragment>

        const className = [
          classNames?.item ?? "dynamic-menu__item",
          item.active ? "dynamic-menu__item--active" : null,
          item.current ? "dynamic-menu__item--current" : null,
          item.children.length ? "dynamic-menu__item--has-children" : null,
          item.css_class,
        ]
          .filter(Boolean)
          .join(" ")

        return (
          <li key={item.id} className={className} data-menu-item={item.id}>
            <ItemContent item={item} className={item.url ? classNames?.link : classNames?.heading} />
            {children}
          </li>
        )
      })}
    </ul>
  )
}

/**
 * A location's menu as a `<nav>` with nested lists, the same markup as
 * `<x-dynamic-menubuilder::menu>`. Renders nothing when the location has no items.
 */
export function Menu({
  location,
  items,
  shareKey,
  label,
  renderItem,
  classNames,
  className,
  ...props
}: MenuProps): React.JSX.Element | null {
  const shared = useMenu(location ?? "", shareKey)
  const nodes = items ?? (location ? shared : [])

  if (!nodes.length) return null

  return (
    <nav
      {...props}
      className={["dynamic-menu", className].filter(Boolean).join(" ")}
      aria-label={label ?? location}
      data-menu-location={location}
    >
      <MenuList items={nodes} depth={1} renderItem={renderItem} classNames={classNames} />
    </nav>
  )
}
