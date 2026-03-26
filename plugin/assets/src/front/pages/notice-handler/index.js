import { noticeHandler } from "../../modules/notice_handler";

const settings = globalThis.wc.wcSettings.getSetting(
    "transbank_webpay_plus_rest_data",
    {}
);

noticeHandler(settings.id);
