import * as React from 'react'
import * as ReactDOM from 'react-dom/client'
import * as ElemacyComponents from '@/components/ui'
import { toast } from 'sonner'

export function exposeElemacyGlobals(): void {
  window.ElemacyShared = Object.freeze({
    React,
    ReactDOM,
    components: ElemacyComponents,
    toast,
  })
}
