<script setup lang="ts">
import { computed, ref, watch } from 'vue';

type TenantRow = {
    id: number;
    name: string;
};

const props = withDefaults(
    defineProps<{
        open: boolean;
        tenant: TenantRow | null;
        defaultLogoWidth?: number;
        defaultFaviconSize?: 16 | 24 | 32 | 48;
    }>(),
    {
        defaultLogoWidth: 240,
        defaultFaviconSize: 32,
    },
);

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
    (e: 'close'): void;
    (e: 'uploaded', payload: any): void;
}>();

// state
const file = ref<File | null>(null);
const logoWidth = ref<number>(props.defaultLogoWidth);
const faviconSize = ref<number>(props.defaultFaviconSize);
const uploading = ref(false);
const errorMsg = ref<string>('');

// helpers
function csrf(): string {
    const el = document.querySelector(
        'meta[name="csrf-token"]',
    ) as HTMLMetaElement | null;
    return el?.content || '';
}

function close() {
    emit('update:open', false);
    emit('close');
}

// ✅ reliable file change handler
function handleFileChange(e: Event) {
    const input = e.target as HTMLInputElement | null;
    const f = input?.files && input.files.length ? input.files[0] : null;
    file.value = f;
    if (f) errorMsg.value = '';
}

async function doUpload() {
    if (!props.tenant) return;
    if (!file.value) {
        errorMsg.value = 'Please select a PNG file.';
        return;
    }
    const mime = (file.value.type || '').toLowerCase();
    const name = (file.value.name || '').toLowerCase();
    if (!(mime.includes('png') || name.endsWith('.png'))) {
        errorMsg.value = 'Selected file is not PNG.';
        return;
    }

    try {
        uploading.value = true;
        errorMsg.value = '';

        const fd = new FormData();
        fd.append('logo', file.value);
        fd.append('logo_width', String(logoWidth.value));
        fd.append('favicon_size', String(faviconSize.value));

        const res = await fetch(`/admin/tenants/${props.tenant.id}/branding`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: fd,
        });

        const data = await res.json().catch(() => ({}));
        if (!res.ok)
            throw new Error(data?.message || data?.error || 'Upload failed');

        emit('uploaded', data);
        close();
    } catch (e: any) {
        errorMsg.value = e?.message || 'Upload failed';
    } finally {
        uploading.value = false;
    }
}

// reset inputs when modal opens/closes
watch(
    () => props.open,
    (v) => {
        if (v) {
            file.value = null;
            logoWidth.value = props.defaultLogoWidth;
            faviconSize.value = props.defaultFaviconSize;
            errorMsg.value = '';
        }
    },
);

const show = computed(() => props.open);
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50" aria-modal="true" role="dialog">
        <div
            class="absolute inset-0 bg-black/40 backdrop-blur-[1px]"
            @click="close"
        ></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div
                class="w-full max-w-lg rounded-xl border border-sidebar-border/70 bg-white shadow-xl dark:border-sidebar-border dark:bg-sidebar"
            >
                <div
                    class="flex items-center justify-between border-b p-4 dark:border-neutral-800"
                >
                    <h3 class="text-lg font-semibold">Upload Branding (PNG)</h3>
                    <button
                        class="rounded px-2 py-1 hover:bg-black/5 dark:hover:bg-white/10"
                        @click="close"
                    >
                        ✕
                    </button>
                </div>

                <div class="space-y-3 p-4">
                    <div class="text-sm">
                        <p class="mb-2">
                            Select a <strong>PNG</strong> logo. Server will
                            create:
                        </p>
                        <ul class="list-disc pl-5">
                            <li>logo.png (pre-sized)</li>
                            <li>
                                favicon.png (square {{ faviconSize }}×{{
                                    faviconSize
                                }})
                            </li>
                        </ul>
                    </div>

                    <!-- ✅ file handler fixed -->
                    <input
                        type="file"
                        accept="image/png,.png"
                        @change="handleFileChange"
                        class="w-full rounded border p-2 dark:bg-transparent"
                    />

                    <div class="grid grid-cols-2 gap-3">
                        <label
                            class="flex items-center justify-between rounded border p-2 text-sm dark:bg-transparent"
                        >
                            <span>Logo width (px)</span>
                            <input
                                type="number"
                                min="64"
                                max="1024"
                                v-model.number="logoWidth"
                                class="w-24 rounded border p-1 text-right dark:bg-transparent"
                            />
                        </label>
                        <label
                            class="flex items-center justify-between rounded border p-2 text-sm dark:bg-transparent"
                        >
                            <span>Favicon size</span>
                            <select
                                v-model.number="faviconSize"
                                class="w-24 rounded border p-1 dark:bg-transparent"
                            >
                                <option :value="16">16</option>
                                <option :value="24">24</option>
                                <option :value="32">32</option>
                                <option :value="48">48</option>
                            </select>
                        </label>
                    </div>

                    <div
                        v-if="errorMsg"
                        class="rounded border border-red-200 bg-red-50 p-2 text-sm text-red-700 dark:border-red-700/40 dark:bg-red-900/20 dark:text-red-300"
                    >
                        {{ errorMsg }}
                    </div>
                </div>

                <div
                    class="flex justify-end gap-2 border-t p-3 dark:border-neutral-800"
                >
                    <button class="rounded border px-4 py-2" @click="close">
                        Cancel
                    </button>
                    <button
                        class="rounded bg-black px-4 py-2 text-white disabled:opacity-60 dark:bg-white dark:text-black"
                        :disabled="uploading"
                        @click="doUpload"
                    >
                        {{ uploading ? 'Uploading…' : 'Upload' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* optional */
</style>
