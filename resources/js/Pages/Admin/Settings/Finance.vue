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
    url: string
    api_key: string
    auto_sync: boolean
    last_synced_at?: string | null
    last_sync_status?: string | null
    last_sync_message?: string | null
  }
  stats: {
    current_period: string
    monthly_reports: number
    avg_oee: number
    endpoint: string
    summary: {
      total_output_fisik?: number
      total_target_plan?: number
      achievement_pct?: number
      total_reject_pcs?: number
      total_material_loss_qty?: number
      total_machine_hours?: number
      total_downtime_hours?: number
      overall_oee_percent?: number
      overall_yield_percent?: number
    }
    variance_count: number
    products_count: number
  }
}

const props = defineProps<Props>()

function sanitizeUrl(raw: string): string {
  if (!raw) return ''
  let cleaned = raw.trim()
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
  finance_api_url: sanitizeUrl(props.settings?.url || 'http://localhost:8000'),
  finance_api_key: props.settings?.api_key || 'bsc_sec_live_9f82d1c6b3e44a7b',
  finance_auto_sync: props.settings?.auto_sync ?? false,
})

const selectedPeriod = ref(props.stats?.current_period || new Date().toISOString().slice(0, 7))
const showApiKey = ref(false)
const isTesting = ref(false)
const testResult = ref<{ success?: boolean; message?: string; latency_ms?: number; endpoint?: string } | null>(null)
const isSyncing = ref(false)
const copiedState = ref(false)

const computedEndpoint = computed(() => {
  const base = sanitizeUrl(form.finance_api_url) || 'http://localhost:8000'
  return `${base}/api/v1/finance/production-feed`
})

function onUrlBlur() {
  form.finance_api_url = sanitizeUrl(form.finance_api_url)
}

function setDefaultLocalUrl() {
  form.finance_api_url = 'http://localhost:8000'
  testResult.value = null
}

function setDefaultApiKey() {
  form.finance_api_key = 'bsc_sec_live_9f82d1c6b3e44a7b'
  testResult.value = null
}

function submit() {
  form.finance_api_url = sanitizeUrl(form.finance_api_url)
  form.post(route('admin.settings.finance.update'), {
    preserveScroll: true,
    onSuccess: () => {
      testResult.value = null
    },
  })
}

async function runTestConnection() {
  form.finance_api_url = sanitizeUrl(form.finance_api_url)
  isTesting.value = true
  testResult.value = null

  try {
    const response = await axios.post(route('admin.settings.finance.test'), {
      finance_api_url: form.finance_api_url,
      finance_api_key: form.finance_api_key,
    })
    testResult.value = response.data
  } catch (err: any) {
    testResult.value = {
      success: false,
      message: err.response?.data?.message || err.message || 'Gagal menghubungi server Finance Monitoring.',
      latency_ms: err.response?.data?.latency_ms,
    }
  } finally {
    isTesting.value = false
  }
}

