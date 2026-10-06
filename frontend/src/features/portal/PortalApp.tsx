import { CalendarCheck, CalendarPlus, CheckCircle2, FileDown, HeartPulse, House, KeyRound, Receipt, Sparkles, TrendingUp, Wallet } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { Link } from 'react-router'
import { errorMessage, validationErrors } from '../../api/client'
import { Button } from '../../components/ui/Button'
import { Alert, Badge, Card } from '../../components/ui/Card'
import { Field, Input, Select, Textarea } from '../../components/ui/Field'
import { Spinner } from '../../components/ui/Spinner'
import { useAuth } from '../../contexts/useAuth'
import { cn } from '../../utils/cn'
import { todayISO } from '../../utils/format'
import {
  appointmentStatusBn,
  bnDate,
  bnNumber,
  bnTaka,
  bnTime,
  bnWeekday,
  invoiceStatusBn,
  methodBn,
  portalPdf,
  usePortalAttendance,
  usePortalBilling,
  usePortalHome,
  usePortalProfile,
  usePortalProgress,
  usePortalSchedule,
  useRequestAppointment,
  useSelectedChild,
  WEEKDAYS_BN,
  type Child,
  type PortalAppointment,
} from './api'

/** Plan §১৫: one child at a time; a switcher appears only for families with more than one child. */
function ChildFrame({ children }: { children: (child: Child) => ReactNode }) {
  const { children: list, child, isLoading, select } = useSelectedChild()
  if (isLoading) return <Spinner className="text-brand-600" />
  if (!child) {
    return (
      <Card className="p-5 text-sm text-slate-600">
        আপনার অ্যাকাউন্টের সাথে এখনো কোনো শিশুর তথ্য যুক্ত করা হয়নি। অনুগ্রহ করে C-STAR রিসেপশনে যোগাযোগ করুন।
      </Card>
    )
  }

  return (
    <div className="space-y-4">
      {list.length > 1 && (
        <div className="flex gap-2 overflow-x-auto" role="tablist" aria-label="সন্তান">
          {list.map((c) => (
            <button
              key={c.id}
              role="tab"
              aria-selected={c.id === child.id}
              onClick={() => select(c.id)}
              className={cn('whitespace-nowrap rounded-full px-4 py-2 text-sm font-medium', c.id === child.id ? 'bg-brand-600 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200')}
            >
              {c.name_bn || c.name}
            </button>
          ))}
        </div>
      )}
      {children(child)}
    </div>
  )
}

function Stat({ icon: Icon, label, value, tone, to }: { icon: typeof House; label: string; value: ReactNode; tone?: string; to?: string }) {
  const body = (
    <Card className="h-full p-4">
      <Icon className="size-5 text-brand-600" />
      <p className="mt-2 text-xs text-slate-500">{label}</p>
      <p className={cn('mt-0.5 text-lg font-semibold text-slate-900', tone)}>{value}</p>
    </Card>
  )
  return to ? <Link to={to}>{body}</Link> : body
}

function AppointmentLine({ a }: { a: PortalAppointment }) {
  return (
    <div className="flex items-center justify-between gap-2 py-2.5">
      <div>
        <p className="font-medium text-slate-900">{a.service_bn || a.service}</p>
        <p className="text-sm text-slate-500">
          {bnWeekday(a.date)}, {bnDate(a.date, { day: 'numeric', month: 'long' })} · {bnTime(a.start_time)}
          {a.therapist && ` · ${a.therapist}`}
        </p>
      </div>
      <Badge tone={a.status === 'confirmed' ? 'green' : a.status === 'completed' ? 'blue' : ['cancelled', 'no_show'].includes(a.status) ? 'red' : 'amber'}>
        {appointmentStatusBn[a.status] ?? a.status}
      </Badge>
    </div>
  )
}

// ---- হোম --------------------------------------------------------------------------------------

