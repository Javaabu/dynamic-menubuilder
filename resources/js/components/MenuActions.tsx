import { Form, LinkButton, Submit } from "@javaabu/forms"
import type * as React from "react"
import type { MenuAbilities, MenuLinks, MenusStrings } from "../types"

export interface MenuActionsProps {
  links: Partial<MenuLinks>
  can: Partial<MenuAbilities>
  strings: Pick<MenusStrings, "edit" | "delete" | "confirm_delete">
  /** Show the Edit button. Default `true` */
  showEdit?: boolean
}

/** Edit and delete buttons, shown when the user may use them */
export function MenuActions({ links, can, strings, showEdit = true }: MenuActionsProps): React.JSX.Element {
  return (
    <div className="flex flex-wrap items-center gap-2" data-menu-actions>
      {showEdit && can.update && links.edit ? (
        <LinkButton url={links.edit} color="secondary" size="sm">
          {strings.edit}
        </LinkButton>
      ) : null}

      {can.delete && links.destroy ? (
        <Form method="DELETE" action={links.destroy}>
          <Submit
            color="danger"
            size="sm"
            animate={false}
            onClick={(event: React.MouseEvent<HTMLButtonElement>) => {
              if (!window.confirm(strings.confirm_delete)) event.preventDefault()
            }}
          >
            {strings.delete}
          </Submit>
        </Form>
      ) : null}
    </div>
  )
}
