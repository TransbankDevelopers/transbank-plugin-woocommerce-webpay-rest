import { test, expect } from "@playwright/test";
import {
    continueToCommerce,
    extractTokenFromUrl
} from "../../../helpers/webpay-form.js";
import {
    getOrderCountByToken,
    getTransactionStatus,
    closePool
} from "../../../helpers/database.js";
import { expectValidResponse } from "../../../helpers/assertions.js";
import {
    runCheckoutFlow,
    holdReturnRequests,
    hasNavigatedPastValidation
} from "../../../helpers/concurrent.js";

const captureAndDuplicateReturn = async (context, page, returnHolder) => {
    await continueToCommerce(page);

    await expect
        .poll(() => returnHolder.commerceReturns.length, {
            message: "Waiting for first intercepted return",
            timeout: 45_000,
            intervals: [500]
        })
        .toBeGreaterThanOrEqual(1);

    const commerceReturnUrl = returnHolder.commerceReturns[0];

    const duplicatePage = await context.newPage();
    const duplicateNavigation = duplicatePage.goto(commerceReturnUrl, {
        waitUntil: "commit",
        timeout: 45_000
    });

    await expect
        .poll(() => returnHolder.commerceReturns.length, {
            message: "Waiting for both returns to be intercepted",
            timeout: 45_000,
            intervals: [500]
        })
        .toBeGreaterThanOrEqual(2);

    console.log(
        `[INTERCEPTOR] Both returns intercepted (${returnHolder.commerceReturns.length}). Releasing...`
    );
    returnHolder.release();

    await Promise.all([
        page.waitForLoadState("load").catch(() => {}),
        duplicateNavigation?.catch(() => {})
    ]);

    await Promise.all([
        page.waitForLoadState("networkidle").catch(() => {}),
        duplicatePage.waitForLoadState("networkidle").catch(() => {})
    ]);

    return duplicatePage;
};

const verifyBothPagesResolved = async (page, duplicatePage) => {
    for (const { label, p } of [
        { label: "Request 1 (original)", p: page },
        { label: "Request 2 (duplicate)", p: duplicatePage }
    ]) {
        await expect
            .poll(() => hasNavigatedPastValidation(p), {
                timeout: 45_000,
                intervals: [1_000],
                message: `${label}: waiting for navigation to finish`
            })
            .toBe(true);

        const { confirmation, paymentErr } = await expectValidResponse(
            p,
            label
        );
        console.log(
            `[INTERCEPTOR] ${label}: url=${p.url()}, confirmation=${confirmation}, payment_error=${paymentErr}`
        );
    }
};

test.describe("Webpay Plus — Lock prevents duplicate orders", () => {
    test("Both requests resolve without error when the return URL receives the same token twice", async ({
        browser,
        baseURL
    }) => {
        console.log("═══ START: Lock prevents duplicate orders ═══");
        const context = await browser.newContext({
            baseURL,
            ignoreHTTPSErrors: true
        });
        const returnHolder = await holdReturnRequests(context);
        const page = await context.newPage();
        let duplicatePage;

        try {
            await test.step("Full checkout up to Transbank", async () =>
                runCheckoutFlow(page));

            await test.step("Capture return URL and open duplicate request", async () => {
                duplicatePage = await captureAndDuplicateReturn(
                    context,
                    page,
                    returnHolder
                );
            });

            await test.step("Verify that 2 requests arrived with the same token", async () => {
                expect(returnHolder.commerceReturns).toHaveLength(2);

                const tokens =
                    returnHolder.commerceReturns.map(extractTokenFromUrl);
                expect(tokens[0]).toBeTruthy();
                expect(tokens[0]).toBe(tokens[1]);
                console.log(`[INTERCEPTOR] token_ws: ${tokens[0]}`);
            });

            await test.step("Verify both requests show a valid response", async () => {
                await verifyBothPagesResolved(page, duplicatePage);
            });

            await test.step("Verify exactly 1 order was created in the database", async () => {
                const token = extractTokenFromUrl(
                    returnHolder.commerceReturns[0]
                );
                expect(
                    token,
                    "Could not extract token from the return URL"
                ).toBeTruthy();

                const orderCount = await getOrderCountByToken(token);
                const txStatus = await getTransactionStatus(token);
                console.log(
                    `[INTERCEPTOR] Orders: ${orderCount}, transaction status: ${txStatus}`
                );

                expect(
                    orderCount,
                    "There must be exactly 1 order for this token"
                ).toBe(1);
                expect(
                    txStatus,
                    "Transaction must be in APPROVED state"
                ).toBe("approved");
            });
        } finally {
            console.log("═══ END: Lock prevents duplicate orders ═══");
            await closePool();
            await context.close();
        }
    });
});
