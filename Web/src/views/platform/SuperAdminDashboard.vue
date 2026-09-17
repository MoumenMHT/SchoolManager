<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import superAdminService from '@/service/SuperAdminService';

const router = useRouter();

const loading = ref(true);
const stats = ref<any>(null);
const revenueChartData = ref<any>(null);
const tenantGrowthData = ref<any>(null);

const revenueChangeClass = computed(() => {
    if (!stats.value) return '';
    const change = stats.value.revenue?.change_percent ?? 0;
    return change >= 0 ? 'text-green-500' : 'text-red-500';
});

const revenueChangeIcon = computed(() => {
    if (!stats.value) return '';
    const change = stats.value.revenue?.change_percent ?? 0;
    return change >= 0 ? 'pi pi-arrow-up' : 'pi pi-arrow-down';
});

const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('fr-DZ', { style: 'currency', currency: 'DZD', maximumFractionDigits: 0 }).format(val);
};

const formatDate = (d: string) => {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
};

const actionLabel = (action: string) => {
    const labels: Record<string, string> = {
        created: 'Created',
        activated: 'Activated',
        deactivated: 'Deactivated',
        updated: 'Updated',
        payment_recorded: 'Payment Recorded',
        payment_status_changed: 'Payment Status Changed',
        payment_deleted: 'Payment Deleted',
        deleted_soft: 'Deleted',
    };
    return labels[action] || action;
};

const actionSeverity = (action: string) => {
    const map: Record<string, string> = {
        created: 'success',
        activated: 'success',
        deactivated: 'danger',
        deleted_soft: 'danger',
        updated: 'info',
        payment_recorded: 'success',
        payment_status_changed: 'warn',
        payment_deleted: 'danger',
    };
    return map[action] || 'secondary';
};

// Chart options
const revenueChartOptions = ref({
    plugins: { legend: { display: false } },
    scales: {
        y: { beginAtZero: true, ticks: { callback: (v: number) => formatCurrency(v) } },
        x: { grid: { display: false } }
    },
    responsive: true,
    maintainAspectRatio: false,
});

const growthChartOptions = ref({
    plugins: { legend: { display: false } },
    scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1 } },
        x: { grid: { display: false } }
    },
    responsive: true,
    maintainAspectRatio: false,
});

const loadDashboard = async () => {
    try {
        loading.value = true;
        const [statsRes, revenueRes, growthRes] = await Promise.all([
            superAdminService.getDashboardStats(),
            superAdminService.getRevenueChart(),
            superAdminService.getTenantGrowth(),
        ]);

        stats.value = statsRes;

        revenueChartData.value = {
            labels: revenueRes.map((m: any) => m.label),
            datasets: [{
                label: 'Revenue',
                data: revenueRes.map((m: any) => m.amount),
                backgroundColor: 'rgba(99, 102, 241, 0.4)',
                borderColor: '#6366f1',
                borderWidth: 2,
                borderRadius: 6,
            }]
        };

        tenantGrowthData.value = {
            labels: growthRes.map((m: any) => m.label),
            datasets: [{
                label: 'New Schools',
                data: growthRes.map((m: any) => m.count),
                borderColor: '#22c55e',
                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
            }]
        };
    } catch (err) {
        console.error('Dashboard load error:', err);
    } finally {
        loading.value = false;
    }
};

onMounted(loadDashboard);
</script>

