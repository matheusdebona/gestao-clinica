import { QueryClient } from '@tanstack/vue-query'
import { ApiError } from '@/types/user'

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      refetchOnWindowFocus: false,
      retry: (count, error) => {
        if (
          error instanceof ApiError &&
          (error.status === 401 || error.status === 403 || error.status === 422 || error.status === 404)
        ) {
          return false
        }
        return count < 1
      },
    },
    mutations: {
      retry: false,
    },
  },
})
