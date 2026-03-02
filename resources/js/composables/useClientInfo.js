import { reactive, readonly } from 'vue'

let state = null

function getClientInfoRaw() {
  const raw = typeof window !== 'undefined' ? window.CLIENT_INFO : null
  return raw != null && typeof raw === 'object' ? raw : {}
}

function ensureString(val) {
  return val != null && typeof val === 'string' ? val : ''
}

export const useClientInfo = () => {
  if (!state) {
    const clientInfo = getClientInfoRaw()
    state = reactive({
      logo: clientInfo.logo ?? null,
      name: ensureString(clientInfo.name),
      location: ensureString(clientInfo.location),
      pin: ensureString(clientInfo.pin),
      phone: ensureString(clientInfo.phone),
      gst_number: ensureString(clientInfo.gst_number),
    })
  }

  return readonly(state)
}