<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { FilterMatchMode } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';
import SubjectService, { type Subject, type SubjectCoefficient } from '@/service/SubjectService';
import LevelService, { type Level } from '@/service/LevelService';

const { t } = useI18n();
const toast = useToast();

const dt = ref();
const subjects = ref<Subject[]>([]);
const subjectDialog = ref(false);
const deleteSubjectDialog = ref(false);
const subject = ref<Partial<Subject>>({});
const submitted = ref(false);
const loading = ref(false);

const filters = ref({
  global: { value: null, matchMode: FilterMatchMode.CONTAINS }
});

// Coefficient Dialog States
const coefficientDialog = ref(false);
const selectedSubject = ref<Subject | null>(null);
const subjectCoefficients = ref<SubjectCoefficient[]>([]);
const loadingCoefficients = ref(false);

// Add/Edit Coefficient States
const levels = ref<Level[]>([]);
const coefficientModel = ref<Partial<SubjectCoefficient>>({});
const coefficientSubmitted = ref(false);
const coefficientDialogEdit = ref(false);
const deleteCoefficientDialog = ref(false);

onMounted(async () => {
    await loadSubjects();
    await loadLevels();
});

const loadSubjects = async () => {
    try {
        loading.value = true;
        subjects.value = await SubjectService.getSubjects();
    } catch (error: any) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: t('subject_management.load_failed'), life: 3000 });
    } finally {
        loading.value = false;
    }
};

const loadLevels = async () => {
    try {
        levels.value = await LevelService.getLevels();
    } catch (error: any) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: t('common.error'), life: 3000 });
    }
};

const openNew = () => {
    subject.value = {};
    submitted.value = false;
    subjectDialog.value = true;
};

const hideDialog = () => {
    subjectDialog.value = false;
    submitted.value = false;
};

const saveSubject = async () => {
    submitted.value = true;

    if (!subject.value.name?.trim()) {
        return;
    }

    try {
        if (subject.value.id) {
            await SubjectService.updateSubject(subject.value.id, {
                name: subject.value.name,
                code: subject.value.code || '',
            });
            toast.add({ severity: 'success', summary: t('common.success'), detail: t('subject_management.updated'), life: 3000 });
        } else {
            await SubjectService.createSubject({
                name: subject.value.name,
                code: subject.value.code || '',
            });
            toast.add({ severity: 'success', summary: t('common.success'), detail: t('subject_management.created'), life: 3000 });
        }

        await loadSubjects();
        subjectDialog.value = false;
        subject.value = {};
    } catch (error: any) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: t('subject_management.save_failed'), life: 3000 });
    }
};

const editSubject = (sub: Subject) => {
    subject.value = { ...sub };
    subjectDialog.value = true;
};

const confirmDeleteSubject = (sub: Subject) => {
    subject.value = sub;
    deleteSubjectDialog.value = true;
};

const deleteSubject = async () => {
    try {
        await SubjectService.deleteSubject(subject.value.id!);
        subjects.value = subjects.value.filter(s => s.id !== subject.value.id);
        deleteSubjectDialog.value = false;
        subject.value = {};
        toast.add({ severity: 'success', summary: t('common.success'), detail: t('subject_management.deleted'), life: 3000 });
    } catch (error: any) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: t('subject_management.delete_failed'), life: 3000 });
    }
};


// -------- COEFFICIENTS MANAGEMENT -------- 

const openCoefficients = async (sub: Subject) => {
    selectedSubject.value = sub;
    coefficientDialog.value = true;
    await loadCoefficients(sub.id);
};

const loadCoefficients = async (subjectId: number) => {
    try {
        loadingCoefficients.value = true;
        let response = await SubjectService.getSubjectCoefficients(subjectId);
        // Sometimes the backend wraps it in 'data', sometimes it's direct. Assuming direct based on our service mapping
        subjectCoefficients.value = Array.isArray(response) ? response : (response as any).data || [];
    } catch (error: any) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: t('subject_management.load_coeff_failed'), life: 3000 });
    } finally {
        loadingCoefficients.value = false;
    }
};

