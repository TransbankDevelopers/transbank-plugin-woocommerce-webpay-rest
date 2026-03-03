export class ApiService {
    constructor(ajaxUrl, nonce) {
        this.ajaxUrl = ajaxUrl;
        this.nonce = nonce;
    }

    async post(action, data = {}) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', this.nonce);

        Object.entries(data).forEach(([key, value]) => {
            formData.append(key, value);
        });

        const response = await fetch(this.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        });

        const rawText = await response.text();

        let parsedBody = rawText;

        try {
            parsedBody = rawText ? JSON.parse(rawText) : null;
        } catch (e) {
            parsedBody = {
                _parseError: true,
                raw: rawText,
                error: e?.message
            };
        }

        if (!response.ok) {
            const message =
                parsedBody?.data?.error ||
                parsedBody?.data?.message ||
                parsedBody?.message ||
                parsedBody?.raw ||
                rawText ||
                `HTTP Error ${response.status}`;

            const error = new Error(String(message));
            error.status = response.status;
            error.body = parsedBody;

            throw error;
        }

        if(parsedBody?._parseError) {
            const preview = String(parsedBody.raw || "").slice(0, 300);
            const error = new Error(
                `Invalid JSON response from server (status ${response.status}). Preview: ${preview}`
            );
            error.status = response.status;
            error.body = parsedBody;
            throw error;
        }

        if (parsedBody?.success === false) {
            const message =
                parsedBody?.data?.error ||
                parsedBody?.data?.message ||
                parsedBody?.message ||
                'Request failed';

            const error = new Error(String(message));
            error.status = response.status;
            error.body = parsedBody;

            throw error;
        }

        return parsedBody;
    }
}
