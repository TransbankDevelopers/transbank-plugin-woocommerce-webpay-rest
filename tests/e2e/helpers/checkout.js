const CUSTOMER = {
    email: process.env.CUSTOMER_EMAIL,
    password: process.env.CUSTOMER_PASSWORD
};

export async function login(page) {
    await page.goto("/?page_id=8");
    await page.locator("#username").fill(CUSTOMER.email);
    await page.locator("#password").fill(CUSTOMER.password);
    await page.locator('button[name="login"]').click();
    await page.waitForLoadState("domcontentloaded", { timeout: 15_000 });
}

export async function addProductToCart(page) {
    await page.goto("/?post_type=product");
    await page.locator('[data-wp-text="state.addToCartText"]').first().click();
    await page.waitForTimeout(2_000);
    await page.goto("/?page_id=6");
    await page.waitForLoadState("domcontentloaded");
}

export async function goThroughCheckoutWithWebpay(page) {
    await page.goto("/?page_id=7");
    await page.waitForLoadState("domcontentloaded");
    await page.locator(".wc-block-components-checkout-place-order-button").click();
    await page.waitForURL(/webpay3gint\.transbank\.cl|tbk\.cl/, {
        timeout: 45_000
    });
}
