/** e.g. "Tuesday, 6 October" — the center works in Asia/Dhaka time. */
export function longDate(date: Date) {
  return date.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'Asia/Dhaka' })
}

/** Today as YYYY-MM-DD in Dhaka time (for date inputs). */
export function todayISO() {
  return new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Dhaka' })
}
