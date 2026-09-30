<template>
  <div>
    <!-- Page Header -->
    <div class="mb-6">
      <h1 class="text-h5 font-weight-bold text-slate-800 mb-1">
        บันทึกประวัติการใช้งาน (Audit Logs)
      </h1>
      <p class="text-body-2 text-medium-emphasis">
        ตรวจสอบประวัติการเข้าสู่ระบบ การดาวน์โหลด และการพรีวิวไฟล์ตามมาตรฐานความปลอดภัย
      </p>
    </div>

    <!-- Filter Bar -->
    <v-card class="rounded-lg border pa-4 mb-4 bg-white elevation-1">
      <v-row dense align="center">
        <v-col cols="12" sm="6" md="3">
          <v-select
            v-model="actionFilter"
            :items="actionOptions"
            label="ประเภทการกระทำ"
            variant="outlined"
            density="compact"
            hide-details
            @update:model-value="fetchLogs"
          ></v-select>
        </v-col>
      </v-row>
    </v-card>

    <!-- Logs Table -->
    <v-card class="rounded-lg border elevation-1 overflow-hidden bg-white">
      <v-table hover>
        <thead>
          <tr class="bg-slate-50">
            <th class="text-left font-weight-bold">วัน - เวลา</th>
            <th class="text-left font-weight-bold">ผู้ดำเนินการ</th>
            <th class="text-center font-weight-bold">การกระทำ (Action)</th>
            <th class="text-left font-weight-bold">ไฟล์ที่เกี่ยวข้อง</th>
            <th class="text-left font-weight-bold">IP Address</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="log in logs" :key="log.id">
            <td class="text-medium-emphasis">{{ formatDate(log.created_at) }}</td>
            <td class="font-weight-medium text-slate-800">
              <span v-if="log.teacher">{{ log.teacher.name }} ({{ log.teacher.teacher_code }})</span>
              <span v-else class="text-medium-emphasis">ระบบ</span>
            </td>
            <td class="text-center">
              <v-chip size="x-small" :color="getActionColor(log.action)" variant="tonal" class="font-weight-bold">
                {{ getActionText(log.action) }}
              </v-chip>
            </td>
            <td class="text-medium-emphasis text-caption">
              <span v-if="log.file">{{ log.file.original_filename }}</span>
              <span v-else>-</span>
            </td>
            <td class="text-caption text-slate-500"><code>{{ log.ip_address || '-' }}</code></td>
          </tr>
        </tbody>
      </v-table>
    </v-card>

    <!-- Pagination -->
    <div v-if="totalPages > 1" class="d-flex justify-center mt-4">
      <v-pagination v-model="page" :length="totalPages" density="compact" color="primary" @update:model-value="fetchLogs"></v-pagination>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const logs = ref([])
const actionFilter = ref('all')
const page = ref(1)
const totalPages = ref(1)

const actionOptions = [
  { title: 'การกระทำทั้งหมด', value: 'all' },
  { title: 'เข้าสู่ระบบ (login)', value: 'login' },
  { title: 'ออกจากระบบ (logout)', value: 'logout' },
  { title: 'ดาวน์โหลดไฟล์ (download)', value: 'download' },
  { title: 'ดูตัวอย่างไฟล์ (preview)', value: 'preview' },
]

const fetchLogs = async () => {
  try {
    const params = {
      page: page.value,
      action: actionFilter.value !== 'all' ? actionFilter.value : undefined,
    }
    const res = await api.get('/admin/logs', { params })
    logs.value = res.data.data || []
    totalPages.value = res.data.last_page || 1
  } catch (err) {
    console.error('Failed to load audit logs:', err)
  }
}

const getActionColor = (action) => {
  switch (action) {
    case 'login': return 'success'
    case 'logout': return 'grey'
    case 'download': return 'primary'
    case 'preview': return 'info'
    default: return 'purple'
  }
}

const getActionText = (action) => {
  switch (action) {
    case 'login': return 'เข้าสู่ระบบ'
    case 'logout': return 'ออกจากระบบ'
    case 'download': return 'ดาวน์โหลด'
    case 'preview': return 'พรีวิว'
    default: return action
  }
}

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return d.toLocaleDateString('th-TH', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  })
}

onMounted(() => {
  fetchLogs()
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
