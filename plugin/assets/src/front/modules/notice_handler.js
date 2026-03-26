export const noticeHandler = (paymentMethod) => {
    if (window.wc.blocksCheckout) {
        let lastNotice = null;
        const { createNotice, removeNotice } = window.wp.data.dispatch(
            "core/notices"
        );
        const { select } = window.wp.data;
        const { getSetting } = window.wc.wcSettings;
        const {
            validationStore,
            paymentStore,
            checkoutStore
        } = window.wc.wcBlocksData;

        const onlyErrorForPaymentMethod = (errors) => {
            return errors.find(
                (error) => error.hidden || error.message.includes(paymentMethod)
            );
        };

        const { unsubscribe } = window.wp.data.subscribe(() => {
            const validationErrors =
                select(validationStore).getValidationErrors();

            if (Object.keys(validationErrors).length === 0) {
                return;
            }

            const currentError = onlyErrorForPaymentMethod(
                Object.values(validationErrors)
            );

            if (lastNotice) {
                removeNotice(lastNotice.id, "wc/checkout");
                lastNotice = null;
            }

            if (currentError && currentError.message) {
                lastNotice = createNotice("error", currentError.message, {
                    id: "transbank-error",
                    context: "wc/checkout",
                    speak: false,
                    isDismissible: true
                });
            }
        });

        const settings = getSetting("transbank_webpay_plus_rest_data");

        if (settings.title === undefined) {
            const paymentStatus =
                select(paymentStore).getState().activePaymentMethod;
            const currentPaymentMethod = select(checkoutStore).getState()
                .activePaymentMethod;

            if (
                paymentStatus === "error" &&
                paymentMethod === currentPaymentMethod
            ) {
                const errorMessage =
                    select(paymentStore).getState().paymentResult
                        .paymentDetails.errorMessage;
                if (errorMessage) {
                    createNotice("error", errorMessage, {
                        id: "transbank-error",
                        context: "wc/checkout",
                        speak: false,
                        isDismissible: true
                    });
                }
            }
        }

        return () => unsubscribe();
    }
};
