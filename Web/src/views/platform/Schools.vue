<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import superAdminService from '@/service/SuperAdminService';
import type { TenantFilters, CreateTenantPayload } from '@/service/SuperAdminService';

const router = useRouter();
const toast = useToast();
const confirm = useConfirm();

// ── State ─────────────────────────────────────────────────
const tenants = ref<any[]>([]);
const totalRecords = ref(0);
const loading = ref(false);
const filters = ref<TenantFilters>({ per_page: 15, page: 1, sort_by: 'created_at', sort_dir: 'desc' });

const searchQuery = ref('');
const statusFilter = ref<string | null>(null);

// Dialogs
const showCreateDialog = ref(false);
const showEditDialog = ref(false);
const creating = ref(false);
const editing = ref(false);

const emptyForm = (): CreateTenantPayload => ({
    id: '', name: '', contact_name: '', contact_email: '', contact_phone: '',
    subscription_plan: 'basic', subscription_expires_at: '', max_students: 0,
    notes: '', address: '', phone: '', email: '', admin_username: '', admin_password: '',
});

const createForm = ref<CreateTenantPayload>(emptyForm());
const editForm = ref<any>({});
const editingId = ref('');

const statusOptions = [
    { label: 'All', value: null },
    { label: 'Active', value: 'active' },
    { label: 'Inactive', value: 'inactive' },
];

const planOptions = ['basic', 'premium', 'free'];

// ── Loaders ───────────────────────────────────────────────

const loadTenants = async () => {
    try {
        loading.value = true;
        const params: TenantFilters = { ...filters.value };
        if (searchQuery.value) params.search = searchQuery.value;
        if (statusFilter.value) params.status = statusFilter.value as any;

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('expiring_soon') === 'true') {
            (params as any).expiring_soon = true;
        }

        const res = await superAdminService.getTenants(params);
        tenants.value = res.data?.data || [];
        totalRecords.value = res.data?.total || 0;
    } catch (err) {
        console.error('Failed to load tenants:', err);
    } finally {
        loading.value = false;
    }
};

const onPage = (event: any) => {
    filters.value.page = event.page + 1;
    filters.value.per_page = event.rows;
    loadTenants();
};

let searchTimeout: any = null;
watch(searchQuery, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        filters.value.page = 1;
        loadTenants();
    }, 400);
});

watch(statusFilter, () => {
    filters.value.page = 1;
    loadTenants();
});

// ── Create ────────────────────────────────────────────────

const openCreate = () => {
    createForm.value = emptyForm();
    showCreateDialog.value = true;
};

const submitCreate = async () => {
    if (!createForm.value.id || !createForm.value.name) {
        toast.add({ severity: 'warn', summary: 'Validation', detail: 'School ID and Name are required', life: 3000 });
        return;
    }
    try {
        creating.value = true;
        await superAdminService.createTenant(createForm.value);
        toast.add({ severity: 'success', summary: 'Success', detail: 'School created successfully', life: 3000 });
        showCreateDialog.value = false;
        loadTenants();
    } catch (err: any) {
        const msg = err.response?.data?.errors || err.response?.data?.message || 'Failed to create school';
        toast.add({ severity: 'error', summary: 'Error', detail: typeof msg === 'object' ? Object.values(msg).flat().join(', ') : msg, life: 5000 });
    } finally {
        creating.value = false;
    }
};

// ── Edit ──────────────────────────────────────────────────

const openEdit = (tenant: any) => {
    editingId.value = tenant.id;
    editForm.value = {
        name: tenant.name || '',
        contact_name: tenant.contact_name || '',
        contact_email: tenant.contact_email || '',
        contact_phone: tenant.contact_phone || '',
        subscription_plan: tenant.subscription_plan || 'basic',
        subscription_expires_at: tenant.subscription_expires_at ? tenant.subscription_expires_at.split('T')[0] : '',
        max_students: tenant.max_students || 0,
        notes: tenant.notes || '',
        address: tenant.address || '',
        phone: tenant.phone || '',
        email: tenant.email || '',
        admin_password: '', // Initialize empty to avoid undefined warnings and ensure it resets
        admin_password_confirmation: '',
    };
    showEditDialog.value = true;
};

