import { BuyOrderValidator } from "../../modules/buy-format-order/buyOrderValidator";
import { BuyOrderPreviewGenerator } from "../../modules/buy-format-order/buyOrderPreviewGenerator";
import { BuyOrderService } from "../../modules/buy-format-order/buyOrderService";
import { BuyOrderFormatComponent } from "../../modules/buy-format-order/buyOrderFormatComponent";
import { DEFAULT_BUY_ORDER_FORMATS, SELECTORS } from "../../modules/buy-format-order/utils/constants";
import { DOMUtils } from "../../utils/dom";

DOMUtils.ready(() => {
    const oneClickInput = document.querySelector(SELECTORS.ONECLICK_BUY_ORDER_INPUT);
    const oneClickChildInput = document.querySelector(SELECTORS.ONECLICK_CHILD_BUY_ORDER_INPUT);

    if (!oneClickInput || !oneClickChildInput)
        return;

    const validator = new BuyOrderValidator();
    const preview = new BuyOrderPreviewGenerator();
    const service = new BuyOrderService(validator, preview);
    const component = new BuyOrderFormatComponent(service);

    component.attach(SELECTORS.ONECLICK_BUY_ORDER_INPUT, DEFAULT_BUY_ORDER_FORMATS.ONECLICK_PARENT, {
        addHelpText: false,
        isOneClick: true,
        getOtherFormat: () => ({
            format: oneClickChildInput.value ?? null,
            customMessage: 'El formato de orden de compra principal no puede ser igual al formato de orden de compra hija.'
        })
    });

    component.attach(
        SELECTORS.ONECLICK_CHILD_BUY_ORDER_INPUT,
        DEFAULT_BUY_ORDER_FORMATS.ONECLICK_CHILD,
        {
            addHelpText: true,
            isOneClick: true,
            getOtherFormat: () => ({
                format: oneClickInput.value ?? null,
            }),
        },
    );
});
