<script setup>
import { ref, onMounted } from 'vue';
import { ParentPortalService } from '@/service/ParentPortalService';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';

const toast = useToast();
const { t } = useI18n();
const contracts = ref([]);
const bills = ref([]);
const loading = ref(true);

const loadFinances = async () => {
    loading.value = true;
    try {
        const [contractsData, billsData] = await Promise.all([
            ParentPortalService.getContracts(),
            ParentPortalService.getBills()
        ]);
        
        contracts.value = contractsData;
        bills.value = billsData;
    } catch (error) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: t('finances.load_error'), life: 3000 });
    } finally {
        loading.value = false;
    }
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString();
};

const getBillStatusSeverity = (status) => {
    switch (status) {
        case 'paid': return 'success';
        case 'partial': return 'info';
        case 'late': return 'danger';
        case 'unpaid': return 'warn';
        default: return 'info';
    }
};

onMounted(() => {
    loadFinances();
});
</script>

<template>
    <div class="grid grid-cols-12 gap-8">
        <div class="col-span-12">
            <div class="card">
                <h5 class="text-surface-900 dark:text-surface-0 font-semibold mb-6">{{ $t('finances.title') }}</h5>
                
                <div v-if="loading" class="flex justify-center items-center py-8">
                    <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
                </div>
                
                <TabView v-else>
                    <TabPanel :header="$t('finances.contracts_tab')">
                        <DataTable :value="contracts" :paginator="true" :rows="10" showGridlines>
                            <Column field="academic_year" :header="$t('finances.academic_year_col')" sortable></Column>
                            <Column field="total_fees" :header="$t('finances.total_amount_col')">
                                <template #body="slotProps">
                                    <span class="font-bold text-primary">{{ slotProps.data.total_fees }} {{ $t('finances.currency_dzd') }}</span>
                                </template>
                            </Column>
                            <Column field="status" :header="$t('finances.status_col')">
                                <template #body="slotProps">
                                    <Tag :value="$t('common.' + slotProps.data.status)" :severity="slotProps.data.status === 'active' ? 'success' : 'warn'"></Tag>
                                </template>
                            </Column>
                            <template #empty>
                                <div class="text-center p-8 bg-surface-50 dark:bg-surface-800 rounded-border mt-4">
                                    <p class="text-muted-color">{{ $t('finances.no_contracts') }}</p>
                                </div>
                            </template>
                        </DataTable>
                    </TabPanel>
                    
                    <TabPanel :header="$t('finances.pending_bills_tab')">
                        <DataTable :value="bills" :paginator="true" :rows="10" showGridlines>
                            <Column field="month_year" :header="$t('finances.description_col')">
                                <template #body="slotProps">
                                    {{ slotProps.data.month_year }}
                                </template>
                            </Column>
                            <Column field="due_date" :header="$t('finances.due_date_col')" sortable>
                                <template #body="slotProps">
                                    {{ formatDate(slotProps.data.due_date) }}
                                </template>
                            </Column>
                            <Column field="amount_due" :header="$t('finances.amount_col')">
                                <template #body="slotProps">
                                    <span class="font-bold text-primary">{{ slotProps.data.amount_due }} {{ $t('finances.currency_dzd') }}</span>
                                </template>
                            </Column>
                            <Column field="status" :header="$t('finances.status_col')">
                                <template #body="slotProps">
                                    <Tag :value="$t('dashboard.bill_status_' + slotProps.data.status)" :severity="getBillStatusSeverity(slotProps.data.status)"></Tag>
                                </template>
                            </Column>
                            <template #empty>
                                <div class="text-center p-8 bg-surface-50 dark:bg-surface-800 rounded-border mt-4">
                                    <i class="pi pi-check-circle text-4xl mb-4 text-green-500"></i>
                                    <p class="text-surface-900 dark:text-surface-0">{{ $t('finances.no_bills') }}</p>
                                </div>
                            </template>
                        </DataTable>
                    </TabPanel>
                </TabView>
                
            </div>
        </div>
    </div>
</template>
