<template>
  <div>
    <!-- Page Header -->
    <div class="mb-6">
      <h1 class="text-h5 font-weight-bold text-slate-800 mb-1">
        จัดการกลุ่ม LINE (Groups)
      </h1>
      <p class="text-body-2 text-medium-emphasis">
        กำหนดชื่อกลุ่ม และระบุโฟลเดอร์ Google Drive สำหรับจัดเก็บไฟล์แยกตามกลุ่ม
      </p>
    </div>

    <!-- Groups Table -->
    <v-card class="rounded-lg border elevation-1 overflow-hidden bg-white">
      <v-table hover>
        <thead>
          <tr class="bg-slate-50">
            <th class="text-left font-weight-bold">ชื่อกลุ่ม LINE</th>
            <th class="text-left font-weight-bold">LINE Group ID</th>
            <th class="text-left font-weight-bold">Google Drive Folder ID</th>
            <th class="text-center font-weight-bold">จำนวนไฟล์</th>
            <th class="text-center font-weight-bold">สถานะจัดเก็บ</th>
            <th class="text-center font-weight-bold">ตั้งค่า</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="group in groups" :key="group.id">
            <td class="font-weight-bold text-slate-800">
              <div class="d-flex align-center">
                <v-avatar color="green-lighten-5" size="32" class="me-2">
                  <v-icon icon="mdi-chat-processing" color="primary" size="18"></v-icon>
                </v-avatar>
                {{ group.group_name }}
              </div>
            </td>
            <td><code class="text-caption bg-slate-100 pa-1 rounded">{{ group.line_group_id }}</code></td>
            <td class="text-medium-emphasis text-caption">
              {{ group.google_drive_folder_id || '(ใช้โครงสร้างอัตโนมัติ)' }}
            </td>
            <td class="text-center font-weight-medium text-primary">
              {{ group.files_count }} ไฟล์
            </td>
            <td class="text-center">
              <v-chip size="small" :color="group.is_active ? 'success' : 'grey'" variant="tonal">
                {{ group.is_active ? 'เปิดรับไฟล์' : 'ปิดชั่วคราว' }}
              </v-chip>
            </td>
            <td class="text-center">
              <v-btn
                icon="mdi-cog-outline"
                variant="text"
                size="small"
                color="primary"
                title="ตั้งค่ากลุ่ม"
                @click="openEditDialog(group)"
              ></v-btn>
            </td>
          </tr>
        </tbody>
      </v-table>
    </v-card>

    <!-- Dialog Edit Group -->
    <v-dialog v-model="dialog" max-width="500">
      <v-card class="rounded-lg">
        <v-card-title class="pa-4 bg-slate-50 border-b font-weight-bold text-subtitle-1">
          ตั้งค่ากลุ่ม LINE
        </v-card-title>
        <v-card-text class="pa-4 pa-sm-6">
          <v-form @submit.prevent="saveGroup">
            <v-text-field
              v-model="form.group_name"
              label="ชื่อกลุ่ม *"
              variant="outlined"
              density="comfortable"
              color="primary"
              class="mb-3"
              required
            ></v-text-field>

            <v-text-field
              v-model="form.google_drive_folder_id"
              label="Google Drive Folder ID (กำหนดเฉพาะกลุ่ม)"
              placeholder="เว้นว่างไว้หากต้องการใช้ Root Folder"
              variant="outlined"
              density="comfortable"
              color="primary"
              class="mb-3"
            ></v-text-field>

            <v-checkbox
              v-model="form.is_active"
              label="เปิดรับไฟล์อัตโนมัติจากกลุ่มนี้"
              density="compact"
              color="success"
              hide-details
            ></v-checkbox>
          </v-form>
        </v-card-text>
        <v-divider></v-divider>
        <v-card-actions class="pa-4 bg-slate-50 d-flex justify-end">
          <v-btn variant="text" color="slate-600" @click="dialog = false">ยกเลิก</v-btn>
          <v-btn color="primary" class="text-white px-4 font-weight-bold" :loading="saving" @click="saveGroup">
            บันทึก
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import api from '@/services/api'

const groups = ref([])
const dialog = ref(false)
const saving = ref(false)
let currentId = null

const form = reactive({
  group_name: '',
  google_drive_folder_id: '',
  is_active: true,
})

const fetchGroups = async () => {
  try {
    const res = await api.get('/admin/groups')
    groups.value = res.data || []
  } catch (err) {
    console.error('Failed to load groups:', err)
  }
}

const openEditDialog = (group) => {
  currentId = group.id
  form.group_name = group.group_name
  form.google_drive_folder_id = group.google_drive_folder_id || ''
  form.is_active = Boolean(group.is_active)
  dialog.value = true
}

const saveGroup = async () => {
  saving.value = true
  try {
    await api.put(`/admin/groups/${currentId}`, form)
    dialog.value = false
    fetchGroups()
  } catch (err) {
    alert('เกิดข้อผิดพลาดในการบันทึกข้อมูล')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  fetchGroups()
})
</script>

<style scoped>
.bg-slate-50 {
  background-color: #f8fafc;
}
.bg-slate-100 {
  background-color: #f1f5f9;
}
.text-slate-800 {
  color: #1e293b;
}
</style>
