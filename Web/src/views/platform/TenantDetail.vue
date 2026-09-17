<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import superAdminService from '@/service/SuperAdminService';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const confirm = useConfirm();

const tenantId = computed(() => route.params.id as string);
const tenant = ref<any>(null);
const stats = ref<any>(null);
const recentActivity = ref<any[]>([]);
const loading = ref(true);

const loadTenant = async () => {
    try {
        loading.value = true;
        const res = await superAdminService.getTenant(tenantId.value);
        tenant.value = res.data;
        stats.value = res.stats;
        recentActivity.value = res.recent_activity || [];
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load school details', life: 5000 });
        router.push('/platform/schools');
    } finally {
        loading.value = false;
    }
};

const toggleActivation = () => {
    if (!tenant.value) return;
    const action = tenant.value.is_active ? 'deactivate' : 'activate';
    confirm.require({
        message: `Are you sure you want to ${action} "${tenant.value.name || tenant.value.id}"?`,
        header: `${action === 'activate' ? 'Activate' : 'Deactivate'} School`,
        icon: action === 'deactivate' ? 'pi pi-exclamation-triangle' : 'pi pi-check-circle',
        acceptClass: action === 'deactivate' ? 'p-button-danger' : 'p-button-success',
        accept: async () => {
            try {
                if (tenant.value.is_active) {
                    await superAdminService.deactivateTenant(tenantId.value);
                } else {
                    await superAdminService.activateTenant(tenantId.value);
                }
                toast.add({ severity: 'success', summary: 'Success', detail: `School ${action}d`, life: 3000 });
                loadTenant();
            } catch (err: any) {
                toast.add({ severity: 'error', summary: 'Error', detail: err.response?.data?.message || `Failed to ${action}`, life: 5000 });
            }
        }
    });
};

const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('fr-DZ', { style: 'currency', currency: 'DZD', maximumFractionDigits: 0 }).format(val || 0);
};

