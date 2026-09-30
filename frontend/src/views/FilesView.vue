<template>
  <v-app class="bg-slate-50">
    <!-- Top Navigation Bar -->
    <v-app-bar flat class="border-b px-2 px-sm-4 bg-white" height="64">
      <v-avatar color="primary" size="38" class="me-3 rounded-md">
        <v-icon icon="mdi-archive-arrow-down-outline" color="white" size="22"></v-icon>
      </v-avatar>
      <div>
        <div class="text-subtitle-1 font-weight-bold text-slate-800 leading-tight">
          LINE File Archive
        </div>
        <div class="text-caption text-medium-emphasis">
          คลังไฟล์เอกสารจาก LINE Group & Google Drive
        </div>
      </div>

      <v-spacer></v-spacer>

      <!-- Admin Portal Link (if admin) -->
      <v-btn
        v-if="authStore.isAdmin"
        to="/admin"
        variant="tonal"
        color="secondary"
        size="small"
        prepend-icon="mdi-shield-crown-outline"
        class="me-2 rounded-md font-weight-medium d-none d-sm-flex"
      >
        แผงควบคุม Admin
      </v-btn>

      <!-- Teacher Profile Chip -->
      <v-chip color="primary" variant="tonal" class="me-2 font-weight-medium rounded-md">
        <v-avatar start color="primary" size="22">
          <v-icon icon="mdi-account" color="white" size="13"></v-icon>
        </v-avatar>
        <span class="d-none d-sm-inline">{{ authStore.teacherName }}</span>
        <span class="text-caption ms-1 text-primary-darken">({{ authStore.teacherCode }})</span>
      </v-chip>

      <!-- Logout Button -->
      <v-btn
        icon="mdi-logout"
        variant="text"
        color="slate-600"
        title="ออกจากระบบ"
        @click="handleLogout"
      ></v-btn>
    </v-app-bar>

    <!-- Main Content Area -->
    <v-main>
      <v-container class="py-5 max-w-7xl">
        <!-- Page Header & Action Bar -->
        <div class="d-flex flex-wrap justify-space-between align-center mb-4 gap-2">
          <div>
            <h2 class="text-h6 font-weight-bold text-slate-800">
              คลังเอกสาร & สื่อการเรียนรู้
            </h2>
            <div class="text-caption text-medium-emphasis">
              จัดการ เพิ่ม ลบ แก้ไข และดูตัวอย่างไฟล์ (รูปภาพ, PDF, Office)
            </div>
          </div>

          <!-- Add / Upload File Button (Admin only) -->
          <v-btn
            v-if="authStore.isAdmin"
            color="primary"
            prepend-icon="mdi-cloud-upload-outline"
            class="elevation-1 font-weight-bold rounded-md"
            @click="openUploadDialog"
          >
            เพิ่ม / อัปโหลดไฟล์
          </v-btn>
        </div>

        <!-- Search and Filter Bar -->
        <v-card class="elevation-1 rounded-lg mb-5 pa-3 pa-sm-4 bg-white border">
          <v-row density="compact" align="center">
            <!-- Search Keyword -->
            <v-col cols="12" md="4">
              <v-text-field
                v-model="filters.search"
                placeholder="ค้นหาชื่อไฟล์, ผู้ส่ง, หรือกลุ่ม..."
                prepend-inner-icon="mdi-magnify"
                variant="outlined"
                density="compact"
                hide-details
                clearable
                color="primary"
                @update:model-value="debouncedFetch"
              ></v-text-field>
            </v-col>

            <!-- Filter Group -->
            <v-col cols="12" sm="6" md="3">
              <v-select
                v-model="filters.groupId"
                :items="groupOptions"
                item-title="name"
                item-value="id"
                label="กลุ่ม LINE"
                variant="outlined"
                density="compact"
                hide-details
                color="primary"
                @update:model-value="fetchFiles"
              ></v-select>
            </v-col>

            <!-- Filter File Type -->
            <v-col cols="12" sm="6" md="3">
              <v-select
                v-model="filters.type"
                :items="typeOptions"
                item-title="title"
                item-value="value"
                label="ประเภทไฟล์"
                variant="outlined"
                density="compact"
                hide-details
                color="primary"
                @update:model-value="fetchFiles"
              ></v-select>
            </v-col>

            <!-- View Switch & Refresh -->
            <v-col cols="12" md="2" class="d-flex justify-end align-center">
              <v-btn-toggle
                v-model="viewMode"
                mandatory
                density="compact"
                color="primary"
                variant="outlined"
                class="me-2 rounded-md"
              >
                <v-btn value="grid" icon="mdi-view-grid-outline" title="แสดงแบบการ์ด"></v-btn>
                <v-btn value="table" icon="mdi-table" title="แสดงแบบตาราง"></v-btn>
              </v-btn-toggle>

              <v-btn
                icon="mdi-refresh"
                variant="tonal"
                size="small"
                color="primary"
                :loading="loading"
                title="รีเฟรชข้อมูล"
                class="rounded-md"
                @click="fetchFiles"
              ></v-btn>
            </v-col>
          </v-row>
        </v-card>

        <!-- Loading Skeleton -->
        <v-row v-if="loading && files.length === 0">
          <v-col v-for="n in 8" :key="n" cols="6" sm="4" md="3" lg="3">
            <v-skeleton-loader type="image, article" class="rounded-lg border"></v-skeleton-loader>
          </v-col>
        </v-row>

        <!-- Empty State -->
        <v-card
          v-else-if="files.length === 0"
          class="text-center py-12 rounded-lg border elevation-0 bg-white"
        >
          <v-avatar color="green-lighten-5" size="72" class="mb-3 rounded-lg">
            <v-icon icon="mdi-file-search-outline" color="primary" size="36"></v-icon>
          </v-avatar>
          <h3 class="text-h6 font-weight-bold text-slate-800 mb-1">
            ไม่พบไฟล์เอกสาร
          </h3>
          <p class="text-body-2 text-medium-emphasis mb-4">
            ยังไม่มีไฟล์ในเงื่อนไขที่เลือก หรือยังไม่มีการส่งไฟล์ในกลุ่ม LINE
          </p>
          <div class="d-flex justify-center gap-2">
            <v-btn
              color="primary"
              variant="tonal"
              prepend-icon="mdi-refresh"
              class="rounded-md me-2"
              @click="resetFilters"
            >
              ล้างตัวกรอง
            </v-btn>
            <v-btn
              v-if="authStore.isAdmin"
              color="primary"
              variant="flat"
              prepend-icon="mdi-plus"
              class="rounded-md"
              @click="openUploadDialog"
            >
              เพิ่มไฟล์ใหม่
            </v-btn>
          </div>
        </v-card>

        <!-- Grid Cards View (with Rich Image Thumbnails & Previews) -->
        <div v-else-if="viewMode === 'grid'">
          <v-row density="compact">
            <v-col
              v-for="file in displayFiles"
              :key="file.id"
              cols="6"
              sm="4"
              md="3"
              lg="3"
            >
              <v-card
                class="rounded-lg elevation-1 file-card h-100 d-flex flex-column bg-white border overflow-hidden position-relative"
                hover
                @click="openFileDetail(file)"
              >
                <!-- Thumbnail Preview Container -->
                <div class="file-thumbnail-container position-relative overflow-hidden d-flex align-center justify-center bg-slate-100">
                  <!-- 1. Real Image Preview -->
                  <template v-if="isImage(file)">
                    <img
                      :src="getFilePreviewUrl(file)"
                      :alt="file.original_filename"
                      class="file-thumb-img"
                      loading="lazy"
                      @error="handleImageError(file)"
                    />
                  </template>

                  <!-- 2. Video Preview -->
                  <template v-else-if="isVideo(file)">
                    <div class="doc-mockup elevation-1 pa-2 d-flex flex-column align-center justify-center bg-red-lighten-5 w-100 h-100">
                      <v-avatar color="#EF4444" size="38" class="mb-1">
                        <v-icon icon="mdi-play" color="white" size="22"></v-icon>
                      </v-avatar>
                      <span class="text-caption font-weight-bold text-red-darken-2" style="font-size: 9px;">VIDEO</span>
                    </div>
                  </template>

                  <!-- 3. PDF Document Mockup -->
                  <template v-else-if="isPdf(file)">
                    <div class="doc-mockup pdf-mockup elevation-1 pa-2 w-100 h-100 d-flex flex-column">
                      <div class="mockup-header d-flex align-center mb-1">
                        <v-icon icon="mdi-file-pdf-box" color="#EF4444" size="18" class="me-1"></v-icon>
                        <span class="mockup-title text-truncate">{{ file.original_filename }}</span>
                      </div>
                      <div class="mockup-line w-100 mb-1"></div>
                      <div class="mockup-line w-75 mb-1"></div>
                      <div class="mockup-line w-85 mb-1"></div>
                      <div class="bg-red-lighten-5 pa-1 rounded text-center mt-auto">
                        <span class="text-caption font-weight-bold text-red-darken-1" style="font-size: 8px;">PDF DOCUMENT</span>
                      </div>
                    </div>
                  </template>

                  <!-- 4. Spreadsheet Mockup -->
                  <template v-else-if="isSpreadsheet(file)">
                    <div class="doc-mockup sheet-mockup elevation-1 pa-2 w-100 h-100 d-flex flex-column">
                      <div class="mockup-header d-flex align-center mb-1">
                        <v-icon icon="mdi-file-excel-box" color="#10B981" size="18" class="me-1"></v-icon>
                        <span class="mockup-title text-truncate">{{ file.original_filename }}</span>
                      </div>
                      <div class="sheet-grid">
                        <div class="sheet-row header-row">
                          <span class="sheet-cell">A</span><span class="sheet-cell">B</span><span class="sheet-cell">C</span>
                        </div>
                        <div class="sheet-row">
                          <span class="sheet-cell"></span><span class="sheet-cell"></span><span class="sheet-cell"></span>
                        </div>
                        <div class="sheet-row">
                          <span class="sheet-cell"></span><span class="sheet-cell"></span><span class="sheet-cell"></span>
                        </div>
                      </div>
                    </div>
                  </template>

                  <!-- 5. Word Document Mockup -->
                  <template v-else-if="isWord(file)">
                    <div class="doc-mockup word-mockup elevation-1 pa-2 w-100 h-100 d-flex flex-column">
                      <div class="mockup-header d-flex align-center mb-1">
                        <v-icon icon="mdi-file-word-box" color="#3B82F6" size="18" class="me-1"></v-icon>
                        <span class="mockup-title text-truncate">{{ file.original_filename }}</span>
                      </div>
                      <div class="mockup-line w-100 mb-1"></div>
                      <div class="mockup-line w-90 mb-1"></div>
                      <div class="mockup-line w-80 mb-1"></div>
                      <div class="bg-blue-lighten-5 pa-1 rounded text-center mt-auto">
                        <span class="text-caption font-weight-bold text-blue-darken-1" style="font-size: 8px;">DOCX DOCUMENT</span>
                      </div>
                    </div>
                  </template>

                  <!-- 6. Presentation Mockup -->
                  <template v-else-if="isPresentation(file)">
                    <div class="doc-mockup ppt-mockup elevation-1 pa-2 w-100 h-100 d-flex flex-column justify-center align-center">
                      <v-icon icon="mdi-file-powerpoint-box" color="#F97316" size="32" class="mb-1"></v-icon>
                      <span class="text-caption font-weight-bold text-orange-darken-2" style="font-size: 9px;">POWERPOINT</span>
                    </div>
                  </template>

                  <!-- 7. Other File Type Mockup -->
                  <template v-else>
                    <div class="text-center pa-2">
                      <v-avatar :color="getFileTypeColor(file.mime_type, file.original_filename)" rounded="md" size="42" class="mb-1">
                        <v-icon :icon="getFileIcon(file.mime_type, file.original_filename)" color="white" size="22"></v-icon>
                      </v-avatar>
                      <div class="text-caption font-weight-bold text-slate-600 text-uppercase" style="font-size: 9px;">
                        {{ file.original_filename.split('.').pop() || 'FILE' }}
                      </div>
                    </div>
                  </template>

                  <!-- Hover Action Overlay -->
                  <div class="thumb-hover-overlay d-flex align-center justify-center gap-1">
                    <v-btn
                      size="x-small"
                      color="white"
                      variant="flat"
                      icon="mdi-eye-outline"
                      title="ดูตัวอย่าง"
                      class="text-slate-800 elevation-2 me-1"
                      @click.stop="previewFile(file)"
                    ></v-btn>
                    <v-btn
                      size="x-small"
                      color="white"
                      variant="flat"
                      icon="mdi-download"
                      title="ดาวน์โหลด"
                      class="text-slate-800 elevation-2 me-1"
                      @click.stop="downloadFile(file)"
                    ></v-btn>
                    <v-btn
                      v-if="authStore.isAdmin"
                      size="x-small"
                      color="white"
                      variant="flat"
                      icon="mdi-pencil-outline"
                      title="แก้ไขข้อมูล (Admin)"
                      class="text-slate-800 elevation-2 me-1"
                      @click.stop="openEditDialog(file)"
                    ></v-btn>
                    <v-btn
                      v-if="authStore.isAdmin"
                      size="x-small"
                      color="red-lighten-4"
                      variant="flat"
                      icon="mdi-delete-outline"
                      title="ลบไฟล์ (Admin)"
                      class="text-red-darken-3 elevation-2"
                      @click.stop="confirmDeleteFile(file)"
                    ></v-btn>
                  </div>
                </div>

                <!-- Card Details Body -->
                <div class="pa-2 pa-sm-3 flex-grow-1 d-flex flex-column justify-space-between bg-white">
                  <div>
                    <div class="d-flex align-start mb-1">
                      <v-icon
                        :icon="getFileIcon(file.mime_type, file.original_filename)"
                        :color="getFileTypeColor(file.mime_type, file.original_filename)"
                        size="17"
                        class="me-1 mt-0.5 flex-shrink-0"
                      ></v-icon>
                      <div class="text-caption text-sm-subtitle-2 font-weight-bold text-truncate text-slate-800" :title="file.original_filename">
                        {{ file.original_filename }}
                      </div>
                    </div>

                    <div class="text-caption text-medium-emphasis d-flex align-center mb-1">
                      <v-icon icon="mdi-account-circle-outline" size="12" class="me-1"></v-icon>
                      <span class="text-truncate">{{ file.sender_name || 'ผู้ส่งใน LINE' }}</span>
                    </div>
                  </div>

                  <div class="d-flex justify-space-between align-center text-caption text-medium-emphasis pt-1 border-t mt-1">
                    <span class="text-truncate me-1 text-caption" style="font-size: 11px;">
                      {{ file.group?.group_name || 'ทั่วไป' }}
                    </span>
                    <span class="flex-shrink-0 font-weight-medium text-caption" style="font-size: 11px;">
                      {{ formatFileSize(file.file_size) }}
                    </span>
                  </div>
                </div>

                <!-- Actions Footer -->
                <div class="px-2 px-sm-3 py-1 bg-slate-50 border-t d-flex justify-space-between align-center" @click.stop>
                  <span class="text-caption text-slate-400" style="font-size: 10px;">
                    {{ formatDateShort(file.created_at) }}
                  </span>

                  <div>
                    <v-btn
                      icon="mdi-eye-outline"
                      variant="text"
                      size="x-small"
                      color="primary"
                      title="ดูตัวอย่าง"
                      @click="previewFile(file)"
                    ></v-btn>
                    <v-btn
                      icon="mdi-download"
                      variant="text"
                      size="x-small"
                      color="primary"
                      title="ดาวน์โหลด"
                      @click="downloadFile(file)"
                    ></v-btn>

                    <!-- More Actions Menu -->
                    <v-menu location="bottom end">
                      <template #activator="{ props }">
                        <v-btn
                          icon="mdi-dots-vertical"
                          variant="text"
                          size="x-small"
                          color="slate-600"
                          v-bind="props"
                        ></v-btn>
                      </template>
                      <v-list density="compact" class="rounded-lg border elevation-2 py-1" min-width="160">
                        <v-list-item
                          v-if="file.google_drive_url"
                          prepend-icon="mdi-google-drive"
                          title="เปิดใน Drive"
                          :href="file.google_drive_url"
                          target="_blank"
                        ></v-list-item>
                        <template v-if="authStore.isAdmin">
                          <v-divider class="my-1"></v-divider>
                          <v-list-item prepend-icon="mdi-pencil-outline" title="แก้ไขข้อมูล" @click="openEditDialog(file)"></v-list-item>
                          <v-list-item
                            prepend-icon="mdi-delete-outline"
                            title="ลบไฟล์"
                            class="text-red-darken-1"
                            @click="confirmDeleteFile(file)"
                          ></v-list-item>
                        </template>
                      </v-list>
                    </v-menu>
                  </div>
                </div>
              </v-card>
            </v-col>
          </v-row>

          <!-- Grid Pagination Bar -->
          <div v-if="totalPages > 1" class="pa-3 bg-white rounded-lg border elevation-1 mt-4 d-flex flex-wrap justify-space-between align-center gap-2">
            <div class="text-caption text-medium-emphasis">
              แสดง {{ files.length }} รายการ (หน้า {{ page }} จาก {{ totalPages }})
            </div>
            <v-pagination
              v-model="page"
              :length="totalPages"
              density="compact"
              total-visible="5"
              rounded="md"
              color="primary"
              @update:model-value="fetchFiles"
            ></v-pagination>
          </div>
        </div>

        <!-- Table View Mode -->
        <div v-else class="drive-list-container bg-white rounded-lg elevation-1 overflow-hidden border">
          <div class="px-4 py-3 bg-slate-50 border-b d-flex justify-space-between align-center">
            <div class="d-flex align-center cursor-pointer text-subtitle-2 font-weight-bold text-slate-700" @click="toggleSort">
              <span>ชื่อเอกสาร</span>
              <v-icon :icon="sortAsc ? 'mdi-arrow-up' : 'mdi-arrow-down'" size="16" class="ms-1 text-primary"></v-icon>
            </div>
            <div class="text-caption font-weight-medium text-slate-500">
              รวม {{ files.length }} รายการ
            </div>
          </div>

          <div
            v-for="file in displayFiles"
            :key="file.id"
            class="drive-list-item px-4 py-3 d-flex align-center justify-space-between border-b"
            @click="openFileDetail(file)"
          >
            <div class="d-flex align-center overflow-hidden flex-grow-1 me-2">
              <v-avatar size="36" class="me-3 flex-shrink-0" rounded="md" :color="getFileTypeColor(file.mime_type, file.original_filename)">
                <img
                  v-if="isImage(file)"
                  :src="getFilePreviewUrl(file)"
                  class="w-100 h-100 object-cover"
                  @error="handleImageError(file)"
                />
                <v-icon v-else :icon="getFileIcon(file.mime_type, file.original_filename)" color="white" size="20"></v-icon>
              </v-avatar>

              <div class="overflow-hidden flex-grow-1">
                <div class="text-body-2 font-weight-medium text-slate-900 text-truncate" :title="file.original_filename">
                  {{ file.original_filename }}
                </div>
                <div class="text-caption text-medium-emphasis d-flex align-center mt-0.5">
                  <span class="text-truncate">{{ file.sender_name || 'ผู้ส่งใน LINE' }}</span>
                  <span class="mx-1">•</span>
                  <span class="text-truncate">{{ file.group?.group_name || 'ทั่วไป' }}</span>
                  <span class="mx-1">•</span>
                  <span class="flex-shrink-0">{{ formatDateShort(file.created_at) }}</span>
                  <span class="mx-1 d-none d-sm-inline">•</span>
                  <span class="d-none d-sm-inline text-slate-400">{{ formatFileSize(file.file_size) }}</span>
                </div>
              </div>
            </div>

            <!-- Trailing Menu -->
            <div class="d-flex align-center" @click.stop>
              <v-btn icon="mdi-eye-outline" variant="text" size="small" color="primary" @click="previewFile(file)"></v-btn>
              <v-btn icon="mdi-download" variant="text" size="small" color="primary" @click="downloadFile(file)"></v-btn>
              <v-menu location="bottom end">
                <template #activator="{ props }">
                  <v-btn icon="mdi-dots-vertical" variant="text" size="small" color="slate-600" v-bind="props"></v-btn>
                </template>
                <v-list density="compact" class="rounded-lg border elevation-2 py-1" min-width="160">
                  <v-list-item
                    v-if="file.google_drive_url"
                    prepend-icon="mdi-google-drive"
                    title="เปิดใน Google Drive"
                    :href="file.google_drive_url"
                    target="_blank"
                  ></v-list-item>
                  <template v-if="authStore.isAdmin">
                    <v-divider class="my-1"></v-divider>
                    <v-list-item prepend-icon="mdi-pencil-outline" title="แก้ไขข้อมูล" @click="openEditDialog(file)"></v-list-item>
                    <v-list-item prepend-icon="mdi-delete-outline" title="ลบไฟล์" class="text-red-darken-1" @click="confirmDeleteFile(file)"></v-list-item>
                  </template>
                </v-list>
              </v-menu>
            </div>
          </div>
        </div>
      </v-container>
    </v-main>

    <!-- Detail & Rich Preview Dialog -->
    <v-dialog v-model="detailDialog" max-width="900" scrollable>
      <v-card v-if="activeFile" class="rounded-lg">
        <v-card-title class="pa-4 bg-slate-50 d-flex justify-space-between align-center border-b">
          <div class="d-flex align-center overflow-hidden">
            <v-avatar :color="getFileTypeColor(activeFile.mime_type, activeFile.original_filename)" rounded="md" size="34" class="me-3">
              <v-icon :icon="getFileIcon(activeFile.mime_type, activeFile.original_filename)" color="white" size="18"></v-icon>
            </v-avatar>
            <div class="text-truncate text-h6 font-weight-bold">
              {{ activeFile.original_filename }}
            </div>
          </div>
          <v-btn icon="mdi-close" variant="text" size="small" @click="detailDialog = false"></v-btn>
        </v-card-title>

        <v-card-text class="pa-4 pa-sm-5">
          <!-- Rich Preview Section -->
          <div class="mb-4">
            <!-- 1. Image Preview -->
            <div v-if="isImage(activeFile)" class="text-center bg-slate-900 rounded-lg pa-3 border overflow-hidden">
              <img
                :src="getFilePreviewUrl(activeFile)"
                alt="image-preview"
                class="rounded mx-auto"
                style="max-height: 480px; max-width: 100%; object-fit: contain;"
              />
            </div>

            <!-- 2. Google Drive Preview Player (PDF, Docs, Sheets, Slides - Never downloads) -->
            <div v-else-if="activeFile.google_drive_file_id && !activeFile.google_drive_file_id.startsWith('sim_')" class="rounded-lg border overflow-hidden bg-slate-100" style="height: 520px;">
              <iframe
                :src="`https://drive.google.com/file/d/${activeFile.google_drive_file_id}/preview`"
                width="100%"
                height="100%"
                frameborder="0"
                allow="autoplay"
                class="w-100 h-100"
              ></iframe>
            </div>

            <!-- 3. Local PDF Preview (Simulation) -->
            <div v-else-if="isPdf(activeFile)" class="rounded-lg border overflow-hidden bg-slate-100" style="height: 520px;">
              <iframe
                :src="getFilePreviewUrl(activeFile)"
                width="100%"
                height="100%"
                frameborder="0"
                class="w-100 h-100"
              ></iframe>
            </div>

            <!-- 4. Video Preview -->
            <div v-else-if="isVideo(activeFile)" class="rounded-lg bg-black pa-2 text-center">
              <video
                :src="getFilePreviewUrl(activeFile)"
                controls
                autoplay
                class="rounded"
                style="max-height: 420px; max-width: 100%;"
              ></video>
            </div>

            <!-- 5. Default Fallback -->
            <div v-else class="text-center pa-6 bg-slate-50 border rounded-lg">
              <v-icon :icon="getFileIcon(activeFile.mime_type, activeFile.original_filename)" size="64" :color="getFileTypeColor(activeFile.mime_type, activeFile.original_filename)" class="mb-2"></v-icon>
              <div class="text-subtitle-1 font-weight-bold text-slate-800 mb-1">
                {{ activeFile.original_filename }}
              </div>
              <p class="text-caption text-medium-emphasis mb-3">
                ไฟล์ประเภทนี้สามารถเปิดดูหรือดาวน์โหลดได้โดยตรงผ่าน Google Drive
              </p>
              <v-btn
                v-if="activeFile.google_drive_url"
                color="secondary"
                variant="outlined"
                prepend-icon="mdi-google-drive"
                :href="activeFile.google_drive_url"
                target="_blank"
                class="rounded-md me-2"
              >
                เปิดใน Google Drive
              </v-btn>
              <v-btn
                color="primary"
                variant="flat"
                prepend-icon="mdi-download"
                class="rounded-md"
                @click="downloadFile(activeFile)"
              >
                ดาวน์โหลดไฟล์
              </v-btn>
            </div>
          </div>

          <!-- Metadata List -->
          <v-list density="compact" class="bg-slate-50 rounded-md pa-2 border">
            <v-list-item>
              <template #prepend><v-icon icon="mdi-file-document-outline" size="18" class="me-2 text-medium-emphasis"></v-icon></template>
              <v-list-item-title class="font-weight-medium">ชื่อไฟล์</v-list-item-title>
              <template #append><span class="text-body-2 font-weight-medium">{{ activeFile.original_filename }}</span></template>
            </v-list-item>
            <v-divider></v-divider>
            <v-list-item>
              <template #prepend><v-icon icon="mdi-account-group-outline" size="18" class="me-2 text-medium-emphasis"></v-icon></template>
              <v-list-item-title class="font-weight-medium">กลุ่ม LINE / โฟลเดอร์</v-list-item-title>
              <template #append><span class="text-body-2">{{ activeFile.group?.group_name || 'ทั่วไป' }}</span></template>
            </v-list-item>
            <v-divider></v-divider>
            <v-list-item>
              <template #prepend><v-icon icon="mdi-account-outline" size="18" class="me-2 text-medium-emphasis"></v-icon></template>
              <v-list-item-title class="font-weight-medium">ผู้ส่งไฟล์</v-list-item-title>
              <template #append><span class="text-body-2">{{ activeFile.sender_name }}</span></template>
            </v-list-item>
            <v-divider></v-divider>
            <v-list-item>
              <template #prepend><v-icon icon="mdi-harddisk" size="18" class="me-2 text-medium-emphasis"></v-icon></template>
              <v-list-item-title class="font-weight-medium">ขนาดไฟล์</v-list-item-title>
              <template #append><span class="text-body-2">{{ formatFileSize(activeFile.file_size) }}</span></template>
            </v-list-item>
            <v-divider></v-divider>
            <v-list-item>
              <template #prepend><v-icon icon="mdi-calendar-clock" size="18" class="me-2 text-medium-emphasis"></v-icon></template>
              <v-list-item-title class="font-weight-medium">วันที่ได้รับ</v-list-item-title>
              <template #append><span class="text-body-2">{{ formatDate(activeFile.created_at) }}</span></template>
            </v-list-item>
          </v-list>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-3 px-4 bg-slate-50 d-flex flex-wrap justify-space-between gap-2">
          <div v-if="authStore.isAdmin">
            <v-btn
              color="error"
              variant="text"
              prepend-icon="mdi-delete-outline"
              class="rounded-md me-1"
              @click="confirmDeleteFile(activeFile)"
            >
              ลบไฟล์
            </v-btn>
            <v-btn
              color="primary"
              variant="text"
              prepend-icon="mdi-pencil-outline"
              class="rounded-md"
              @click="openEditDialog(activeFile)"
            >
              แก้ไขชื่อ / กลุ่ม
            </v-btn>
          </div>

          <div class="d-flex gap-2">
            <v-btn
              v-if="activeFile.google_drive_url"
              variant="outlined"
              color="secondary"
              prepend-icon="mdi-google-drive"
              class="rounded-md"
              :href="activeFile.google_drive_url"
              target="_blank"
            >
              เปิดใน Drive
            </v-btn>
            <v-btn
              color="primary"
              prepend-icon="mdi-download"
              class="text-white elevation-1 font-weight-bold rounded-md"
              @click="downloadFile(activeFile)"
            >
              ดาวน์โหลด
            </v-btn>
          </div>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Upload File Dialog -->
    <v-dialog v-model="uploadDialog" max-width="540" persistent>
      <v-card class="rounded-lg">
        <v-card-title class="pa-4 bg-slate-50 d-flex justify-space-between align-center border-b">
          <div class="d-flex align-center font-weight-bold text-subtitle-1">
            <v-icon icon="mdi-cloud-upload-outline" color="primary" class="me-2"></v-icon>
            เพิ่ม / อัปโหลดไฟล์ใหม่
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
            :items="editableGroupOptions"
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

    <!-- Edit File Dialog -->
    <v-dialog v-model="editDialog" max-width="500">
      <v-card class="rounded-lg">
        <v-card-title class="pa-4 bg-slate-50 d-flex justify-space-between align-center border-b">
          <div class="d-flex align-center font-weight-bold text-subtitle-1">
            <v-icon icon="mdi-pencil-outline" color="primary" class="me-2"></v-icon>
            แก้ไขข้อมูลไฟล์
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
            :items="editableGroupOptions"
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
          ยืนยันการลบไฟล์
        </v-card-title>

        <v-card-text class="pa-4">
          <p class="text-body-1 mb-2">คุณแน่ใจหรือไม่ว่าต้องการลบไฟล์นี้?</p>
          <div class="pa-3 bg-slate-100 rounded-md font-weight-bold text-slate-800 text-truncate">
            {{ fileToDelete?.original_filename }}
          </div>
          <p class="text-caption text-medium-emphasis mt-2 mb-0">
            การดำเนินการนี้จะนำไฟล์ออกจากระบบคลังเอกสาร
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

    <!-- Global Notification Snackbar -->
    <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000" location="bottom end">
      {{ snackbar.text }}
      <template #actions>
        <v-btn variant="text" @click="snackbar.show = false">ปิด</v-btn>
      </template>
    </v-snackbar>
  </v-app>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const router = useRouter()
