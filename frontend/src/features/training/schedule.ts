import type { TrainingClass } from './types'

export const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
// Bangladesh week starts on Saturday.
export const weekOrder = [6, 0, 1, 2, 3, 4, 5]

/** "Sat, Sun, Mon … · 10:00–13:00" */
export function scheduleText(c: TrainingClass) {
  if (!c.schedules?.length) return 'No schedule'
  const first = c.schedules[0]
  const same = c.schedules.every((s) => s.start_time === first.start_time && s.end_time === first.end_time)
  const ordered = weekOrder.filter((d) => c.schedules!.some((s) => s.weekday === d)).map((d) => dayNames[d])
  return same ? `${ordered.join(', ')} · ${first.start_time}–${first.end_time}` : ordered.join(', ')
}
