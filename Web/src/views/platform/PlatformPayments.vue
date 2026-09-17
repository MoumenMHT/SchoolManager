<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import superAdminService from '@/service/SuperAdminService';
import type { RecordPaymentPayload, PaymentFilters } from '@/service/SuperAdminService';

const toast = useToast();
const confirm = useConfirm();

// ── State ─────────────────────────────────────────────────
const payments = ref<any[]>([]);
const totalRecords = ref(0);
const loading = ref(false);
const summary = ref<any>(null);
const filters = ref<PaymentFilters>({ per_page: 15, page: 1, sort_by: 'payment_date', sort_dir: 'desc' });

const statusFilter = ref<string | null>(null);
const methodFilter = ref<string | null>(null);

// Dialogs
const showCreateDialog = ref(false);
const creating = ref(false);

const emptyPayment = (): RecordPaymentPayload => ({
    tenant_id: '', amount: 0, currency: 'DZD', status: 'completed',
    payment_method: 'cash', payment_date: '', period_start: '', period_end: '',
    reference: '', notes: '',
});
const paymentForm = ref<RecordPaymentPayload>(emptyPayment());

const statusOptions = [
    { label: 'All', value: null },
    { label: 'Completed', value: 'completed' },
    { label: 'Pending', value: 'pending' },
    { label: 'Failed', value: 'failed' },
    { label: 'Refunded', value: 'refunded' },
];

const methodOptions = [
    { label: 'All', value: null },
    { label: 'Cash', value: 'cash' },
    { label: 'Bank Transfer', value: 'bank_transfer' },
    { label: 'Check', value: 'check' },
    { label: 'Online', value: 'online' },
];

const paymentMethodLabels: Record<string, string> = {
    cash: 'Cash', bank_transfer: 'Bank Transfer', check: 'Check', online: 'Online',
};

const statusSeverity: Record<string, string> = {
    completed: 'success', pending: 'warn', failed: 'danger', refunded: 'info',
};

// ── Loaders ───────────────────────────────────────────────

const loadPayments = async () => {
    try {
        loading.value = true;
        const params: PaymentFilters = { ...filters.value };
        if (statusFilter.value) params.status = statusFilter.value;
        if (methodFilter.value) params.payment_method = methodFilter.value;

        const res = await superAdminService.getPayments(params);
        payments.value = res.data?.data || [];
        totalRecords.value = res.data?.total || 0;
    } catch (err) {
        console.error('Failed to load payments:', err);
    } finally {
        loading.value = false;
    }
};

const loadSummary = async () => {
    try {
        summary.value = await superAdminService.getPaymentSummary();
    } catch (err) {
        console.error('Failed to load summary:', err);
    }
};

const onPage = (event: any) => {
    filters.value.page = event.page + 1;
    filters.value.per_page = event.rows;
    loadPayments();
};

watch([statusFilter, methodFilter], () => {
    filters.value.page = 1;
    loadPayments();
});

// ── Create ────────────────────────────────────────────────

const openCreate = () => {
    paymentForm.value = emptyPayment();
    showCreateDialog.value = true;
};

const submitPayment = async () => {
    if (!paymentForm.value.tenant_id || !paymentForm.value.amount || !paymentForm.value.payment_date) {
        toast.add({ severity: 'warn', summary: 'Validation', detail: 'School, amount, and date are required', life: 3000 });
        return;
    }
    try {
        creating.value = true;
        await superAdminService.recordPayment(paymentForm.value);
        toast.add({ severity: 'success', summary: 'Success', detail: 'Payment recorded successfully', life: 3000 });
        showCreateDialog.value = false;
        loadPayments();
        loadSummary();
    } catch (err: any) {
        const msg = err.response?.data?.errors || err.response?.data?.message || 'Failed to record payment';
        toast.add({ severity: 'error', summary: 'Error', detail: typeof msg === 'object' ? Object.values(msg).flat().join(', ') : msg, life: 5000 });
    } finally {
        creating.value = false;
    }
};

// ── Delete ────────────────────────────────────────────────

const deletePayment = (payment: any) => {
    confirm.require({
        message: `Delete payment #${payment.id} of ${formatCurrency(payment.amount)}?`,
        header: 'Delete Payment',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await superAdminService.deletePayment(payment.id);
                toast.add({ severity: 'success', summary: 'Deleted', detail: 'Payment deleted', life: 3000 });
                loadPayments();
                loadSummary();
            } catch (err: any) {
                toast.add({ severity: 'error', summary: 'Error', detail: err.response?.data?.message || 'Failed to delete', life: 5000 });
            }
        }
    });
};

const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('fr-DZ', { style: 'currency', currency: 'DZD', maximumFractionDigits: 0 }).format(val);
};

const formatDate = (d: string) => {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
};

onMounted(() => {
    loadPayments();
    loadSummary();
});
</script>

