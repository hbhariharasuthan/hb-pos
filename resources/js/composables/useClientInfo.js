import { reactive, readonly } from 'vue'

const state = reactive({
  logo: window.CLIENT_INFO.logo ?? null,
  name: window.CLIENT_INFO.name,
  location: window.CLIENT_INFO.location,
  pin: window.CLIENT_INFO.pin,
  phone: window.CLIENT_INFO.phone,
})

export const useClientInfo = () => {
  return readonly(state)
}