export function PortalHome() {
  const { user } = useAuth()
  return (
    <div className="space-y-4">
      <h1 className="text-xl font-semibold text-slate-900">আসসালামু আলাইকুম, {user?.name}</h1>
      <ChildFrame>{(child) => <HomeBody child={child} />}</ChildFrame>
    </div>
  )
}

function HomeBody({ child }: { child: Child }) {
  const { data, isLoading } = usePortalHome(child.id)
  if (isLoading || !data) return <Spinner className="text-brand-600" />

  return (
    <>
      <Card className="p-4">
        <p className="text-lg font-semibold text-slate-900">{child.name_bn || child.name}</p>
        <p className="text-sm text-slate-500">
          {child.patient_code}
          {child.age && ` · ${child.age.replace(/\d+/g, (d) => bnNumber(Number(d)))}`}
        </p>
        <div className="mt-2 flex flex-wrap gap-2">
          {child.programmes.map((p, i) => (
            <span key={i} className="rounded-lg bg-slate-100 px-2.5 py-1 text-xs text-slate-700">
              {p.type === 'training' ? `নিয়মিত ট্রেনিং — ${p.name}` : p.name_bn || p.name}
              {p.with && ` · ${p.with}`}
            </span>
          ))}
          {child.programmes.length === 0 && <span className="text-xs text-slate-500">এখন কোনো চলমান প্রোগ্রাম নেই</span>}
        </div>
      </Card>

      <div className="grid grid-cols-2 gap-3">
        <Stat
          icon={CalendarCheck}
          label="পরবর্তী অ্যাপয়েন্টমেন্ট"
          to="/portal/schedule"
          value={data.next_appointment ? <span className="text-base">{bnDate(data.next_appointment.date, { day: 'numeric', month: 'short' })}, {bnTime(data.next_appointment.start_time)}</span> : '—'}
        />
        {child.has_training && <Stat icon={TrendingUp} label="এই মাসের উপস্থিতি" to="/portal/progress" value={data.attendance?.rate != null ? `${bnNumber(data.attendance.rate)}%` : '—'} />}
        <Stat icon={Wallet} label="বকেয়া" to="/portal/billing" value={bnTaka(data.due)} tone={data.due > 0 ? 'text-red-600' : 'text-brand-700'} />
        <Stat icon={Sparkles} label="নতুন রিপোর্ট" to="/portal/progress" value={bnNumber(data.new_reports)} />
      </div>

      {data.latest_note && (
        <Card className="p-4">
          <p className="text-xs text-slate-500">
            সর্বশেষ নোট · {data.latest_note.from} · {bnDate(data.latest_note.date, { day: 'numeric', month: 'long' })}
          </p>
          <p className="mt-1 text-slate-800">{data.latest_note.text}</p>
          {data.latest_note.home_practice && (
            <p className="mt-2 rounded-lg bg-brand-50 p-2 text-sm text-brand-900">
              <b>বাসায় অনুশীলন:</b> {data.latest_note.home_practice}
            </p>
          )}
        </Card>
      )}

      <Card className="p-4">
        <h2 className="font-semibold text-slate-900">সাম্প্রতিক খবর</h2>
        <ul className="mt-2 space-y-3">
          {data.updates.map((u, i) => (
            <li key={i} className="border-l-2 border-brand-200 pl-3">
              <p className="text-sm text-slate-800">{u.title}</p>
              {u.description && <p className="text-xs text-slate-500">{u.description}</p>}
              <p className="text-xs text-slate-400">{bnDate(u.at, { day: 'numeric', month: 'long' })}</p>
            </li>
          ))}
          {data.updates.length === 0 && <li className="text-sm text-slate-500">এখনো কোনো খবর নেই।</li>}
        </ul>
      </Card>
    </>
  )
}

// ---- সময়সূচি ----------------------------------------------------------------------------------

export function PortalSchedule() {
  return (
    <div className="space-y-4">
      <h1 className="text-xl font-semibold text-slate-900">সময়সূচি</h1>
      <ChildFrame>{(child) => <ScheduleBody child={child} />}</ChildFrame>
    </div>
  )
}

