import { ApiService } from "../../modules/api/apiService";
import { ProductConnectionChecker } from "../../modules/connection/connectionChecker";
import { ButtonBusyManager } from "../../modules/ui/busyManager";
import { DOMUtils } from "../../utils/dom";
import { getAjaxConfig } from "../../utils/getAjaxConfig";

DOMUtils.ready(() => {
    const connectionTestCards = document.querySelectorAll("[data-connection-test]");

    if (!connectionTestCards.length) {
        return;
    }

    const config = getAjaxConfig();

    if (!config) {
        return;
    }

    const apiService = new ApiService(config.ajax_url, config.nonce);

    connectionTestCards.forEach((card) => {
        const checkButton = card.querySelector('[data-role="check-button"]');
        const resultContainer = card.querySelector('[data-role="result-container"]');
        const responseBadge = card.querySelector('[data-role="status-badge"]');
        const responseEnvironment = card.querySelector('[data-role="environment-value"]');

        if (!checkButton || !resultContainer || !responseBadge || !responseEnvironment) {
            return;
        }

        const checker = new ProductConnectionChecker(
            apiService,
            new ButtonBusyManager(),
            {
                card,
                checkButton,
                resultContainer,
                responseBadge,
                responseEnvironment
            },
            {
                actionName: card.dataset.action || "check_connection",
                productKey: card.dataset.productKey || ""
            }
        );

        checker.init();
    });
});