const authStore = useAuthStore()

const files = ref([])
const loading = ref(false)
const viewMode = ref('grid')
const page = ref(1)
const totalPages = ref(1)
const totalFilesCount = ref(0)
const sortAsc = ref(true)

const detailDialog = ref(false)
const activeFile = ref(null)

const groupOptions = ref([{ name: 'ทุกกลุ่ม LINE', id: null }])
const editableGroupOptions = ref([])

const typeOptions = [
  { title: 'ทุกประเภทไฟล์', value: null },
  { title: 'เอกสาร PDF', value: 'pdf' },
  { title: 'วิดีโอ (MP4, MOV)', value: 'video' },
  { title: 'รูปภาพ (JPG, PNG)', value: 'image' },
  { title: 'Word เอกสาร (DOC, DOCX)', value: 'document' },
  { title: 'Excel สเปรดชีต (XLS, XLSX)', value: 'spreadsheet' },
  { title: 'PowerPoint (PPT, PPTX)', value: 'presentation' },
  { title: 'ไฟล์บีบอัด (ZIP, RAR)', value: 'archive' },
]

const filters = reactive({
  search: '',
  groupId: null,
  type: null,
})

// Upload State
const uploadDialog = ref(false)
const uploadLoading = ref(false)
const uploadFileRef = ref(null)
const uploadForm = reactive({
  group_id: null,
  sender_name: '',
})

