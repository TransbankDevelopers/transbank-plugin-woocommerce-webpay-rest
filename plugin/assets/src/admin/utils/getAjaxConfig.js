export const getAjaxConfig = () => {
    const config = globalThis.ajax_object;

    if (!config?.ajax_url || !config?.nonce) {
        return null;
    }

    return config;
}
