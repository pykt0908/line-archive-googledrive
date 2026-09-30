<template>
  <div>
    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-space-between align-center mb-6 gap-2">
      <div>
        <h1 class="text-h5 font-weight-bold text-slate-800 mb-1">
          จัดการไฟล์ทั้งหมด (Admin File Manager)
        </h1>
        <p class="text-body-2 text-medium-emphasis">
          ศูนย์ควบคุมเอกสาร: ดูตัวอย่าง แก้ไขข้อมูล ลบไฟล์ และซิงค์ Google Drive
        </p>
      </div>

      <!-- Add File Button -->
      <v-btn
        color="primary"
        prepend-icon="mdi-cloud-upload-outline"
        class="elevation-1 font-weight-bold rounded-md"
        @click="openUploadDialog"
      >
        เพิ่ม / อัปโหลดไฟล์ใหม่
      </v-btn>
    </div>

    <!-- Filter Bar -->
    <v-card class="rounded-lg border pa-4 mb-4 bg-white elevation-1">
      <v-row dense align="center">
        <v-col cols="12" sm="6" md="4">
          <v-text-field
            v-model="search"
            placeholder="ค้นหาชื่อไฟล์ หรือผู้ส่ง..."
            prepend-inner-icon="mdi-magnify"
            variant="outlined"
            density="compact"
            hide-details
            clearable
            @update:model-value="debouncedFetch"
          ></v-text-field>
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-select
            v-model="statusFilter"
            :items="statusOptions"
            label="สถานะการประมวลผล"
            variant="outlined"
            density="compact"
            hide-details
            @update:model-value="fetchFiles"
          ></v-select>
        </v-col>
        <v-spacer></v-spacer>
        <v-col cols="auto">
          <v-btn
            icon="mdi-refresh"
            variant="tonal"
            size="small"
            color="primary"
            class="rounded-md"
            title="รีเฟรชข้อมูล"
            @click="fetchFiles"
          ></v-btn>
        </v-col>
      </v-row>
    </v-card>

    <!-- Files Table -->
    <v-card class="rounded-lg border elevation-1 overflow-hidden bg-white">
      <v-table hover>
        <thead>
          <tr class="bg-slate-50">
            <th class="text-left font-weight-bold">สถานะ</th>
            <th class="text-left font-weight-bold">ชื่อไฟล์ต้นฉบับ</th>
            <th class="text-left font-weight-bold">กลุ่ม LINE</th>
            <th class="text-left font-weight-bold">ผู้ส่ง</th>
            <th class="text-left font-weight-bold">ขนาด</th>
            <th class="text-left font-weight-bold">วันที่สร้าง</th>
            <th class="text-center font-weight-bold" style="min-width: 170px;">การจัดการ (Admin)</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="files.length === 0">
            <td colspan="7" class="text-center py-8 text-medium-emphasis">
              <v-icon icon="mdi-file-outline" size="36" class="mb-2 text-slate-300"></v-icon>
              <div>ไม่พบรายการไฟล์ที่ตรงกับเงื่อนไข</div>
            </td>
          </tr>
          <tr v-for="file in files" :key="file.id">
            <td>
              <v-chip size="x-small" :color="getStatusColor(file.status)" variant="tonal" class="font-weight-bold">
                {{ getStatusText(file.status) }}
              </v-chip>
            </td>
            <td class="font-weight-medium text-slate-800">
              <div class="d-flex align-center">
                <v-icon :icon="getFileIcon(file.mime_type, file.original_filename)" size="18" class="me-2 text-primary"></v-icon>
                <span class="text-truncate cursor-pointer hover:text-primary" style="max-width: 250px;" :title="file.original_filename" @click="previewFile(file)">
                  {{ file.original_filename }}
                </span>
              </div>
              <div v-if="file.error_message" class="text-caption text-error text-truncate" style="max-width: 280px;" :title="file.error_message">
                ข้อผิดพลาด: {{ file.error_message }}
              </div>
            </td>
            <td class="text-medium-emphasis">{{ file.group?.group_name || '-' }}</td>
            <td class="text-medium-emphasis">{{ file.sender_name }}</td>
            <td class="text-medium-emphasis">{{ formatFileSize(file.file_size) }}</td>
            <td class="text-medium-emphasis">{{ formatDate(file.created_at) }}</td>
            <td class="text-center">
              <!-- Retry if failed -->
              <v-btn
                v-if="file.status === 'failed'"
                size="x-small"
                color="warning"
                variant="tonal"
                prepend-icon="mdi-reload"
                class="me-1 font-weight-bold"
                @click="retryFile(file)"
              >
                Retry
              </v-btn>

              <!-- Preview Button -->
              <v-btn
                icon="mdi-eye-outline"
                variant="text"
                size="small"
                color="primary"
                title="ดูตัวอย่าง (Preview)"
                @click="previewFile(file)"
              ></v-btn>

              <!-- Edit Button -->
              <v-btn
                icon="mdi-pencil-outline"
                variant="text"
                size="small"
                color="primary"
                title="แก้ไขข้อมูลไฟล์"
                @click="openEditDialog(file)"
              ></v-btn>

              <!-- Open Google Drive -->
              <v-btn
                v-if="file.google_drive_url"
                icon="mdi-google-drive"
                variant="text"
                size="small"
                color="secondary"
                title="เปิดใน Drive"
                :href="file.google_drive_url"
                target="_blank"
              ></v-btn>

              <!-- Download -->
              <v-btn
                icon="mdi-download"
                variant="text"
                size="small"
                color="slate-600"
                title="ดาวน์โหลด"
                @click="downloadFile(file)"
              ></v-btn>

              <!-- Delete Button -->
              <v-btn
                icon="mdi-delete-outline"
                variant="text"
                size="small"
                color="error"
                title="ลบไฟล์"
                @click="confirmDeleteFile(file)"
              ></v-btn>
            </td>
          </tr>
        </tbody>
      </v-table>
    </v-card>

    <!-- Pagination -->
    <div v-if="totalPages > 1" class="d-flex justify-center mt-4">
      <v-pagination v-model="page" :length="totalPages" density="compact" color="primary" @update:model-value="fetchFiles"></v-pagination>
    </div>

    <!-- Edit File Dialog -->
    <v-dialog v-model="editDialog" max-width="500">
      <v-card class="rounded-lg">
        <v-card-title class="pa-4 bg-slate-50 d-flex justify-space-between align-center border-b">
          <div class="d-flex align-center font-weight-bold text-subtitle-1">
            <v-icon icon="mdi-pencil-outline" color="primary" class="me-2"></v-icon>
            แก้ไขข้อมูลไฟล์ (Admin)
          </div>
          <v-btn icon="mdi-close" variant="text" size="small" @click="editDialog = false"></v-btn>
        </v-card-title>

        <v-card-text class="pa-4">
          <v-text-field
            v-model="editForm.original_filename"
            label="ชื่อไฟล์"
            variant="outlined"
            density="compact"
            class="mb-3"
            required
          ></v-text-field>

          <v-select
            v-model="editForm.group_id"
            :items="groupOptions"
            item-title="name"
            item-value="id"
            label="กลุ่ม LINE / โฟลเดอร์"
            variant="outlined"
            density="compact"
            class="mb-3"
          ></v-select>

          <v-text-field
            v-model="editForm.sender_name"
            label="ชื่อผู้ส่ง / เจ้าของเอกสาร"
            variant="outlined"
            density="compact"
            class="mb-1"
          ></v-text-field>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-3 px-4 bg-slate-50 d-flex justify-end">
          <v-btn variant="text" @click="editDialog = false">ยกเลิก</v-btn>
          <v-btn
            color="primary"
            variant="flat"
            :loading="editLoading"
            class="font-weight-bold rounded-md"
            @click="handleUpdateFile"
          >
            บันทึกการแก้ไข
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete Confirmation Dialog -->
    <v-dialog v-model="deleteDialog" max-width="450">
      <v-card class="rounded-lg">
        <v-card-title class="pa-4 bg-red-lighten-5 text-red-darken-3 d-flex align-center">
          <v-icon icon="mdi-alert-circle-outline" class="me-2" color="error"></v-icon>
          ยืนยันการลบไฟล์ (Admin)
        </v-card-title>

        <v-card-text class="pa-4">
          <p class="text-body-1 mb-2">คุณแน่ใจหรือไม่ว่าต้องการลบไฟล์นี้?</p>
          <div class="pa-3 bg-slate-100 rounded-md font-weight-bold text-slate-800 text-truncate">
            {{ fileToDelete?.original_filename }}
          </div>
          <p class="text-caption text-medium-emphasis mt-2 mb-0">
            การดำเนินการนี้จะนำไฟล์ออกจากระบบคลังเอกสารและ Google Drive
          </p>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-3 px-4 bg-slate-50 d-flex justify-end">
          <v-btn variant="text" :disabled="deleteLoading" @click="deleteDialog = false">ยกเลิก</v-btn>
          <v-btn
            color="error"
            variant="flat"
            :loading="deleteLoading"
            class="font-weight-bold rounded-md"
            @click="handleDeleteFile"
          >
            ลบไฟล์ทันที
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Upload File Dialog -->
    <v-dialog v-model="uploadDialog" max-width="540" persistent>
      <v-card class="rounded-lg">
        <v-card-title class="pa-4 bg-slate-50 d-flex justify-space-between align-center border-b">
          <div class="d-flex align-center font-weight-bold text-subtitle-1">
            <v-icon icon="mdi-cloud-upload-outline" color="primary" class="me-2"></v-icon>
            เพิ่ม / อัปโหลดไฟล์ใหม่ (Admin)
          </div>
          <v-btn icon="mdi-close" variant="text" size="small" :disabled="uploadLoading" @click="uploadDialog = false"></v-btn>
        </v-card-title>

        <v-card-text class="pa-4">
          <v-file-input
            v-model="uploadFileRef"
            label="เลือกไฟล์ (รูปภาพ, PDF, Word, Excel, วิดีโอ...)"
            variant="outlined"
            density="compact"
            prepend-icon=""
            prepend-inner-icon="mdi-paperclip"
            show-size
            class="mb-3"
            :disabled="uploadLoading"
          ></v-file-input>

          <v-select
            v-model="uploadForm.group_id"
            :items="groupOptions"
            item-title="name"
            item-value="id"
            label="เลือกกลุ่ม LINE / โฟลเดอร์ปลายทาง"
            variant="outlined"
            density="compact"
            class="mb-3"
            :disabled="uploadLoading"
          ></v-select>

          <v-text-field
            v-model="uploadForm.sender_name"
            label="ชื่อผู้ส่ง / เจ้าของเอกสาร"
            variant="outlined"
            density="compact"
            class="mb-1"
            :disabled="uploadLoading"
          ></v-text-field>

          <v-progress-linear
            v-if="uploadLoading"
            indeterminate
            color="primary"
            class="mt-3 rounded"
          ></v-progress-linear>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-3 px-4 bg-slate-50 d-flex justify-end">
          <v-btn variant="text" :disabled="uploadLoading" @click="uploadDialog = false">ยกเลิก</v-btn>
          <v-btn
            color="primary"
            variant="flat"
            :loading="uploadLoading"
            :disabled="!uploadFileRef"
            class="font-weight-bold rounded-md"
            @click="handleUploadFile"
          >
            อัปโหลดทันที
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Preview Dialog -->
    <v-dialog v-model="previewDialog" max-width="850" scrollable>
      <v-card v-if="activePreviewFile" class="rounded-lg">
        <v-card-title class="pa-4 bg-slate-50 d-flex justify-space-between align-center border-b">
          <div class="text-truncate text-h6 font-weight-bold">
            {{ activePreviewFile.original_filename }}
          </div>
          <v-btn icon="mdi-close" variant="text" size="small" @click="previewDialog = false"></v-btn>
        </v-card-title>
        <v-card-text class="pa-4">
          <!-- Image -->
          <div v-if="isImage(activePreviewFile)" class="text-center bg-slate-900 rounded-lg pa-3">
            <img :src="getFilePreviewUrl(activePreviewFile)" class="rounded mx-auto" style="max-height: 480px; max-width: 100%; object-fit: contain;" />
          </div>
          <!-- PDF -->
          <div v-else-if="isPdf(activePreviewFile)" style="height: 520px;">
            <iframe :src="getFilePreviewUrl(activePreviewFile)" width="100%" height="100%" frameborder="0"></iframe>
          </div>
          <!-- Office on Google Drive -->
          <div v-else-if="activePreviewFile.google_drive_file_id" style="height: 520px;">
            <iframe :src="`https://drive.google.com/file/d/${activePreviewFile.google_drive_file_id}/preview`" width="100%" height="100%" frameborder="0"></iframe>
          </div>
          <!-- Other -->
          <div v-else class="text-center pa-8 bg-slate-50 rounded-lg border">
            <v-icon icon="mdi-file-outline" size="64" class="text-slate-400 mb-2"></v-icon>
            <div class="font-weight-bold text-subtitle-1 mb-2">{{ activePreviewFile.original_filename }}</div>
            <v-btn color="primary" prepend-icon="mdi-download" @click="downloadFile(activePreviewFile)">ดาวน์โหลดไฟล์</v-btn>
          </div>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- Toast Snackbar -->
    <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000" location="bottom end">
      {{ snackbar.text }}
      <template #actions>
        <v-btn variant="text" @click="snackbar.show = false">ปิด</v-btn>
      </template>
    </v-snackbar>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const route = useRoute()
