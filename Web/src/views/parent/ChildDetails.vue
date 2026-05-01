<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { ParentPortalService } from '@/service/ParentPortalService';
import { useToast } from 'primevue/usetoast';

const route = useRoute();
const toast = useToast();

const studentId = ref(route.params.id);
const loading = ref(true);

const schedule = ref([]);
const attendance = ref([]);
const grades = ref([]);
const payments = ref([]);

const loadData = async () => {
    loading.value = true;
    try {
        const [schData, attData, grdData, payData] = await Promise.all([
            ParentPortalService.getChildSchedule(studentId.value),
            ParentPortalService.getChildAttendances(studentId.value),
            ParentPortalService.getChildGrades(studentId.value),
            ParentPortalService.getChildPayments(studentId.value)
        ]);
        
        schedule.value = schData.data || schData;
        attendance.value = attData.data || attData;
        grades.value = grdData.data || grdData;
        payments.value = payData.data || payData;
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error loading data', detail: 'Could not load details for this student.', life: 3000 });
    } finally {
        loading.value = false;
    }
};

const downloadReportCard = async () => {
    try {
        const blob = await ParentPortalService.getChildReportCard(studentId.value);
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `ReportCard_Student_${studentId.value}.pdf`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
    } catch(err) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to download report card.', life: 3000 });
    }
};

onMounted(() => {
    loadData();
});
</script>

<template>
    <div class="card" v-if="!loading">
        <div class="flex justify-between items-center mb-6">
            <h5 class="text-surface-900 dark:text-surface-0 font-semibold m-0">Student Details</h5>
        </div>
        
        <TabView>
            <TabPanel header="Schedule">
                <div v-if="schedule && schedule.length > 0">
                    <DataTable :value="schedule" responsiveLayout="scroll" showGridlines>
                        <Column field="day" header="Day" sortable></Column>
                        <Column field="room.name" header="Room"></Column>
                        <Column field="subject.name" header="Subject"></Column>
                        <Column field="teacher.first_name" header="Teacher"></Column>
                        <Column header="Time">
                            <template #body="slotProps">
                                {{ slotProps.data.start_time }} - {{ slotProps.data.end_time }}
                            </template>
                        </Column>
                    </DataTable>
                </div>
                <div v-else class="text-center p-8 bg-surface-50 dark:bg-surface-800 rounded-border mt-4">
                    <p class="text-muted-color">No schedule specific to this student found.</p>
                </div>
            </TabPanel>
            <TabPanel header="Attendance">
                <DataTable :value="attendance" responsiveLayout="scroll" :paginator="true" :rows="10">
                    <Column field="date" header="Date"></Column>
                    <Column field="status" header="Status">
                        <template #body="slotProps">
                            <Tag :severity="slotProps.data.status === 'present' ? 'success' : (slotProps.data.status === 'absent' ? 'danger' : 'warn')" :value="slotProps.data.status.toUpperCase()"></Tag>
                        </template>
                    </Column>
                    <Column field="notes" header="Notes"></Column>
                </DataTable>
            </TabPanel>
            <TabPanel header="Grades">
                <div class="mb-4">
                    <Button label="Download Report Card" icon="pi pi-download" @click="downloadReportCard" class="p-button-sm p-button-info" />
                </div>
                <DataTable :value="grades" responsiveLayout="scroll" :paginator="true" :rows="10">
                    <Column field="subject" header="Subject"></Column>
                    <Column field="term" header="Term"></Column>
                    <Column field="type" header="Type"></Column>
                    <Column field="score" header="Grade"></Column>
                    <Column field="notes" header="Feedback"></Column>
                </DataTable>
            </TabPanel>
            <TabPanel header="Payments">
                <DataTable :value="payments" responsiveLayout="scroll" :paginator="true" :rows="10">
                    <Column field="payment_date" header="Date"></Column>
                    <Column field="amount" header="Amount">
                        <template #body="slotProps">
                            {{ slotProps.data.amount }} DZD
                        </template>
                    </Column>
                    <Column field="payment_method" header="Method"></Column>
                    <Column field="status" header="Status">
                        <template #body="slotProps">
                            <Tag :value="slotProps.data.status" :severity="slotProps.data.status === 'completed' ? 'success' : 'warn'"></Tag>
                        </template>
                    </Column>
                </DataTable>
            </TabPanel>
        </TabView>
    </div>
    
    <div v-else class="card flex justify-center items-center py-12">
        <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
    </div>
</template>