<template>
    <ConfirmDialog />

    <div class="grid grid-cols-12 gap-6">
        <!-- Summary Cards -->
        <template v-if="summary">
            <div class="col-span-12 md:col-span-4">
                <div class="card text-center">
                    <div class="text-muted-color font-medium mb-2">Total Collected</div>
                    <div class="text-2xl font-bold text-green-500">{{ formatCurrency(summary.total_collected) }}</div>
                </div>
            </div>
            <div class="col-span-12 md:col-span-4">
                <div class="card text-center">
                    <div class="text-muted-color font-medium mb-2">Pending</div>
                    <div class="text-2xl font-bold text-orange-500">{{ formatCurrency(summary.total_pending) }}</div>
                </div>
            </div>
            <div class="col-span-12 md:col-span-4">
                <div class="card text-center">
                    <div class="text-muted-color font-medium mb-2">This Month</div>
                    <div class="text-2xl font-bold text-blue-500">{{ formatCurrency(summary.this_month) }}</div>
                </div>
            </div>
        </template>

        <!-- Payments Table -->
        <div class="col-span-12">
            <div class="card">
                <div class="flex flex-col md:flex-row items-center justify-between gap-4 mb-6">
                    <h5 class="m-0 font-semibold text-xl">Platform Payments</h5>
                    <div class="flex items-center gap-3 flex-wrap">
                        <Select v-model="statusFilter" :options="statusOptions" optionLabel="label" optionValue="value" placeholder="Status" class="w-36" />
                        <Select v-model="methodFilter" :options="methodOptions" optionLabel="label" optionValue="value" placeholder="Method" class="w-40" />
                        <Button label="Record Payment" icon="pi pi-plus" @click="openCreate" />
                    </div>
                </div>

                <DataTable
                    :value="payments"
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
                    <Column field="id" header="#" style="min-width: 60px" />
                    <Column header="School" style="min-width: 160px">
                        <template #body="{ data }">
                            <span class="font-medium">{{ data.tenant?.name || data.tenant_id }}</span>
                        </template>
                    </Column>
                    <Column header="Amount" style="min-width: 120px">
                        <template #body="{ data }">
                            <span class="font-semibold">{{ formatCurrency(data.amount) }}</span>
                        </template>
                    </Column>
                    <Column header="Status" style="min-width: 100px">
                        <template #body="{ data }">
                            <Tag :value="data.status" :severity="statusSeverity[data.status] || 'secondary'" />
                        </template>
                    </Column>
                    <Column header="Method" style="min-width: 120px">
                        <template #body="{ data }">{{ paymentMethodLabels[data.payment_method] || data.payment_method }}</template>
                    </Column>
                    <Column header="Date" style="min-width: 120px">
                        <template #body="{ data }">{{ formatDate(data.payment_date) }}</template>
                    </Column>
                    <Column header="Period" style="min-width: 200px">
                        <template #body="{ data }">{{ formatDate(data.period_start) }} → {{ formatDate(data.period_end) }}</template>
                    </Column>
                    <Column header="Reference" style="min-width: 120px">
                        <template #body="{ data }">{{ data.reference || '—' }}</template>
                    </Column>
                    <Column header="By" style="min-width: 100px">
                        <template #body="{ data }">{{ data.recorded_by_user?.username || '—' }}</template>
                    </Column>
                    <Column header="Actions" style="min-width: 100px">
                        <template #body="{ data }">
                            <Button icon="pi pi-trash" text rounded severity="danger" @click="deletePayment(data)" v-tooltip="'Delete'" />
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>
    </div>

    <!-- Create Payment Dialog -->
    <Dialog v-model:visible="showCreateDialog" header="Record Payment" :modal="true" :style="{ width: '550px' }">
        <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
                <label class="block font-medium mb-1">School ID *</label>
                <InputText v-model="paymentForm.tenant_id" class="w-full" placeholder="e.g. school-oran" />
            </div>
            <div class="col-span-1">
                <label class="block font-medium mb-1">Amount *</label>
                <InputNumber v-model="paymentForm.amount" class="w-full" :min="0" mode="currency" currency="DZD" locale="fr-DZ" />
            </div>
            <div class="col-span-1">
                <label class="block font-medium mb-1">Status *</label>
                <Select v-model="paymentForm.status" :options="['pending', 'completed', 'failed', 'refunded']" class="w-full" />
            </div>
            <div class="col-span-1">
                <label class="block font-medium mb-1">Method *</label>
                <Select v-model="paymentForm.payment_method" :options="['cash', 'bank_transfer', 'check', 'online']" class="w-full" />
            </div>
            <div class="col-span-1">
                <label class="block font-medium mb-1">Payment Date *</label>
                <InputText v-model="paymentForm.payment_date" class="w-full" type="date" />
            </div>
            <div class="col-span-1">
                <label class="block font-medium mb-1">Period Start *</label>
                <InputText v-model="paymentForm.period_start" class="w-full" type="date" />
            </div>
            <div class="col-span-1">
                <label class="block font-medium mb-1">Period End *</label>
                <InputText v-model="paymentForm.period_end" class="w-full" type="date" />
            </div>
            <div class="col-span-1">
                <label class="block font-medium mb-1">Reference</label>
                <InputText v-model="paymentForm.reference" class="w-full" />
            </div>
            <div class="col-span-2">
                <label class="block font-medium mb-1">Notes</label>
                <Textarea v-model="paymentForm.notes" class="w-full" rows="2" />
            </div>
        </div>

        <template #footer>
            <Button label="Cancel" icon="pi pi-times" text @click="showCreateDialog = false" />
            <Button label="Record" icon="pi pi-check" :loading="creating" @click="submitPayment" />
        </template>
    </Dialog>
</template>