// Edit State
const editDialog = ref(false)
const editLoading = ref(false)
const fileToEdit = ref(null)
const editForm = reactive({
  original_filename: '',
  group_id: null,
  sender_name: '',
})

// Delete State
const deleteDialog = ref(false)
const deleteLoading = ref(false)
const fileToDelete = ref(null)

// Notification Snackbar
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

let searchTimeout = null
const debouncedFetch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    page.value = 1
    fetchFiles()
  }, 300)
}

const toggleSort = () => {
  sortAsc.value = !sortAsc.value
}

const displayFiles = computed(() => {
  return [...files.value].sort((a, b) => {
    const comp = a.original_filename.localeCompare(b.original_filename, 'th')
    return sortAsc.value ? comp : -comp
  })
})

const getFilePreviewUrl = (file) => {
  if (!file) return ''
  const token = authStore.token || ''
  return `/api/files/${file.id}/preview?token=${encodeURIComponent(token)}`
}

const fetchGroups = async () => {
  try {
    const res = await api.get('/files/groups')
    const list = res.data || []
    groupOptions.value = [
      { name: 'ทุกกลุ่ม LINE', id: null },
      ...list.map(g => ({ name: g.group_name, id: g.id }))
    ]
    editableGroupOptions.value = [
      { name: 'ไม่ระบุกลุ่ม (General)', id: null },
      ...list.map(g => ({ name: g.group_name, id: g.id }))
    ]
  } catch (err) {
    console.error('Failed to load groups:', err)
  }
}