const submitEdit = async () => {
    if (editForm.value.admin_password && editForm.value.admin_password !== editForm.value.admin_password_confirmation) {
        toast.add({ severity: 'error', summary: 'Validation Error', detail: 'Admin passwords do not match.', life: 5000 });
        return;
    }
    try {
        editing.value = true;
        await superAdminService.updateTenant(editingId.value, editForm.value);
        toast.add({ severity: 'success', summary: 'Success', detail: 'School updated successfully', life: 3000 });
        showEditDialog.value = false;
        loadTenants();
    } catch (err: any) {
        const msg = err.response?.data?.message || 'Failed to update';
        toast.add({ severity: 'error', summary: 'Error', detail: msg, life: 5000 });
    } finally {
        editing.value = false;
    }
};

// ── Activate / Deactivate ─────────────────────────────────

const toggleActivation = async (tenant: any) => {
    const action = tenant.is_active ? 'deactivate' : 'activate';
    confirm.require({
        message: `Are you sure you want to ${action} "${tenant.name || tenant.id}"? ${action === 'deactivate' ? 'All users from this school will lose access.' : ''}`,
        header: `${action === 'activate' ? 'Activate' : 'Deactivate'} School`,
        icon: action === 'deactivate' ? 'pi pi-exclamation-triangle' : 'pi pi-check-circle',
        acceptClass: action === 'deactivate' ? 'p-button-danger' : 'p-button-success',
        accept: async () => {
            try {
                if (tenant.is_active) {
                    await superAdminService.deactivateTenant(tenant.id);
                } else {
                    await superAdminService.activateTenant(tenant.id);
                }
                toast.add({ severity: 'success', summary: 'Success', detail: `School ${action}d successfully`, life: 3000 });
                loadTenants();
            } catch (err: any) {
                toast.add({ severity: 'error', summary: 'Error', detail: err.response?.data?.message || `Failed to ${action}`, life: 5000 });
            }
        }
    });
};

// ── Delete ────────────────────────────────────────────────

const deleteTenant = (tenant: any) => {
    confirm.require({
        message: `Are you sure you want to delete "${tenant.name || tenant.id}"? The school will be deactivated permanently.`,
        header: 'Delete School',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await superAdminService.deleteTenant(tenant.id);
                toast.add({ severity: 'success', summary: 'Deleted', detail: 'School has been deactivated', life: 3000 });
                loadTenants();
            } catch (err: any) {
                toast.add({ severity: 'error', summary: 'Error', detail: err.response?.data?.message || 'Failed to delete', life: 5000 });
            }
        }
    });
};

const formatDate = (d: string) => {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
};

onMounted(loadTenants);
</script>

