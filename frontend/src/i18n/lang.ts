/**
 * Staff UI language (Sprint 23 — decision D7: admin in English with a Bangla toggle). Chosen per device; switching
 * reloads the page so every screen re-renders in the new language. The parent portal is always Bangla.
 */
export type Lang = 'en' | 'bn'

const KEY = 'cstar.lang'

export const lang: Lang = (() => {
  try {
    return localStorage.getItem(KEY) === 'bn' ? 'bn' : 'en'
  } catch {
    return 'en'
  }
})()

export function setLang(next: Lang) {
  try {
    localStorage.setItem(KEY, next)
  } catch {
    // private mode: the choice lasts until reload only
  }
  window.location.reload()
}