const fetchFiles = async () => {
  loading.value = true
  try {
    const params = {
      page: page.value,
      per_page: viewMode.value === 'table' ? 100 : 24,
      search: filters.search || undefined,
      group_id: filters.groupId || undefined,
      type: filters.type || undefined,
    }
    const res = await api.get('/files', { params })
    files.value = res.data.data || []
    totalPages.value = res.data.last_page || 1
    totalFilesCount.value = res.data.total ?? files.value.length
  } catch (err) {
    console.error('Failed to fetch files:', err)
  } finally {
    loading.value = false
  }
}

const resetFilters = () => {
  filters.search = ''
  filters.groupId = null
  filters.type = null
  page.value = 1
  fetchFiles()
}

const openFileDetail = (file) => {
  activeFile.value = file
  detailDialog.value = true
}

const previewFile = (file) => {
  activeFile.value = file
  detailDialog.value = true
}

const downloadFile = (file) => {
  const token = authStore.token || ''
  window.open(`/api/files/${file.id}/download?token=${encodeURIComponent(token)}`, '_blank')
}

// Upload Operations
const openUploadDialog = () => {
  uploadFileRef.value = null
  uploadForm.group_id = filters.groupId || null
  uploadForm.sender_name = authStore.teacherName || ''
  uploadDialog.value = true
}