const openNewCoefficient = () => {
    coefficientModel.value = {
        coefficient: 1,
        weekly_sessions_required: 2
    };
    coefficientSubmitted.value = false;
    coefficientDialogEdit.value = true;
};

const hideCoefficientDialog = () => {
    coefficientDialogEdit.value = false;
    coefficientSubmitted.value = false;
};

const saveCoefficient = async () => {
    coefficientSubmitted.value = true;

    if (!coefficientModel.value.level_id || !coefficientModel.value.coefficient || !coefficientModel.value.weekly_sessions_required) {
        return;
    }

    try {
        if (coefficientModel.value.id) {
            // Update
            await SubjectService.updateCoefficient(coefficientModel.value.id, {
                coefficient: coefficientModel.value.coefficient,
                weekly_sessions_required: coefficientModel.value.weekly_sessions_required
            });
            toast.add({ severity: 'success', summary: t('common.success'), detail: t('subject_management.coeff_updated'), life: 3000 });
        } else {
            // Create
            if(!selectedSubject.value?.id) return;
            await SubjectService.createCoefficient({
                subject_id: selectedSubject.value.id,
                level_id: coefficientModel.value.level_id,
                coefficient: coefficientModel.value.coefficient,
                weekly_sessions_required: coefficientModel.value.weekly_sessions_required
            });
            toast.add({ severity: 'success', summary: t('common.success'), detail: t('subject_management.coeff_created'), life: 3000 });
        }
        
        await loadCoefficients(selectedSubject.value!.id);
        coefficientDialogEdit.value = false;
        coefficientModel.value = {};
    } catch (error: any) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: error.response?.data?.message || t('subject_management.save_coeff_failed'), life: 3000 });
    }
};

const editCoefficient = (coeff: SubjectCoefficient) => {
    coefficientModel.value = { ...coeff };
    coefficientDialogEdit.value = true;
};

const confirmDeleteCoefficient = (coeff: SubjectCoefficient) => {
    coefficientModel.value = coeff;
    deleteCoefficientDialog.value = true;
};

const removeCoefficient = async () => {
    try {
        await SubjectService.deleteCoefficient(coefficientModel.value.id!);
        await loadCoefficients(selectedSubject.value!.id);
        deleteCoefficientDialog.value = false;
        coefficientModel.value = {};
        toast.add({ severity: 'success', summary: t('common.success'), detail: t('subject_management.coeff_removed'), life: 3000 });
    } catch (error: any) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: t('subject_management.remove_coeff_failed'), life: 3000 });
    }
};

</script>

