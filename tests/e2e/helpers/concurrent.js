import {
    login,
    addProductToCart,
    goThroughCheckoutWithWebpay
} from "./checkout.js";
import { fillCardAndAuthenticate } from "./webpay-form.js";
import { isPaymentError } from "./assertions.js";

const isReturnUrl = (url) => {
    const s = url.toString();

    return s.includes("wc-api") && s.includes("token_ws=");
};

export async function runCheckoutFlow(page) {
    await login(page);
    await addProductToCart(page);
    await goThroughCheckoutWithWebpay(page);
    await fillCardAndAuthenticate(page);
}

export async function holdReturnRequests(context) {
    let releaseReturns;
    let returnsReleased = false;
    const returnsCanContinue = new Promise((resolve) => {
        releaseReturns = resolve;
    });
    const commerceReturns = [];

    await context.route(isReturnUrl, async (route) => {
        const requestUrl = route.request().url();
        commerceReturns.push(requestUrl);
        console.log(
            `[INTERCEPTOR] Return #${commerceReturns.length} intercepted: ${requestUrl}`
        );

        if (!returnsReleased) {
            await returnsCanContinue;
        }

        await route.continue();
    });

    return {
        commerceReturns,
        release() {
            returnsReleased = true;
            releaseReturns();
        }
    };
}

export function hasNavigatedPastValidation(page) {
    try {
        return !page.url().includes("wc-api");
    } catch {
        return false;
    }
}

export async function hasErrorContent(page) {
    try {
        return isPaymentError(page.url());
    } catch {
        return false;
    }
}
