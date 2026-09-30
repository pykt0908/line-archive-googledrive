<template>
  <div>
    <!-- Page Header -->
    <div class="mb-6">
      <h1 class="text-h5 font-weight-bold text-slate-800 mb-1">
        ภาพรวมระบบ (Dashboard)
      </h1>
      <p class="text-body-2 text-medium-emphasis">
        สถิติการจัดเก็บไฟล์ สถานะ Google Drive และการทำงานของ Webhook
      </p>
    </div>

    <!-- Alert for Failed Files (Spec Section 32) -->
    <v-alert
      v-if="stats.files_failed > 0"
      type="error"
      variant="tonal"
      class="mb-6 rounded-lg border border-red-200"
      closable
    >
      <div class="d-flex justify-space-between align-center flex-wrap gap-2">
        <div class="d-flex align-center">
          <v-icon icon="mdi-alert-circle" size="24" class="me-2"></v-icon>
          <div>
            <strong>มีไฟล์ที่ไม่สามารถจัดเก็บได้ {{ stats.files_failed }} ไฟล์</strong>
            <div class="text-caption">ตรวจสอบสาเหตุและสั่งประมวลผลใหม่ได้ในเมนูจัดการไฟล์</div>
          </div>
        </div>
        <v-btn to="/admin/files?status=failed" color="error" size="small" class="rounded-lg">
          ตรวจสอบและ Retry
        </v-btn>
      </div>
    </v-alert>

    <!-- Metrics Cards Row -->
    <v-row dense class="mb-6">
      <v-col cols="12" sm="6" md="3">
        <v-card class="rounded-lg border pa-4 bg-white elevation-1">
          <div class="d-flex justify-space-between align-center">
            <div>
              <div class="text-caption text-medium-emphasis font-weight-medium">ไฟล์ทั้งหมด</div>
              <div class="text-h4 font-weight-bold text-slate-800 mt-1">{{ stats.total_files }}</div>
            </div>
            <v-avatar color="green-lighten-5" size="48">
              <v-icon icon="mdi-file-multiple" color="primary" size="26"></v-icon>
            </v-avatar>
          </div>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card class="rounded-lg border pa-4 bg-white elevation-1">
          <div class="d-flex justify-space-between align-center">
            <div>
              <div class="text-caption text-medium-emphasis font-weight-medium">ไฟล์วันนี้</div>
              <div class="text-h4 font-weight-bold text-slate-800 mt-1">{{ stats.files_today }}</div>
            </div>
            <v-avatar color="blue-lighten-5" size="48">
              <v-icon icon="mdi-calendar-today" color="info" size="26"></v-icon>
            </v-avatar>
          </div>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card class="rounded-lg border pa-4 bg-white elevation-1">
          <div class="d-flex justify-space-between align-center">
            <div>
              <div class="text-caption text-medium-emphasis font-weight-medium">รอประมวลผล</div>
              <div class="text-h4 font-weight-bold text-warning mt-1">{{ stats.files_pending }}</div>
            </div>
            <v-avatar color="amber-lighten-5" size="48">
              <v-icon icon="mdi-clock-outline" color="warning" size="26"></v-icon>
            </v-avatar>
          </div>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card class="rounded-lg border pa-4 bg-white elevation-1">
          <div class="d-flex justify-space-between align-center">
            <div>
              <div class="text-caption text-medium-emphasis font-weight-medium">ขนาดข้อมูลรวม</div>
              <div class="text-h5 font-weight-bold text-slate-800 mt-1">{{ formatFileSize(stats.storage_bytes) }}</div>
            </div>
            <v-avatar color="purple-lighten-5" size="48">
              <v-icon icon="mdi-database-outline" color="purple" size="26"></v-icon>
            </v-avatar>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- System Status & Google Drive Integration Health -->
    <v-row class="mb-6">
      <v-col cols="12" md="6">
        <v-card class="rounded-lg border pa-5 bg-white elevation-1 h-100">
          <div class="d-flex align-center mb-4">
            <v-avatar color="blue-lighten-5" size="36" class="me-3">
              <v-icon icon="mdi-google-drive" color="info" size="20"></v-icon>
            </v-avatar>
            <div class="text-subtitle-1 font-weight-bold text-slate-800">
              สถานะ Google Drive Storage
            </div>
          </div>
          <v-list density="compact" class="bg-slate-50 rounded-lg border pa-2">
            <v-list-item>
              <v-list-item-title class="font-weight-medium text-caption">Storage Driver</v-list-item-title>
              <template #append>
                <v-chip size="small" :color="driveHealth.configured ? 'success' : 'info'" variant="tonal">
                  {{ driveHealth.driver || 'กำลังตรวจสอบ' }}
                </v-chip>
              </template>
            </v-list-item>
            <v-divider></v-divider>
            <v-list-item>
              <v-list-item-title class="font-weight-medium text-caption">Shared Drive</v-list-item-title>
              <template #append><span class="text-caption">{{ driveHealth.shared_drive_id }}</span></template>
            </v-list-item>
            <v-divider></v-divider>
            <v-list-item>
              <v-list-item-title class="font-weight-medium text-caption">Root Folder</v-list-item-title>
              <template #append><span class="text-caption">{{ driveHealth.root_folder_id }}</span></template>
            </v-list-item>
          </v-list>
        </v-card>
      </v-col>

      <v-col cols="12" md="6">
        <v-card class="rounded-lg border pa-5 bg-white elevation-1 h-100">
          <div class="d-flex align-center mb-4">
            <v-avatar color="green-lighten-5" size="36" class="me-3">
              <v-icon icon="mdi-account-group" color="primary" size="20"></v-icon>
            </v-avatar>
            <div class="text-subtitle-1 font-weight-bold text-slate-800">
              ผู้ใช้งานและกลุ่มสนทนา
            </div>
          </div>
          <v-row dense>
            <v-col cols="6">
              <div class="pa-3 bg-slate-50 rounded-lg border text-center">
                <div class="text-caption text-medium-emphasis">ครูทั้งหมดในระบบ</div>
                <div class="text-h5 font-weight-bold text-primary mt-1">{{ stats.total_teachers }}</div>
                <div class="text-caption text-success">เปิดใช้งาน {{ stats.active_teachers }} คน</div>
              </div>
            </v-col>
            <v-col cols="6">
              <div class="pa-3 bg-slate-50 rounded-lg border text-center">
                <div class="text-caption text-medium-emphasis">กลุ่ม LINE ที่ผูกไว้</div>
                <div class="text-h5 font-weight-bold text-secondary mt-1">{{ stats.total_groups }}</div>
                <div class="text-caption text-medium-emphasis">พร้อมรับไฟล์อัตโนมัติ</div>
              </div>
            </v-col>
          </v-row>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const stats = ref({
  total_files: 0,
  files_today: 0,
  files_pending: 0,
  files_failed: 0,
  total_teachers: 0,
  active_teachers: 0,
  total_groups: 0,
  storage_bytes: 0,
})

const driveHealth = ref({})

const fetchDashboard = async () => {
  try {
    const res = await api.get('/admin/dashboard')
    stats.value = res.data.stats || {}
    driveHealth.value = res.data.drive_health || {}
  } catch (err) {
    console.error('Failed to load dashboard:', err)
  }
}

const formatFileSize = (bytes) => {
  if (!bytes) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`
}

onMounted(() => {
  fetchDashboard()
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
