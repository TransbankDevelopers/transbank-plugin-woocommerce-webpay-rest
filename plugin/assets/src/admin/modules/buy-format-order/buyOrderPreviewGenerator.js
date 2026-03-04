export class BuyOrderPreviewGenerator {
    constructor(defaultRandomLength = 8) {
        this.defaultRandomLength = defaultRandomLength;
    }

    generate(format) {
        return String(format).replace(
            /\{(orderId|random(?:,\s*length=\d+)?)\}/gi,
            (_, token) => {
                const t = token.toLowerCase();

                if (t === "orderid") {
                    const array = new Uint16Array(1);
                    crypto.getRandomValues(array);
                    const orderId = 10000 + (array[0] % 90000);
                    return orderId;
                }

                const lengthMatch = token.match(/length=(\d+)/i);
                const length = lengthMatch
                    ? parseInt(lengthMatch[1], 10)
                    : this.defaultRandomLength;

                return this.generateRandomString(length);
            },
        );
    }

    generateRandomString(length) {
        const characters =
            "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
        const charactersLength = characters.length;
        let result = "";
        const randomValues = new Uint8Array(length);
        crypto.getRandomValues(randomValues);
        for (let i = 0; i < length; i++) {
            result += characters.charAt(randomValues[i] % charactersLength);
        }
        return result;
    }
}
