import { Alert } from "@javaabu/forms"
import type * as React from "react"
import type { MenusPageProps } from "../types"

/** The success or error message a redirect flashed */
export function StatusAlerts({ status }: Pick<MenusPageProps, "status">): React.JSX.Element {
  return (
    <>
      {status.success ? <Alert type="success">{status.success}</Alert> : null}
      {status.error ? <Alert type="danger">{status.error}</Alert> : null}
    </>
  )
}
