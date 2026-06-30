// @ts-check
import "dotenv/config";
import { defineConfig } from "@playwright/test";

export default defineConfig({
    testDir: "./specs",
    fullyParallel: false,
    workers: 1,
    timeout: 120_000,
    expect: {
        timeout: 15_000
    },
    retries: 0,
    reporter: [["html", { open: "never" }], ["list"]],
    use: {
        baseURL: process.env.BASE_URL,
        ignoreHTTPSErrors: true,
        trace: "retain-on-failure",
        screenshot: "only-on-failure",
        video: "retain-on-failure",
        actionTimeout: 15_000,
        navigationTimeout: 45_000
    }
});
