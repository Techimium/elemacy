import { __ } from "@wordpress/i18n";
import { useId } from "react"

import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { useToggleModuleMutation } from "@/services/module";

interface ModuleSwitchProps {
  isEnabled?: boolean
  name: string
}

export default function ModuleSwitch({ isEnabled, name }: ModuleSwitchProps) {
  const id = useId()
  const { mutateAsync: toggleModule } = useToggleModuleMutation();

  return (
    <div className="flex">
      <Switch
        id={id}
        defaultChecked={isEnabled}
        onCheckedChange={(value) => toggleModule({ isEnabled: value, name })}
      />
      <Label htmlFor={id} className="sr-only">
        {__('Toggle Module', 'elemacy')}
      </Label>
    </div>
  )
}
