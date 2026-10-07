// Custom JSX runtime (Sprint 23): React's own, with interface text passed through the Bangla dictionary when the
// staff UI language is Bangla. Configured as jsxImportSource in vite.config.ts and tsconfig.app.json.
import { Fragment, jsx as reactJsx, jsxs as reactJsxs } from 'react/jsx-runtime'
import { translateProps } from './translate'

export { Fragment }
export type { JSX } from 'react/jsx-runtime'

export const jsx: typeof reactJsx = (type, props, key) => reactJsx(type, translateProps(props), key)
export const jsxs: typeof reactJsxs = (type, props, key) => reactJsxs(type, translateProps(props), key)
