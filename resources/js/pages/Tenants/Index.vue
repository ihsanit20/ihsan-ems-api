<script setup lang="ts">
import UploadBrandingModal from '@/components/admin/UploadBrandingModal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

// icons
import {
    Activity,
    Clock,
    Database,
    ImageUp,
    ListChecks,
    MoreHorizontal,
    Pencil,
    Play,
    Power,
    PowerOff,
    Server,
    Trash2,
} from 'lucide-vue-next';

type TenantRow = {
    id: number;
    name: string;
    domain: string;
    db_name: string;
    is_active: boolean;
    db_host?: string | null;
    db_port?: number | null;
    db_username?: string | null;
    branding_urls?: { logo_url?: string; favicon_url?: string };
};

const props = defineProps<{ tenants: { data: TenantRow[] } }>();
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tenants', href: '/admin/tenants' },
];

// ---------- Create/Edit modal ----------
const modalOpen = ref(false);
const editingId = ref<number | null>(null);

// ---------- Result modal ----------
const resultOpen = ref(false);
const resultTitle = ref('');
const resultBody = ref('');

// ---------- More menu (portal) ----------
const moreOpen = ref(false);
const moreTenant = ref<TenantRow | null>(null);
const morePos = ref({ top: 0, left: 0 });
const moreAnchorEl = ref<HTMLElement | null>(null);
const menuRef = ref<HTMLElement | null>(null);

// ---------- Form ----------
const form = useForm({
    name: '',
    domain: '',
    db_name: '',
    is_active: true,
    db_host: '',
    db_port: null as number | null,
    db_username: '',
    db_password: '',
});

function openCreate() {
    resetForm();
    modalOpen.value = true;
}
function openEdit(t: TenantRow) {
    editingId.value = t.id;
    form.name = t.name;
    form.domain = t.domain;
    form.db_name = t.db_name;
    form.is_active = t.is_active;
    form.db_host = t.db_host ?? '';
    form.db_port = (t.db_port ?? null) as number | null;
    form.db_username = t.db_username ?? '';
    form.db_password = '';
    modalOpen.value = true;
}
function closeModal() {
    modalOpen.value = false;
}
function resetForm() {
    form.reset();
    form.is_active = true;
    form.db_port = null;
    editingId.value = null;
}
function submit() {
    if (editingId.value) {
        router.put(`/admin/tenants/${editingId.value}`, form.data(), {
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
                resetForm();
            },
        });
    } else {
        form.post('/admin/tenants', {
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
                resetForm();
            },
        });
    }
}

// ---------- Helpers ----------
const ask = (msg: string) => window.confirm(msg);

// JSON GET helper
const jsonGet = async (url: string) => {
    const res = await fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });
    return res.json();
};

// CSRF helper for JSON POST (not used for FormData)
const csrf = () =>
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
        ?.content || '';

const jsonPost = async (url: string, payload: any = {}) => {
    const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        throw new Error(data?.message || data?.error || 'Request failed');
    }
    return data;
};

function showResult(title: string, body: string | object) {
    resultTitle.value = title;
    resultBody.value =
        typeof body === 'string' ? body : JSON.stringify(body, null, 2);
    resultOpen.value = true;
}
function closeResult() {
    resultOpen.value = false;
    resultTitle.value = '';
    resultBody.value = '';
}

// ---------- Actions ----------
const runMigrate = async (t: TenantRow) => {
    if (!ask(`Run migrations for "${t.name}"?`)) return;
    try {
        const data = await jsonPost(`/admin/tenants/${t.id}/migrate`);
        showResult(`Migrate — ${t.name}`, data);
        router.reload({ only: ['tenants'] });
    } catch (e: any) {
        alert(e.message);
    }
};
const runProvision = async (t: TenantRow) => {
    if (!ask(`Provision (create DB if missing) and migrate "${t.name}"?`))
        return;
    try {
        const data = await jsonPost(`/admin/tenants/${t.id}/provision`);
        showResult(`Provision — ${t.name}`, data);
        router.reload({ only: ['tenants'] });
    } catch (e: any) {
        alert(e.message);
    }
};
const destroyTenant = (t: TenantRow) => {
    if (!ask(`Delete tenant "${t.name}"? This cannot be undone.`)) return;
    router.delete(`/admin/tenants/${t.id}`, { preserveScroll: true });
};
const toggleTenant = (t: TenantRow) => {
    const to = t.is_active ? 'deactivate' : 'activate';
    if (!ask(`Are you sure you want to ${to} "${t.name}"?`)) return;
    router.post(`/admin/tenants/${t.id}/toggle`, {}, { preserveScroll: true });
};

