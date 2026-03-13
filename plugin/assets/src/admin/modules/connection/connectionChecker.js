import { setText } from "../../utils/setText";
import { elementFactory } from "../../utils/elementFactory";

export class ProductConnectionChecker {
    constructor(apiService, buttonBusyManager, dom, config = {}) {
        this.apiService = apiService;
        this.buttonBusyManager = buttonBusyManager;
        this.dom = dom;
        this.config = config;
    }

    init() {
        this.dom.checkButton?.addEventListener?.("click", (e) => {
            e.preventDefault();
            this.checkConnection();
        });
    }

    async checkConnection() {
        const spinner = elementFactory("i", { className: "fa fa-spinner fa-spin" });
        const text = document.createTextNode("Verificando... ");

        const release = this.buttonBusyManager.setBusy(this.dom.checkButton, [
            text,
            spinner,
        ]);

        this.setLoadingUIState();

        try {
            const response = await this.apiService.post(this.config.actionName, {
                product: this.config.productKey,
            });
            this.applyResponseState(response);
        } catch (error) {
            this.handleError(error);
        } finally {
            release();
        }
    }

    setLoadingUIState() {
        this.setContainerState("is-loading");
        this.applyLoadingState();
    }

    applyResponseState(response) {
        this.applyResultState(response);
        this.setContainerState("is-ready");
    }

    applyLoadingState() {
        if (this.dom.responseBadge) {
            this.dom.responseBadge.textContent = "Verificando";
            this.dom.responseBadge.classList.remove("label-success", "label-danger");
        }

        setText(this.dom.responseEnvironment, "Detectando...");
    }

    applyResultState(response) {
        const meta = response?.meta ?? {};
        const isSuccess = response?.status?.string === "OK";
        const statusLabel = isSuccess ? "Conexión OK" : "Conexión con error";

        setText(this.dom.responseEnvironment, meta?.environmentLabel ?? "No disponible");

        if (this.dom.responseBadge) {
            this.dom.responseBadge.textContent = statusLabel;
            this.dom.responseBadge.classList.remove("label-success", "label-danger");
            this.dom.responseBadge.classList.add(isSuccess ? "label-success" : "label-danger");
        }
    }

    handleError() {
        this.applyResultState({
            status: { string: "Error" },
            meta: {
                environmentLabel: "No disponible",
            },
        });
        this.setContainerState("is-ready");
    }

    setContainerState(nextState) {
        if (!this.dom.resultContainer) {
            return;
        }

        this.dom.resultContainer.classList.remove("is-loading", "is-ready");
        this.dom.resultContainer.classList.add(nextState);
    }
}