const handleUploadFile = async () => {
  if (!uploadFileRef.value) return
  uploadLoading.value = true
  try {
    const formData = new FormData()
    // Handle both single file or array from v-file-input
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
    showToast(err.response?.data?.message || 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์', 'error')
  } finally {
    uploadLoading.value = false
  }
}

// Edit Operations
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
    
    // Update local list
    const index = files.value.findIndex(f => f.id === fileToEdit.value.id)
    if (index !== -1 && res.data.file) {
      files.value[index] = res.data.file
    }
    if (activeFile.value && activeFile.value.id === fileToEdit.value.id && res.data.file) {
      activeFile.value = res.data.file
    }
  } catch (err) {
    console.error('Update failed:', err)
    showToast(err.response?.data?.message || 'แก้ไขไฟล์ไม่สำเร็จ', 'error')
  } finally {
    editLoading.value = false
  }
}

// Delete Operations
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
    
    if (detailDialog.value && activeFile.value?.id === fileToDelete.value.id) {
      detailDialog.value = false
    }
    fetchFiles()
  } catch (err) {
    console.error('Delete failed:', err)
    showToast(err.response?.data?.message || 'เกิดข้อผิดพลาดในการลบไฟล์', 'error')
  } finally {
    deleteLoading.value = false
  }
}

