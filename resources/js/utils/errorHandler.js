import { useToast } from "vue-toastification";

export function handleApiError(error) {
    const toast = useToast();

    if (error.response?.status === 422) {
        const messages = Object.values(error.response.data.errors)
            .flat()
            .join('\n');

        toast.error(messages);
    } else {
        toast.error(
            error.response?.data?.message || error
        );
    }
}
