import { Route, Routes } from "react-router"
import Dashboard from "@/features/dashboard/pages/dasbhoard"
import ThemeBuilder from "@/features/theme-builder/pages/theme-builder"
import Widgets from "@/features/widgets/pages/widgets"

function App() {
  return (
    <Routes>
      <Route path="/" element={<Dashboard />} />
      <Route path="/theme-builder" element={<ThemeBuilder />} />
      <Route path="/widgets" element={<Widgets />} />
    </Routes>
  )
}

export default App
