<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import axios from 'axios'

interface Props {
  settings: {
    hris_app_url: string
    hris_api_key: string
    hris_auto_sync: boolean
  }
  stats: {
    current_period: string
    monthly_reports: number
    avg_oee: number
    endpoint: string
  }
}

const props = defineProps<Props>()

function sanitizeUrl(raw: string): string {
  if (!raw) return ''
  let cleaned = raw.trim()
  // Tangani duplikasi paste (cth: http://backuphris.testhttp://backuphris.test)
  const dupMatch = cleaned.match(/(https?:\/\/[^\/]+?)https?:\/\//i)
  if (dupMatch) {
    cleaned = dupMatch[1]
  }
  if (!cleaned.match(/^https?:\/\//i)) {
    cleaned = 'http://' + cleaned
  }
  return cleaned.replace(/\/+$/, '')
}

const form = useForm({
  hris_app_url: sanitizeUrl(props.settings?.hris_app_url || 'http://backuphris.test'),
  hris_api_key: props.settings?.hris_api_key || 'bsc_sec_live_9f82d1c6b3e44a7b',
  hris_auto_sync: props.settings?.hris_auto_sync ?? true,
})

const showApiKey = ref(false)
const isTesting = ref(false)
const testResult = ref<{ success?: boolean; message?: string; latency_ms?: number; endpoint?: string } | null>(null)
const isSyncing = ref(false)
const copiedState = ref(false)

const computedEndpoint = computed(() => {
  const base = sanitizeUrl(form.hris_app_url) || 'http://backuphris.test'
  return `${base}/api/v1/inbound/production-metrics`
})

function onUrlBlur() {
  form.hris_app_url = sanitizeUrl(form.hris_app_url)
}

function setDefaultLocalUrl() {
  form.hris_app_url = 'http://backuphris.test'
  testResult.value = null
}

function setDefaultApiKey() {
  form.hris_api_key = 'bsc_sec_live_9f82d1c6b3e44a7b'
  testResult.value = null
}

function submit() {
  form.hris_app_url = sanitizeUrl(form.hris_app_url)
  form.post(route('admin.settings.hris.update'), {
    preserveScroll: true,
    onSuccess: () => {
      testResult.value = null
    },
  })
}

async function runTestConnection() {
  form.hris_app_url = sanitizeUrl(form.hris_app_url)
  isTesting.value = true
  testResult.value = null

  try {
    const response = await axios.post(route('admin.settings.hris.test'), {
      hris_app_url: form.hris_app_url,
      hris_api_key: form.hris_api_key,
    })
    testResult.value = response.data
  } catch (err: any) {
    testResult.value = {
      success: false,
      message: err.response?.data?.message || err.message || 'Gagal menghubungi server HRIS.',
      latency_ms: err.response?.data?.latency_ms,
    }
  } finally {
    isTesting.value = false
  }
}

function runSyncNow() {
  if (!confirm(`Kirim dan sinkronkan metrik OEE, Availability & Yield periode ${props.stats.current_period} ke HRIS sekarang?`)) {
    return
  }
  isSyncing.value = true
  router.post(route('admin.settings.hris.sync'), {
    period: props.stats.current_period,
  }, {
    preserveScroll: true,
    onFinish: () => {
      isSyncing.value = false
    }
  })
}

function copyEndpoint() {
  navigator.clipboard.writeText(computedEndpoint.value).then(() => {
    copiedState.value = true
    setTimeout(() => {
      copiedState.value = false
    }, 2500)
  })
}
</script>

<template>
  <Head title="Pengaturan Integrasi API HRIS" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-lg sm:text-xl font-bold leading-tight text-gray-800 dark:text-gray-200">
            Integrasi API HRIS (Sasaran Mutu)
          </h2>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
            Koneksi outbound untuk mengirim metrik OEE mesin, availability, dan yield pabrik ke HRIS Pusat
          </p>
        </div>
        <Link
          :href="route('admin.produk.index')"
          class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 w-fit"
        >
          ← Kembali ke Master Produk
        </Link>
      </div>
    </template>

    <div class="mx-auto max-w-7xl px-3 sm:px-6 py-2 sm:py-4">
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        
        <!-- FORM SETTINGS (2 COLS) -->
        <div class="lg:col-span-2 space-y-6">
          <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="mb-5 flex items-center justify-between border-b border-gray-100 pb-4 dark:border-gray-700">
              <div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Kredensial Host & API Key</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                  Dapatkan Secret API Key dari HRIS menu <strong>Sasaran Mutu &rarr; Hub Koneksi Multi-Sistem (Tab 5)</strong>
                </p>
              </div>
              <span class="inline-flex items-center gap-1 rounded-full bg-sky-50 px-2.5 py-1 text-xs font-medium text-sky-700 ring-1 ring-inset ring-sky-600/20 dark:bg-sky-950/50 dark:text-sky-300">
                Inbound REST API
              </span>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
              <!-- HRIS Host URL -->
              <div>
                <div class="flex items-center justify-between mb-1">
                  <InputLabel for="hris_app_url" value="Base URL Sistem HRIS *" class="text-xs font-bold uppercase tracking-wider" />
                  <button
                    type="button"
                    @click="setDefaultLocalUrl"
                    class="text-[11px] text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 font-semibold"
                  >
                    Reset ke Default Local
                  </button>
                </div>
                <div class="relative">
                  <TextInput
                    id="hris_app_url"
                    v-model="form.hris_app_url"
                    type="text"
                    @blur="onUrlBlur"
                    class="block w-full font-mono text-sm text-gray-900 dark:text-gray-100"
                    placeholder="http://backuphris.test"
                    required
                  />
                </div>
                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                  Host URL aplikasi HRIS (contoh: <code>http://backuphris.test</code> atau domain server HRIS).
                </p>
                <InputError class="mt-1" :message="form.errors.hris_app_url" />
              </div>

              <!-- Secret API Key -->
              <div>
                <div class="flex items-center justify-between mb-1">
                  <InputLabel for="hris_api_key" value="Secret API Key (X-API-KEY) *" class="text-xs font-bold uppercase tracking-wider" />
                  <button
                    type="button"
                    @click="setDefaultApiKey"
                    class="text-[11px] text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 font-semibold"
                  >
                    Gunakan Key Standar
                  </button>
                </div>
                <div class="relative">
                  <TextInput
                    id="hris_api_key"
                    v-model="form.hris_api_key"
                    :type="showApiKey ? 'text' : 'password'"
                    class="block w-full font-mono text-sm pr-20 text-gray-900 dark:text-gray-100"
                    placeholder="bsc_sec_live_9f82d1c6b3e44a7b"
                    required
                  />
                  <button
                    type="button"
                    @click="showApiKey = !showApiKey"
                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                  >
                    {{ showApiKey ? 'Sembunyikan' : 'Lihat Key' }}
                  </button>
                </div>
                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                  Disalin dari Tab 5 (Hub Koneksi Multi-Sistem & API) pada HRIS Sasaran Mutu.
                </p>
                <InputError class="mt-1" :message="form.errors.hris_api_key" />
              </div>

              <!-- Target Endpoint Calculation Box -->
              <div class="rounded-lg border border-sky-200 bg-sky-50/70 p-3.5 dark:border-sky-800 dark:bg-sky-950/40">
                <div class="flex items-center justify-between text-xs font-semibold text-sky-900 dark:text-sky-300 mb-1">
                  <span>TARGET ENDPOINT INBOUND TERHITUNG:</span>
                  <button
                    type="button"
                    @click="copyEndpoint"
                    class="text-[11px] text-sky-700 hover:text-sky-900 dark:text-sky-300 font-bold flex items-center gap-1"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                    <span>{{ copiedState ? '✓ URL Disalin!' : 'Salin Endpoint' }}</span>
                  </button>
                </div>
                <div class="flex items-center gap-2 font-mono text-xs text-sky-800 dark:text-sky-200 bg-white dark:bg-gray-900 px-3 py-2 rounded border border-sky-200 dark:border-sky-800 overflow-x-auto">
                  <span class="font-bold text-sky-600 dark:text-sky-400">POST</span>
                  <span>{{ computedEndpoint }}</span>
                </div>
              </div>

              <!-- Test Result Alert Box -->
              <div
                v-if="testResult"
                class="rounded-lg p-3.5 text-xs font-medium border transition-all"
                :class="testResult.success
                  ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300'
                  : 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-800 dark:bg-rose-950/50 dark:text-rose-300'"
              >
                <div class="flex items-start gap-2">
                  <span class="text-sm shrink-0">{{ testResult.success ? '✓' : '⚠️' }}</span>
                  <div class="space-y-1">
                    <p class="font-bold">{{ testResult.success ? 'Koneksi Berhasil!' : 'Koneksi Gagal' }}</p>
                    <p class="text-[11px] leading-relaxed">{{ testResult.message }}</p>
                    <p v-if="testResult.latency_ms" class="text-[10px] text-gray-500 dark:text-gray-400">
                      Waktu Respon: {{ testResult.latency_ms }} ms
                    </p>
                  </div>
                </div>
              </div>

              <!-- Action Buttons -->
              <div class="flex flex-wrap items-center justify-between gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                <SecondaryButton
                  type="button"
                  @click="runTestConnection"
                  :disabled="isTesting"
                  class="inline-flex items-center gap-1.5"
                >
                  <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                  </svg>
                  <span>{{ isTesting ? 'Menguji Koneksi...' : 'Test Koneksi ke HRIS' }}</span>
                </SecondaryButton>

                <PrimaryButton :disabled="form.processing">
                  {{ form.processing ? 'Menyimpan...' : 'Simpan Pengaturan' }}
                </PrimaryButton>
              </div>
            </form>
          </div>
        </div>

        <!-- RIGHT SIDE: STATUS & INSTANT PUSH (1 COL) -->
        <div class="space-y-6">
          <!-- Summary Card -->
          <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3 flex items-center gap-2">
              <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
              Data Produksi ({{ stats.current_period }})
            </h3>
            
            <dl class="space-y-2.5 text-xs">
              <div class="flex items-center justify-between border-b border-gray-100 pb-2 dark:border-gray-700">
                <dt class="text-gray-500 dark:text-gray-400">Total Laporan Harian</dt>
                <dd class="font-bold text-gray-900 dark:text-gray-100 font-mono">{{ stats.monthly_reports }} Laporan</dd>
              </div>
              <div class="flex items-center justify-between border-b border-gray-100 pb-2 dark:border-gray-700">
                <dt class="text-gray-500 dark:text-gray-400">Rata-rata OEE</dt>
                <dd class="font-bold text-emerald-600 dark:text-emerald-400 font-mono">{{ stats.avg_oee }}%</dd>
              </div>
              <div class="flex items-center justify-between">
                <dt class="text-gray-500 dark:text-gray-400">Metrik Terkirim</dt>
                <dd class="font-medium text-gray-700 dark:text-gray-300">OEE, Availability, Yield, Output</dd>
              </div>
            </dl>

            <div class="mt-5 pt-4 border-t border-gray-100 dark:border-gray-700">
              <button
                type="button"
                @click="runSyncNow"
                :disabled="isSyncing"
                class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:opacity-50"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
                <span>{{ isSyncing ? 'Mengirim Data...' : 'Kirim Metrik ke HRIS Sekarang' }}</span>
              </button>
            </div>
          </div>

          <!-- Petunjuk HRIS Card -->
          <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">
              Petunjuk Integrasi
            </h3>
            <ol class="list-decimal pl-4 space-y-1.5 text-xs text-gray-600 dark:text-gray-300">
              <li>Buka halaman Sasaran Mutu di HRIS.</li>
              <li>Pilih <strong>Tab 5 (Hub Koneksi Multi-Sistem & API)</strong>.</li>
              <li>Klik tombol <strong>"Salin Key"</strong> pada kartu Global Secret Key.</li>
              <li>Paste Key tersebut ke form di samping kiri dan klik <strong>Test Koneksi</strong>.</li>
            </ol>
          </div>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>
