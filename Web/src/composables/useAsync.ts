import { ref } from 'vue';

export function useAsync<T>() {
  const data = ref<T | null>(null);
  const loading = ref(false);
  const error = ref<string | null>(null);

  const execute = async (asyncFunction: () => Promise<T>) => {
    try {
      loading.value = true;
      error.value = null;
      data.value = await asyncFunction();
      return data.value;
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'An error occurred';
      console.error('Async error:', err);
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const reset = () => {
    data.value = null;
    loading.value = false;
    error.value = null;
  };

  return {
    data,
    loading,
    error,
    execute,
    reset
  };
}
