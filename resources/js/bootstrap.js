import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Automatically inject Shopify App Bridge Session Token into all Axios requests
window.axios.interceptors.request.use(async (config) => {
    if (window.shopify && typeof window.shopify.idToken === 'function') {
        try {
            const token = await window.shopify.idToken();
            if (token) {
                config.headers['Authorization'] = `Bearer ${token}`;
            }
        } catch (e) {
            console.warn('Error fetching Shopify idToken for axios:', e);
        }
    }
    return config;
});