<template>
    <ConfirmDialog />

    <div class="grid grid-cols-12 gap-6">
        <div class="col-span-12">
            <div class="card">
                <div class="flex flex-col md:flex-row items-center justify-between gap-4 mb-6">
                    <h5 class="m-0 font-semibold text-xl">Schools Management</h5>
                    <div class="flex items-center gap-3 flex-wrap">
                        <IconField>
                            <InputIcon class="pi pi-search" />
                            <InputText v-model="searchQuery" placeholder="Search schools..." class="w-64" />
                        </IconField>
                        <Select v-model="statusFilter" :options="statusOptions" optionLabel="label" optionValue="value" placeholder="Status" class="w-36" />
                        <Button label="New School" icon="pi pi-plus" @click="openCreate" />
                    </div>
                </div>

                <DataTable
                    :value="tenants"
                    :loading="loading"
                    :lazy="true"
                    :paginator="true"
                    :rows="filters.per_page"
                    :totalRecords="totalRecords"
                    @page="onPage"
                    responsiveLayout="scroll"
                    stripedRows
                    :rowsPerPageOptions="[10, 15, 25, 50]"
                >
                    <Column field="id" header="ID" style="min-width: 120px" />
                    <Column header="Name" style="min-width: 180px">
                        <template #body="{ data }">
                            <span class="font-semibold cursor-pointer text-primary hover:underline" @click="router.push(`/platform/schools/${data.id}`)">
                                {{ data.name || data.id }}
                            </span>
                        </template>
                    </Column>
                    <Column header="Contact" style="min-width: 180px">
                        <template #body="{ data }">
                            <div v-if="data.contact_name" class="text-sm">{{ data.contact_name }}</div>
                            <div v-if="data.contact_email" class="text-xs text-muted-color">{{ data.contact_email }}</div>
                        </template>
                    </Column>
                    <Column header="Plan" style="min-width: 100px">
                        <template #body="{ data }">
                            <Tag :value="data.subscription_plan || 'basic'" :severity="data.subscription_plan === 'premium' ? 'success' : 'info'" />
                        </template>
                    </Column>
                    <Column header="Status" style="min-width: 100px">
                        <template #body="{ data }">
                            <Tag :value="data.is_active ? 'Active' : 'Inactive'" :severity="data.is_active ? 'success' : 'danger'" />
                        </template>
                    </Column>
                    <Column header="Students" style="min-width: 90px">
                        <template #body="{ data }">{{ data.students_count ?? 0 }}</template>
                    </Column>
                    <Column header="Expires" style="min-width: 120px">
                        <template #body="{ data }">{{ formatDate(data.subscription_expires_at) }}</template>
                    </Column>
                    <Column header="Created" style="min-width: 120px">
                        <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
                    </Column>
                    <Column header="Actions" style="min-width: 200px" frozen alignFrozen="right">
                        <template #body="{ data }">
                            <div class="flex gap-2">
                                <Button icon="pi pi-eye" text rounded severity="info" @click="router.push(`/platform/schools/${data.id}`)" v-tooltip="'View'" />
                                <Button icon="pi pi-pencil" text rounded severity="secondary" @click="openEdit(data)" v-tooltip="'Edit'" />
                                <Button
                                    :icon="data.is_active ? 'pi pi-ban' : 'pi pi-check'"
                                    text rounded
                                    :severity="data.is_active ? 'warn' : 'success'"
                                    @click="toggleActivation(data)"
                                    v-tooltip="data.is_active ? 'Deactivate' : 'Activate'"
                                />
                                <Button icon="pi pi-trash" text rounded severity="danger" @click="deleteTenant(data)" v-tooltip="'Delete'" />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>
    </div>

    <!-- Create Dialog -->
    <Dialog v-model:visible="showCreateDialog" header="Create New School" :modal="true" :style="{ width: '650px' }">
        <div class="grid grid-cols-2 gap-4">
            <div class="col-span-1">
                <label class="block font-medium mb-1">School ID *</label>
                <InputText v-model="createForm.id" class="w-full" placeholder="e.g. school-oran" />
            </div>
            <div class="col-span-1">
                <label class="block font-medium mb-1">School Name *</label>
                <InputText v-model="createForm.name" class="w-full" placeholder="School name" />
            </div>
            <div class="col-span-1">
                <label class="block font-medium mb-1">Contact Name</label>
                <InputText v-model="createForm.contact_name" class="w-full" />
            </div>
            <div class="col-span-1">
                <label class="block font-medium mb-1">Contact Email</label>
                <InputText v-model="createForm.contact_email" class="w-full" type="email" />
            </div>
            <div class="col-span-1">
                <label class="block font-medium mb-1">Contact Phone</label>
                <InputText v-model="createForm.contact_phone" class="w-full" />
            </div>
            <div class="col-span-1">
                <label class="block font-medium mb-1">Plan</label>
                <Select v-model="createForm.subscription_plan" :options="planOptions" class="w-full" />
            </div>
            <div class="col-span-1">
                <label class="block font-medium mb-1">Expires At</label>
                <InputText v-model="createForm.subscription_expires_at" class="w-full" type="date" />
            </div>
            <div class="col-span-1">
                <label class="block font-medium mb-1">Max Students</label>
                <InputNumber v-model="createForm.max_students" class="w-full" :min="0" />
            </div>
            <div class="col-span-2">
                <label class="block font-medium mb-1">Notes</label>
                <Textarea v-model="createForm.notes" class="w-full" rows="2" />
            </div>

            <!-- Admin user -->
            <div class="col-span-2">
                <Divider />
                <h6 class="text-muted-color">First Admin Account (optional)</h6>
            </div>
            <div class="col-span-1">
                <label class="block font-medium mb-1">Admin Username</label>
                <InputText v-model="createForm.admin_username" class="w-full" />
            </div>
            <div class="col-span-1">
                <label class="block font-medium mb-1">Admin Password</label>
                <InputText v-model="createForm.admin_password" class="w-full" type="password" />
            </div>
        </div>

        <template #footer>
            <Button label="Cancel" icon="pi pi-times" text @click="showCreateDialog = false" />
            <Button label="Create" icon="pi pi-check" :loading="creating" @click="submitCreate" />
        </template>
    </Dialog>

    <!-- Edit Dialog -->
    <Dialog v-model:visible="showEditDialog" header="Edit School" :modal="true" :style="{ width: '600px' }">
        <div class="grid grid-cols-2 gap-4">
            <div class="col-span-1">
                <label class="block font-medium mb-1">School Name</label>
                <InputText v-model="editForm.name" class="w-full" />
            </div>
            <div class="col-span-1">
                <label class="block font-medium mb-1">Contact Name</label>
                <InputText v-model="editForm.contact_name" class="w-full" />
            </div>
            <div class="col-span-1">
                <label class="block font-medium mb-1">Contact Email</label>
                <InputText v-model="editForm.contact_email" class="w-full" type="email" />
            </div>
            <div class="col-span-1">
                <label class="block font-medium mb-1">Contact Phone</label>
                <InputText v-model="editForm.contact_phone" class="w-full" />
            </div>
            <div class="col-span-1">
                <label class="block font-medium mb-1">Plan</label>
                <Select v-model="editForm.subscription_plan" :options="planOptions" class="w-full" />
            </div>
            <div class="col-span-1">
                <label class="block font-medium mb-1">Expires At</label>
                <InputText v-model="editForm.subscription_expires_at" class="w-full" type="date" />
            </div>
            <div class="col-span-1">
                <label class="block font-medium mb-1">Max Students</label>
                <InputNumber v-model="editForm.max_students" class="w-full" :min="0" />
            </div>
            <div class="col-span-2">
                <label class="block font-medium mb-1">Notes</label>
                <Textarea v-model="editForm.notes" class="w-full" rows="2" />
            </div>

            <!-- Admin Password Reset -->
            <div class="col-span-2 mt-4">
                <Divider />
                <h6 class="text-muted-color">Reset Admin Password (optional)</h6>
                <p class="text-sm text-muted-color mb-2">Leave blank if you do not wish to change the password for the primary admin of this school.</p>
            </div>
            <div class="col-span-1">
                <label class="block font-medium mb-1">New Admin Password</label>
                <InputText v-model="editForm.admin_password" class="w-full" type="password" placeholder="Enter new password" />
            </div>
            <div class="col-span-1">
                <label class="block font-medium mb-1">Confirm Password</label>
                <InputText v-model="editForm.admin_password_confirmation" class="w-full" type="password" placeholder="Confirm new password" />
            </div>
        </div>

        <template #footer>
            <Button label="Cancel" icon="pi pi-times" text @click="showEditDialog = false" />
            <Button label="Save" icon="pi pi-check" :loading="editing" @click="submitEdit" />
        </template>
    </Dialog>
</template>
