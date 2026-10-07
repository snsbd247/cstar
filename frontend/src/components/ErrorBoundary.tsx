import { Component, type ErrorInfo, type ReactNode } from 'react'
import { useLocation, useRouteError } from 'react-router'
import { APP_BASE } from '../api/client'

function ErrorMessage({ error }: { error: unknown }) {
  const detail = error instanceof Error ? error.message : null

  return (
    <div role="alert" className="mx-auto mt-10 max-w-md rounded-xl border border-red-200 bg-red-50 p-6 text-red-800">
      <p className="font-semibold">Something went wrong on this page.</p>
      <p className="mt-1 text-sm">কিছু একটা সমস্যা হয়েছে। পাতাটি আবার লোড করুন — সমস্যা থাকলে IT-কে জানান।</p>
      {detail && <p className="mt-3 break-words font-mono text-xs text-red-700/80">{detail}</p>}
      <div className="mt-4 flex gap-2">
        <button onClick={() => window.location.reload()} className="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700">
          Reload / আবার লোড
        </button>
        <a href={`${APP_BASE}/`} className="rounded-lg px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-100">
          Home
        </a>
      </div>
    </div>
  )
}

class Boundary extends Component<{ children: ReactNode }, { error: unknown }> {
  state = { error: null as unknown }

  static getDerivedStateFromError(error: unknown) {
    return { error }
  }

  componentDidCatch(error: unknown, info: ErrorInfo) {
    console.error('Page crashed', error, info.componentStack)
  }

  render() {
    return this.state.error ? <ErrorMessage error={this.state.error} /> : this.props.children
  }
}

/** Sprint 21: a crash inside one page shows a message there; the menu keeps working and moving to another page clears it. */
export function PageErrorBoundary({ children }: { children: ReactNode }) {
  const { pathname } = useLocation()
  return <Boundary key={pathname}>{children}</Boundary>
}

/** Last resort for the router (errors outside any layout). */
export function RouteError() {
  return <ErrorMessage error={useRouteError()} />
}
