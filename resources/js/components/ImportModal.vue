<template>
  <teleport to="body">
    <div v-if="show" class="modal-overlay" @click.self="close">
      <div class="modal-card">
        <h2>Import {{ title }}</h2>

        <!-- Sample Download -->
        <button class="btn outline" @click="downloadSample">
          Download Sample {{ title }} CSV
        </button>

        <!-- Upload -->
        <div class="upload-box">
          <input type="file" id="file" @change="upload" />
          <label for="file">
            {{ file ? file.name : 'Choose CSV / Excel file' }}
          </label>
        </div>

        <!-- Import -->
        <button
          class="btn primary"
          :disabled="!file || loading"
          @click="importFile"
        >
          {{ loading ? 'Importing...' : 'Import' }}
        </button>

        <!-- Result -->
        <div v-if="result" class="result-box">
          <p class="success">Imported: {{ result.success }}</p>

          <ul v-if="result.errors?.length">
            <li v-for="err in result.errors" :key="err.row">
              Row {{ err.row }} – {{ err.error }}
            </li>
          </ul>
        </div>

        <button class="btn close" @click="close">Close</button>
      </div>
    </div>
  </teleport>
</template>

<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
  show: Boolean,
  type: String
})

const emit = defineEmits(['close'])

const file = ref(null)
const result = ref(null)
const loading = ref(false)

const title = computed(() =>
  props.type.charAt(0).toUpperCase() + props.type.slice(1)
)

/* ✅ CORRECT DOWNLOAD FUNCTION */
const downloadSample = async () => {
  try {
    const response = await axios.get(
      `/api/import/sample/${props.type}`,
      { responseType: 'blob' }
    )

    const blob = new Blob([response.data])
    const url = window.URL.createObjectURL(blob)

    const a = document.createElement('a')
    a.href = url
    a.download = `${props.type}_sample.xlsx`
    a.click()

    window.URL.revokeObjectURL(url)
  } catch (error) {
    alert('Sample download failed')
    console.error(error)
  }
}

const upload = e => {
  file.value = e.target.files[0]
}

const importFile = async () => {
  loading.value = true
  result.value = null

  const formData = new FormData()
  formData.append('file', file.value)

  try {
    const res = await axios.post(`/api/import/${props.type}`, formData)
    result.value = res.data.result
  } finally {
    loading.value = false
  }
}

const close = () => {
  file.value = null
  result.value = null
  emit('close')
}
</script>




<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 999;
}

.modal-card {
  background: #fff;
  width: 420px;
  padding: 24px;
  border-radius: 12px;
}

.upload-box input {
  display: none;
}

.upload-box label {
  display: block;
  padding: 12px;
  border: 2px dashed #cbd5e1;
  border-radius: 8px;
  text-align: center;
  cursor: pointer;
  margin-top: 12px;
}

.btn {
  width: 100%;
  padding: 10px;
  margin-top: 12px;
  border-radius: 6px;
  border: none;
  cursor: pointer;
  font-weight: 600;
}

.btn.primary {
  background: #2563eb;
  color: white;
}

.btn.outline {
  background: transparent;
  color: #2563eb;
  border: 1px solid #2563eb;
}

.btn.outline:hover {
  background: #2563eb;
  color: white;
}

.btn.close {
  background: #e5e7eb;
}

.success {
  color: #16a34a;
  margin-top: 10px;
}

.result-box {
  margin-top: 12px;
}
</style>

