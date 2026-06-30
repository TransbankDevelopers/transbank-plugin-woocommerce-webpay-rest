import { test, expect } from "@playwright/test";
import {
    continueToCommerce,
    extractTokenFromUrl
} from "../../../helpers/webpay-form.js";
import {
    getOrderCountByToken,
    holdLock,
    isLockHeld,
    closePool
} from "../../../helpers/database.js";
import { expectPaymentError } from "../../../helpers/assertions.js";
import {
    runCheckoutFlow,
    holdReturnRequests,
    hasErrorContent
} from "../../../helpers/concurrent.js";

const captureAndDuplicateWithLock = async (context, page, returnHolder) => {
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
        timeout: 120_000
    });

    await expect
        .poll(() => returnHolder.commerceReturns.length, {
            message: "Waiting for both returns to be intercepted",
            timeout: 45_000,
            intervals: [500]
        })
        .toBeGreaterThanOrEqual(2);

    const token = extractTokenFromUrl(commerceReturnUrl);
    expect(token, "Could not extract token from the return URL").toBeTruthy();

    const externalLock = await holdLock(token);
    expect(
        await isLockHeld(token),
        "External lock must be active before releasing returns"
    ).toBe(true);
    console.log(`[INTERCEPTOR] External lock acquired for token: ${token}`);

    console.log(
        `[INTERCEPTOR] Both returns intercepted (${returnHolder.commerceReturns.length}). Releasing simultaneously...`
    );
    returnHolder.release();

    duplicateNavigation.catch((err) => {
        console.log(`[INTERCEPTOR] Duplicate navigation error: ${err.message}`);
    });

    return { duplicatePage, externalLock };
};

test.describe("Webpay Plus — Max retries exhausted", () => {
    test(
        "Duplicate request shows error after retries exhaust",
        { timeout: 120_000 },
        async ({ browser, baseURL }) => {
            console.log("═══ START: Max retries exhausted ═══");
            const context = await browser.newContext({
                baseURL,
                ignoreHTTPSErrors: true
            });
            const returnHolder = await holdReturnRequests(context);
            const page = await context.newPage();
            let duplicatePage;
            let externalLock;

            try {
                await test.step("Full checkout up to Transbank", async () =>
                    runCheckoutFlow(page));

                await test.step("Capture return URL, duplicate request and acquire external lock", async () => {
                    ({ duplicatePage, externalLock } =
                        await captureAndDuplicateWithLock(
                            context,
                            page,
                            returnHolder
                        ));
                });

                const checkErrorContent = () => hasErrorContent(duplicatePage);

                await test.step("Wait for Request B retries to exhaust", async () => {
                    await expect
                        .poll(checkErrorContent, {
                            timeout: 60_000,
                            intervals: [1_000],
                            message: "Waiting for Request B error page to render"
                        })
                        .toBe(true);
                });

                await test.step("Verify Request B shows a payment error (not a fatal error)", async () => {
                    await expectPaymentError(duplicatePage);
                    console.log(
                        `[INTERCEPTOR] Request B (duplicate): url=${duplicatePage.url()}, payment_error=true`
                    );
                });

                await test.step("Verify no duplicate order was created", async () => {
                    const token = extractTokenFromUrl(
                        returnHolder.commerceReturns[0]
                    );
                    const orderCount = await getOrderCountByToken(token);
                    console.log(`[INTERCEPTOR] Orders for this token: ${orderCount}`);
                    expect(
                        orderCount,
                        "At most 1 order must exist (no duplicate from Request B)"
                    ).toBeLessThanOrEqual(1);
                });
            } finally {
                await externalLock?.release();
                console.log("═══ END: Max retries exhausted ═══");
                await closePool();
                await context.close();
            }
        }
    );
});
