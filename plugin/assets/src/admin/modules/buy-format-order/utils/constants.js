export const SELECTORS = {
    WEBPAY_BUY_ORDER_INPUT: "#woocommerce_transbank_webpay_plus_rest_buy_order_format",
    ONECLICK_BUY_ORDER_INPUT: "#woocommerce_transbank_oneclick_mall_rest_buy_order_format",
    ONECLICK_CHILD_BUY_ORDER_INPUT: "#woocommerce_transbank_oneclick_mall_rest_child_buy_order_format",
};

export const DEFAULT_BUY_ORDER_FORMATS = {
    WEBPAY: "wc-{random, length=8}-{orderId}",
    ONECLICK_PARENT: "wc-{random, length=8}-{orderId}",
    ONECLICK_CHILD: "wc-child-{random, length=8}-{orderId}",
};
