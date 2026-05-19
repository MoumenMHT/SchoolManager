<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ParentPortalService } from '@/service/ParentPortalService';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';

const router = useRouter();
const toast = useToast();
const { t } = useI18n();

const children = ref([]);
const loading = ref(true);

const loadChildren = async () => {
    loading.value = true;
    try {
        children.value = await ParentPortalService.getMyChildren();
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Could not load your children profiles.', life: 3000 });
    } finally {
        loading.value = false;
    }
};

const viewDetails = (childId) => {
    router.push(`/parent/children/${childId}`);
};

onMounted(() => {
    loadChildren();
});
</script>

<template>
    <div class="grid grid-cols-12 gap-8">
        <div class="col-span-12">
            <div class="card">
                <h5 class="text-surface-900 dark:text-surface-0 font-semibold mb-2">{{ t('parent_portal.my_children') }}</h5>
                <p class="text-muted-color">{{ t('parent_portal.select_child_hint') }}</p>

                <div v-if="loading" class="flex justify-center items-center py-8">
                    <i class="pi pi-spin pi-spinner text-4xl text-primary"></i>
                </div>
                
                <div v-else-if="children.length === 0" class="text-center p-8 bg-surface-50 dark:bg-surface-800 rounded-border mt-4">
                    <i class="pi pi-info-circle text-4xl mb-4 text-muted-color"></i>
                    <p class="text-surface-900 dark:text-surface-0">{{ t('parent_portal.no_children_enrolled') }}</p>
                </div>
                
                <div v-else class="grid grid-cols-12 gap-4 mt-6">
                    <div class="col-span-12 md:col-span-6 lg:col-span-4" v-for="child in children" :key="child.id">
                        <div class="card border border-surface shadow-none hover:shadow-md cursor-pointer transition-all duration-200 h-full flex flex-col justify-between" @click="viewDetails(child.id)">
                            <div>
                                <div class="text-center mb-4">
                                    <div class="w-16 h-16 bg-blue-100 dark:bg-blue-400/10 text-blue-500 rounded-full mx-auto flex justify-center items-center mb-4 font-semibold text-2xl">
                                        {{ child.first_name ? child.first_name.charAt(0) : '' }}{{ child.last_name ? child.last_name.charAt(0) : '' }}
                                    </div>
                                </div>
                                <h6 class="text-center text-xl mb-2 text-surface-900 dark:text-surface-0">{{ child.first_name }} {{ child.last_name }}</h6>
                                <p class="text-center text-muted-color">{{ child.class?.name || (child.level?.name + ' - ' + child.grade) || t('parent_portal.registration_pending') }}</p>
                            </div>
                            <div class="mt-6 w-full flex justify-center">
                                <Button :label="t('parent_portal.view_details')" icon="pi pi-search" class="p-button-outlined p-button-sm" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
