<template>
  <div>
    <!-- Page Header -->
    <div class="d-flex justify-space-between align-center mb-6 flex-wrap gap-2">
      <div>
        <h1 class="text-h5 font-weight-bold text-slate-800 mb-1">
          จัดการข้อมูลครู (Teachers)
        </h1>
        <p class="text-body-2 text-medium-emphasis">
          กำหนดรหัสประจำตัวครูสำหรับใช้เข้าสู่ระบบค้นหาไฟล์เอกสาร
        </p>
      </div>
      <v-btn
        color="primary"
        prepend-icon="mdi-plus"
        class="rounded-lg text-white font-weight-bold elevation-1"
        @click="openAddDialog"
      >
        เพิ่มรหัสครูใหม่
      </v-btn>
    </div>

    <!-- Filter & Search Card -->
    <v-card class="rounded-lg border pa-4 mb-4 bg-white elevation-1">
      <v-row dense align="center">
        <v-col cols="12" sm="6">
          <v-text-field
            v-model="search"
            placeholder="ค้นหาชื่อครู, รหัสประจำตัว, หรืออีเมล..."
            prepend-inner-icon="mdi-magnify"
            variant="outlined"
            density="compact"
            hide-details
            clearable
            @update:model-value="debouncedFetch"
          ></v-text-field>
        </v-col>
      </v-row>
    </v-card>

    <!-- Teachers Table -->
    <v-card class="rounded-lg border elevation-1 overflow-hidden bg-white">
      <v-table hover>
        <thead>
          <tr class="bg-slate-50">
            <th class="text-left font-weight-bold">รหัสประจำตัว</th>
            <th class="text-left font-weight-bold">ชื่อ - นามสกุล</th>
            <th class="text-left font-weight-bold">อีเมล</th>
            <th class="text-center font-weight-bold">สิทธิ์ Admin</th>
            <th class="text-center font-weight-bold">สถานะใช้งาน</th>
            <th class="text-center font-weight-bold">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="teacher in teachers" :key="teacher.id">
            <td>
              <v-chip size="small" color="primary" variant="outlined" class="font-weight-bold">
                {{ teacher.teacher_code }}
              </v-chip>
            </td>
            <td class="font-weight-medium text-slate-800">{{ teacher.name }}</td>
            <td class="text-medium-emphasis">{{ teacher.email || '-' }}</td>
            <td class="text-center">
              <v-chip v-if="teacher.is_admin" size="x-small" color="secondary" variant="flat">
                Admin
              </v-chip>
              <span v-else class="text-caption text-medium-emphasis">ครูทั่วไป</span>
            </td>
            <td class="text-center">
              <v-switch
                :model-value="Boolean(teacher.is_active)"
                color="success"
                density="compact"
                hide-details
                inset
                class="d-inline-flex"
                @update:model-value="toggleStatus(teacher)"
              ></v-switch>
            </td>
            <td class="text-center">
              <v-btn
                icon="mdi-pencil-outline"
                variant="text"
                size="small"
                color="info"
                title="แก้ไข"
                @click="openEditDialog(teacher)"
              ></v-btn>
              <v-btn
                icon="mdi-delete-outline"
                variant="text"
                size="small"
                color="error"
                title="ลบ"
                @click="deleteTeacher(teacher)"
              ></v-btn>
            </td>
          </tr>
        </tbody>
      </v-table>
    </v-card>

    <!-- Dialog Add/Edit Teacher -->
    <v-dialog v-model="dialog" max-width="500">
      <v-card class="rounded-lg">
        <v-card-title class="pa-4 bg-slate-50 border-b font-weight-bold text-subtitle-1">
          {{ isEditing ? 'แก้ไขข้อมูลครู' : 'เพิ่มรหัสประจำตัวครูใหม่' }}
        </v-card-title>
        <v-card-text class="pa-4 pa-sm-6">
          <v-form @submit.prevent="saveTeacher">
            <v-text-field
              v-model="form.teacher_code"
              label="รหัสประจำตัวครู (Teacher ID) *"
              placeholder="เช่น T004"
              variant="outlined"
              density="comfortable"
              color="primary"
              class="mb-3"
              required
            ></v-text-field>

            <v-text-field
              v-model="form.name"
              label="ชื่อ - นามสกุล *"
              placeholder="เช่น ครูสมหมาย มั่นคง"
              variant="outlined"
              density="comfortable"
              color="primary"
              class="mb-3"
              required
            ></v-text-field>

            <v-text-field
              v-model="form.email"
              label="อีเมล (ถ้ามี)"
              placeholder="teacher@school.ac.th"
              variant="outlined"
              density="comfortable"
              color="primary"
              class="mb-3"
            ></v-text-field>

            <v-checkbox
              v-model="form.is_admin"
              label="กำหนดสิทธิ์ผู้ดูแลระบบ (Admin)"
              density="compact"
              color="primary"
              hide-details
              class="mb-2"
            ></v-checkbox>

            <v-checkbox
              v-model="form.is_active"
              label="เปิดใช้งาน (Active)"
              density="compact"
              color="success"
              hide-details
            ></v-checkbox>
          </v-form>
        </v-card-text>
        <v-divider></v-divider>
        <v-card-actions class="pa-4 bg-slate-50 d-flex justify-end">
          <v-btn variant="text" color="slate-600" @click="dialog = false">ยกเลิก</v-btn>
          <v-btn color="primary" class="text-white px-4 font-weight-bold" :loading="saving" @click="saveTeacher">
            บันทึกข้อมูล
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import api from '@/services/api'

