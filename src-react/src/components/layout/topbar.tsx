import { __ } from "@wordpress/i18n"
import { Button } from "@/components/ui/button"
import Container from "@/components/container"

function Topbar() {
  return (
    <div className="bg-white px-6 py-2 border-b">
      <Container>
        <div className="flex justify-between items-center">
          <div>
            <img src="http://localhost:5173/src/assets/images/logo.png" alt={__('Elemacy Logo', 'elemacy')} className="h-12" />
          </div>
          <Button variant="outline">{__('Docs', 'elemacy')}</Button>
        </div>
      </Container>
    </div>
  )
}

export default Topbar
