import { Route, Routes } from "react-router"
import Dashboard from "@/features/dashboard/pages/dashboard"
import ThemeBuilder from "@/features/theme-builder/pages/theme-builder"
import Widgets from "@/features/widgets/pages/widgets"
import Modules from "@/pages/modules/modules"

function App() {
  return (
    <Routes>
      <Route path="/" element={<Dashboard />} />
      <Route path="/theme-builder" element={<ThemeBuilder />} />
      <Route path="/widgets" element={<Widgets />} />
      <Route path="/modules" element={<Modules />} />
    </Routes>
  )
}

export default App
