import axios, { AxiosInstance } from 'axios';

/**
 * Pre-configured axios client for all Studio API calls.
 *
 * Auth piggybacks on the Sanctum session cookie set by Laravel — we rely
 * on `withCredentials: true` and the CSRF token embedded in the Blade
 * shell. No bearer tokens, no login flow duplication.
 */
export function createApiClient(csrfToken: string): AxiosInstance {
    const client = axios.create({
        withCredentials: true,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken,
            Accept: 'application/json',
        },
    });

    client.interceptors.response.use(
        (response) => response,
        (error) => {
            if (error.response?.status === 419) {
                // eslint-disable-next-line no-console
                console.warn('Studio: CSRF token expired — reload page to recover.');
            }
            return Promise.reject(error);
        },
    );

    return client;
}
