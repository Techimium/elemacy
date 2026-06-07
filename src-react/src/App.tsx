import { useSyncExternalStore } from "react"
import { Route, Routes } from "react-router"
import Dashboard from "@/features/dashboard/pages/dashboard"
import ThemeBuilder from "@/features/theme-builder/pages/theme-builder"
import Popups from "@/features/popups/pages/popups"
import Widgets from "@/features/widgets/pages/widgets"
import Modules from "@/pages/modules/modules"
import { routeRegistry } from "@/lib/route-registry"

function App() {
  const extraRoutes = useSyncExternalStore(routeRegistry.subscribe, routeRegistry.getAll)

  return (
    <Routes>
      <Route path="/" element={<Dashboard />} />
      <Route path="/theme-builder" element={<ThemeBuilder />} />
      <Route path="/popups" element={<Popups />} />
      <Route path="/widgets" element={<Widgets />} />
      <Route path="/modules" element={<Modules />} />
      {extraRoutes.map(({ path, component: Component }) => (
        <Route key={path} path={path} element={<Component />} />
      ))}
    </Routes>
  )
}

export default App
