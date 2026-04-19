import { readFileSync, existsSync } from 'node:fs'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const DATA_PATH = resolve(__dirname, 'ext-data.json')
const NOTES_PATH = resolve(__dirname, '../en/guide/extension-notes.md')

export default {
  watch: [DATA_PATH, NOTES_PATH],

  load() {
    if (!existsSync(DATA_PATH)) {
      console.warn(
        '[extensions.data.js] ext-data.json not found. ' +
        'Run `bin/spc dev:gen-ext-docs` to generate it.'
      )
      return { extensions: [], missing: true }
    }

    const raw = JSON.parse(readFileSync(DATA_PATH, 'utf-8'))

    // Build the set of extension names that have a section in extension-notes.md.
    // Headings at level 2 or 3 are matched; leading/trailing whitespace is stripped.
    const notesSet = new Set()
    if (existsSync(NOTES_PATH)) {
      const notesContent = readFileSync(NOTES_PATH, 'utf-8')
      for (const match of notesContent.matchAll(/^#{2,3}\s+(\S+)/gm)) {
        notesSet.add(match[1].toLowerCase())
      }
    }

    const extensions = raw.extensions.map(ext => ({
      ...ext,
      hasNotes: notesSet.has(ext.name.toLowerCase()),
    }))

    return { extensions, missing: false }
  },
}