function ScheduleBody({ child }: { child: Child }) {
  const { data, isLoading } = usePortalSchedule(child.id)
  const [asking, setAsking] = useState(false)
  if (isLoading || !data) return <Spinner className="text-brand-600" />

  return (
    <>
      {data.class && (
        <Card className="p-4">
          <h2 className="font-semibold text-slate-900">নিয়মিত ট্রেনিং — {data.class.name}</h2>
          {data.class.trainer && <p className="text-sm text-slate-500">ট্রেইনার: {data.class.trainer}</p>}
          <ul className="mt-2 divide-y divide-slate-100 text-sm">
            {data.class.days.map((d) => (
              <li key={d.weekday} className="flex justify-between py-1.5">
                <span>{WEEKDAYS_BN[d.weekday]}</span>
                <span className="text-slate-600">
                  {bnTime(d.start_time)} – {bnTime(d.end_time)}
                </span>
              </li>
            ))}
          </ul>
        </Card>
      )}

      <Card className="p-4">
        <div className="flex items-center justify-between">
          <h2 className="font-semibold text-slate-900">আসন্ন অ্যাপয়েন্টমেন্ট</h2>
          <Button variant="secondary" onClick={() => setAsking((x) => !x)}>
            <CalendarPlus className="size-4" /> অনুরোধ
          </Button>
        </div>
        {asking && <RequestForm childId={child.id} services={data.services} onDone={() => setAsking(false)} />}
        <div className="divide-y divide-slate-100">
          {data.upcoming.map((a) => (
            <AppointmentLine key={a.id} a={a} />
          ))}
          {data.upcoming.length === 0 && <p className="py-3 text-sm text-slate-500">এখন কোনো অ্যাপয়েন্টমেন্ট নির্ধারিত নেই।</p>}
        </div>
      </Card>

      {data.past.length > 0 && (
        <Card className="p-4">
          <h2 className="font-semibold text-slate-900">আগের অ্যাপয়েন্টমেন্ট</h2>
          <div className="divide-y divide-slate-100">
            {data.past.map((a) => (
              <AppointmentLine key={a.id} a={a} />
            ))}
          </div>
        </Card>
      )}
    </>
  )
}

function RequestForm({ childId, services, onDone }: { childId: number; services: { id: number; name: string; name_bn: string | null }[]; onDone: () => void }) {
  const request = useRequestAppointment(childId)
  const [v, setV] = useState({ service_id: '', preferred_date: '', preferred_time: '', message: '' })
  const [error, setError] = useState<string | null>(null)
  const [sent, setSent] = useState<string | null>(null)

  if (sent) {
    return (
      <div className="my-3">
        <Alert tone="green">
          <span className="inline-flex items-center gap-1.5">
            <CheckCircle2 className="size-4" /> অনুরোধ পাঠানো হয়েছে ({sent})। রিসেপশন থেকে শীঘ্রই ফোন করে নিশ্চিত করা হবে।
          </span>
        </Alert>
        <Button variant="ghost" className="mt-2" onClick={onDone}>
          ঠিক আছে
        </Button>
      </div>
    )
  }

  const submit = async () => {
    setError(null)
    try {
      const res = await request.mutateAsync({
        service_id: Number(v.service_id) || null,
        preferred_date: v.preferred_date || null,
        preferred_time: v.preferred_time || null,
        message: v.message || null,
      })
      setSent(res.reference)
    } catch (e) {
      setError(Object.values(validationErrors(e))[0] ?? errorMessage(e))
    }
  }

  return (
    <div className="my-3 space-y-3 rounded-lg bg-slate-50 p-3">
      {error && <Alert>{error}</Alert>}
      <Field label="কোন সেবা?" htmlFor="rq_service">
        <Select id="rq_service" value={v.service_id} onChange={(e) => setV({ ...v, service_id: e.target.value })}>
          <option value="">বেছে নিন (ঐচ্ছিক)</option>
          {services.map((s) => (
            <option key={s.id} value={s.id}>
              {s.name_bn || s.name}
            </option>
          ))}
        </Select>
      </Field>
      <div className="grid grid-cols-2 gap-2">
        <Field label="পছন্দের তারিখ" htmlFor="rq_date">
          <Input id="rq_date" type="date" min={todayISO()} value={v.preferred_date} onChange={(e) => setV({ ...v, preferred_date: e.target.value })} />
        </Field>
        <Field label="সময়" htmlFor="rq_time">
          <Select id="rq_time" value={v.preferred_time} onChange={(e) => setV({ ...v, preferred_time: e.target.value })}>
            <option value="">যেকোনো</option>
            <option value="morning">সকাল</option>
            <option value="afternoon">দুপুর</option>
            <option value="evening">বিকাল/সন্ধ্যা</option>
          </Select>
        </Field>
      </div>
      <Field label="কিছু বলতে চান? (সময় পরিবর্তন, বাতিল ইত্যাদি)" htmlFor="rq_msg">
        <Textarea id="rq_msg" rows={2} value={v.message} onChange={(e) => setV({ ...v, message: e.target.value })} />
      </Field>
      <Button className="w-full" loading={request.isPending} onClick={submit}>
        অনুরোধ পাঠান
      </Button>
    </div>
  )
}

