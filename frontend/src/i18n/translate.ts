import { bn } from './bn'
import { lang } from './lang'

/** Props whose plain-text value is shown to people (and so is translated). */
const TEXT_PROPS = ['label', 'placeholder', 'title', 'hint', 'description', 'aria-label', 'alt', 'intro', 'empty'] as const

/** Text that changes with a number or name, e.g. "3 day(s)" → "৩ দিন". Checked after the exact dictionary. */
const PATTERNS: [RegExp, (m: RegExpMatchArray) => string][] = [
  [/^(\d+) day\(s\)$/, (m) => `${m[1]} দিন`],
  [/^waiting (\d+) day\(s\)$/, (m) => `${m[1]} দিন ধরে অপেক্ষায়`],
  [/^(\d+) item\(s\) still open\.$/, (m) => `${m[1]}টি বিষয় বাকি।`],
  [/^Page (\d+) of (\d+)$/, (m) => `পৃষ্ঠা ${m[1]} / ${m[2]}`],
  [/^Welcome, (.+)$/, (m) => `স্বাগতম, ${m[1]}`],
  [/^Good day, (.+)$/, (m) => `শুভ দিন, ${m[1]}`],
  [/^Due — (.+)$/, (m) => `বকেয়া — ${m[1]}`],
  [/^(\d+) left$/, (m) => `${m[1]} বাকি`],
  [/^(\d+) of (\d+) used$/, (m) => `${m[2]}-এর মধ্যে ${m[1]} ব্যবহৃত`],
  [/^(\d+) session\(s\)$/, (m) => `${m[1]}টি সেশন`],
  [/^(\d+) appointment\(s\)$/, (m) => `${m[1]}টি অ্যাপয়েন্টমেন্ট`],
]

const memo = new Map<string, string | null>()

/** Translates one piece of interface text; anything not in the dictionary stays as it is (English). */
export function tr(text: string): string {
  if (lang !== 'bn') return text
  const key = text.replace(/\s+/g, ' ').trim()
  if (!key || !/[A-Za-z]/.test(key)) return text
  let found = memo.get(key)
  if (found === undefined) {
    found = bn[key] ?? null
    if (found === null) {
      for (const [re, fn] of PATTERNS) {
        const m = key.match(re)
        if (m) {
          found = fn(m)
          break
        }
      }
    }
    memo.set(key, found)
  }
  if (found === null) return text
  const lead = text.match(/^\s*/)?.[0] ?? ''
  const trail = text.match(/\s*$/)?.[0] ?? ''

  return lead + found + trail
}

const translateChild = (c: unknown): unknown => (typeof c === 'string' ? tr(c) : c)

/** Used by the JSX runtime: static text children and text props of every element. */
export function translateProps<P>(props: P): P {
  if (lang !== 'bn' || !props || typeof props !== 'object') return props
  const p = props as Record<string, unknown>
  if (p['data-no-translate']) return props
  let out: Record<string, unknown> | null = null
  const set = (k: string, v: unknown) => {
    if (v !== p[k]) (out ??= { ...p })[k] = v
  }
  if (typeof p.children === 'string') set('children', tr(p.children))
  else if (Array.isArray(p.children) && p.children.some((c) => typeof c === 'string')) set('children', p.children.map(translateChild))
  for (const k of TEXT_PROPS) if (typeof p[k] === 'string') set(k, tr(p[k] as string))

  return (out ?? props) as P
}
