import type { StencilComponentProps } from "@javaabu/stencil"
import type * as React from "react"
import type { BuilderStrings, LinkableSourceOption, MenuLocale, MenuLocationOption } from "../types"
import { MenuItemsField } from "./MenuItemsField"

/**
 * The stencil `menu-items` node (PHP `MenuItemsField`) as `MenuItemsField`. The builder page
 * registers it; register it yourself to render the menu form in your own page:
 * `<StencilForm schema={form} components={{ "menu-items": StencilMenuItems }} />`.
 */
export function StencilMenuItems({ node }: StencilComponentProps): React.JSX.Element {
  const props = (node.props ?? {}) as Record<string, unknown>

  return (
    <MenuItemsField
      name={node.name ?? node.key}
      label={props.label as string | undefined}
      showLabel={props.showLabel as boolean | undefined}
      help={props.help as string | undefined}
      sources={props.sources as LinkableSourceOption[] | undefined}
      locations={props.locations as MenuLocationOption[] | undefined}
      locationsField={props.locationsField as string | null | undefined}
      defaultMaxDepth={props.defaultMaxDepth as number | undefined}
      searchUrl={props.searchUrl as string | null | undefined}
      locales={props.locales as MenuLocale[] | null | undefined}
      visibilities={props.visibilities as Array<{ value: string; label: string }> | undefined}
      strings={props.strings as BuilderStrings}
    />
  )
}

/** The stencil components the menu form needs */
export const menuStencilComponents = { "menu-items": StencilMenuItems }