// ---- অগ্রগতি ----------------------------------------------------------------------------------

export function PortalProgress() {
  return (
    <div className="space-y-4">
      <h1 className="text-xl font-semibold text-slate-900">অগ্রগতি</h1>
      <ChildFrame>{(child) => <ProgressBody child={child} />}</ChildFrame>
    </div>
  )
}

const goalStatusBn: Record<string, string> = { not_started: 'শুরু হয়নি', in_progress: 'চলছে', achieved: 'অর্জিত', on_hold: 'স্থগিত' }

function ProgressBody({ child }: { child: Child }) {
  const { data, isLoading } = usePortalProgress(child.id)
  if (isLoading || !data) return <Spinner className="text-brand-600" />

  return (
    <>
      {child.has_training && <AttendanceCard childId={child.id} />}

      {data.programmes.flatMap((p) => p.plans.map((plan) => ({ p, plan }))).map(({ p, plan }) => (
        <Card key={plan.title} className="p-4">
          <h2 className="font-semibold text-slate-900">{plan.title}</h2>
          <p className="text-xs text-slate-500">
            {p.label}
            {plan.review_date && ` · পর্যালোচনা ${bnDate(plan.review_date, { day: 'numeric', month: 'long' })}`}
          </p>
          <ul className="mt-3 space-y-3">
            {plan.goals.map((g) => (
              <li key={g.title}>
                <div className="flex items-center justify-between gap-2 text-sm">
                  <span className="font-medium text-slate-800">{g.title}</span>
                  <span className="text-xs text-slate-500">{goalStatusBn[g.status] ?? g.status}</span>
                </div>
                {g.target && <p className="text-xs text-slate-500">{g.target}</p>}
                <div className="mt-1 flex items-center gap-2">
                  <div className="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                    <div className="h-full rounded-full bg-brand-500" style={{ width: `${g.progress_percent}%` }} />
                  </div>
                  <span className="w-10 text-right text-xs text-slate-600">{bnNumber(g.progress_percent)}%</span>
                </div>
              </li>
            ))}
          </ul>
        </Card>
      ))}

      <Card className="p-4">
        <div className="flex items-center justify-between gap-2">
          <h2 className="font-semibold text-slate-900">রিপোর্ট</h2>
          <a href={portalPdf.progress(child.id)} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-sm font-medium text-brand-700">
            <FileDown className="size-4" /> অগ্রগতি রিপোর্ট
          </a>
        </div>
        <ul className="mt-2 divide-y divide-slate-100">
          {data.reports.map((r) => (
            <li key={r.id} className="py-2.5">
              <a href={portalPdf.assessment(r.id)} target="_blank" rel="noreferrer" className="flex items-center justify-between gap-2">
                <span className="font-medium text-slate-900">{r.title_bn || r.title}</span>
                <FileDown className="size-4 text-slate-400" />
              </a>
              <p className="text-xs text-slate-500">
                {bnDate(r.date)} · {r.by}
              </p>
              {r.summary && <p className="mt-1 text-sm text-slate-700">{r.summary}</p>}
            </li>
          ))}
          {data.reports.length === 0 && <li className="py-2 text-sm text-slate-500">এখনো কোনো রিপোর্ট শেয়ার করা হয়নি।</li>}
        </ul>
      </Card>

      <Card className="p-4">
        <h2 className="font-semibold text-slate-900">সেশন ও ক্লাসের নোট</h2>
        <ul className="mt-2 space-y-3">
          {data.notes.map((n, i) => (
            <li key={i} className={cn('border-l-2 pl-3', n.kind === 'therapy' ? 'border-sky-brand-300' : 'border-brand-300')}>
              <p className="flex items-center gap-1.5 text-xs text-slate-500">
                {n.kind === 'therapy' ? <HeartPulse className="size-3.5" /> : <House className="size-3.5" />}
                {n.title} · {bnDate(n.date, { day: 'numeric', month: 'long' })}
                {n.by && ` · ${n.by}`}
              </p>
              {n.text && <p className="text-sm text-slate-800">{n.text}</p>}
              {n.home_practice && <p className="mt-1 text-sm text-brand-800">বাসায় অনুশীলন: {n.home_practice}</p>}
            </li>
          ))}
          {data.notes.length === 0 && <li className="text-sm text-slate-500">এখনো কোনো নোট নেই।</li>}
        </ul>
      </Card>
    </>
  )
}

