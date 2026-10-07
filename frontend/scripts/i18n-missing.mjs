// Lists staff-screen text that has no Bangla yet (Sprint 23). Run after adding screens:
//   node scripts/i18n-missing.mjs
// It reads JSX text and text props (label, placeholder, title, hint, description…) with the TypeScript parser and
// compares them with src/i18n/bn*.ts. Brand and technical words (bKash, PDF, SMS…) are fine to leave in English.
import { readdirSync, readFileSync, statSync } from 'node:fs'
import { createRequire } from 'node:module'
import { join, relative } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'

const ts = createRequire(import.meta.url)('typescript')
const root = fileURLToPath(new URL('../src/', import.meta.url))
const files = []
const walk = (d) => readdirSync(d).forEach((f) => { const p = join(d, f); statSync(p).isDirectory() ? walk(p) : /\.tsx?$/.test(p) && !p.endsWith('.d.ts') && files.push(p) })
walk(root)

const PROPS = new Set(['label', 'placeholder', 'title', 'hint', 'description', 'aria-label', 'alt', 'intro', 'empty'])
const found = new Map()
const add = (s, file) => {
  s = s.replace(/\s+/g, ' ').trim()
  if (s && /[A-Za-z]{2}/.test(s) && !/[ঀ-৿]/.test(s) && !/^[a-z0-9_.\-/:?=&]+$/.test(s) && !found.has(s)) found.set(s, relative(root, file))
}
for (const file of files) {
  if (/[\/](portal|website|i18n)[\/]/.test(file)) continue
  const src = ts.createSourceFile(file, readFileSync(file, 'utf8'), ts.ScriptTarget.Latest, true, file.endsWith('.tsx') ? ts.ScriptKind.TSX : ts.ScriptKind.TS)
  const visit = (n) => {
    if (ts.isJsxText(n)) add(n.text.replace(/&amp;/g, '&'), file)
    else if (ts.isJsxAttribute(n) && n.initializer && ts.isStringLiteral(n.initializer) && PROPS.has(n.name.getText(src))) add(n.initializer.text, file)
    else if (ts.isPropertyAssignment(n) && PROPS.has(n.name.getText(src).replace(/['"]/g, '')) && ts.isStringLiteral(n.initializer)) add(n.initializer.text, file)
    ts.forEachChild(n, visit)
  }
  visit(src)
}

const dict = {}
for (const f of readdirSync(join(root, 'i18n')).filter((f) => /^bn\d+\.ts$/.test(f))) Object.assign(dict, ...Object.values(await import(pathToFileURL(join(root, 'i18n', f)).href)))
const missing = [...found].filter(([s]) => !(s in dict))
for (const [s, file] of missing) console.log(`${file}\t${s}`)
console.log(`\n${missing.length} of ${found.size} texts have no Bangla yet (dictionary: ${Object.keys(dict).length}).`)
