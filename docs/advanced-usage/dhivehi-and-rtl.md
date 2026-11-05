---
title: Dhivehi and RTL
sidebar_position: 5
---

Every string the package shows is in `lang/en/strings.php` and `lang/dv/strings.php` (namespace `dynamic-menubuilder::strings`), and the permission descriptions are in `lang/dv.json`. Publish them with `--tag=dynamic-menubuilder-translations` to change them.

The React pages get their text from the server (the `strings` prop and the builder field's `strings`), so they follow the app's locale without a JavaScript translation setup.

# Right to left

- The builder and the rendered menus use logical CSS only (`margin-inline-start`, `ms-`, `text-start`), so they lay out right to left in a `dir="rtl"` page.
- Nesting is drawn from the inline start, and the indent and outdent arrows are mirrored.
- <kbd>Alt</kbd>+<kbd>→</kbd> and <kbd>Alt</kbd>+<kbd>←</kbd> on a drag handle swap in right-to-left pages, so the arrow pointing "in" always indents.
- Titles render with `dir="auto"`, URLs, icons and CSS classes left to right.

# Thaana

Menu names and item titles take Thaana. With javaabu/translatable, the Dhivehi title input has `dir="rtl"`, `lang="dv"` and, in React, Onu's Thaana keyboard.

# For review

These Dhivehi lines were written without a native speaker's review and should be checked:

| Key | Dhivehi | Note |
| --- | --- | --- |
| `fields.locations` | ދައްކާނެ ތަންތަން | "Menu location" as "the place it's shown" |
| `sections.locations` | މެނޫ ދައްކާ ތަންތަން | |
| `visibility.guests` | ލޮގިން ނުވާ ޒިޔާރަތްކުރާ މީހުންނަށް | "Signed-out visitors" |
| `visibility.users` | ލޮގިން ވެފައިވާ ބޭނުންކުރާ މީހުންނަށް | "Signed-in users" |
| `validation.depth` | … ފިޔަވަޅު | "Level" of nesting as ފިޔަވަޅު |
| `builder.max_depth` | … :max ފިޔަވަޅާ ހަމައަށް | |
| `builder.indent` | މަތީގައިވާ އައިޓަމްގެ ދަށަށް | "Move under the item above" |
| `builder.outdent` | މައި އައިޓަމްގެ ދަށުން ބޭރަށް | "Move out of its parent" |
| `builder.drag` | :title ގެ ތަރުތީބު ބަދަލުކުރަން ދަމާ | |
| `builder.rel` | ލިންކުގެ ގުޅުން (rel) | |
| `types.index` | ލިސްޓު ސަފްހާ | "Index page" |
| `types.model` | ރެކޯޑް | A linked record |
| `actions.actions` | ކުރެވޭނެ ކަންތައް | |