function runSyncNow() {
  if (!confirm(`Kirim feed data produksi (Output Fisik, Target, OEE, Jam Mesin, Downtime & SO Variance) periode ${selectedPeriod.value} ke Finance Monitoring sekarang?`)) {
    return
  }
  isSyncing.value = true
  router.post(route('admin.settings.finance.sync'), {
    period: selectedPeriod.value,
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

function formatNumber(num?: number): string {
  if (num === undefined || num === null) return '0'
  return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(num)
}
</script>

<template>
  <Head title="Pengaturan Integrasi Finance Monitoring" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-lg sm:text-xl font-bold leading-tight text-gray-800 dark:text-gray-200 flex items-center gap-2">
            <span>Integrasi Finance Monitoring</span>
            <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-950/40 dark:text-emerald-300">
              REST Outbound Feed
            </span>
          </h2>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
            Koneksi outbound untuk mengirim data capaian output, jam mesin, downtime, reject/loss, dan selisih Stock Opname ke sistem Finance (FAT)
          </p>
        </div>
        <Link
          :href="route('admin.produk.index')"
          class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 w-fit"
        >
          ← Kembali ke Master Data
        </Link>
      </div>
    </template>

    <div class="mx-auto max-w-7xl px-3 sm:px-6 py-2 sm:py-4">
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        
        <!-- FORM SETTINGS (2 COLS) -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Connection Form Card -->
          <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="mb-5 flex items-center justify-between border-b border-gray-100 pb-4 dark:border-gray-700">
              <div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Kredensial Host & API Security</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                  Konfigurasikan endpoint tujuan sistem Finance Monitoring untuk sinkronisasi otomatis maupun manual.
                </p>
              </div>
              <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-600/20 dark:bg-indigo-950/50 dark:text-indigo-300">
                Outbound API
              </span>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
              <!-- Finance Host URL -->
              <div>
                <div class="flex items-center justify-between mb-1">
                  <InputLabel for="finance_api_url" value="Base URL Sistem Finance *" class="text-xs font-bold uppercase tracking-wider" />
                  <button
                    type="button"
                    @click="setDefaultLocalUrl"
                    class="text-[11px] text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 font-semibold"
                  >
                    Reset Default (http://localhost:8000)
                  </button>
                </div>
                <div class="relative">
                  <TextInput
                    id="finance_api_url"
                    v-model="form.finance_api_url"
                    type="text"
                    @blur="onUrlBlur"
                    class="block w-full font-mono text-sm text-gray-900 dark:text-gray-100"
                    placeholder="http://localhost:8000"
                    required
                  />
                </div>
                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                  Host URL aplikasi Finance Monitoring (contoh: <code>http://localhost:8000</code> atau <code>http://financea.test</code>).
                </p>
                <InputError class="mt-1" :message="form.errors.finance_api_url" />
              </div>

              <!-- Secret API Key -->
              <div>
                <div class="flex items-center justify-between mb-1">
                  <InputLabel for="finance_api_key" value="Secret API Key (X-API-KEY) *" class="text-xs font-bold uppercase tracking-wider" />
                  <button
                    type="button"
                    @click="setDefaultApiKey"
                    class="text-[11px] text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 font-semibold"
                  >
                    Gunakan SuperApps Key
                  </button>
                </div>
                <div class="relative">
                  <TextInput
                    id="finance_api_key"
                    v-model="form.finance_api_key"
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
                  Kunci otentikasi API yang dikenali oleh middleware security pada Finance Monitoring.
                </p>
                <InputError class="mt-1" :message="form.errors.finance_api_key" />
              </div>

              <!-- Target Endpoint Calculation Box -->
              <div class="rounded-lg border border-emerald-200 bg-emerald-50/70 p-3.5 dark:border-emerald-800 dark:bg-emerald-950/40">
                <div class="flex items-center justify-between text-xs font-semibold text-emerald-900 dark:text-emerald-300 mb-1">
                  <span>TARGET ENDPOINT INGESTION FINANCE:</span>
                  <button
                    type="button"
                    @click="copyEndpoint"
                    class="text-[11px] text-emerald-700 hover:text-emerald-900 dark:text-emerald-300 font-bold flex items-center gap-1"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                    <span>{{ copiedState ? '✓ Endpoint Disalin!' : 'Salin Endpoint' }}</span>
                  </button>
                </div>
                <div class="flex items-center gap-2 font-mono text-xs text-emerald-800 dark:text-emerald-200 bg-white dark:bg-gray-900 px-3 py-2 rounded border border-emerald-200 dark:border-emerald-800 overflow-x-auto">
                  <span class="font-bold text-emerald-600 dark:text-emerald-400">POST</span>
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
                  <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                  </svg>
                  <span>{{ isTesting ? 'Menguji Koneksi...' : 'Test Koneksi ke Finance' }}</span>
                </SecondaryButton>

                <PrimaryButton :disabled="form.processing">
                  {{ form.processing ? 'Menyimpan...' : 'Simpan Pengaturan' }}
                </PrimaryButton>
              </div>
            </form>
          </div>

          <!-- Live Aggregated Data Preview Card -->
          <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="mb-4 flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
              <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                Preview Payload Data Produksi (Periode: {{ stats.current_period }})
              </h3>
              <span class="text-xs text-gray-500 dark:text-gray-400">
                {{ stats.products_count }} SKU Produk Terdata
              </span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
              <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900 border border-gray-100 dark:border-gray-800">
                <p class="text-[11px] text-gray-500 dark:text-gray-400">Output Fisik</p>
                <p class="text-base font-bold text-gray-900 dark:text-gray-100 font-mono">
                  {{ formatNumber(stats.summary?.total_output_fisik) }}
                </p>
                <p class="text-[10px] text-emerald-600 font-medium">Target: {{ formatNumber(stats.summary?.total_target_plan) }}</p>
              </div>

              <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900 border border-gray-100 dark:border-gray-800">
                <p class="text-[11px] text-gray-500 dark:text-gray-400">Pencapaian Plan</p>
                <p class="text-base font-bold text-emerald-600 dark:text-emerald-400 font-mono">
                  {{ formatNumber(stats.summary?.achievement_pct) }}%
                </p>
                <p class="text-[10px] text-gray-500">Yield: {{ formatNumber(stats.summary?.overall_yield_percent) }}%</p>
              </div>

              <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900 border border-gray-100 dark:border-gray-800">
                <p class="text-[11px] text-gray-500 dark:text-gray-400">Jam Mesin / DT</p>
                <p class="text-base font-bold text-indigo-600 dark:text-indigo-400 font-mono">
                  {{ formatNumber(stats.summary?.total_machine_hours) }} Jam
                </p>
                <p class="text-[10px] text-rose-500 font-medium">DT: {{ formatNumber(stats.summary?.total_downtime_hours) }} Jam</p>
              </div>

              <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900 border border-gray-100 dark:border-gray-800">
                <p class="text-[11px] text-gray-500 dark:text-gray-400">Total Reject / Loss</p>
                <p class="text-base font-bold text-rose-600 dark:text-rose-400 font-mono">
                  {{ formatNumber(stats.summary?.total_reject_pcs) }} Pcs
                </p>
                <p class="text-[10px] text-amber-500 font-medium">Loss: {{ formatNumber(stats.summary?.total_material_loss_qty) }}</p>
              </div>
            </div>

            <div class="mt-3 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 pt-3 border-t border-gray-100 dark:border-gray-700">
              <span>Variansi Stock Opname: <strong>{{ stats.variance_count }} Kategori</strong></span>
              <span>Overall OEE: <strong class="text-emerald-600 font-mono">{{ formatNumber(stats.summary?.overall_oee_percent) }}%</strong></span>
            </div>
          </div>
        </div>

        <!-- RIGHT SIDE: STATUS & INSTANT PUSH (1 COL) -->
        <div class="space-y-6">
          <!-- Push Action Card -->
          <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3 flex items-center gap-2">
              <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
              </svg>
              Eksekusi Kirim ke Finance
            </h3>

            <div class="space-y-3">
              <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                  Pilih Periode Bulan:
                </label>
                <input
                  v-model="selectedPeriod"
                  type="month"
                  class="w-full rounded-lg border-gray-300 text-xs shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                />
              </div>

              <!-- Last Sync Status Info -->
              <div v-if="settings.last_synced_at" class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900/80 border border-gray-200 dark:border-gray-700 text-xs space-y-1">
                <div class="flex items-center justify-between">
                  <span class="text-gray-500 dark:text-gray-400">Status Terakhir:</span>
                  <span
                    class="font-semibold uppercase text-[10px] px-2 py-0.5 rounded-full"
                    :class="settings.last_sync_status === 'success'
                      ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                      : 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300'"
                  >
                    {{ settings.last_sync_status || 'N/A' }}
                  </span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-gray-500 dark:text-gray-400">Waktu Sync:</span>
                  <span class="font-mono text-gray-700 dark:text-gray-300">{{ settings.last_synced_at }}</span>
                </div>
                <p v-if="settings.last_sync_message" class="text-[11px] text-gray-600 dark:text-gray-400 truncate mt-1">
                  {{ settings.last_sync_message }}
                </p>
              </div>

              <button
                type="button"
                @click="runSyncNow"
                :disabled="isSyncing"
                class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:opacity-50"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
                <span>{{ isSyncing ? 'Mengirim Data...' : 'Kirim Feed ke Finance Sekarang' }}</span>
              </button>
            </div>
          </div>

          <!-- Petunjuk Integrasi Card -->
          <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2 flex items-center gap-1.5">
              <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              Alur Data Feed Produksi
            </h3>
            <ul class="space-y-2 text-xs text-gray-600 dark:text-gray-300">
              <li class="flex items-start gap-2">
                <span class="rounded bg-indigo-100 text-indigo-800 dark:bg-indigo-900/60 dark:text-indigo-300 px-1.5 py-0.5 text-[10px] font-bold shrink-0">1</span>
                <span><strong>Output Fisik & Yield:</strong> Diambil dari agregasi Laporan Harian operator mesin per bulan.</span>
              </li>
              <li class="flex items-start gap-2">
                <span class="rounded bg-indigo-100 text-indigo-800 dark:bg-indigo-900/60 dark:text-indigo-300 px-1.5 py-0.5 text-[10px] font-bold shrink-0">2</span>
                <span><strong>Jam Mesin & Downtime:</strong> Digunakan Finance untuk kalkulasi OEE & alokasi biaya overhead pabrik.</span>
              </li>
              <li class="flex items-start gap-2">
                <span class="rounded bg-indigo-100 text-indigo-800 dark:bg-indigo-900/60 dark:text-indigo-300 px-1.5 py-0.5 text-[10px] font-bold shrink-0">3</span>
                <span><strong>Stock Opname Variance:</strong> Selisih fisik stok gudang yang siap dianalisis selisih nilai rupiahnya di Finance FAT.</span>
              </li>
            </ul>
          </div>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>
