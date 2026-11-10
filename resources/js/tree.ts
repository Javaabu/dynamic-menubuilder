/**
 * The menu tree as the builders edit it: a flat list in tree order (a parent, then its
 * children), each item with its depth (1 for the top level). Every operation moves an item
 * with its descendants (its "block") and returns a new list. Used by the React builder and
 * the Blade builder's script, so both behave the same.
 */

export interface TreeItem {
  key: string
  depth: number
}

/** The index after the item's last descendant */
export function blockEnd<T extends TreeItem>(items: readonly T[], index: number): number {
  const depth = items[index]?.depth ?? 0
  let end = index + 1

  while (end < items.length && items[end]!.depth > depth) end++

  return end
}

/** How many levels the item's block spans (1 for an item without children) */
export function blockHeight<T extends TreeItem>(items: readonly T[], index: number): number {
  const depth = items[index]?.depth ?? 0
  let deepest = depth

  for (let i = index + 1; i < blockEnd(items, index); i++) deepest = Math.max(deepest, items[i]!.depth)

  return deepest - depth + 1
}

/** The index of the item's parent, or -1 for a top-level item */
export function parentIndex<T extends TreeItem>(items: readonly T[], index: number): number {
  const depth = items[index]?.depth ?? 1

  for (let i = index - 1; i >= 0; i--) {
    if (items[i]!.depth < depth) return i
  }

  return -1
}

/** The index of the previous item with the same parent, or -1 */
export function previousSibling<T extends TreeItem>(items: readonly T[], index: number): number {
  const depth = items[index]?.depth ?? 1

  for (let i = index - 1; i >= 0; i--) {
    if (items[i]!.depth < depth) return -1
    if (items[i]!.depth === depth) return i
  }

  return -1
}

/** The index of the next item with the same parent, or -1 */
export function nextSibling<T extends TreeItem>(items: readonly T[], index: number): number {
  const end = blockEnd(items, index)

  return end < items.length && items[end]!.depth === items[index]!.depth ? end : -1
}

/** Each item's parent key (null at the top level), keyed by item key */
export function parents<T extends TreeItem>(items: readonly T[]): Record<string, string | null> {
  const result: Record<string, string | null> = {}

  items.forEach((item, index) => {
    const parent = parentIndex(items, index)
    result[item.key] = parent === -1 ? null : items[parent]!.key
  })

  return result
}

/**
 * Depths from parent keys, for a list from the server (`{ key, parent }` in tree order).
 * An item whose parent isn't before it goes to the top level.
 */
export function withDepths<T extends { key: string; parent?: string | null }>(
  items: readonly T[],
): Array<T & TreeItem> {
  const depths: Record<string, number> = {}

  return items.map((item) => {
    const parent = item.parent ?? null
    const depth = parent !== null && depths[parent] !== undefined ? depths[parent]! + 1 : 1
    depths[item.key] = depth

    return { ...item, depth }
  })
}

/** Keep the tree valid: start at depth 1, never skip a level, never go deeper than `maxDepth` */
export function normalize<T extends TreeItem>(items: readonly T[], maxDepth = Infinity): T[] {
  let previous = 0

  return items.map((item) => {
    const depth = Math.max(1, Math.min(item.depth, previous + 1, maxDepth))
    previous = depth

    return depth === item.depth ? item : { ...item, depth }
  })
}

function shiftBlock<T extends TreeItem>(block: readonly T[], by: number): T[] {
  return block.map((item) => ({ ...item, depth: item.depth + by }))
}

export function canMoveUp<T extends TreeItem>(items: readonly T[], index: number): boolean {
  return previousSibling(items, index) !== -1
}

export function canMoveDown<T extends TreeItem>(items: readonly T[], index: number): boolean {
  return nextSibling(items, index) !== -1
}

/** Swap the item with its previous sibling */
export function moveUp<T extends TreeItem>(items: readonly T[], index: number): T[] {
  const previous = previousSibling(items, index)
  if (previous === -1) return [...items]

  const end = blockEnd(items, index)

  return [...items.slice(0, previous), ...items.slice(index, end), ...items.slice(previous, index), ...items.slice(end)]
}

/** Swap the item with its next sibling */
export function moveDown<T extends TreeItem>(items: readonly T[], index: number): T[] {
  const next = nextSibling(items, index)
  if (next === -1) return [...items]

  return moveUp(items, next)
}

