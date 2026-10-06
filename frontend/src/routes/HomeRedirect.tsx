import { Navigate } from 'react-router'
import { FullPageSpinner } from '../components/ui/Spinner'
import { useAuth } from '../contexts/useAuth'

/** "/" sends a signed-in user to their own app (admin / trainer / therapist / portal). */
export function HomeRedirect() {
  const { user, isLoading } = useAuth()
  if (isLoading) return <FullPageSpinner />
  return <Navigate to={user ? user.home_path : '/login'} replace />
}
