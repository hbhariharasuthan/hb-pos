import { reactive, readonly } from 'vue'
const clientInfo = window.CLIENT_INFO ?? {};

const state = reactive({
  logo: clientInfo.logo ?? null,
  name: clientInfo.name ?? '',
  location: clientInfo.location ?? '',
  pin: clientInfo.pin ?? '',
  phone: clientInfo.phone ?? '',
});

export const useClientInfo = () => {
  return readonly(state)
}