const teachers = ref([])
const search = ref('')
const dialog = ref(false)
const isEditing = ref(false)
const saving = ref(false)
let currentId = null

const form = reactive({
  teacher_code: '',
  name: '',
  email: '',
  is_admin: false,
  is_active: true,
})

let searchTimeout = null
const debouncedFetch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    fetchTeachers()
  }, 300)
}

const fetchTeachers = async () => {
  try {
    const res = await api.get('/admin/teachers', { params: { search: search.value || undefined } })
    teachers.value = res.data.data || []
  } catch (err) {
    console.error('Failed to load teachers:', err)
  }
}

const openAddDialog = () => {
  isEditing.value = false
  currentId = null
  form.teacher_code = ''
  form.name = ''
  form.email = ''
  form.is_admin = false
  form.is_active = true
  dialog.value = true
}

const openEditDialog = (teacher) => {
  isEditing.value = true
  currentId = teacher.id
  form.teacher_code = teacher.teacher_code
  form.name = teacher.name
  form.email = teacher.email || ''
  form.is_admin = Boolean(teacher.is_admin)
  form.is_active = Boolean(teacher.is_active)
  dialog.value = true
}

const saveTeacher = async () => {
  if (!form.teacher_code || !form.name) return
  saving.value = true
  try {
    if (isEditing.value) {
      await api.put(`/admin/teachers/${currentId}`, form)
    } else {
      await api.post('/admin/teachers', form)
    }
    dialog.value = false
    fetchTeachers()
  } catch (err) {
    alert(err.response?.data?.message || 'เกิดข้อผิดพลาดในการบันทึก')
  } finally {
    saving.value = false
  }
}

const toggleStatus = async (teacher) => {
  try {
    await api.post(`/admin/teachers/${teacher.id}/toggle-status`)
    teacher.is_active = !teacher.is_active
  } catch (err) {
    console.error('Failed to toggle status:', err)
  }
}

const deleteTeacher = async (teacher) => {
  if (confirm(`คุณต้องการลบข้อมูลครู "${teacher.name}" ใช่หรือไม่?`)) {
    try {
      await api.delete(`/admin/teachers/${teacher.id}`)
      fetchTeachers()
    } catch (err) {
      alert('ไม่สามารถลบข้อมูลครูได้')
    }
  }
}

onMounted(() => {
  fetchTeachers()
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