const handleLogout = async () => {
  await authStore.logout()
  router.push('/login')
}

const handleImageError = (file) => {
  file._imgError = true
}

// Helpers
const getFileIcon = (mime, filename = '') => {
  const ext = filename.split('.').pop()?.toLowerCase()
  if (['mov', 'mp4', 'avi', 'mkv', 'webm'].includes(ext) || mime?.includes('video')) return 'mdi-movie-open'
  if (mime?.includes('pdf') || ext === 'pdf') return 'mdi-file-pdf-box'
  if (mime?.includes('image') || ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ext)) return 'mdi-file-image'
  if (['xls', 'xlsx', 'csv'].includes(ext) || mime?.includes('sheet')) return 'mdi-file-excel-box'
  if (['doc', 'docx'].includes(ext) || mime?.includes('word')) return 'mdi-file-word-box'
  if (['ppt', 'pptx'].includes(ext) || mime?.includes('presentation')) return 'mdi-file-powerpoint-box'
  if (['zip', 'rar', '7z'].includes(ext)) return 'mdi-folder-zip-outline'
  return 'mdi-file-outline'
}

const getFileTypeColor = (mime, filename = '') => {
  const ext = filename.split('.').pop()?.toLowerCase()
  if (['mov', 'mp4', 'avi', 'mkv'].includes(ext) || mime?.includes('video')) return '#EF4444'
  if (mime?.includes('pdf') || ext === 'pdf') return '#EF4444'
  if (mime?.includes('image') || ['jpg', 'jpeg', 'png', 'webp'].includes(ext)) return '#8B5CF6'
  if (['xls', 'xlsx', 'csv'].includes(ext)) return '#10B981'
  if (['doc', 'docx'].includes(ext)) return '#3B82F6'
  if (['ppt', 'pptx'].includes(ext)) return '#F97316'
  if (['zip', 'rar'].includes(ext)) return '#EAB308'
  return '#64748B'
}

