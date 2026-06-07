import * as React from 'react'
import * as ReactDOM from 'react-dom/client'
import * as ElemacyComponents from '@/components/ui'
import { toast } from 'sonner'
import { registry } from '@/lib/registry'
import { routeRegistry } from '@/lib/route-registry'

export function exposeElemacyGlobals(): void {
  window.ElemacyShared = Object.freeze({
    React,
    ReactDOM,
    components: ElemacyComponents,
    toast,
    registry,
    routes: routeRegistry,
  })
}
