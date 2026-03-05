import { BuyOrderValidator } from "../../modules/buy-format-order/buyOrderValidator";
import { BuyOrderPreviewGenerator } from "../../modules/buy-format-order/buyOrderPreviewGenerator";
import { BuyOrderService } from "../../modules/buy-format-order/buyOrderService";
import { BuyOrderFormatComponent } from "../../modules/buy-format-order/buyOrderFormatComponent";
import { DEFAULT_BUY_ORDER_FORMATS, SELECTORS } from "../../modules/buy-format-order/utils/constants";
import { DOMUtils } from "../../utils/dom";

DOMUtils.ready(() => {
    const webpayInput = document.querySelector(SELECTORS.WEBPAY_BUY_ORDER_INPUT);
    if (!webpayInput)
        return;

    const validator = new BuyOrderValidator();
    const preview = new BuyOrderPreviewGenerator();
    const service = new BuyOrderService(validator, preview);
    const component = new BuyOrderFormatComponent(service);

    component.attach(SELECTORS.WEBPAY_BUY_ORDER_INPUT, DEFAULT_BUY_ORDER_FORMATS.WEBPAY, {
        addHelpText: true,
    });
});
