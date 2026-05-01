<script setup>
import { ref, onMounted } from 'vue';
import { ParentPortalService } from '@/service/ParentPortalService';
import { useToast } from 'primevue/usetoast';

const toast = useToast();
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
        toast.add({ severity: 'error', summary: 'Error', detail: 'Could not load financial records.', life: 3000 });
    } finally {
        loading.value = false;
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
                <h5 class="text-surface-900 dark:text-surface-0 font-semibold mb-6">My Financial Overview</h5>
                
                <div v-if="loading" class="flex justify-center items-center py-8">
                    <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
                </div>
                
                <TabView v-else>
                    <TabPanel header="Contracts">
                        <DataTable :value="contracts" :paginator="true" :rows="10" showGridlines>
                            <Column field="academic_year" header="Academic Year" sortable></Column>
                            <Column field="student.first_name" header="Student"></Column>
                            <Column field="total_amount" header="Total Amount">
                                <template #body="slotProps">
                                    <span class="font-bold text-primary">{{ slotProps.data.total_amount }} DZD</span>
                                </template>
                            </Column>
                            <Column field="status" header="Status">
                                <template #body="slotProps">
                                    <Tag :value="slotProps.data.status" :severity="slotProps.data.status === 'active' ? 'success' : 'warn'"></Tag>
                                </template>
                            </Column>
                            <Column header="Actions">
                                <template #body="slotProps">
                                    <Button icon="pi pi-eye" class="p-button-rounded p-button-info mr-2 p-button-text" @click="$router.push(`/parent/contracts/${slotProps.data.id}`)" />
                                </template>
                            </Column>
                            <template #empty>
                                <div class="text-center p-8 bg-surface-50 dark:bg-surface-800 rounded-border mt-4">
                                    <p class="text-muted-color">No contracts found.</p>
                                </div>
                            </template>
                        </DataTable>
                    </TabPanel>
                    
                    <TabPanel header="Pending Bills">
                        <DataTable :value="bills" :paginator="true" :rows="10" showGridlines>
                            <Column field="title" header="Description"></Column>
                            <Column field="due_date" header="Due Date" sortable></Column>
                            <Column field="amount" header="Amount">
                                <template #body="slotProps">
                                    <span class="font-bold text-primary">{{ slotProps.data.amount }} DZD</span>
                                </template>
                            </Column>
                            <Column field="status" header="Status">
                                <template #body="slotProps">
                                    <Tag :value="slotProps.data.status" :severity="slotProps.data.status === 'unpaid' ? 'danger' : 'success'"></Tag>
                                </template>
                            </Column>
                            <template #empty>
                                <div class="text-center p-8 bg-surface-50 dark:bg-surface-800 rounded-border mt-4">
                                    <i class="pi pi-check-circle text-4xl mb-4 text-green-500"></i>
                                    <p class="text-surface-900 dark:text-surface-0">No pending bills found. You are all caught up!</p>
                                </div>
                            </template>
                        </DataTable>
                    </TabPanel>
                </TabView>
                
            </div>
        </div>
    </div>
</template>