const formatDate = (d: string) => {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatDateTime = (d: string) => {
    if (!d) return '—';
    return new Date(d).toLocaleString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const actionLabel = (action: string) => {
    const labels: Record<string, string> = {
        created: 'Created', activated: 'Activated', deactivated: 'Deactivated',
        updated: 'Updated', payment_recorded: 'Payment', payment_status_changed: 'Status Change',
        payment_deleted: 'Payment Deleted', deleted_soft: 'Deleted',
    };
    return labels[action] || action;
};

const actionSeverity = (action: string) => {
    const map: Record<string, string> = {
        created: 'success', activated: 'success', deactivated: 'danger',
        deleted_soft: 'danger', updated: 'info', payment_recorded: 'success',
        payment_status_changed: 'warn', payment_deleted: 'danger',
    };
    return map[action] || 'secondary';
};

onMounted(loadTenant);
</script>

<template>
    <ConfirmDialog />

    <div class="grid grid-cols-12 gap-6">
        <div v-if="loading" class="col-span-12 text-center py-12">
            <i class="pi pi-spin pi-spinner text-5xl text-primary"></i>
        </div>

        <template v-else-if="tenant">
            <!-- Header -->
            <div class="col-span-12">
                <div class="card">
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-3 mb-2">
                                <Button icon="pi pi-arrow-left" text rounded @click="router.push('/platform/schools')" />
                                <h3 class="m-0 font-bold text-2xl">{{ tenant.name || tenant.id }}</h3>
                                <Tag :value="tenant.is_active ? 'Active' : 'Inactive'" :severity="tenant.is_active ? 'success' : 'danger'" />
                                <Tag :value="tenant.subscription_plan || 'basic'" severity="info" />
                            </div>
                            <p class="text-muted-color m-0">ID: {{ tenant.id }} • Created: {{ formatDate(tenant.created_at) }}</p>
                        </div>
                        <div class="flex gap-2">
                            <Button
                                :label="tenant.is_active ? 'Deactivate' : 'Activate'"
                                :icon="tenant.is_active ? 'pi pi-ban' : 'pi pi-check'"
                                :severity="tenant.is_active ? 'danger' : 'success'"
                                @click="toggleActivation"
                            />
                            <Button label="Edit" icon="pi pi-pencil" severity="secondary" @click="router.push(`/platform/schools?edit=${tenant.id}`)" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="col-span-12 md:col-span-3">
                <div class="card text-center">
                    <div class="text-muted-color font-medium mb-2">Users</div>
                    <div class="text-2xl font-bold text-blue-500">{{ stats?.users_count ?? 0 }}</div>
                </div>
            </div>
            <div class="col-span-12 md:col-span-3">
                <div class="card text-center">
                    <div class="text-muted-color font-medium mb-2">Students</div>
                    <div class="text-2xl font-bold text-purple-500">{{ stats?.students_count ?? 0 }}</div>
                </div>
            </div>
            <div class="col-span-12 md:col-span-3">
                <div class="card text-center">
                    <div class="text-muted-color font-medium mb-2">Total Paid</div>
                    <div class="text-2xl font-bold text-green-500">{{ formatCurrency(stats?.total_payments) }}</div>
                </div>
            </div>
            <div class="col-span-12 md:col-span-3">
                <div class="card text-center">
                    <div class="text-muted-color font-medium mb-2">Pending</div>
                    <div class="text-2xl font-bold text-orange-500">{{ formatCurrency(stats?.pending_payments) }}</div>
                </div>
            </div>

            <!-- Details -->
            <div class="col-span-12 lg:col-span-6">
                <div class="card">
                    <h5 class="font-semibold text-lg mb-4">Contact Information</h5>
                    <div class="grid grid-cols-2 gap-3">
                        <div><span class="text-muted-color">Name:</span> {{ tenant.contact_name || '—' }}</div>
                        <div><span class="text-muted-color">Email:</span> {{ tenant.contact_email || '—' }}</div>
                        <div><span class="text-muted-color">Phone:</span> {{ tenant.contact_phone || '—' }}</div>
                        <div><span class="text-muted-color">Address:</span> {{ tenant.address || '—' }}</div>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-6">
                <div class="card">
                    <h5 class="font-semibold text-lg mb-4">Subscription</h5>
                    <div class="grid grid-cols-2 gap-3">
                        <div><span class="text-muted-color">Plan:</span> {{ tenant.subscription_plan || 'basic' }}</div>
                        <div><span class="text-muted-color">Expires:</span> {{ formatDate(tenant.subscription_expires_at) }}</div>
                        <div><span class="text-muted-color">Max Students:</span> {{ tenant.max_students || 'Unlimited' }}</div>
                        <div><span class="text-muted-color">Teachers:</span> {{ stats?.teachers_count ?? 0 }}</div>
                        <div><span class="text-muted-color">Last Activated:</span> {{ formatDateTime(tenant.last_activation_at) }}</div>
                        <div><span class="text-muted-color">Last Deactivated:</span> {{ formatDateTime(tenant.last_deactivation_at) }}</div>
                    </div>
                    <div v-if="tenant.notes" class="mt-4">
                        <span class="text-muted-color">Notes:</span>
                        <p class="mt-1">{{ tenant.notes }}</p>
                    </div>
                </div>
            </div>

            <!-- Last Payment -->
            <div v-if="stats?.last_payment" class="col-span-12 lg:col-span-6">
                <div class="card">
                    <h5 class="font-semibold text-lg mb-4">Last Payment</h5>
                    <div class="grid grid-cols-2 gap-3">
                        <div><span class="text-muted-color">Amount:</span> {{ formatCurrency(stats.last_payment.amount) }}</div>
                        <div><span class="text-muted-color">Date:</span> {{ formatDate(stats.last_payment.payment_date) }}</div>
                        <div><span class="text-muted-color">Method:</span> {{ stats.last_payment.payment_method }}</div>
                        <div><span class="text-muted-color">Status:</span>
                            <Tag :value="stats.last_payment.status" :severity="stats.last_payment.status === 'completed' ? 'success' : 'warn'" class="ml-2" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activity Log -->
            <div class="col-span-12" :class="{ 'lg:col-span-6': stats?.last_payment }">
                <div class="card">
                    <h5 class="font-semibold text-lg mb-4">Recent Activity</h5>
                    <div v-if="recentActivity.length === 0" class="text-muted-color text-center py-4">No activity yet</div>
                    <div v-else class="flex flex-col gap-3">
                        <div v-for="log in recentActivity" :key="log.id" class="flex items-center gap-3 p-3 rounded-lg bg-surface-50 dark:bg-surface-800">
                            <Tag :value="actionLabel(log.action)" :severity="actionSeverity(log.action)" />
                            <span class="flex-1 text-sm">{{ log.performer?.username || 'System' }}</span>
                            <span class="text-xs text-muted-color">{{ formatDateTime(log.created_at) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
