import { Construction } from 'lucide-react'
import { Link } from 'react-router'
import { Card } from '../components/ui/Card'

export default function ComingSoon({ title, sprint, bangla }: { title: string; sprint?: number; bangla?: boolean }) {
  return (
    <Card className="mx-auto mt-6 max-w-lg p-8 text-center">
      <span className="mx-auto flex size-12 items-center justify-center rounded-full bg-amber-50 text-amber-600">
        <Construction className="size-6" />
      </span>
      <h1 className="mt-4 text-lg font-semibold text-slate-900">{title}</h1>
      {bangla ? (
        <p className="mt-2 text-sm text-slate-500">এই অংশটি শীঘ্রই যুক্ত হবে।</p>
      ) : (
        <p className="mt-2 text-sm text-slate-500">
          This module is planned{sprint ? ` for Sprint ${sprint}` : ''} of the C-STAR roadmap and is not built yet.
        </p>
      )}
    </Card>
  )
}

export function NotFound() {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center gap-3 px-4 text-center">
      <p className="text-5xl font-bold text-brand-600">404</p>
      <p className="text-slate-600">This page does not exist.</p>
      <Link to="/" className="text-sm font-medium text-sky-brand-600 hover:underline">
        Go to my home page
      </Link>
    </div>
  )
}
