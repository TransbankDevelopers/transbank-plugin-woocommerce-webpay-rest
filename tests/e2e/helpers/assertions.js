import { expect } from "@playwright/test";

export function hasFatalError(content) {
    return (
        content.includes("Fatal error") ||
        content.includes("500 Internal Server Error")
    );
}

export function isOrderConfirmation(url) {
    return url.includes("order-received");
}

export function isPaymentError(url) {
    return url.includes("transbank_status=");
}

export async function expectOrderConfirmation(page) {
    const url = page.url();
    const content = await page.content();

    expect(hasFatalError(content), `Page has fatal errors — url: ${url}`).toBe(
        false,
    );
    expect(
        isOrderConfirmation(url),
        `Expected order-received in URL — got: ${url}`,
    ).toBe(true);
}

export async function expectPaymentError(page) {
    const url = page.url();
    const content = await page.content();

    expect(hasFatalError(content), `Page has fatal errors — url: ${url}`).toBe(
        false,
    );
    expect(
        isPaymentError(url),
        `Expected payment error — url: ${url}`,
    ).toBe(true);
}

export async function expectValidResponse(page, label) {
    const url = page.url();
    const content = await page.content();
    const fatal = hasFatalError(content);
    const confirmation = isOrderConfirmation(url);
    const paymentErr = isPaymentError(url);

    expect(fatal, `${label} has fatal errors — url: ${url}`).toBe(false);
    expect(
        confirmation || paymentErr,
        `${label} must show confirmation or error — url: ${url}`,
    ).toBe(true);

    return { confirmation, paymentErr };
}