/** Whether the item can go under its previous sibling without passing `maxDepth` */
export function canIndent<T extends TreeItem>(items: readonly T[], index: number, maxDepth = Infinity): boolean {
  if (previousSibling(items, index) === -1) return false

  return items[index]!.depth + blockHeight(items, index) <= maxDepth
}

/** Make the item the last child of its previous sibling */
export function indent<T extends TreeItem>(items: readonly T[], index: number, maxDepth = Infinity): T[] {
  if (!canIndent(items, index, maxDepth)) return [...items]

  const end = blockEnd(items, index)

  return [...items.slice(0, index), ...shiftBlock(items.slice(index, end), 1), ...items.slice(end)]
}

export function canOutdent<T extends TreeItem>(items: readonly T[], index: number): boolean {
  return (items[index]?.depth ?? 1) > 1
}

/** Take the item out of its parent: it goes right after the parent, one level up */
export function outdent<T extends TreeItem>(items: readonly T[], index: number): T[] {
  if (!canOutdent(items, index)) return [...items]

  const parent = parentIndex(items, index)
  const end = blockEnd(items, index)
  const parentEnd = blockEnd(items, parent)
  const block = shiftBlock(items.slice(index, end), -1)

  return [...items.slice(0, index), ...items.slice(end, parentEnd), ...block, ...items.slice(parentEnd)]
}

/** Remove the item; its children take its place, one level up */
export function remove<T extends TreeItem>(items: readonly T[], index: number): T[] {
  const end = blockEnd(items, index)

  return [...items.slice(0, index), ...shiftBlock(items.slice(index + 1, end), -1), ...items.slice(end)]
}

/**
 * Move the item's block so it starts where the item at `target` is in the list (before it
 * when moving up, after its block when moving down), at `depth` (clamped so the tree stays
 * valid: no deeper than one level under the item before it, and no shallower than the item
 * after it). Dropping an item inside its own block does nothing.
 */
export function moveTo<T extends TreeItem>(
  items: readonly T[],
  index: number,
  target: number,
  depth?: number,
  maxDepth = Infinity,
): T[] {
  const end = blockEnd(items, index)

  if (target >= index && target < end) return [...items]

  const block = items.slice(index, end)
  const rest = [...items.slice(0, index), ...items.slice(end)]
  const targetItem = items[target]

  if (!targetItem) return [...items]

  let insertAt = rest.indexOf(targetItem)

  if (target > index) insertAt = blockEnd(rest, insertAt)

  const previousDepth = rest[insertAt - 1]?.depth ?? 0
  // Never shallower than the item after it, which would make that item its child
  const nextDepth = rest[insertAt]?.depth ?? 1
  const height = blockHeight(items, index)
  const wanted = depth ?? block[0]!.depth
  const newDepth = Math.max(1, Math.min(Math.max(wanted, nextDepth), previousDepth + 1, maxDepth - height + 1))

  return normalize(
    [...rest.slice(0, insertAt), ...shiftBlock(block, newDepth - block[0]!.depth), ...rest.slice(insertAt)],
    maxDepth,
  )
}

/** The deepest level in the list */
export function treeDepth<T extends TreeItem>(items: readonly T[]): number {
  return items.reduce((deepest, item) => Math.max(deepest, item.depth), 0)
}

/** The 1-based position of the item among its siblings, and how many siblings there are */
export function siblingPosition<T extends TreeItem>(
  items: readonly T[],
  index: number,
): { position: number; total: number } {
  const parent = parentIndex(items, index)
  const depth = items[index]!.depth
  const start = parent + 1
  const end = parent === -1 ? items.length : blockEnd(items, parent)
  let position = 0
  let total = 0

  for (let i = start; i < end; i++) {
    if (items[i]!.depth !== depth) continue
    total++
    if (i <= index) position = total
  }

  return { position, total }
}

/** Replace `:name` placeholders in a translated string */
export function format(text: string, replacements: Record<string, string | number>): string {
  return Object.entries(replacements)
    .sort(([a], [b]) => b.length - a.length)
    .reduce((result, [name, value]) => result.split(`:${name}`).join(String(value)), text)
}

let counter = 0

/** A key for a new item, unique on the page */
export function newKey(): string {
  counter++

  return `new-${Date.now().toString(36)}-${counter}`
}