const attendanceTone: Record<string, string> = {
  present: 'bg-brand-500 text-white',
  late: 'bg-amber-400 text-white',
  absent: 'bg-red-500 text-white',
  leave: 'bg-sky-brand-400 text-white',
  holiday: 'bg-slate-200 text-slate-500',
}

function AttendanceCard({ childId }: { childId: number }) {
  const [month, setMonth] = useState(todayISO().slice(0, 7))
  const { data } = usePortalAttendance(childId, month, true)
  const [y, m] = month.split('-').map(Number)
  const days = new Date(y, m, 0).getDate()
  const firstWeekday = new Date(y, m - 1, 1).getDay()
  const byDate = Object.fromEntries((data?.days ?? []).map((d) => [d.date, d.status]))

  return (
    <Card className="p-4">
      <div className="flex items-center justify-between gap-2">
        <h2 className="font-semibold text-slate-900">উপস্থিতি</h2>
        <Input type="month" max={todayISO().slice(0, 7)} value={month} onChange={(e) => setMonth(e.target.value)} className="w-40" aria-label="মাস" />
      </div>
      {data?.summary && (
        <p className="mt-1 text-sm text-slate-600">
          উপস্থিত {bnNumber(data.summary.present + data.summary.late)} দিন · অনুপস্থিত {bnNumber(data.summary.absent)} দিন
          {data.summary.rate != null && <b className="ml-1 text-brand-700">({bnNumber(data.summary.rate)}%)</b>}
        </p>
      )}
      <div className="mt-3 grid grid-cols-7 gap-1 text-center text-xs">
        {['র', 'সো', 'ম', 'বু', 'বৃ', 'শু', 'শ'].map((d) => (
          <span key={d} className="text-slate-400">
            {d}
          </span>
        ))}
        {Array.from({ length: firstWeekday }).map((_, i) => (
          <span key={`b${i}`} />
        ))}
        {Array.from({ length: days }).map((_, i) => {
          const date = `${month}-${String(i + 1).padStart(2, '0')}`
          const status = byDate[date]
          return (
            <span key={date} className={cn('rounded-md py-1.5', status ? attendanceTone[status] : 'text-slate-500')}>
              {bnNumber(i + 1)}
            </span>
          )
        })}
      </div>
      <p className="mt-2 flex flex-wrap gap-3 text-xs text-slate-500">
        {[
          ['present', 'উপস্থিত'],
          ['late', 'দেরি'],
          ['absent', 'অনুপস্থিত'],
          ['leave', 'ছুটি'],
          ['holiday', 'বন্ধ'],
        ].map(([k, l]) => (
          <span key={k} className="flex items-center gap-1">
            <span className={cn('size-2.5 rounded-sm', attendanceTone[k])} /> {l}
          </span>
        ))}
      </p>
    </Card>
  )
}