const isVideo = (file) => {
  if (!file) return false
  const ext = file.original_filename.split('.').pop()?.toLowerCase()
  return ['mov', 'mp4', 'avi', 'mkv', 'webm'].includes(ext) || file.mime_type?.includes('video')
}

const isImage = (file) => {
  if (!file || file._imgError) return false
  const ext = file.original_filename.split('.').pop()?.toLowerCase()
  return file.mime_type?.includes('image') || ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ext)
}

const isPdf = (file) => {
  if (!file) return false
  const ext = file.original_filename.split('.').pop()?.toLowerCase()
  return file.mime_type?.includes('pdf') || ext === 'pdf'
}

const isSpreadsheet = (file) => {
  if (!file) return false
  const ext = file.original_filename.split('.').pop()?.toLowerCase()
  return ['xls', 'xlsx', 'csv'].includes(ext) || file.mime_type?.includes('sheet')
}

const isWord = (file) => {
  if (!file) return false
  const ext = file.original_filename.split('.').pop()?.toLowerCase()
  return ['doc', 'docx'].includes(ext) || file.mime_type?.includes('word')
}

const isPresentation = (file) => {
  if (!file) return false
  const ext = file.original_filename.split('.').pop()?.toLowerCase()
  return ['ppt', 'pptx'].includes(ext) || file.mime_type?.includes('presentation')
}

