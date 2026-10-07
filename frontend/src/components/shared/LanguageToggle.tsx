import { Languages } from 'lucide-react'
import { lang, setLang } from '../../i18n/lang'

/** English ⇄ বাংলা for the staff screens (Sprint 23, decision D7). */
export function LanguageToggle() {
  return (
    <button
      type="button"
      data-no-translate
      onClick={() => setLang(lang === 'bn' ? 'en' : 'bn')}
      className="inline-flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-100"
      aria-label={lang === 'bn' ? 'Switch to English' : 'বাংলায় দেখুন'}
      title={lang === 'bn' ? 'Switch to English' : 'বাংলায় দেখুন'}
    >
      <Languages className="size-4" />
      <span className={lang === 'bn' ? '' : 'font-bn'}>{lang === 'bn' ? 'English' : 'বাংলা'}</span>
    </button>
  )
}
