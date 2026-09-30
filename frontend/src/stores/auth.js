import { defineStore } from 'pinia'
import api from '@/services/api'

function getStoredTeacher() {
  try {
    const raw = localStorage.getItem('teacher')
    if (!raw || raw === 'undefined' || raw === 'null') return null
    return JSON.parse(raw)
  } catch {
    localStorage.removeItem('teacher')
    return null
  }
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem('token') || null,
    teacher: getStoredTeacher(),
    loading: false,
    error: null,
  }),

  getters: {
    isAuthenticated: (state) => !!state.token && !!state.teacher,
    isAdmin: (state) => !!state.teacher?.is_admin,
    teacherName: (state) => state.teacher?.name || 'คุณครู',
    teacherCode: (state) => state.teacher?.teacher_code || '',
  },

  actions: {
    async login(teacherCode) {
      this.loading = true
      this.error = null
      try {
        const response = await api.post('/auth/login', {
          teacher_code: teacherCode,
        })
        const { token, teacher } = response.data || {}
        if (!token || !teacher) {
          this.error = 'ข้อมูลตอบกลับจากเซิร์ฟเวอร์ไม่ถูกต้อง'
          return { success: false, message: this.error }
        }
        this.token = token
        this.teacher = teacher
        localStorage.setItem('token', token)
        localStorage.setItem('teacher', JSON.stringify(teacher))
        return { success: true }
      } catch (err) {
        this.error = err.response?.data?.message || 'เกิดข้อผิดพลาดในการเข้าสู่ระบบ'
        return { success: false, message: this.error }
      } finally {
        this.loading = false
      }
    },

    async logout() {
      try {
        if (this.token) {
          await api.post('/auth/logout').catch(() => {})
        }
      } finally {
        this.token = null
        this.teacher = null
        localStorage.removeItem('token')
        localStorage.removeItem('teacher')
      }
    },

    async fetchMe() {
      if (!this.token) return
      try {
        const response = await api.get('/auth/me')
        this.teacher = response.data.teacher
        localStorage.setItem('teacher', JSON.stringify(this.teacher))
      } catch (err) {
        this.logout()
      }
    },
  },
})
