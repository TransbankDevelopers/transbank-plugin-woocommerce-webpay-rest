import { setInnerHtmlElement } from "../../utils/setInnerHtmlElement";
import { setText } from "../../utils/setText";
import { elementFactory } from "../../utils/elementFactory";

export class ConnectionChecker {
    constructor(apiService, buttonBusyManager, visibilityManager, dom) {
        this.apiService = apiService;
        this.buttonBusyManager = buttonBusyManager;
        this.visibilityManager = visibilityManager;
        this.dom = dom;
    }

    init() {
        this.dom.checkButton?.addEventListener?.("click", (e) => {
            e.preventDefault();
            this.check();
        });
    }

    async check() {
        const spinner = elementFactory("i", { className: "fa fa-spinner fa-spin" });
        const text = document.createTextNode("Verificando... ");

        const release = this.buttonBusyManager.setBusy(this.dom.checkButton, [
            text,
            spinner,
        ]);

        this.resetUI();

        try {
            const response = await this.apiService.post("check_connection");
            this.handleResponse(response);
        } catch (error) {
            this.handleError(error);
        } finally {
            release();
        }
    }

    resetUI() {
        this.visibilityManager.show(this.dom.resultContainer);
        this.visibilityManager.hide(this.dom.errorContainer);
        this.visibilityManager.hide(this.dom.successContainer);
    }

    handleResponse(response) {
        if (response?.status?.string === "OK") {
            this.showSuccess(response.response);
            return;
        }

        this.showError(response.response);
    }

    showSuccess(data) {
        setText(this.dom.responseUrl, data?.url ?? "");
        setInnerHtmlElement(this.dom.responseToken, "pre", null, [data?.token ?? ""]);

        this.visibilityManager.show(this.dom.successContainer);
    }

    showError(data) {
        setText(this.dom.errorResponse, data?.error ?? "");
        setInnerHtmlElement(
            this.dom.errorDetail,
            "code",
            { className: "check-conection-code" },
            [data?.detail ?? ""],
        );

        this.visibilityManager.show(this.dom.errorContainer);
    }

    handleError(error) {
        this.showError({
            error: "Error de conexión",
            detail: error?.message || "Error al verificar la conexión",
        });
    }
}
