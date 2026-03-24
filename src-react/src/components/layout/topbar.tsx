import { Button } from "@/components/ui/button"
import Container from "@/components/container"

function Topbar() {
  return (
    <div className="bg-white px-6 py-2 border-b">
      <Container>
        <div className="flex justify-between items-center">
          <div>
            <img src="http://localhost:5173/src/assets/images/logo.png" alt="Elemacy Logo" className="h-12" />
          </div>
          <Button variant="outline">Docs</Button>
        </div>
      </Container>
    </div>
  )
}

export default Topbar
