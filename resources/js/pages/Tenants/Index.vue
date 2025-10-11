<!-- resources/js/Pages/Tenants/Index.vue -->
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

type TenantRow = {
    id: number;
    name: string;
    domain: string;
    db_name: string;
    is_active: boolean;
    db_host?: string | null;
    db_port?: number | null;
    db_username?: string | null;
};

const props = defineProps<{ tenants: { data: TenantRow[] } }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tenants', href: '/admin/tenants' },
];

// --- create/edit modal state
const modalOpen = ref(false);
const editingId = ref<number | null>(null);

// --- result modal (for status/db-check/pending outputs)
const resultOpen = ref(false);
const resultTitle = ref('');
const resultBody = ref('');

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

// --- form
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
    form.db_password = ''; // নিরাপত্তার কারণে দেখাচ্ছি না
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

// ---- helpers
const ask = (msg: string) => window.confirm(msg);
const jsonGet = async (url: string) => {
    const res = await fetch(url, { headers: { Accept: 'application/json' } });
    return res.json();
};

// ---- actions (all with confirm)
const runMigrate = (t: TenantRow) => {
    if (!ask(`Run migrations for "${t.name}"?`)) return;
    router.post(`/admin/tenants/${t.id}/migrate`, {}, { preserveScroll: true });
};
const runProvision = (t: TenantRow) => {
    if (!ask(`Provision (create DB if missing) and migrate "${t.name}"?`))
        return;
    router.post(
        `/admin/tenants/${t.id}/provision`,
        {},
        { preserveScroll: true },
    );
};
const runFresh = (t: TenantRow) => {
    if (!ask(`Fresh migrate "${t.name}" (DROP all tables then migrate)?`))
        return;
    router.post(
        `/admin/tenants/${t.id}/migrate-fresh`,
        {},
        { preserveScroll: true },
    );
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

// info/status GETs → show in result modal
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

// Esc closes create/edit modal
function onKey(e: KeyboardEvent) {
    if (e.key === 'Escape' && modalOpen.value) closeModal();
}
onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <Head title="Tenants" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header + Add button -->
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">Tenants</h1>
                <button
                    @click="openCreate"
                    class="rounded bg-black px-4 py-2 text-white dark:bg-white dark:text-black"
                >
                    New Tenant
                </button>
            </div>

            <!-- List -->
            <div
                class="rounded-xl border border-sidebar-border/70 bg-white p-4 dark:border-sidebar-border dark:bg-sidebar"
            >
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr
                                class="bg-gray-100 text-left dark:bg-neutral-800"
                            >
                                <th class="p-2">Name</th>
                                <th class="p-2">Domain</th>
                                <th class="p-2">DB</th>
                                <th class="p-2 text-center">Active</th>
                                <th class="p-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="t in props.tenants.data"
                                :key="t.id"
                                class="border-t dark:border-neutral-700"
                            >
                                <td class="p-2">{{ t.name }}</td>
                                <td class="p-2">{{ t.domain }}</td>
                                <td class="p-2">{{ t.db_name }}</td>
                                <td class="p-2 text-center">
                                    <span
                                        class="rounded border px-2 py-0.5 text-xs"
                                        :class="
                                            t.is_active
                                                ? 'border-green-300 text-green-700'
                                                : 'border-red-300 text-red-700'
                                        "
                                    >
                                        {{ t.is_active ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                                <td class="p-2">
                                    <div class="flex flex-wrap gap-2">
                                        <!-- mutate ops -->
                                        <button
                                            @click="runMigrate(t)"
                                            class="rounded border px-2 py-1"
                                        >
                                            Migrate
                                        </button>
                                        <button
                                            @click="runFresh(t)"
                                            class="rounded border px-2 py-1 text-amber-700"
                                        >
                                            Fresh
                                        </button>
                                        <button
                                            @click="runProvision(t)"
                                            class="rounded border px-2 py-1"
                                        >
                                            Provision
                                        </button>

                                        <!-- status/info -->
                                        <button
                                            @click="checkDb(t)"
                                            class="rounded border px-2 py-1"
                                        >
                                            DB Check
                                        </button>
                                        <button
                                            @click="showConnStatus(t)"
                                            class="rounded border px-2 py-1"
                                        >
                                            Status
                                        </button>
                                        <button
                                            @click="showMigrationStatus(t)"
                                            class="rounded border px-2 py-1"
                                        >
                                            Mig Status
                                        </button>
                                        <button
                                            @click="showPending(t)"
                                            class="rounded border px-2 py-1"
                                        >
                                            Pending
                                        </button>

                                        <!-- state & crud -->
                                        <button
                                            @click="toggleTenant(t)"
                                            class="rounded border px-2 py-1"
                                        >
                                            {{
                                                t.is_active
                                                    ? 'Deactivate'
                                                    : 'Activate'
                                            }}
                                        </button>
                                        <button
                                            @click="openEdit(t)"
                                            class="rounded border px-2 py-1"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            @click="destroyTenant(t)"
                                            class="rounded border px-2 py-1 text-red-600"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!props.tenants.data?.length">
                                <td
                                    class="p-4 text-center text-neutral-500"
                                    colspan="5"
                                >
                                    No tenants found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
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

            <!-- Result Modal (for JSON/text outputs) -->
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
            <!-- /Result Modal -->
        </div>
    </AppLayout>
</template>

<style scoped lang="css">
/* optional */
</style>
