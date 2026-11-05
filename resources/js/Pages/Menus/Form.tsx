import { LinkButton } from "@javaabu/forms"
import { StencilForm } from "@javaabu/stencil"
import { MenuActions } from "../../components/MenuActions"
import { StatusAlerts } from "../../components/StatusAlerts"
import { menuStencilComponents } from "../../components/StencilMenuItems"
import type { MenusFormProps } from "../../types"

/** dynamic-menubuilder::Menus/Form — create a menu (no `menu`) or build one */
export default function Form(props: MenusFormProps) {
  const { strings, urls } = props

  return (
    <div className="flex flex-col gap-6" data-menus-form>
      <div className="flex flex-wrap items-center justify-between gap-4">
        <h1 className="text-2xl font-semibold" dir="auto">
          {props.title}
        </h1>
        <LinkButton url={urls.index} color="secondary" size="sm" startIcon="chevron-left">
          {strings.back}
        </LinkButton>
      </div>

      <StatusAlerts status={props.status} />

      <StencilForm schema={props.form} components={menuStencilComponents} />

      {props.menu && props.can.delete ? (
        <div className="flex justify-end">
          <MenuActions links={urls} can={props.can} strings={strings} showEdit={false} />
        </div>
      ) : null}
    </div>
  )
}
