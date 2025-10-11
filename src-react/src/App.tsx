import { Route, Routes } from "react-router"
import Dashboard from "@/features/dashboard/pages/dasbhoard"
import ThemeBuilder from "./features/theme-builder/pages/theme-builder"

function App() {
  return (
    <Routes>
      <Route path="/" element={<Dashboard />} />
      <Route path="/theme-builder" element={<ThemeBuilder />} />
    </Routes>
  )
}

export default App