// info/status → result modal
const checkDb = async (t: TenantRow) => {
    if (!ask(`Check DB connection & existence for "${t.name}"?`)) return;
    const data = await jsonGet(`/admin/tenants/${t.id}/db-check`);
    showResult(`DB Check — ${t.name}`, data);
};
const showConnStatus = async (t: TenantRow) => {
    if (!ask(`Check connection status for "${t.name}"?`)) return;
    const data = await jsonGet(`/admin/tenants/${t.id}/status`);
    showResult(`Connection Status — ${t.name}`, data);
};
const showMigrationStatus = async (t: TenantRow) => {
    if (!ask(`Show migrate:status for "${t.name}"?`)) return;
    const data = await jsonGet(`/admin/tenants/${t.id}/migrations/status`);
    showResult(`Migration Status — ${t.name}`, data?.output ?? data);
};
const showPending = async (t: TenantRow) => {
    if (!ask(`Show pending migrations for "${t.name}"?`)) return;
    const data = await jsonGet(`/admin/tenants/${t.id}/migrations/pending`);
    showResult(`Pending Migrations — ${t.name}`, data);
};

// ---------- Upload Branding modal state ----------
const uploadOpen = ref(false);
const uploadTenant = ref<TenantRow | null>(null);

function openUpload(t: TenantRow) {
    uploadTenant.value = t;
    uploadOpen.value = true;
}
function onUploaded(payload: any) {
    showResult(
        `Branding uploaded — ${uploadTenant.value?.name ?? ''}`,
        payload,
    );
    router.reload({ only: ['tenants'] });
}

// ---------- More menu logic ----------
function openMore(t: TenantRow, el: HTMLElement) {
    const r = el.getBoundingClientRect();
    const menuWidth = 224;
    const padding = 8;
    const top = r.bottom + 6;
    const left = Math.min(r.left, window.innerWidth - menuWidth - padding);
    morePos.value = { top, left };
    moreTenant.value = t;
    moreAnchorEl.value = el;
    moreOpen.value = true;
}
function closeMore() {
    moreOpen.value = false;
    moreTenant.value = null;
    moreAnchorEl.value = null;
}
function doAndClose(action: (t: TenantRow) => any | Promise<any>) {
    const t = moreTenant.value;
    if (!t) return;
    action(t);
    closeMore();
}

// ---------- Global listeners ----------
function onGlobalClick(e: MouseEvent) {
    if (!moreOpen.value) return;
    const t = e.target as Node;
    const insideAnchor = !!moreAnchorEl.value?.contains(t);
    const insideMenu = !!menuRef.value?.contains(t);
    if (!insideAnchor && !insideMenu) closeMore();
}
function onGlobalResizeScroll() {
    if (moreOpen.value) closeMore();
}
function onKey(e: KeyboardEvent) {
    if (e.key === 'Escape') {
        if (modalOpen.value) closeModal();
        if (uploadOpen.value) uploadOpen.value = false;
        if (resultOpen.value) closeResult();
        if (moreOpen.value) closeMore();
    }
}
onMounted(() => {
    window.addEventListener('click', onGlobalClick, true);
    window.addEventListener('resize', onGlobalResizeScroll, { passive: true });
    window.addEventListener('scroll', onGlobalResizeScroll, { passive: true });
    window.addEventListener('keydown', onKey);
});
onBeforeUnmount(() => {
    window.removeEventListener('click', onGlobalClick, true);
    window.removeEventListener('resize', onGlobalResizeScroll);
    window.removeEventListener('scroll', onGlobalResizeScroll);
    window.removeEventListener('keydown', onKey);
});
</script>