const formatFileSize = (bytes) => {
  if (!bytes) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`
}

const formatDateShort = (dateStr) => {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return d.toLocaleDateString('th-TH', {
    day: 'numeric',
    month: 'short',
  })
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
.bg-slate-100 {
  background-color: #f1f5f9;
}
.text-slate-800 {
  color: #1e293b;
}
.file-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  cursor: pointer;
}
.file-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 16px -2px rgba(0, 0, 0, 0.08) !important;
}

.file-thumbnail-container {
  height: 130px;
  background-color: #f8fafc;
}

.file-thumb-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.thumb-hover-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(2px);
  opacity: 0;
  transition: opacity 0.2s ease;
}

.file-card:hover .thumb-hover-overlay {
  opacity: 1;
}

/* Document Mockup Styles */
.doc-mockup {
  background: white;
  border-radius: 4px;
}
.mockup-header {
  font-size: 10px;
  font-weight: bold;
}
.mockup-title {
  max-width: 110px;
  font-size: 9px;
  color: #334155;
}
.mockup-line {
  height: 4px;
  background: #e2e8f0;
  border-radius: 2px;
}

/* Sheet Grid */
.sheet-grid {
  display: flex;
  flex-direction: column;
  gap: 2px;
  margin-top: 4px;
}
.sheet-row {
  display: flex;
  gap: 2px;
}
.sheet-cell {
  flex: 1;
  height: 12px;
  background: #f1f5f9;
  border-radius: 2px;
  font-size: 8px;
  text-align: center;
  line-height: 12px;
  color: #64748b;
}
.header-row .sheet-cell {
  background: #dcfce7;
  font-weight: bold;
  color: #166534;
}

.drive-list-item {
  transition: background-color 0.15s ease;
  cursor: pointer;
}
.drive-list-item:hover {
  background-color: #f8fafc;
}
</style>
