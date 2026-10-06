import axios, { AxiosError } from 'axios'

/**
 * Same-origin API client. Sanctum SPA auth uses the session cookie + XSRF-TOKEN cookie,
 * so no token is ever stored in the browser.
 */
export const api = axios.create({
  baseURL: '/api/v1',
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json' },
})

export function fetchCsrfCookie() {
  return axios.get('/sanctum/csrf-cookie', { withCredentials: true })
}

type LaravelError = { message?: string; errors?: Record<string, string[]> }

/** Field errors from a Laravel 422 response, flattened to the first message per field. */
export function validationErrors(error: unknown): Record<string, string> {
  if (error instanceof AxiosError && error.response?.status === 422) {
    const errors = (error.response.data as LaravelError).errors ?? {}
    return Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, messages[0]]))
  }
  return {}
}

export function errorMessage(error: unknown): string {
  if (error instanceof AxiosError) {
    if (error.response?.status === 403) return 'You do not have permission to do this.'
    if (error.response?.status === 429) return 'Too many attempts. Please wait a minute and try again.'
    return (error.response?.data as LaravelError)?.message ?? error.message
  }
  return 'Something went wrong.'
}