const authStore = useAuthStore()

const files = ref([])
const search = ref('')
const statusFilter = ref(route.query.status || 'all')
const page = ref(1)
const totalPages = ref(1)
const groupOptions = ref([{ name: 'ไม่ระบุกลุ่ม (General)', id: null }])

// Dialogs state
const editDialog = ref(false)
const editLoading = ref(false)
const fileToEdit = ref(null)
const editForm = reactive({
  original_filename: '',
  group_id: null,
  sender_name: '',
})

const deleteDialog = ref(false)
const deleteLoading = ref(false)
const fileToDelete = ref(null)

const uploadDialog = ref(false)
const uploadLoading = ref(false)
const uploadFileRef = ref(null)
const uploadForm = reactive({
  group_id: null,
  sender_name: '',
})

const previewDialog = ref(false)
const activePreviewFile = ref(null)

const snackbar = reactive({
  show: false,
  text: '',
  color: 'success',
})

const showToast = (text, color = 'success') => {
  snackbar.text = text
  snackbar.color = color
  snackbar.show = true
}

const statusOptions = [
  { title: 'ทุกสถานะ', value: 'all' },
  { title: 'จัดเก็บสำเร็จ (completed / ready)', value: 'completed' },
  { title: 'รอดำเนินการ (pending)', value: 'pending' },
  { title: 'กำลังประมวลผล (processing)', value: 'processing' },
  { title: 'ผิดพลาด (failed)', value: 'failed' },
]

