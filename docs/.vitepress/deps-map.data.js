import { readFileSync, existsSync } from 'node:fs'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const DATA_PATH = resolve(__dirname, 'deps-data.json')

export default {
  watch: [DATA_PATH],

  load() {
    if (!existsSync(DATA_PATH)) {
      console.warn(
        '[deps-map.data.js] deps-data.json not found. ' +
        'Run `bin/spc dev:gen-deps-data` to generate it.'
      )
      return { packages: {}, missing: true }
    }

    const raw = JSON.parse(readFileSync(DATA_PATH, 'utf-8'))
    return { packages: raw.packages ?? {}, missing: false }
  },
}
