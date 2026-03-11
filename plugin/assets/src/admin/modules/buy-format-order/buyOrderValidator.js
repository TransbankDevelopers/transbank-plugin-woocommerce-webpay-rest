export class BuyOrderValidator {
    constructor(options = {}) {
       const {
            maxLength = 26,
            allowedCharsRegex = /^[A-Za-z0-9\-_:]*$/,
            orderIdRequiredRegex = /\{orderId\}/i,
            orderIdPlaceholderRegex = /\{orderId\}/gi,
            randomPlaceholderRegex = /\{random(?:,\s*length=\d+)?\}/gi,
        } = options;

        this.allowedCharsRegex = allowedCharsRegex;
        this.orderIdRequiredRegex = orderIdRequiredRegex;
        this.orderIdPlaceholderRegex = orderIdPlaceholderRegex;
        this.randomPlaceholderRegex = randomPlaceholderRegex;
        this.maxLength = maxLength;
    }

    validate(format) {
        if (!format) {
            return { valid: false, error: "El formato no puede estar vacío" };
        }

        if (!this.orderIdRequiredRegex.test(format)) {
            return { valid: false, error: "El formato debe contener {orderId}" };
        }

        const formatWithoutPlaceholders = this.removePlaceholders(format);

        if (!this.allowedCharsRegex.test(formatWithoutPlaceholders)) {
            return {
                valid: false,
                error:
                    "Formato inválido. Asegúrate de que contenga solo caracteres alfanuméricos, guiones (-), guiones bajos (_) o dos puntos (:), sin espacios, y que contenga {orderId}.",
            };
        }

        return { valid: true, error: null };
    }

    validateDifferent(format1, format2, customMessage = null) {
        if (format1 && format2 && format1.toUpperCase() === format2.toUpperCase()) {
            return {
                valid: false,
                error:
                    customMessage ??
                    "El formato de orden de compra hija no puede ser igual al formato de orden de compra principal",
            };
        }

        return { valid: true, error: null };
    }

    exceedsMaxLength(length) {
        return length > this.maxLength;
    }

    removePlaceholders(format) {
        return String(format)
            .replace(this.orderIdPlaceholderRegex, "")
            .replace(this.randomPlaceholderRegex, "");
    }
}
