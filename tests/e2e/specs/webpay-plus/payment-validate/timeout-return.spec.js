import { test, expect } from "@playwright/test";
import { login, addProductToCart, goThroughCheckoutWithWebpay } from "../../../helpers/checkout.js";
import {
    closePool,
    getTransactionByOrderId,
    getTransactionStatusById
} from "../../../helpers/database.js";

const WEBPAY_API = "wc_gateway_transbank_webpay_plus_rest";

async function startCheckoutAndGetTransaction(page) {
    await login(page);
    await addProductToCart(page);

    const checkoutResponsePromise = page.waitForResponse(
        (response) =>
            response.request().method() === "POST" &&
            response.url().includes("/wc/store/v1/checkout"),
        { timeout: 45_000 }
    );
    await goThroughCheckoutWithWebpay(page);

    const checkoutResponse = await checkoutResponsePromise;
    const checkoutPayload = await checkoutResponse.json();
    const orderId = checkoutPayload.order_id;
    expect(orderId, "Checkout response should include the created order ID").toBeTruthy();

    const transaction = await getTransactionByOrderId(orderId);
    expect(transaction, "Checkout should create a Webpay transaction").toBeTruthy();
    expect(transaction.status).toBe("initialized");
    expect(transaction.session_id).toBeTruthy();

    return transaction;
}

function timeoutCallbackUrl(baseURL, transaction, sessionId) {
    const url = new URL("/", baseURL);
    url.searchParams.set("wc-api", WEBPAY_API);
    url.searchParams.set("TBK_ID_SESION", sessionId);
    url.searchParams.set("TBK_ORDEN_COMPRA", transaction.buy_order);

    return url.toString();
}

test.describe("Webpay Plus — Timeout return", () => {
    test.afterEach(async () => {
        await closePool();
    });

    test("Matching session marks the initialized transaction as timeout", async ({ page, baseURL }) => {
        const transaction = await startCheckoutAndGetTransaction(page);

        await page.goto(timeoutCallbackUrl(baseURL, transaction, transaction.session_id));

        await expect(page).toHaveURL(/transbank_status=10/);
        await expect.poll(() => getTransactionStatusById(transaction.id)).toBe("timeout");
        expect((await page.content()).includes("Fatal error")).toBe(false);
    });

    test("Mismatched session leaves the transaction initialized and shows generic error", async ({ page, baseURL }) => {
        const transaction = await startCheckoutAndGetTransaction(page);
        const mismatchedSessionId = `${transaction.session_id}-mismatch`;

        await page.goto(timeoutCallbackUrl(baseURL, transaction, mismatchedSessionId));

        await expect(page).toHaveURL(/transbank_status=12/);
        expect(await getTransactionStatusById(transaction.id)).toBe("initialized");
        expect((await page.content()).includes("Fatal error")).toBe(false);
    });
});