let searchTimeout = null
const debouncedFetch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    page.value = 1
    fetchFiles()
  }, 300)
}

const fetchGroups = async () => {
  try {
    const res = await api.get('/files/groups')
    const list = res.data || []
    groupOptions.value = [
      { name: 'ไม่ระบุกลุ่ม (General)', id: null },
      ...list.map(g => ({ name: g.group_name, id: g.id }))
    ]
  } catch (err) {
    console.error('Failed to load groups:', err)
  }
}

const fetchFiles = async () => {
  try {
    const params = {
      page: page.value,
      search: search.value || undefined,
      status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
    }
    const res = await api.get('/admin/files', { params })
    files.value = res.data.data || []
    totalPages.value = res.data.last_page || 1
  } catch (err) {
    console.error('Failed to load files:', err)
  }
}

// Edit operations
const openEditDialog = (file) => {
  fileToEdit.value = file
  editForm.original_filename = file.original_filename
  editForm.group_id = file.line_group_id || null
  editForm.sender_name = file.sender_name || ''
  editDialog.value = true
}

const handleUpdateFile = async () => {
  if (!fileToEdit.value) return
  editLoading.value = true
  try {
    const res = await api.put(`/files/${fileToEdit.value.id}`, {
      original_filename: editForm.original_filename,
      group_id: editForm.group_id,
      sender_name: editForm.sender_name,
    })
    editDialog.value = false
    showToast('แก้ไขข้อมูลไฟล์สำเร็จ!')
    fetchFiles()
  } catch (err) {
    console.error('Update failed:', err)
    showToast(err.response?.data?.message || 'แก้ไขข้อมูลไฟล์ไม่สำเร็จ', 'error')
  } finally {
    editLoading.value = false
  }
}