// ---- বিল --------------------------------------------------------------------------------------

export function PortalBilling() {
  return (
    <div className="space-y-4">
      <h1 className="text-xl font-semibold text-slate-900">বিল ও পেমেন্ট</h1>
      <ChildFrame>{(child) => <BillingBody child={child} />}</ChildFrame>
    </div>
  )
}

function BillingBody({ child }: { child: Child }) {
  const { data, isLoading } = usePortalBilling(child.id)
  if (isLoading || !data) return <Spinner className="text-brand-600" />

  return (
    <>
      <div className="grid grid-cols-2 gap-3">
        <Stat icon={Wallet} label="মোট বকেয়া" value={bnTaka(data.due)} tone={data.due > 0 ? 'text-red-600' : 'text-brand-700'} />
        <Stat icon={Receipt} label="অগ্রিম জমা" value={bnTaka(data.advance)} />
      </div>
      {data.due > 0 && <p className="text-sm text-slate-600">বকেয়া পরিশোধ করতে রিসেপশনে নগদ, বিকাশ, নগদ (মোবাইল) বা কার্ডে দিন। রসিদ এখানেই পাবেন।</p>}

      {data.packages.length > 0 && (
        <Card className="p-4">
          <h2 className="font-semibold text-slate-900">প্যাকেজ</h2>
          {data.packages.map((p, i) => (
            <div key={i} className="mt-3">
              <div className="flex justify-between text-sm">
                <span className="font-medium text-slate-800">{p.name_bn || p.name}</span>
                <span className="text-slate-600">বাকি {bnNumber(p.remaining)}টি সেশন</span>
              </div>
              <div className="mt-1 h-2 overflow-hidden rounded-full bg-slate-100">
                <div className="h-full rounded-full bg-brand-500" style={{ width: `${(p.used_sessions / p.total_sessions) * 100}%` }} />
              </div>
              <p className="mt-0.5 text-xs text-slate-500">
                {bnNumber(p.total_sessions)}টির মধ্যে {bnNumber(p.used_sessions)}টি ব্যবহার · মেয়াদ {bnDate(p.expiry_date)}
              </p>
            </div>
          ))}
        </Card>
      )}

      <Card className="p-4">
        <h2 className="font-semibold text-slate-900">বিল (ইনভয়েস)</h2>
        <ul className="mt-2 divide-y divide-slate-100">
          {data.invoices.map((i) => (
            <li key={i.id}>
              <a href={portalPdf.invoice(i.id)} target="_blank" rel="noreferrer" className="flex items-center justify-between gap-2 py-2.5">
                <div className="min-w-0">
                  <p className="text-sm font-medium text-slate-900">{i.items.join(', ')}</p>
                  <p className="text-xs text-slate-500">
                    {i.invoice_no} · {bnDate(i.issue_date)}
                  </p>
                </div>
                <div className="text-right">
                  <p className="text-sm font-semibold">{bnTaka(i.total)}</p>
                  <Badge tone={invoiceStatusBn[i.status]?.tone ?? 'gray'}>{invoiceStatusBn[i.status]?.label ?? i.status}</Badge>
                </div>
              </a>
            </li>
          ))}
          {data.invoices.length === 0 && <li className="py-2 text-sm text-slate-500">কোনো বিল নেই।</li>}
        </ul>
      </Card>

      <Card className="p-4">
        <h2 className="font-semibold text-slate-900">পেমেন্ট ও রসিদ</h2>
        <ul className="mt-2 divide-y divide-slate-100">
          {data.payments.map((p) => (
            <li key={p.id}>
              <a href={portalPdf.receipt(p.id)} target="_blank" rel="noreferrer" className="flex items-center justify-between gap-2 py-2.5">
                <div>
                  <p className="text-sm font-medium text-slate-900">
                    {p.type === 'refund' ? 'ফেরত' : 'পরিশোধ'} · {methodBn[p.method] ?? p.method}
                  </p>
                  <p className="text-xs text-slate-500">
                    {p.receipt_no} · {bnDate(p.paid_at)}
                  </p>
                </div>
                <span className="flex items-center gap-2 text-sm font-semibold">
                  {bnTaka(p.amount)} <FileDown className="size-4 text-slate-400" />
                </span>
              </a>
            </li>
          ))}
          {data.payments.length === 0 && <li className="py-2 text-sm text-slate-500">এখনো কোনো পেমেন্ট নেই।</li>}
        </ul>
      </Card>
    </>
  )
}

