import { __ } from "@wordpress/i18n"
import { Button } from "@/components/ui/button"
import Container from "@/components/container"
import Logo from '@/assets/images/logo.svg'
import { Link } from "react-router"

function Topbar() {
  return (
    <div className="bg-white px-6 py-2 border-b">
      <Container className="px-4">
        <div className="flex justify-between items-center">
          <Link to="/">
            <img
              src={Logo}
              alt={__('Elemacy Logo', 'elemacy')}
              width={670}
              height={155}
              className="h-8 w-auto"
            />
          </Link>
          <Button className="cursor-pointer" variant="outline" onClick={() => window.open('https://elemacy.com', '_blank')}>
            {__('Docs', 'elemacy')}
          </Button>
        </div>
      </Container>
    </div>
  )
}

export default Topbar