// Delete operations
const confirmDeleteFile = (file) => {
  fileToDelete.value = file
  deleteDialog.value = true
}

const handleDeleteFile = async () => {
  if (!fileToDelete.value) return
  deleteLoading.value = true
  try {
    await api.delete(`/files/${fileToDelete.value.id}`)
    deleteDialog.value = false
    showToast('ลบไฟล์เรียบร้อยแล้ว!')
    fetchFiles()
  } catch (err) {
    console.error('Delete failed:', err)
    showToast(err.response?.data?.message || 'เกิดข้อผิดพลาดในการลบไฟล์', 'error')
  } finally {
    deleteLoading.value = false
  }
}

// Upload operations
const openUploadDialog = () => {
  uploadFileRef.value = null
  uploadForm.group_id = null
  uploadForm.sender_name = authStore.teacherName || ''
  uploadDialog.value = true
}

const handleUploadFile = async () => {
  if (!uploadFileRef.value) return
  uploadLoading.value = true
  try {
    const formData = new FormData()
    const fileObj = Array.isArray(uploadFileRef.value) ? uploadFileRef.value[0] : uploadFileRef.value
    formData.append('file', fileObj)
    if (uploadForm.group_id) formData.append('group_id', uploadForm.group_id)
    if (uploadForm.sender_name) formData.append('sender_name', uploadForm.sender_name)

    await api.post('/files', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    uploadDialog.value = false
    showToast('อัปโหลดไฟล์เรียบร้อยแล้ว!')
    fetchFiles()
  } catch (err) {
    console.error('Upload failed:', err)
    showToast(err.response?.data?.message || 'อัปโหลดไฟล์ไม่สำเร็จ', 'error')
  } finally {
    uploadLoading.value = false
  }
}

// Preview operations
const previewFile = (file) => {
  activePreviewFile.value = file
  previewDialog.value = true
}

const downloadFile = (file) => {
  const token = authStore.token || ''
  window.open(`/api/files/${file.id}/download?token=${encodeURIComponent(token)}`, '_blank')
}

const getFilePreviewUrl = (file) => {
  if (!file) return ''
  const token = authStore.token || ''
  return `/api/files/${file.id}/preview?token=${encodeURIComponent(token)}`
}

const retryFile = async (file) => {
  try {
    await api.post(`/admin/files/${file.id}/retry`)
    showToast('สั่งรันใหม่อีกครั้งแล้ว')
    fetchFiles()
  } catch (err) {
    showToast('ไม่สามารถส่งคำขอประมวลผลใหม่ได้', 'error')
  }
}

const isImage = (file) => {
  if (!file) return false
  const ext = file.original_filename?.split('.').pop()?.toLowerCase()
  return file.mime_type?.includes('image') || ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ext)
}

