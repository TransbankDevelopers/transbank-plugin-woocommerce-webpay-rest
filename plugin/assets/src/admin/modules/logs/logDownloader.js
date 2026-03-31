export class LogDownloader {
    constructor(apiService, buttonDownloadSelector, logPathSelector, noticeManager) {
        this.apiService = apiService;

        this.buttonSelector = buttonDownloadSelector;
        this.selectSelector = logPathSelector;
        this.noticeManager = noticeManager;
    }

    init() {
        const button = document.querySelector(this.buttonSelector);
        button?.addEventListener('click', (e) => {
            e.preventDefault();
            this.download();
        });
    }

    async download() {
        const selectElement = document.querySelector(this.selectSelector);
        const logFile = selectElement?.value ?? null;

        if (!logFile) {
            this.noticeManager.renderNotice({
                type: "error",
                title: "Error en la descarga",
                message: "Selecciona un archivo para descargar."
            });
            return;
        }

        try {
            const permissionResponse = await this.checkPermission(logFile);

            const isSuccess = permissionResponse?.success;

            if (!isSuccess) {
                this.noticeManager.renderNotice({
                    type: "error",
                    title: "Error en la descarga",
                    message: permissionResponse?.data?.error
                });
                return;
            }

            globalThis.location.href = permissionResponse.data.downloadUrl;

        } catch (error) {
            this.noticeManager.renderNotice({
                type: "error",
                title: "Error en la descarga",
                message: error.message || 'Ocurrió un error al intentar descargar el archivo'
            });
        }
    }

    async checkPermission(file) {
        const response = await this.apiService.post('check_can_download_file', { file });
        return response;
    }
}
