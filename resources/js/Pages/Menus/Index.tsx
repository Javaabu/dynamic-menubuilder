import {
  Form,
  LinkButton,
  Pagination,
  Submit,
  Table,
  TableCell,
  TableEmptyRow,
  TableHeading,
  TableRow,
  Text,
} from "@javaabu/forms"
import { MenuActions } from "../../components/MenuActions"
import { StatusAlerts } from "../../components/StatusAlerts"
import type { MenusIndexProps } from "../../types"

/** dynamic-menubuilder::Menus/Index — the locations and the menu each shows, and the menus */
export default function Index(props: MenusIndexProps) {
  const { strings, urls, rows, locations, menus } = props

  return (
    <div className="flex flex-col gap-6" data-menus-index>
      <div className="flex flex-wrap items-center justify-between gap-4">
        <h1 className="text-2xl font-semibold">{props.title}</h1>
        {props.can.create ? (
          <LinkButton url={urls.create} startIcon="plus">
            {strings.new}
          </LinkButton>
        ) : null}
      </div>

      <StatusAlerts status={props.status} />

      <section className="flex flex-col gap-3" data-menu-locations aria-labelledby="menu-locations-heading">
        <h2 id="menu-locations-heading" className="text-lg font-semibold">
          {strings.locations}
        </h2>

        {locations.length ? (
          <Table
            model="menu-locations"
            noCheckbox
            noBulk
            headers={
              <>
                <TableHeading label={strings.location} />
                <TableHeading label={strings.menu} />
              </>
            }
          >
            {locations.map((location) => (
              <TableRow key={location.key} modelId={location.key} noCheckbox data-menu-location-row={location.key}>
                <TableCell name="location" label={strings.location}>
                  <span className="font-medium" dir="auto">
                    {location.label}
                  </span>
                  {location.description ? (
                    <span className="block text-sm text-muted-foreground" dir="auto">
                      {location.description}
                    </span>
                  ) : null}
                </TableCell>
                <TableCell name="menu" label={strings.menu}>
                  {location.menu?.edit_url ? (
                    <a href={location.menu.edit_url} className="hover:underline" dir="auto">
                      {location.menu.name}
                    </a>
                  ) : location.menu ? (
                    <span dir="auto">{location.menu.name}</span>
                  ) : (
                    <span className="text-muted-foreground">{strings.no_menu}</span>
                  )}
                </TableCell>
              </TableRow>
            ))}
          </Table>
        ) : (
          <p className="text-muted-foreground">{strings.no_locations}</p>
        )}
      </section>

      <section className="flex flex-col gap-3" aria-labelledby="menus-heading">
        <h2 id="menus-heading" className="text-lg font-semibold">
          {strings.menus}
        </h2>

        <Form method="GET" action={urls.index} className="flex flex-wrap items-end gap-2" data-menus-search>
          <Text
            name="search"
            label={strings.search}
            placeholder={strings.search_placeholder}
            showPlaceholder
            default={props.search ?? ""}
          />
          <Submit color="secondary" animate={false}>
            {strings.search}
          </Submit>
        </Form>

        <Table
          model="menus"
          noCheckbox
          noBulk
          headers={
            <>
              <TableHeading label={strings.name} />
              <TableHeading label={strings.locations} />
              <TableHeading label={strings.items} />
              <TableHeading label={strings.actions} />
            </>
          }
          pagination={menus.last_page > 1 ? <Pagination paginator={menus} /> : null}
        >
          {rows.length ? (
            rows.map((row) => (
              <TableRow key={row.id} modelId={String(row.id)} noCheckbox data-menu-row={row.id}>
                <TableCell name="name" label={strings.name}>
                  {row.can.update ? (
                    <a href={row.links.edit} className="font-medium hover:underline" dir="auto">
                      {row.name}
                    </a>
                  ) : (
                    <span dir="auto">{row.name}</span>
                  )}
                </TableCell>
                <TableCell name="locations" label={strings.locations}>
                  <span dir="auto">{row.locations.length ? row.locations.join("، ") : "—"}</span>
                </TableCell>
                <TableCell name="items" label={strings.items}>
                  <span dir="ltr">{row.items_count}</span>
                </TableCell>
                <TableCell name="actions" label={strings.actions}>
                  <MenuActions links={row.links} can={row.can} strings={strings} />
                </TableCell>
              </TableRow>
            ))
          ) : (
            <TableEmptyRow columns={4} noCheckbox>
              <p className="font-medium">{strings.empty}</p>
            </TableEmptyRow>
          )}
        </Table>
      </section>
    </div>
  )
}
