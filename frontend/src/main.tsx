import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import App from './app/App.tsx'
import './index.css'
import { lang } from './i18n/lang'

// Bangla staff screens: right language tag (screen readers, hyphenation) and the Bangla font.
document.documentElement.lang = lang
document.documentElement.classList.toggle('lang-bn', lang === 'bn')

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