<template>
    <Head title="Tenants" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-semibold">Tenants</h1>
                <button
                    @click="openCreate"
                    class="rounded bg-black px-2 py-1 text-white dark:bg-white dark:text-black"
                >
                    +
                </button>
            </div>

            <!-- Cards Grid -->
            <div
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4"
            >
                <div
                    v-for="t in props.tenants.data"
                    :key="t.id"
                    class="relative rounded-xl border border-sidebar-border/70 bg-white p-4 dark:border-sidebar-border dark:bg-sidebar"
                >
                    <!-- Header row -->
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="truncate text-base font-semibold">
                                    {{ t.name }}
                                </h3>
                                <!-- small favicon near name -->
                                <img
                                    v-if="t.branding_urls?.favicon_url"
                                    :src="t.branding_urls.favicon_url"
                                    class="h-4 w-4 rounded"
                                    alt="favicon"
                                    title="Favicon preview"
                                />
                            </div>
                            <div
                                class="mt-1 text-xs text-neutral-500 dark:text-neutral-400"
                            >
                                <div class="truncate">
                                    Domain:
                                    <span
                                        class="font-medium text-neutral-700 dark:text-neutral-200"
                                        >{{ t.domain }}</span
                                    >
                                </div>
                                <div class="truncate">
                                    DB:
                                    <span
                                        class="font-medium text-neutral-700 dark:text-neutral-200"
                                        >{{ t.db_name }}</span
                                    >
                                </div>
                            </div>

                            <!-- ✅ Branding preview block -->
                            <div class="mt-3 flex items-center gap-3">
                                <!-- logo preview -->
                                <a
                                    v-if="t.branding_urls?.logo_url"
                                    :href="t.branding_urls.logo_url"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center gap-2 rounded border px-2 py-1"
                                    title="Open logo in new tab"
                                >
                                    <img
                                        :src="t.branding_urls.logo_url"
                                        alt="logo"
                                        class="h-8 max-w-[160px] object-contain"
                                    />
                                    <span class="text-xs text-neutral-500"
                                        >logo.png</span
                                    >
                                </a>
                                <span
                                    v-else
                                    class="inline-flex h-8 items-center justify-center rounded border px-2 text-xs text-neutral-500 dark:border-neutral-700"
                                >
                                    No logo
                                </span>

                                <!-- favicon preview (bigger) -->
                                <a
                                    v-if="t.branding_urls?.favicon_url"
                                    :href="t.branding_urls.favicon_url"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center gap-2 rounded border px-2 py-1"
                                    title="Open favicon in new tab"
                                >
                                    <img
                                        :src="t.branding_urls.favicon_url"
                                        alt="favicon"
                                        class="h-8 w-8 rounded"
                                    />
                                    <span class="text-xs text-neutral-500"
                                        >favicon.png</span
                                    >
                                </a>
                                <span
                                    v-else
                                    class="inline-flex h-8 items-center justify-center rounded border px-2 text-xs text-neutral-500 dark:border-neutral-700"
                                >
                                    No favicon
                                </span>
                            </div>
                            <!-- /Branding preview block -->
                        </div>

                        <div class="flex items-center gap-2">
                            <span
                                class="shrink-0 rounded-full px-2 py-0.5 text-xs"
                                :class="
                                    t.is_active
                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-300'
                                        : 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-300'
                                "
                            >
                                {{ t.is_active ? 'Active' : 'Inactive' }}
                            </span>

                            <button
                                class="rounded border p-1.5 hover:bg-black/5 dark:hover:bg-white/10"
                                @click="
                                    openMore(
                                        t,
                                        $event.currentTarget as HTMLElement,
                                    )
                                "
                                title="More actions"
                                aria-label="More actions"
                            >
                                <MoreHorizontal class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Action row -->
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <button
                            class="inline-flex items-center gap-1.5 rounded border px-2 py-1 text-sm hover:bg-black/5 dark:hover:bg-white/10"
                            @click="openEdit(t)"
                            title="Edit tenant"
                        >
                            <Pencil class="h-4 w-4" /> Edit
                        </button>

                        <button
                            class="inline-flex items-center gap-1.5 rounded border px-2 py-1 text-sm hover:bg-black/5 dark:hover:bg-white/10"
                            @click="runMigrate(t)"
                            title="Run tenant migrations"
                        >
                            <Play class="h-4 w-4" /> Migrate
                        </button>

                        <button
                            class="inline-flex items-center gap-1.5 rounded border px-2 py-1 text-sm hover:bg-black/5 dark:hover:bg-white/10"
                            @click="toggleTenant(t)"
                            :title="
                                t.is_active
                                    ? 'Deactivate tenant'
                                    : 'Activate tenant'
                            "
                        >
                            <component
                                :is="t.is_active ? PowerOff : Power"
                                class="h-4 w-4"
                            />
                            {{ t.is_active ? 'Deactivate' : 'Activate' }}
                        </button>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-if="!props.tenants.data?.length" class="col-span-full">
                    <div
                        class="rounded-xl border border-dashed p-8 text-center text-neutral-500 dark:border-neutral-700"
                    >
                        No tenants found.
                    </div>
                </div>
            </div>

            <!-- Create/Edit Modal -->
            <div
                v-if="modalOpen"
                class="fixed inset-0 z-50"
                aria-modal="true"
                role="dialog"
            >
                <div
                    class="absolute inset-0 bg-black/40 backdrop-blur-[1px]"
                    @click="closeModal"
                ></div>
                <div
                    class="absolute inset-0 flex items-center justify-center p-4"
                >
                    <div
                        class="w-full max-w-2xl rounded-xl border border-sidebar-border/70 bg-white shadow-xl dark:border-sidebar-border dark:bg-sidebar"
                    >
                        <div
                            class="flex items-center justify-between border-b p-4 dark:border-neutral-800"
                        >
                            <h3 class="text-lg font-semibold">
                                {{
                                    editingId
                                        ? 'Update Tenant'
                                        : 'Create Tenant'
                                }}
                            </h3>
                            <button
                                class="rounded px-2 py-1 hover:bg-black/5 dark:hover:bg-white/10"
                                @click="closeModal"
                            >
                                ✕
                            </button>
                        </div>

                        <form
                            @submit.prevent="submit"
                            class="grid grid-cols-1 gap-3 p-4 md:grid-cols-2"
                        >
                            <input
                                v-model="form.name"
                                placeholder="Name"
                                class="rounded border p-2 dark:bg-transparent"
                            />
                            <input
                                v-model="form.domain"
                                placeholder="Domain (e.g. demo.local.test)"
                                class="rounded border p-2 dark:bg-transparent"
                            />
                            <input
                                v-model="form.db_name"
                                placeholder="DB Name (e.g. ems_demo)"
                                class="rounded border p-2 dark:bg-transparent"
                            />

                            <label
                                class="flex items-center gap-2 rounded border p-2 dark:bg-transparent"
                            >
                                <input
                                    type="checkbox"
                                    v-model="form.is_active"
                                />
                                <span>Active</span>
                            </label>

                            <input
                                v-model="form.db_host"
                                placeholder="db_host (optional)"
                                class="rounded border p-2 dark:bg-transparent"
                            />
                            <input
                                v-model.number="form.db_port"
                                placeholder="db_port (optional)"
                                class="rounded border p-2 dark:bg-transparent"
                            />
                            <input
                                v-model="form.db_username"
                                placeholder="db_username (optional)"
                                class="rounded border p-2 dark:bg-transparent"
                            />
                            <input
                                v-model="form.db_password"
                                placeholder="db_password (optional)"
                                class="rounded border p-2 dark:bg-transparent"
                            />

                            <div
                                v-if="Object.keys(form.errors).length"
                                class="col-span-full rounded border border-red-200 bg-red-50 p-2 text-sm text-red-700 dark:border-red-700/40 dark:bg-red-900/20 dark:text-red-300"
                            >
                                <ul class="list-disc pl-5">
                                    <li
                                        v-for="(msg, key) in form.errors"
                                        :key="key"
                                    >
                                        {{ msg }}
                                    </li>
                                </ul>
                            </div>

                            <div
                                class="col-span-full flex items-center justify-end gap-2 pt-2"
                            >
                                <button
                                    type="button"
                                    @click="closeModal"
                                    class="rounded border px-4 py-2"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    class="rounded bg-black px-4 py-2 text-white disabled:opacity-60 dark:bg-white dark:text-black"
                                    :disabled="form.processing"
                                >
                                    {{ editingId ? 'Update' : 'Create' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Result Modal -->
            <div
                v-if="resultOpen"
                class="fixed inset-0 z-50"
                aria-modal="true"
                role="dialog"
            >
                <div
                    class="absolute inset-0 bg-black/40 backdrop-blur-[1px]"
                    @click="closeResult"
                ></div>
                <div
                    class="absolute inset-0 flex items-center justify-center p-4"
                >
                    <div
                        class="w-full max-w-3xl rounded-xl border border-sidebar-border/70 bg-white shadow-xl dark:border-sidebar-border dark:bg-sidebar"
                    >
                        <div
                            class="flex items-center justify-between border-b p-4 dark:border-neutral-800"
                        >
                            <h3 class="text-lg font-semibold">
                                {{ resultTitle }}
                            </h3>
                            <button
                                class="rounded px-2 py-1 hover:bg-black/5 dark:hover:bg-white/10"
                                @click="closeResult"
                            >
                                ✕
                            </button>
                        </div>
                        <div class="max-h-[70vh] overflow-auto p-4">
                            <pre class="text-sm whitespace-pre-wrap">{{
                                resultBody
                            }}</pre>
                        </div>
                        <div
                            class="flex justify-end gap-2 border-t p-3 dark:border-neutral-800"
                        >
                            <button
                                class="rounded border px-4 py-2"
                                @click="closeResult"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upload Branding Modal (component) -->
            <UploadBrandingModal
                v-model:open="uploadOpen"
                :tenant="uploadTenant"
                @uploaded="onUploaded"
            />

            <!-- More menu -->
            <Teleport to="body">
                <div
                    v-if="moreOpen && moreTenant"
                    class="fixed z-[60] w-56 rounded border bg-white p-1 shadow-md dark:border-sidebar-border dark:bg-sidebar"
                    :style="{
                        top: morePos.top + 'px',
                        left: morePos.left + 'px',
                    }"
                    ref="menuRef"
                >
                    <button
                        @click="doAndClose(openUpload)"
                        class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left hover:bg-black/5 dark:hover:bg-white/10"
                        title="Upload logo (PNG) and auto-generate favicon"
                    >
                        <ImageUp class="h-4 w-4" /> Upload branding
                    </button>
                    <hr
                        class="my-1 border-neutral-200 dark:border-neutral-700"
                    />
                    <button
                        @click="doAndClose(runProvision)"
                        class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left hover:bg-black/5 dark:hover:bg-white/10"
                        title="Create DB if missing and run migrations"
                    >
                        <Server class="h-4 w-4" /> Provision
                    </button>
                    <hr
                        class="my-1 border-neutral-200 dark:border-neutral-700"
                    />
                    <button
                        @click="doAndClose(checkDb)"
                        class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left hover:bg-black/5 dark:hover:bg-white/10"
                    >
                        <Database class="h-4 w-4" /> DB check
                    </button>
                    <button
                        @click="doAndClose(showConnStatus)"
                        class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left hover:bg-black/5 dark:hover:bg-white/10"
                    >
                        <Activity class="h-4 w-4" /> Status
                    </button>
                    <button
                        @click="doAndClose(showMigrationStatus)"
                        class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left hover:bg-black/5 dark:hover:bg-white/10"
                    >
                        <ListChecks class="h-4 w-4" /> Migration status
                    </button>
                    <button
                        @click="doAndClose(showPending)"
                        class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left hover:bg-black/5 dark:hover:bg-white/10"
                    >
                        <Clock class="h-4 w-4" /> Pending
                    </button>
                    <hr
                        class="my-1 border-neutral-200 dark:border-neutral-700"
                    />
                    <button
                        @click="doAndClose(destroyTenant)"
                        class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
                    >
                        <Trash2 class="h-4 w-4" /> Delete
                    </button>
                </div>
            </Teleport>
        </div>
    </AppLayout>
</template>

<style scoped>
/* optional */
</style>
