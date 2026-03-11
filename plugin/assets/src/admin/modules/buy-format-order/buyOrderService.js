export class BuyOrderService {
    constructor(validator, previewGenerator) {
        this.validator = validator;
        this.previewGenerator = previewGenerator;
    }

    validateAndPreview(format, otherFormat = null) {
        const validation = this.validator.validate(format);
        if (!validation.valid)
            return validation;

        if (otherFormat !== null) {
            const diff = this.validator.validateDifferent(format, otherFormat.format, otherFormat.customMessage);
            if (!diff.valid)
                return diff;
        }

        const preview = this.previewGenerator.generate(format);
        const length = preview.length;

        if (this.validator.exceedsMaxLength(length)) {
            return {
                valid: false,
                error: `El formato genera ${length} caracteres, pero el máximo permitido es ${this.validator.maxLength}`,
            };
        }

        return { valid: true, preview, length };
    }
}