// ---- প্রোফাইল ---------------------------------------------------------------------------------

const relationBn: Record<string, string> = { father: 'বাবা', mother: 'মা', grandparent: 'দাদা/দাদি/নানা/নানি', sibling: 'ভাই/বোন', uncle: 'চাচা/মামা', aunt: 'খালা/ফুফু', other: 'অন্যান্য' }
const requestStatusBn: Record<string, string> = { new: 'নতুন', contacted: 'যোগাযোগ করা হয়েছে', converted: 'অ্যাপয়েন্টমেন্ট হয়েছে', rejected: 'বাতিল', spam: 'বাতিল' }

export function PortalProfile() {
  const { data, isLoading } = usePortalProfile()
  if (isLoading || !data) return <Spinner className="text-brand-600" />

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-semibold text-slate-900">প্রোফাইল</h1>
      <Card className="p-4">
        <p className="text-lg font-semibold text-slate-900">{data.guardian?.name}</p>
        <p className="text-sm text-slate-600">মোবাইল: {data.guardian?.phone}</p>
        {data.guardian?.email && <p className="text-sm text-slate-600">ইমেইল: {data.guardian.email}</p>}
        {data.guardian?.address && <p className="text-sm text-slate-600">ঠিকানা: {data.guardian.address}</p>}
        <p className="mt-2 text-xs text-slate-500">তথ্য পরিবর্তন করতে রিসেপশনে জানান।</p>
        <Link to="/change-password" className="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-brand-700">
          <KeyRound className="size-4" /> পাসওয়ার্ড পরিবর্তন
        </Link>
      </Card>
      <Card className="p-4">
        <h2 className="font-semibold text-slate-900">আমার সন্তান</h2>
        <ul className="mt-2 divide-y divide-slate-100 text-sm">
          {data.children.map((c) => (
            <li key={c.id} className="flex justify-between py-2">
              <span>{c.name_bn || c.name}</span>
              <span className="text-slate-500">
                {c.patient_code} · {relationBn[c.relationship] ?? c.relationship}
              </span>
            </li>
          ))}
        </ul>
      </Card>
      {data.requests.length > 0 && (
        <Card className="p-4">
          <h2 className="font-semibold text-slate-900">আমার অনুরোধ</h2>
          <ul className="mt-2 divide-y divide-slate-100 text-sm">
            {data.requests.map((r) => (
              <li key={r.reference} className="py-2">
                <div className="flex justify-between">
                  <span>{r.reference}</span>
                  <Badge tone={r.status === 'converted' ? 'green' : r.status === 'new' ? 'amber' : 'gray'}>{requestStatusBn[r.status] ?? r.status}</Badge>
                </div>
                {r.message && <p className="text-xs text-slate-500">{r.message}</p>}
              </li>
            ))}
          </ul>
        </Card>
      )}
    </div>
  )
}
