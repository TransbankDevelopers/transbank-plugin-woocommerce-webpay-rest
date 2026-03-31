import { registerPaymentMethod } from "@woocommerce/blocks-registry";
import { decodeEntities } from "@wordpress/html-entities";
import { getSetting } from "@woocommerce/settings";
import { noticeHandler } from "../../../modules/notice_handler";

const { useEffect } = globalThis.wp.element;
const settings = getSetting("transbank_oneclick_mall_rest_data", {});
const label = decodeEntities(settings.title);

noticeHandler(settings.id);

const Content = (props) => {
    const { eventRegistration, emitResponse } = props;
    const { onCheckoutFail } = eventRegistration;

    useEffect(() => {
        const onError = ({ processingResponse }) => {
            const errorMessage =
                processingResponse?.paymentDetails?.errorMessage;

            if (errorMessage) {
                return {
                    type: emitResponse.responseTypes.ERROR,
                    message: errorMessage,
                    messageContext: emitResponse.noticeContexts.PAYMENTS
                };
            }

            return null;
        };

        const unsubscribe = onCheckoutFail(onError);

        return unsubscribe;
    }, [emitResponse, onCheckoutFail]);

    return decodeEntities(settings.description);
};

const Label = ({ settings }) => {
    const title = decodeEntities(settings.title);
    const imagePath = settings.icon;
    const paymentImage = (
        <img
            className="tbk-checkout-block-label__logo"
            src={imagePath}
            alt="oneclick logo"
        />
    );

    return (
        <div className="tbk-checkout-block-label">
            <span className="tbk-checkout-block-label__title">{title}</span>
            {paymentImage}
        </div>
    );
};

const TransbankOneclickBlocks = {
    name: settings.id,
    label: <Label settings={settings} />,
    content: <Content />,
    edit: <Content />,
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports
    }
};

registerPaymentMethod(TransbankOneclickBlocks);
