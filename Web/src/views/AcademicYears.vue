<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import apiService from '@/service/ApiService';
import { FilterMatchMode } from '@primevue/core/api';

const toast = useToast();
const academicYears = ref([]);
const loading = ref(true);
const dialogVisible = ref(false);
const submitted = ref(false);
const isEditing = ref(false);
const saving = ref(false);

const currentYear = ref({
    name: '',
    start_date: null,
    end_date: null,
    is_current: false
});

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS }
});

const fetchAcademicYears = async () => {
    loading.value = true;
    try {
        const response = await apiService.get('/academic-years');
        academicYears.value = response;
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to fetch academic years', life: 3000 });
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchAcademicYears();
});

const openNew = () => {
    currentYear.value = {
        name: '',
        start_date: null,
        end_date: null,
        is_current: false
    };
    submitted.value = false;
    isEditing.value = false;
    dialogVisible.value = true;
};

const editYear = (year) => {
    currentYear.value = { ...year, start_date: year.start_date ? new Date(year.start_date) : null, end_date: year.end_date ? new Date(year.end_date) : null };
    submitted.value = false;
    isEditing.value = true;
    dialogVisible.value = true;
};

const hideDialog = () => {
    dialogVisible.value = false;
    submitted.value = false;
};

const saveYear = async () => {
    submitted.value = true;
    
    if (currentYear.value.name.trim()) {
        saving.value = true;
        try {
            const payload = {
                ...currentYear.value,
                start_date: currentYear.value.start_date ? new Date(currentYear.value.start_date).toISOString().split('T')[0] : null,
                end_date: currentYear.value.end_date ? new Date(currentYear.value.end_date).toISOString().split('T')[0] : null
            };

            if (isEditing.value) {
                await apiService.put(`/academic-years/${currentYear.value.id}`, payload);
                toast.add({ severity: 'success', summary: 'Successful', detail: 'Academic Year Updated', life: 3000 });
            } else {
                await apiService.post('/academic-years', payload);
                toast.add({ severity: 'success', summary: 'Successful', detail: 'Academic Year Created', life: 3000 });
            }
            hideDialog();
            fetchAcademicYears();
        } catch (error) {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to save academic year', life: 3000 });
        } finally {
            saving.value = false;
        }
    }
};

const deleteYear = async (year) => {
    try {
        await apiService.delete(`/academic-years/${year.id}`);
        toast.add({ severity: 'success', summary: 'Successful', detail: 'Academic Year Deleted', life: 3000 });
        fetchAcademicYears();
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete academic year', life: 3000 });
    }
};

const confirmDeleteYear = (year) => {
    if (confirm(`Are you sure you want to delete ${year.name}?`)) {
        deleteYear(year);
    }
};

const setAsCurrent = async (year) => {
    try {
        await apiService.put(`/academic-years/${year.id}/current`);
        toast.add({ severity: 'success', summary: 'Successful', detail: 'Academic Year set as Current', life: 3000 });
        fetchAcademicYears();
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to update current status', life: 3000 });
    }
};
</script>

<template>
    <div class="card">
        <h5>Academic Years</h5>
        
        <Toolbar class="mb-4">
            <template #start>
                <Button label="New" icon="pi pi-plus" class="mr-2" @click="openNew" />
            </template>
        </Toolbar>

        <DataTable :value="academicYears" :loading="loading" dataKey="id" :filters="filters" filterDisplay="menu" :globalFilterFields="['name']" responsiveLayout="scroll">
            <template #header>
                <div class="flex justify-between items-center">
                    <span class="p-input-icon-left">
                        <i class="pi pi-search" />
                        <InputText v-model="filters['global'].value" placeholder="Search..." />
                    </span>
                </div>
            </template>

            <Column field="name" header="Name" sortable></Column>
            <Column field="start_date" header="Start Date" sortable>
                <template #body="slotProps">
                    {{ slotProps.data.start_date ? new Date(slotProps.data.start_date).toLocaleDateString() : '-' }}
                </template>
            </Column>
            <Column field="end_date" header="End Date" sortable>
                <template #body="slotProps">
                    {{ slotProps.data.end_date ? new Date(slotProps.data.end_date).toLocaleDateString() : '-' }}
                </template>
            </Column>
            <Column field="is_current" header="Current Year">
                <template #body="slotProps">
                    <Tag :severity="slotProps.data.is_current ? 'success' : 'secondary'">
                        {{ slotProps.data.is_current ? 'Current' : '-' }}
                    </Tag>
                </template>
            </Column>
            <Column header="Actions" :exportable="false" style="min-width:12rem">
                <template #body="slotProps">
                    <Button icon="pi pi-check" v-if="!slotProps.data.is_current" class="p-button-rounded p-button-success p-button-text mr-2" @click="setAsCurrent(slotProps.data)" v-tooltip="'Set as Current'" />
                    <Button icon="pi pi-pencil" class="p-button-rounded p-button-info p-button-text mr-2" @click="editYear(slotProps.data)" />
                    <Button icon="pi pi-trash" class="p-button-rounded p-button-danger p-button-text" @click="confirmDeleteYear(slotProps.data)" />
                </template>
            </Column>
        </DataTable>

        <Dialog v-model:visible="dialogVisible" :style="{width: '450px'}" header="Academic Year Details" :modal="true" class="p-fluid">
            <div class="field mb-4">
                <label for="name">Name (e.g. 2023-2024)</label>
                <InputText id="name" v-model.trim="currentYear.name" required="true" autofocus :class="{'p-invalid': submitted && !currentYear.name}" />
                <small class="p-error" v-if="submitted && !currentYear.name">Name is required.</small>
            </div>
            
            <div class="field mb-4">
                <label for="start_date">Start Date</label>
                <DatePicker id="start_date" v-model="currentYear.start_date" dateFormat="yy-mm-dd" showIcon />
            </div>

            <div class="field mb-4">
                <label for="end_date">End Date</label>
                <DatePicker id="end_date" v-model="currentYear.end_date" dateFormat="yy-mm-dd" showIcon />
            </div>

            <div class="field-checkbox mb-4 flex items-center gap-2">
                <Checkbox id="is_current" v-model="currentYear.is_current" :binary="true" />
                <label for="is_current">Set as Current Year</label>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="hideDialog" />
                <Button label="Save" icon="pi pi-check" :loading="saving" @click="saveYear" />
            </template>
        </Dialog>
    </div>
</template>
