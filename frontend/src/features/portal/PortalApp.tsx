import { Card } from '../../components/ui/Card'
import { useAuth } from '../../contexts/useAuth'

/** Plan §১৫: shows training and/or therapy sections depending on the child's enrollments (Sprint 13). */
export function PortalHome() {
  const { user } = useAuth()

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-semibold text-slate-900">স্বাগতম, {user?.name}</h1>
      <div className="grid grid-cols-2 gap-3">
        {['পরবর্তী অ্যাপয়েন্টমেন্ট', 'এই মাসের উপস্থিতি', 'বকেয়া', 'নতুন রিপোর্ট'].map((label) => (
          <Card key={label} className="p-4">
            <p className="text-xs text-slate-500">{label}</p>
            <p className="mt-1 text-lg font-semibold text-slate-300">—</p>
          </Card>
        ))}
      </div>
      <Card className="p-5">
        <h2 className="font-medium text-slate-900">আমার সন্তান</h2>
        <p className="mt-1 text-sm text-slate-500">আপনার সন্তানের তথ্য, সময়সূচি, অগ্রগতি ও বিল শীঘ্রই এখানে দেখা যাবে।</p>
      </Card>
    </div>
  )
}
