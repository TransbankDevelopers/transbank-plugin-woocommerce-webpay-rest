import { expect } from "@playwright/test";

const TEST_CARD = {
    number: "4051885600446623",
    expiry: "12/30",
    cvv: "123"
};

const BANK_SIM = {
    rut: "11.111.111-1",
    password: "123"
};

const S = {
    tarjetasBtn: "#tarjetas",
    cardNumber: "#card-number",
    cardExpiry: "#card-exp",
    cardCvv: "#card-cvv",
    payButton: "app-tarjeta form button.submit",
    bankRut: "#rutClient",
    bankPassword: "#passwordClient",
    bankAccept: 'input[type="submit"][value="Aceptar"]',
    resultVci: "#vci",
    continueBtn: 'input[type="submit"][value="Continuar"]'
};

export async function fillCardAndAuthenticate(page) {
    await page
        .locator(S.tarjetasBtn)
        .waitFor({ state: "visible", timeout: 15_000 });
    await page.locator(S.tarjetasBtn).click();

    await page
        .locator(S.cardNumber)
        .waitFor({ state: "visible", timeout: 15_000 });
    await page.locator(S.cardNumber).fill(TEST_CARD.number);

    await page.locator(S.cardNumber).blur();

    await page
        .locator(S.cardExpiry)
        .waitFor({ state: "visible", timeout: 15_000 });
    await page.locator(S.cardExpiry).fill(TEST_CARD.expiry);

    await page
        .locator(S.cardCvv)
        .waitFor({ state: "visible", timeout: 10_000 });
    await page.locator(S.cardCvv).fill(TEST_CARD.cvv);

    await page.locator(S.payButton).click();

    await page
        .locator(S.bankRut)
        .waitFor({ state: "visible", timeout: 30_000 });
    await page.locator(S.bankRut).fill(BANK_SIM.rut);
    await page.locator(S.bankPassword).fill(BANK_SIM.password);
    await page.locator(S.bankAccept).click();

    await page
        .locator(S.resultVci)
        .waitFor({ state: "visible", timeout: 30_000 });
}

export async function continueToCommerce(page) {
    await expect(page.locator(S.resultVci)).toHaveValue("TSY", {
        timeout: 30_000
    });
    await page.locator(S.continueBtn).click();
}

export function extractTokenFromUrl(url) {
    return /token_ws=([^&]+)/.exec(url)?.[1] ?? null;
}
