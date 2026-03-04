import { ApiService } from "../../modules/api/apiService";
import { ConnectionChecker } from "../../modules/connection/connectionChecker";
import { ButtonBusyManager } from "../../modules/ui/busyManager";
import { DOMUtils } from "../../utils/dom";
import { ElementVisibilityManager } from "../../modules/ui/elementVisibilityManager";
import { getAjaxConfig } from '../../utils/getAjaxConfig';

DOMUtils.ready(() => {
    const checkButton = document.querySelector(".check_conn");
    const resultContainer = document.querySelector("#tbk_response_status");
    const errorContainer = document.querySelector("#div_status_error");
    const successContainer = document.querySelector("#div_status_ok");

    const responseUrl = document.querySelector("#response_url_text");
    const responseToken = document.querySelector("#response_token_text");
    const errorResponse = document.querySelector("#error_response_text");
    const errorDetail = document.querySelector("#error_detail_response_text");

    if (!checkButton || !resultContainer || !errorContainer || !successContainer) {
        return;
    }

    const dom = {
        checkButton,
        resultContainer,
        errorContainer,
        successContainer,
        responseUrl,
        responseToken,
        errorResponse,
        errorDetail,
    };

    const config = getAjaxConfig();
        
    if (!config) {
        return;
    }
    
    const apiService = new ApiService(config.ajax_url, config.nonce);

    const buttonBusyManager = new ButtonBusyManager();
    const visibilityManager = new ElementVisibilityManager();

    const checker = new ConnectionChecker(
        apiService,
        buttonBusyManager,
        visibilityManager,
        dom
    );

    checker.init();
});
