import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { HashRouter } from 'react-router'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { Toaster } from "@/components/ui/sonner"
import '@/index.css'
import { exposeElemacyGlobals } from '@/lib/globals'
import App from '@/App.tsx'

exposeElemacyGlobals()

const queryClient = new QueryClient()

createRoot(document.getElementById('elemacy_root')!).render(
  <StrictMode>
    <HashRouter>
      <QueryClientProvider client={queryClient}>
        <App />
        <Toaster />
      </QueryClientProvider>
    </HashRouter>
  </StrictMode>
)