const isPdf = (file) => {
  if (!file) return false
  const ext = file.original_filename?.split('.').pop()?.toLowerCase()
  return file.mime_type?.includes('pdf') || ext === 'pdf'
}

const getFileIcon = (mime, filename = '') => {
  const ext = filename.split('.').pop()?.toLowerCase()
  if (['mov', 'mp4', 'avi', 'mkv', 'webm'].includes(ext) || mime?.includes('video')) return 'mdi-movie-open'
  if (mime?.includes('pdf') || ext === 'pdf') return 'mdi-file-pdf-box'
  if (mime?.includes('image') || ['jpg', 'jpeg', 'png', 'webp'].includes(ext)) return 'mdi-file-image'
  if (['xls', 'xlsx', 'csv'].includes(ext) || mime?.includes('sheet')) return 'mdi-file-excel-box'
  if (['doc', 'docx'].includes(ext) || mime?.includes('word')) return 'mdi-file-word-box'
  if (['ppt', 'pptx'].includes(ext) || mime?.includes('presentation')) return 'mdi-file-powerpoint-box'
  return 'mdi-file-outline'
}

const getStatusColor = (status) => {
  switch (status) {
    case 'completed':
    case 'ready': return 'success'
    case 'pending': return 'warning'
    case 'processing': return 'info'
    case 'failed': return 'error'
    default: return 'grey'
  }
}

const getStatusText = (status) => {
  switch (status) {
    case 'completed':
    case 'ready': return 'จัดเก็บแล้ว'
    case 'pending': return 'รอดำเนินการ'
    case 'processing': return 'กำลังประมวลผล'
    case 'failed': return 'ล้มเหลว'
    default: return status
  }
}

const formatFileSize = (bytes) => {
  if (!bytes) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`
}

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return d.toLocaleDateString('th-TH', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}

onMounted(() => {
  fetchGroups()
  fetchFiles()
})
</script>

<style scoped>
.bg-slate-50 {
  background-color: #f8fafc;
}
.text-slate-800 {
  color: #1e293b;
}
</style>
