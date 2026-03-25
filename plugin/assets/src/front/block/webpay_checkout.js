import { registerPaymentMethod } from "@woocommerce/blocks-registry";
import { decodeEntities } from "@wordpress/html-entities";
import { getSetting } from "@woocommerce/settings";
import { noticeHandler } from "./notice_handler";

const settings = getSetting("transbank_webpay_plus_rest_data", {});
const label = decodeEntities(settings.title);

noticeHandler(settings.id);

const Content = ({ settings }) => {
    return decodeEntities(settings.description);
};

const Label = ({ settings }) => {
    const title = decodeEntities(settings.title);
    const imagePath = settings.icon;
    const paymentImage = <img src={imagePath} alt="webpay plus logo" />;
    return (
        <div>
            <span>{title}</span>
            {paymentImage}
        </div>
    );
};

const TransbankWebpayBlocks = {
    name: settings.id,
    label: <Label settings={settings} />,
    content: <Content settings={settings} />,
    edit: <Content settings={settings} />,
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports
    }
};

registerPaymentMethod(TransbankWebpayBlocks);
