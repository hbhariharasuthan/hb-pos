import { reactive, readonly } from 'vue'

let state = null

export const useClientInfo = () => {
  if (!state) {
    const clientInfo = window.CLIENT_INFO ?? {}

    state = reactive({
      logo: clientInfo.logo ?? null,
      name: clientInfo.name ?? '',
      location: clientInfo.location ?? '',
      pin: clientInfo.pin ?? '',
      phone: clientInfo.phone ?? '',
    })
  }

  return readonly(state)
}