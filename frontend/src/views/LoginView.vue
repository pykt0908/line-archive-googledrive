<template>
  <div class="login-wrapper">
    <v-container class="pa-3 pa-sm-4">
      <v-row align="center" justify="center" no-gutters>
        <v-col cols="12" sm="8" md="5" lg="4" xl="3">
          <v-card class="elevation-4 rounded-xl pa-5 pa-sm-8 login-card">
            <!-- Logo & Brand Header -->
            <div class="text-center mb-6">
              <v-avatar color="primary" size="64" class="elevation-3 mb-3 rounded-xl">
                <v-icon icon="mdi-archive-arrow-down-outline" color="white" size="36"></v-icon>
              </v-avatar>
              <h1 class="text-h5 font-weight-bold text-slate-800 mb-1">
                LINE File Archive
              </h1>
              <p class="text-caption text-medium-emphasis">
                ระบบจัดเก็บและค้นหาไฟล์จาก LINE Group แบบอัตโนมัติ
              </p>
            </div>

            <!-- Alert for Error/Rate Limiting -->
            <v-slide-y-transition>
              <v-alert
                v-if="errorMessage"
                type="error"
                variant="tonal"
                density="comfortable"
                closable
                class="mb-5 rounded-lg text-body-2"
                @click:close="errorMessage = ''"
              >
                {{ errorMessage }}
              </v-alert>
            </v-slide-y-transition>

            <!-- Login Form -->
            <v-form @submit.prevent="handleLogin">
              <div class="text-subtitle-2 font-weight-medium mb-2 text-slate-700">
                รหัสประจำตัวครู (Teacher ID)
              </div>
              <v-text-field
                v-model="teacherCode"
                placeholder="กรุณากรอกรหัสประจำตัวครู / บุคลากร"
                prepend-inner-icon="mdi-account-badge-outline"
                variant="outlined"
                color="primary"
                density="comfortable"
                class="mb-3"
                autofocus
                :disabled="loading"
                :rules="[v => !!v || 'กรุณากรอกรหัสประจำตัวครู']"
              ></v-text-field>

              <v-btn
                type="submit"
                color="primary"
                size="large"
                block
                class="rounded-lg font-weight-bold mt-2 elevation-2 text-white"
                :loading="loading"
              >
                <v-icon icon="mdi-login-variant" start></v-icon>
                เข้าสู่ระบบ
              </v-btn>
            </v-form>
          </v-card>
        </v-col>
      </v-row>
    </v-container>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const teacherCode = ref('')
const loading = ref(false)
const errorMessage = ref('')

const handleLogin = async () => {
  if (!teacherCode.value.trim()) return

  loading.value = true
  errorMessage.value = ''

  const result = await authStore.login(teacherCode.value.trim())
  loading.value = false

  if (result.success) {
    router.push('/files')
  } else {
    errorMessage.value = result.message
  }
}
</script>

<style scoped>
.login-wrapper {
  min-height: 100vh;
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #f0fdf4 0%, #e2e8f0 100%);
}

.login-card {
  border: 1px solid rgba(226, 232, 240, 0.85);
  background-color: #ffffff;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04) !important;
}

.text-slate-700 {
  color: #334155;
}
.text-slate-800 {
  color: #1e293b;
}
</style>