<template>
  <div class="card">
    <Toast />

    <Toolbar class="mb-6">
        <template #start>
            <Button :label="$t('subject_management.new_subject')" icon="pi pi-plus" severity="secondary" class="mr-2" @click="openNew" />
        </template>
    </Toolbar>

            <DataTable
                ref="dt"
                :value="subjects"
                dataKey="id"
                :paginator="true"
                :rows="10"
                :filters="filters"
                paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                :rowsPerPageOptions="[5, 10, 25]"
                :currentPageReportTemplate="$t('subject_management.page_report')"
                responsiveLayout="scroll"
                :loading="loading"
                @row-click="openCoefficients($event.data)"
            >   
                <template #header>
                    <div class="flex flex-wrap gap-2 items-center justify-between">
                        <h4 class="m-0 text-xl font-semibold">{{ $t('subject_management.title') }}</h4>
                        <IconField>
                            <InputIcon>
                                <i class="pi pi-search" />
                            </InputIcon>
                            <InputText v-model="filters['global'].value" :placeholder="$t('subject_management.search_placeholder')" />
                        </IconField>
                    </div>
                </template>

                <Column field="name" :header="$t('subject_management.col_name')" :sortable="true" style="min-width: 14rem">
                    <template #body="{ data }">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-book text-primary"></i>
                            <span class="font-semibold">{{ data.name }}</span>
                        </div>
                    </template>
                </Column>
                <Column field="code" :header="$t('subject_management.col_code')" :sortable="true" style="min-width: 10rem">
                    <template #body="{ data }">
                        <span class="text-muted-color">{{ data.code || $t('common.na') }}</span>
                    </template>
                </Column>
                <Column :header="$t('common.actions')" :exportable="false" style="min-width: 13rem">
                    <template #body="{ data }">
                        <Button icon="pi pi-pencil" outlined rounded class="mr-2" @click="editSubject(data)" v-tooltip.top="$t('common.edit')" />
                        <Button icon="pi pi-cog" outlined rounded class="mr-2" @click="openCoefficients(data)" v-tooltip.top="$t('subject_management.title')" />
                        <Button icon="pi pi-trash" outlined rounded severity="danger" @click="confirmDeleteSubject(data)" v-tooltip.top="$t('common.delete')" />
                    </template>
                </Column>
            </DataTable>

            <!-- Subject Dialog -->
            <Dialog v-model:visible="subjectDialog" :style="{ width: '550px' }" :header="$t('subject_management.subject_details')" :modal="true" class="p-fluid">
                <div class="flex flex-col gap-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="name" class="block font-semibold mb-2">{{ $t('subject_management.col_name') }} <span class="text-red-500">*</span></label>
                            <InputText id="name" v-model.trim="subject.name" required="true" autofocus :invalid="submitted && !subject.name" />
                            <small class="text-red-500" v-if="submitted && !subject.name">{{ $t('subject_management.name_required') }}</small>
                        </div>
                        <div>
                            <label for="code" class="block font-semibold mb-2">{{ $t('subject_management.col_code') }} <span class="text-red-500">*</span></label>
                            <InputText id="code" v-model.trim="subject.code" required="true" :invalid="submitted && !subject.code" />
                            <small class="text-red-500" v-if="submitted && !subject.code">{{ $t('subject_management.code_required') }}</small>
                        </div>
                    </div>
                </div>
                <template #footer>
                    <Button :label="$t('common.cancel')" icon="pi pi-times" text @click="hideDialog" />
                    <Button :label="$t('common.save')" icon="pi pi-check" @click="saveSubject" />
                </template>
            </Dialog>

            <!-- Delete Subject Dialog -->
            <Dialog v-model:visible="deleteSubjectDialog" :style="{ width: '450px' }" :header="$t('common.confirm_deletion')" :modal="true">
                <div class="flex align-items-center justify-content-center">
                    <i class="pi pi-exclamation-triangle mr-3" style="font-size: 2rem" />
                    <span v-if="subject">{{ $t('subject_management.confirm_delete', { name: subject.name }) }}</span>
                </div>
                <template #footer>
                    <Button :label="$t('common.no')" icon="pi pi-times" text @click="deleteSubjectDialog = false" />
                    <Button :label="$t('common.yes')" icon="pi pi-check" text @click="deleteSubject" />
                </template>
            </Dialog>

            <!-- Manage Coefficients Dialog -->
            <Dialog v-model:visible="coefficientDialog" :style="{ width: '800px' }" :header="$t('subject_management.coefficients_title', { name: selectedSubject?.name })" :modal="true" class="p-fluid">
                <Toolbar class="mb-4">
                    <template #start>
                        <Button :label="$t('subject_management.add_coefficient')" icon="pi pi-plus" @click="openNewCoefficient" />
                    </template>
                </Toolbar>
                <DataTable :value="subjectCoefficients" :loading="loadingCoefficients" responsiveLayout="scroll">
                    <template #empty>
                        {{ $t('subject_management.no_coefficients') }}
                    </template>
                    <Column field="level.name" :header="$t('subject_management.col_level')">
                         <template #body="slotProps">
                             {{ slotProps.data.level?.name || $t('subject_management.unknown_level') }} ({{ slotProps.data.level?.cycle }})
                         </template>
                    </Column>
                    <Column field="coefficient" :header="$t('subject_management.col_coefficient')"></Column>
                    <Column field="weekly_sessions_required" :header="$t('subject_management.col_weekly_sessions')"></Column>
                    <Column headerStyle="width: 8rem;">
                        <template #body="{ data }">
                            <Button icon="pi pi-pencil" outlined rounded class="mr-2" @click="editCoefficient(data)" />
                            <Button icon="pi pi-trash" outlined rounded severity="danger" @click="confirmDeleteCoefficient(data)" />
                        </template>
                    </Column>
                </DataTable>
            </Dialog>

            <!-- Add/Edit Coefficient Dialog -->
            <Dialog v-model:visible="coefficientDialogEdit" :style="{ width: '450px' }" :header="$t('subject_management.coefficient_details')" :modal="true" class="p-fluid">
                <div class="flex flex-col gap-6">
                    <div v-if="!coefficientModel.id">
                        <label for="level_id" class="block font-semibold mb-2">{{ $t('subject_management.col_level') }} <span class="text-red-500">*</span></label>
                        <Select id="level_id" v-model="coefficientModel.level_id" :options="levels" optionLabel="name" optionValue="id" :placeholder="$t('subject_management.select_level')" :invalid="coefficientSubmitted && !coefficientModel.level_id">
                            <template #value="slotProps">
                                <div v-if="slotProps.value">
                                     {{ levels.find(l => l.id === slotProps.value)?.name }}
                                </div>
                                <span v-else>
                                    {{ slotProps.placeholder }}
                                </span>
                            </template>
                            <template #option="slotProps">
                                <div>{{ slotProps.option.name }} ({{ slotProps.option.cycle }})</div>
                            </template>
                        </Select>
                        <small class="text-red-500" v-if="coefficientSubmitted && !coefficientModel.level_id">{{ $t('subject_management.level_required') }}</small>
                    </div>
                    <div>
                        <label for="coefficient" class="block font-semibold mb-2">{{ $t('subject_management.col_coefficient') }} <span class="text-red-500">*</span></label>
                        <InputNumber id="coefficient" v-model="coefficientModel.coefficient" :min="1" :max="10" />
                        <small class="text-red-500" v-if="coefficientSubmitted && !coefficientModel.coefficient">{{ $t('subject_management.coefficient_required') }}</small>
                    </div>
                    <div>
                        <label for="weekly_sessions" class="block font-semibold mb-2">{{ $t('subject_management.weekly_sessions_label') }} <span class="text-red-500">*</span></label>
                        <InputNumber id="weekly_sessions" v-model="coefficientModel.weekly_sessions_required" :min="1" :max="20" />
                        <small class="text-red-500" v-if="coefficientSubmitted && !coefficientModel.weekly_sessions_required">{{ $t('subject_management.weekly_sessions_required_msg') }}</small>
                    </div>
                </div>
                <template #footer>
                    <Button :label="$t('common.cancel')" icon="pi pi-times" text @click="hideCoefficientDialog" />
                    <Button :label="$t('common.save')" icon="pi pi-check" @click="saveCoefficient" />
                </template>
            </Dialog>

             <!-- Delete Coefficient Dialog -->
             <Dialog v-model:visible="deleteCoefficientDialog" :style="{ width: '450px' }" :header="$t('common.confirm_deletion')" :modal="true">
                <div class="flex align-items-center justify-content-center">
                    <i class="pi pi-exclamation-triangle mr-3" style="font-size: 2rem" />
                    <span>{{ $t('subject_management.confirm_delete_coeff') }}</span>
                </div>
                <template #footer>
                    <Button :label="$t('common.no')" icon="pi pi-times" text @click="deleteCoefficientDialog = false" />
                    <Button :label="$t('common.yes')" icon="pi pi-check" text @click="removeCoefficient" />
                </template>
            </Dialog>

  </div>
  
</template>
<style scoped>
:deep(.p-datatable .p-datatable-tbody > tr) {
  cursor: pointer;
}
</style>
