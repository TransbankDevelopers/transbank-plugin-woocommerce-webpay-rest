import { getSetting } from "@woocommerce/settings";
import { noticeHandler } from "../../modules/notice_handler";

const settings = getSetting("transbank_webpay_plus_rest_data", {});

if (settings?.id) {
    noticeHandler(settings.id);
}