<template>
    <div class="grid grid-cols-12 gap-6">
        <!-- Loading -->
        <div v-if="loading" class="col-span-12 text-center py-12">
            <i class="pi pi-spin pi-spinner text-5xl text-primary"></i>
            <p class="mt-4 text-muted-color text-lg">Loading platform dashboard...</p>
        </div>

        <template v-else-if="stats">
            <!-- KPI Cards -->
            <div class="col-span-12 lg:col-span-3">
                <div class="card h-full">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-muted-color font-medium">Total Schools</span>
                        <div class="flex items-center justify-center bg-blue-100 dark:bg-blue-400/10 rounded-full" style="width: 2.5rem; height: 2.5rem">
                            <i class="pi pi-building text-blue-500 text-xl"></i>
                        </div>
                    </div>
                    <div class="text-3xl font-bold text-surface-900 dark:text-surface-0">{{ stats.tenants.total }}</div>
                    <div class="mt-2 text-sm">
                        <Tag severity="success" :value="`${stats.tenants.active} active`" class="mr-2" />
                        <Tag severity="danger" :value="`${stats.tenants.inactive} inactive`" />
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-3">
                <div class="card h-full">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-muted-color font-medium">Revenue This Month</span>
                        <div class="flex items-center justify-center bg-green-100 dark:bg-green-400/10 rounded-full" style="width: 2.5rem; height: 2.5rem">
                            <i class="pi pi-dollar text-green-500 text-xl"></i>
                        </div>
                    </div>
                    <div class="text-3xl font-bold text-surface-900 dark:text-surface-0">{{ formatCurrency(stats.revenue.this_month) }}</div>
                    <div class="mt-2 text-sm" :class="revenueChangeClass">
                        <i :class="revenueChangeIcon" class="mr-1"></i>
                        {{ Math.abs(stats.revenue.change_percent) }}% vs last month
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-3">
                <div class="card h-full">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-muted-color font-medium">Total Students</span>
                        <div class="flex items-center justify-center bg-purple-100 dark:bg-purple-400/10 rounded-full" style="width: 2.5rem; height: 2.5rem">
                            <i class="pi pi-graduation-cap text-purple-500 text-xl"></i>
                        </div>
                    </div>
                    <div class="text-3xl font-bold text-surface-900 dark:text-surface-0">{{ stats.platform.total_students }}</div>
                    <div class="mt-2 text-sm text-muted-color">{{ stats.platform.total_users }} total users</div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-3">
                <div class="card h-full">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-muted-color font-medium">Pending Payments</span>
                        <div class="flex items-center justify-center bg-orange-100 dark:bg-orange-400/10 rounded-full" style="width: 2.5rem; height: 2.5rem">
                            <i class="pi pi-clock text-orange-500 text-xl"></i>
                        </div>
                    </div>
                    <div class="text-3xl font-bold text-surface-900 dark:text-surface-0">{{ formatCurrency(stats.revenue.pending) }}</div>
                    <div class="mt-2 text-sm text-muted-color">Total: {{ formatCurrency(stats.revenue.total) }}</div>
                </div>
            </div>

            <!-- Expiring soon alert -->
            <div v-if="stats.tenants.expiring_soon > 0" class="col-span-12">
                <Message severity="warn" :closable="false">
                    <i class="pi pi-exclamation-triangle mr-2"></i>
                    <strong>{{ stats.tenants.expiring_soon }} school(s)</strong> have subscriptions expiring within 30 days.
                    <Button label="View" class="ml-3 p-button-sm" @click="router.push('/platform/schools?expiring_soon=true')" />
                </Message>
            </div>

            <!-- Revenue Chart -->
            <div class="col-span-12 xl:col-span-7">
                <div class="card">
                    <div class="flex items-center justify-between mb-6">
                        <h5 class="m-0 font-semibold text-lg">Monthly Revenue</h5>
                        <Button icon="pi pi-external-link" text rounded @click="router.push('/platform/payments')" />
                    </div>
                    <div style="height: 300px">
                        <Chart type="bar" :data="revenueChartData" :options="revenueChartOptions" class="h-full" />
                    </div>
                </div>
            </div>

            <!-- Tenant Growth Chart -->
            <div class="col-span-12 xl:col-span-5">
                <div class="card">
                    <div class="flex items-center justify-between mb-6">
                        <h5 class="m-0 font-semibold text-lg">School Growth</h5>
                        <Button icon="pi pi-external-link" text rounded @click="router.push('/platform/schools')" />
                    </div>
                    <div style="height: 300px">
                        <Chart type="line" :data="tenantGrowthData" :options="growthChartOptions" class="h-full" />
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="col-span-12">
                <div class="card">
                    <div class="flex items-center justify-between mb-6">
                        <h5 class="m-0 font-semibold text-lg">Recent Activity</h5>
                    </div>
                    <DataTable :value="stats.recent_activity" :rows="10" responsiveLayout="scroll" stripedRows>
                        <Column header="Date" style="min-width: 140px">
                            <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
                        </Column>
                        <Column header="School" style="min-width: 150px">
                            <template #body="{ data }">
                                <span class="font-medium">{{ data.tenant?.name || data.tenant_id }}</span>
                            </template>
                        </Column>
                        <Column header="Action" style="min-width: 160px">
                            <template #body="{ data }">
                                <Tag :severity="actionSeverity(data.action)" :value="actionLabel(data.action)" />
                            </template>
                        </Column>
                        <Column header="By" style="min-width: 120px">
                            <template #body="{ data }">{{ data.performer?.username || '—' }}</template>
                        </Column>
                    </DataTable>
                </div>
            </div>
        </template>
    </div>
</template>
