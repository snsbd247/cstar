// Development twin of jsx-runtime.ts (Vite dev server uses jsxDEV).
import { Fragment, jsxDEV as reactJsxDEV } from 'react/jsx-dev-runtime'
import { translateProps } from './translate'

export { Fragment }
export type { JSX } from 'react/jsx-dev-runtime'

export const jsxDEV: typeof reactJsxDEV = (type, props, key, isStatic, source, self) => reactJsxDEV(type, translateProps(props), key, isStatic, source, self)
