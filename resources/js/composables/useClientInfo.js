import { reactive, readonly } from 'vue'
import axios from 'axios'

const state = reactive({
  logo: null,
  name: '',
  location: '',
  pin: '',
  phone: '',
  gst_number: '',
  loaded: false
})

let loadingPromise = null

function ensureLoaded() {
  if (!loadingPromise) {
    loadingPromise = axios
      .get('/client-info')
      .then(({ data }) => {
        state.logo = data.logo ?? null
        state.name = data.name ?? ''
        state.location = data.location ?? ''
        state.pin = data.pin ?? ''
        state.phone = data.phone ?? ''
        state.gst_number = data.gst_number ?? ''
        state.loaded = true
      })
      .catch((error) => {
        console.error('Failed to load client info:', error)
      })
  }
  return loadingPromise
}

export const useClientInfo = () => {
  // Kick off load on first use; components can just read `client`
  ensureLoaded()
  return readonly(state)
}

// Optional explicit loader if a component wants to await it
export const loadClientInfo = () => ensureLoaded()