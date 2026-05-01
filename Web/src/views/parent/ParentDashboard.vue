<script setup>
import { ref, onMounted, computed } from 'vue';
import { ParentPortalService } from '@/service/ParentPortalService';
import { useToast } from 'primevue/usetoast';

const dashboardData = ref(null);
const childrenData = ref([]);
const loading = ref(true);
const toast = useToast();

// Calculate total children based on actual student profiles
const totalChildren = computed(() => {
    return childrenData.value ? childrenData.value.length : 0;
});

// Sum up the remaining amount across all contracts
const totalOutstanding = computed(() => {
    if (!dashboardData.value) return 0;
    return dashboardData.value.reduce((sum, item) => sum + parseFloat(item.remaining_amount || 0), 0).toFixed(2);
});

// Sum up total unpaid constraints/bills
const unpaidBillsCount = computed(() => {
    if (!dashboardData.value) return 0;
    return dashboardData.value.reduce((sum, item) => sum + parseInt(item.unpaid_bills_count || 0), 0);
});

// Sum up total late bills
const lateBillsCount = computed(() => {
    if (!dashboardData.value) return 0;
    return dashboardData.value.reduce((sum, item) => sum + parseInt(item.late_bills_count || 0), 0);
});

// Get closest upcoming due date
const nextDueDate = computed(() => {
    if (!dashboardData.value) return null;
    const dates = dashboardData.value
        .map(item => item.next_due_date)
        .filter(date => date)
        .map(date => new Date(date))
        .sort((a, b) => a - b);
    return dates.length > 0 ? dates[0] : null;
});

// Extract last payments from all contracts for recent activity
const recentActivity = computed(() => {
    if (!dashboardData.value) return [];
    return dashboardData.value
        .filter(item => item.last_payment)
        .map(item => item.last_payment)
        .sort((a, b) => new Date(b.paid_date) - new Date(a.paid_date)); // Sort newest first
});

const loadDashboard = async () => {
    loading.value = true;
    try {
        const [dashRes, childRes] = await Promise.all([
            ParentPortalService.getDashboard(),
            ParentPortalService.getMyChildren()
        ]);
        dashboardData.value = dashRes;
        childrenData.value = childRes;
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load dashboard data', life: 3000 });
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    loadDashboard();
});
</script>

<template>
    <div class="grid grid-cols-12 gap-8" v-if="!loading && dashboardData">
        <div class="col-span-12 lg:col-span-6 xl:col-span-3">
            <div class="card mb-0">
                <div class="flex justify-between mb-4">
                    <div>
                        <span class="block text-muted-color font-medium mb-4">Total Children</span>
                        <div class="text-surface-900 dark:text-surface-0 font-medium text-xl">{{ totalChildren }}</div>
                    </div>
                    <div class="flex items-center justify-center bg-blue-100 dark:bg-blue-400/10 rounded-border" style="width: 2.5rem; height: 2.5rem">
                        <i class="pi pi-users text-blue-500 text-xl!"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-span-12 lg:col-span-6 xl:col-span-3">
            <div class="card mb-0">
                <div class="flex justify-between mb-4">
                    <div>
                        <span class="block text-muted-color font-medium mb-4">Outstanding Balance</span>
                        <div class="text-surface-900 dark:text-surface-0 font-medium text-xl">{{ totalOutstanding }} DZD</div>
                    </div>
                    <div class="flex items-center justify-center bg-orange-100 dark:bg-orange-400/10 rounded-border" style="width: 2.5rem; height: 2.5rem">
                        <i class="pi pi-wallet text-orange-500 text-xl!"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-span-12 lg:col-span-6 xl:col-span-3">
            <div class="card mb-0">
                <div class="flex justify-between mb-4">
                    <div>
                        <span class="block text-muted-color font-medium mb-4">Unpaid Bills</span>
                        <div class="text-surface-900 dark:text-surface-0 font-medium text-xl">
                            {{ unpaidBillsCount }}
                            <span v-if="lateBillsCount > 0" class="text-red-500 text-sm ml-2">({{ lateBillsCount }} Late)</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-center bg-red-100 dark:bg-red-400/10 rounded-border" style="width: 2.5rem; height: 2.5rem">
                        <i class="pi pi-file-excel text-red-500 text-xl!"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-span-12 lg:col-span-6 xl:col-span-3">
            <div class="card mb-0">
                <div class="flex justify-between mb-4">
                    <div>
                        <span class="block text-muted-color font-medium mb-4">Next Payment Due</span>
                        <div class="text-surface-900 dark:text-surface-0 font-medium text-xl">
                            {{ nextDueDate ? nextDueDate.toLocaleDateString() : 'No pending payments' }}
                        </div>
                    </div>
                    <div class="flex items-center justify-center bg-green-100 dark:bg-green-400/10 rounded-border" style="width: 2.5rem; height: 2.5rem">
                        <i class="pi pi-calendar text-green-500 text-xl!"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-span-12 xl:col-span-8">
            <div class="card">
                <h5 class="text-surface-900 dark:text-surface-0 font-semibold mb-4">Recent Payments</h5>
                <p class="text-muted-color" v-if="recentActivity.length === 0">No recent activity found.</p>
                <ul class="p-0 mx-0 mt-0 mb-4 list-none" v-else>
                    <li v-for="(payment, i) in recentActivity" :key="i" class="flex items-center py-3 border-b border-surface">
                        <span class="text-surface-900 dark:text-surface-0 leading-normal">
                            <span class="font-semibold text-primary block">{{ payment.amount }} DZD</span>
                            <span class="text-muted-color text-sm">Paid on {{ new Date(payment.paid_date).toLocaleDateString() }} 
                            via {{ payment.payment_type.replace('_', ' ') }}</span>
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="col-span-12 xl:col-span-4">
            <div class="card">
                <h5 class="text-surface-900 dark:text-surface-0 font-semibold mb-4">Quick Actions</h5>
                <div class="flex flex-col gap-3">
                    <Button label="View Children" icon="pi pi-users" class="w-full p-button-outlined" @click="$router.push('/parent/children')" />
                    <Button label="View Contracts & Bills" icon="pi pi-wallet" class="w-full p-button-outlined p-button-secondary" @click="$router.push('/parent/finances')" />
                    <Button label="Contact Administration" icon="pi pi-phone" class="w-full p-button-outlined p-button-info" />
                </div>
            </div>
        </div>
    </div>
    
    <div v-else-if="loading" class="flex justify-center items-center py-8">
        <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
    </div>
</template>
