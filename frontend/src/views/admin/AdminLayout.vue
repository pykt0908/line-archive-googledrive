<template>
  <v-app class="bg-slate-50">
    <!-- Admin Navigation Drawer -->
    <v-navigation-drawer v-model="drawer" class="bg-slate-900 text-white" elevation="2">
      <div class="pa-4 d-flex align-center border-b border-slate-700">
        <v-avatar color="primary" size="40" class="me-3">
          <v-icon icon="mdi-shield-crown" color="white" size="22"></v-icon>
        </v-avatar>
        <div>
          <div class="text-subtitle-2 font-weight-bold text-white leading-tight">
            Admin Console
          </div>
          <div class="text-caption text-slate-400">
            LINE File Archive
          </div>
        </div>
      </div>

      <v-list density="comfortable" nav class="pa-2">
        <v-list-item
          to="/admin"
          exact
          prepend-icon="mdi-view-dashboard-outline"
          title="ภาพรวมระบบ"
          value="dashboard"
          class="rounded-lg mb-1"
          active-color="primary"
        ></v-list-item>

        <v-list-item
          to="/admin/teachers"
          prepend-icon="mdi-account-group-outline"
          title="จัดการครู"
          value="teachers"
          class="rounded-lg mb-1"
          active-color="primary"
        ></v-list-item>

        <v-list-item
          to="/admin/groups"
          prepend-icon="mdi-chat-processing-outline"
          title="จัดการกลุ่ม LINE"
          value="groups"
          class="rounded-lg mb-1"
          active-color="primary"
        ></v-list-item>

        <v-list-item
          to="/admin/files"
          prepend-icon="mdi-file-cabinet"
          title="จัดการไฟล์ทั้งหมด"
          value="files"
          class="rounded-lg mb-1"
          active-color="primary"
        ></v-list-item>

        <v-list-item
          to="/admin/logs"
          prepend-icon="mdi-clipboard-text-clock-outline"
          title="บันทึกระบบ (Audit)"
          value="logs"
          class="rounded-lg mb-1"
          active-color="primary"
        ></v-list-item>
      </v-list>

      <template #append>
        <div class="pa-3 border-t border-slate-800">
          <v-btn
            to="/files"
            block
            variant="tonal"
            color="primary"
            prepend-icon="mdi-arrow-left"
            class="rounded-lg mb-2 text-white"
          >
            กลับสู่คลังไฟล์
          </v-btn>
          <v-btn
            block
            variant="text"
            color="error"
            prepend-icon="mdi-logout"
            class="rounded-lg"
            @click="handleLogout"
          >
            ออกจากระบบ
          </v-btn>
        </div>
      </template>
    </v-navigation-drawer>

    <!-- Top Bar for Admin -->
    <v-app-bar flat class="border-b px-4 bg-white" height="64">
      <v-app-bar-nav-icon @click="drawer = !drawer"></v-app-bar-nav-icon>
      <v-toolbar-title class="text-subtitle-1 font-weight-bold text-slate-800">
        ระบบบริหารจัดการส่วนกลาง
      </v-toolbar-title>
      <v-spacer></v-spacer>
      <v-chip color="secondary" variant="tonal" size="small" class="font-weight-medium">
        <v-icon icon="mdi-shield-check" start size="16"></v-icon>
        {{ authStore.teacherName }}
      </v-chip>
    </v-app-bar>

    <!-- Router View for Admin Pages -->
    <v-main>
      <v-container class="py-6 max-w-7xl">
        <router-view></router-view>
      </v-container>
    </v-main>
  </v-app>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()
const drawer = ref(true)

const handleLogout = async () => {
  await authStore.logout()
  router.push('/login')
}
</script>

<style scoped>
.bg-slate-900 {
  background-color: #0f172a !important;
}
.border-slate-700 {
  border-color: #334155 !important;
}
.border-slate-800 {
  border-color: #1e293b !important;
}
.text-slate-400 {
  color: #94a3b8;
}
</style>
