import { CalendarDays, Home, TrendingUp, UserRound, Wallet } from 'lucide-react'
import type { BottomNavItem } from '../../layouts/MobileAppLayout'

/** Parent portal is Bangla-first (decision D7). */
export const portalNav: BottomNavItem[] = [
  { label: 'হোম', to: '/portal', icon: Home },
  { label: 'সময়সূচি', to: '/portal/schedule', icon: CalendarDays },
  { label: 'অগ্রগতি', to: '/portal/progress', icon: TrendingUp },
  { label: 'বিল', to: '/portal/billing', icon: Wallet },
  { label: 'প্রোফাইল', to: '/portal/profile', icon: UserRound },
]
