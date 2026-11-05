import { describe, expect, it } from "vitest"

import {
  blockEnd,
  blockHeight,
  canIndent,
  canMoveDown,
  canMoveUp,
  canOutdent,
  format,
  indent,
  moveDown,
  moveTo,
  moveUp,
  newKey,
  normalize,
  outdent,
  parents,
  remove,
  siblingPosition,
  treeDepth,
  withDepths,
  type TreeItem,
} from "@javaabu/dynamic-menubuilder/tree"

// home
// services
//   permits
//     forms
//   licences
// contact
const tree: TreeItem[] = [
  { key: "home", depth: 1 },
  { key: "services", depth: 1 },
  { key: "permits", depth: 2 },
  { key: "forms", depth: 3 },
  { key: "licences", depth: 2 },
  { key: "contact", depth: 1 },
]

const shape = (items: TreeItem[]) => items.map((item) => `${"-".repeat(item.depth - 1)}${item.key}`)

describe("tree", () => {
  it("finds blocks and parents", () => {
    expect(blockEnd(tree, 1)).toBe(5)
    expect(blockEnd(tree, 2)).toBe(4)
    expect(blockHeight(tree, 1)).toBe(3)
    expect(blockHeight(tree, 0)).toBe(1)
    expect(parents(tree)).toEqual({
      home: null,
      services: null,
      permits: "services",
      forms: "permits",
      licences: "services",
      contact: null,
    })
    expect(treeDepth(tree)).toBe(3)
    expect(treeDepth([])).toBe(0)
  })

  it("computes depths from parent keys", () => {
    expect(
      withDepths([
        { key: "a", parent: null },
        { key: "b", parent: "a" },
        { key: "c", parent: "b" },
        { key: "d", parent: "missing" },
      ]).map((item) => item.depth),
    ).toEqual([1, 2, 3, 1])
  })

  it("moves an item with its children among its siblings", () => {
    expect(canMoveUp(tree, 0)).toBe(false)
    expect(canMoveUp(tree, 1)).toBe(true)
    expect(canMoveDown(tree, 4)).toBe(false)

    expect(shape(moveUp(tree, 1))).toEqual(["services", "-permits", "--forms", "-licences", "home", "contact"])
    expect(shape(moveDown(tree, 1))).toEqual(["home", "contact", "services", "-permits", "--forms", "-licences"])
    expect(shape(moveUp(tree, 4))).toEqual(["home", "services", "-licences", "-permits", "--forms", "contact"])
    expect(moveUp(tree, 0)).toEqual(tree)
    expect(moveDown(tree, 5)).toEqual(tree)
  })

  it("indents under the previous sibling within the maximum depth", () => {
    expect(canIndent(tree, 0)).toBe(false)
    expect(canIndent(tree, 1)).toBe(true)
    expect(canIndent(tree, 1, 3)).toBe(false)
    expect(canIndent(tree, 5, 2)).toBe(true)

    expect(shape(indent(tree, 1))).toEqual(["home", "-services", "--permits", "---forms", "--licences", "contact"])
    expect(shape(indent(tree, 4))).toEqual(["home", "services", "-permits", "--forms", "--licences", "contact"])
    expect(indent(tree, 1, 3)).toEqual(tree)
  })

  it("outdents to just after the parent", () => {
    expect(canOutdent(tree, 0)).toBe(false)
    expect(canOutdent(tree, 2)).toBe(true)

    expect(shape(outdent(tree, 2))).toEqual(["home", "services", "-licences", "permits", "-forms", "contact"])
    expect(shape(outdent(tree, 3))).toEqual(["home", "services", "-permits", "-forms", "-licences", "contact"])
    expect(outdent(tree, 0)).toEqual(tree)
  })

  it("removes an item and lifts its children", () => {
    expect(shape(remove(tree, 1))).toEqual(["home", "permits", "-forms", "licences", "contact"])
    expect(shape(remove(tree, 5))).toEqual(["home", "services", "-permits", "--forms", "-licences"])
  })

  it("moves a block to a target, clamping its depth", () => {
    expect(shape(moveTo(tree, 5, 1))).toEqual(["home", "contact", "services", "-permits", "--forms", "-licences"])
    expect(shape(moveTo(tree, 0, 4))).toEqual(["services", "-permits", "--forms", "-licences", "home", "contact"])
    expect(shape(moveTo(tree, 0, 4, 2))).toEqual(["services", "-permits", "--forms", "-licences", "-home", "contact"])
    expect(shape(moveTo(tree, 5, 2, 9))).toEqual(["home", "services", "-contact", "-permits", "--forms", "-licences"])
    expect(shape(moveTo(tree, 1, 0, 1))).toEqual(["services", "-permits", "--forms", "-licences", "home", "contact"])
    expect(shape(moveTo(tree, 2, 5, 3, 2))).toEqual(["home", "services", "-licences", "contact", "permits", "-forms"])
    // before a parent's first child: it joins the children instead of adopting them
    expect(shape(moveTo(tree, 5, 2))).toEqual(["home", "services", "-contact", "-permits", "--forms", "-licences"])
    expect(moveTo(tree, 1, 3)).toEqual(tree)
    expect(moveTo(tree, 1, 99)).toEqual(tree)
  })

  it("normalizes depths", () => {
    expect(
      normalize([
        { key: "a", depth: 2 },
        { key: "b", depth: 4 },
        { key: "c", depth: 3 },
      ]).map((item) => item.depth),
    ).toEqual([1, 2, 3])
    expect(normalize(tree, 2).map((item) => item.depth)).toEqual([1, 1, 2, 2, 2, 1])
  })

  it("gives the position among siblings", () => {
    expect(siblingPosition(tree, 5)).toEqual({ position: 3, total: 3 })
    expect(siblingPosition(tree, 4)).toEqual({ position: 2, total: 2 })
  })

  it("formats strings and makes keys", () => {
    expect(format(":title moved to :position of :total", { title: "ގޭ", position: 2, total: 3 })).toBe(
      "ގޭ moved to 2 of 3",
    )
    expect(format(":total and :to", { to: "x", total: 1 })).toBe("1 and x")
    expect(newKey()).not.toBe(newKey())
  })
})
