export class BuyOrderFormatComponent {
    constructor(service) {
        this.service = service;
        this.instances = new Map();
    }

    attach(inputId, defaultFormat, options = {}) {
        if (this.instances.has(inputId)) {
            this.destroy(inputId);
        }

        const input = document.querySelector(inputId);
        if (!input) {
            return;
        }

        const instance = {
            inputId,
            input,
            defaultFormat,
            addHelpText: Boolean(options.addHelpText),
            errorDisplay: null,
            valueDisplay: null,
            helpTextNode: null,
            isOneClick: Boolean(options.isOneClick),
            onChange:
                typeof options.onChange === "function"
                    ? options.onChange
                    : null,
            getOtherFormat:
                typeof options.getOtherFormat === "function"
                    ? options.getOtherFormat
                    : null,
            inputHandler: null
        };

        this.createUI(instance);
        this.bind(instance);
        this.instances.set(inputId, instance);
        this.refresh(inputId);
    }

    destroy(inputId) {
        const instance = this.instances.get(inputId);
        if (!instance) {
            return;
        }

        this.unbind(instance);
        this.instances.delete(inputId);
    }

    unbind(instance) {
        if (!instance?.input || !instance?.inputHandler) {
            return;
        }

        instance.input.removeEventListener("input", instance.inputHandler);
        instance.input.removeEventListener("change", instance.inputHandler);
        instance.inputHandler = null;
    }

    refresh(inputId) {
        const instance = this.instances.get(inputId);
        if (!instance) {
            return;
        }

        const format = instance.input.value;
        const otherFormat = instance.getOtherFormat
            ? instance.getOtherFormat()
            : null;
        const result = this.service.validateAndPreview(format, otherFormat);

        if (!result.valid) {
            this.renderError(instance, result.error || "Formato inválido");
            return;
        }

        this.renderSuccess(
            instance,
            `✅ Vista previa: ${result.preview} (${result.length} caracteres)`
        );
    }

    reset(inputId) {
        const instance = this.instances.get(inputId);
        if (!instance) {
            return;
        }

        instance.input.value = instance.defaultFormat;
        this.refresh(inputId);
    }

    createUI(instance) {
        const { input } = instance;

        const wrapper = document.createElement("div");
        wrapper.className = "tbk-buy-order-format-container";

        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        const refreshBtn = this.createButton("Refrescar", "primary", (e) => {
            e.preventDefault();
            this.refresh(instance.inputId);
            if (instance.onChange) {
                instance.onChange(instance.input.value);
            }
        });

        const resetBtn = this.createButton("Restablecer", "secondary", (e) => {
            e.preventDefault();
            this.reset(instance.inputId);
            if (instance.onChange) {
                instance.onChange(instance.input.value);
            }
        });

        wrapper.appendChild(refreshBtn);
        wrapper.appendChild(resetBtn);

        const valueDisplay = document.createElement("div");
        valueDisplay.className = "tbk-buy-order-format-value-display";

        const errorDisplay = document.createElement("div");
        errorDisplay.className = "tbk-buy-order-format-error-display tbk-hide";

        wrapper.parentNode.insertBefore(valueDisplay, wrapper.nextSibling);
        wrapper.parentNode.insertBefore(errorDisplay, valueDisplay.nextSibling);

        instance.valueDisplay = valueDisplay;
        instance.errorDisplay = errorDisplay;

        if (instance.addHelpText) {
            const help = this.createHelpText(instance.isOneClick);
            wrapper.parentNode.insertBefore(help, errorDisplay.nextSibling);
            instance.helpTextNode = help;
        }
    }

    createButton(text, type, onClick) {
        const button = document.createElement("button");
        button.type = "button";
        button.textContent = text;
        button.className = `button button-${type} tbk-button-${type}`;
        button.addEventListener("click", onClick);
        return button;
    }

    createHelpText(isOneClick) {
        const helpText = document.createElement("div");
        helpText.className = "tbk-buy-order-format-help-text";

        helpText.appendChild(document.createElement("br"));
        helpText.appendChild(document.createElement("br"));
        helpText.appendChild(this.createStrongParagraph("ℹ️ Información: "));
        helpText.appendChild(
            this.createStrongParagraph("Componentes disponibles:")
        );
        helpText.appendChild(
            this.createParagraphWithCode(
                "•",
                "{orderId}",
                " Número de orden de compra en Woocommerce (obligatorio)."
            )
        );
        helpText.appendChild(
            this.createParagraphWithCode(
                "•",
                "{random}",
                " Texto aleatorio con longitud de 8 caracteres (opcional)."
            )
        );
        helpText.appendChild(
            this.createParagraphWithCode(
                "•",
                "{random, length=12}",
                " Texto aleatorio con longitud especifica (opcional)."
            )
        );
        helpText.appendChild(
            this.createParagraphWithCode(
                "Ejemplo: ",
                "cualquierTexto-{random, length=12}-{orderId}",
                "",
                true
            )
        );
        helpText.appendChild(this.createStrongParagraph("Notas:"));
        helpText.appendChild(
            this.createParagraphWithInlineCode([
                "•Solo se permiten caracteres alfanuméricos, guiones (",
                { code: "-" },
                "), guiones bajos (",
                { code: "_" },
                ") o dos puntos (",
                { code: ":" },
                "). No se permiten espacios."
            ])
        );
        helpText.appendChild(
            this.createParagraph(
                "•El valor generado no puede exceder los 26 caracteres."
            )
        );

        if (isOneClick) {
            helpText.appendChild(
                this.createParagraph(
                    "•El formato de orden de compra hija debe ser distinto al formato de orden de compra principal."
                )
            );
        }

        return helpText;
    }

    createParagraph(text) {
        const paragraph = document.createElement("p");
        paragraph.textContent = text;
        return paragraph;
    }

    createStrongParagraph(text) {
        const paragraph = document.createElement("p");
        const strong = document.createElement("strong");
        strong.textContent = text;
        paragraph.appendChild(strong);
        return paragraph;
    }

    createParagraphWithCode(prefix, codeValue, suffix, strongPrefix = false) {
        const paragraph = document.createElement("p");

        if (strongPrefix) {
            const prefixNode = document.createElement("strong");
            prefixNode.textContent = prefix;
            paragraph.appendChild(prefixNode);
        } else {
            paragraph.appendChild(document.createTextNode(prefix));
        }

        const code = document.createElement("code");
        code.textContent = codeValue;
        paragraph.appendChild(code);
        paragraph.appendChild(document.createTextNode(suffix));

        return paragraph;
    }

    createParagraphWithInlineCode(parts) {
        const paragraph = document.createElement("p");

        parts.forEach((part) => {
            if (typeof part === "string") {
                paragraph.appendChild(document.createTextNode(part));
                return;
            }

            const code = document.createElement("code");
            code.textContent = part.code;
            paragraph.appendChild(code);
        });

        return paragraph;
    }

    bind(instance) {
        const handler = this.debounce(() => {
            this.refresh(instance.inputId);
            if (instance.onChange) {
                instance.onChange(instance.input.value);
            }
        }, 200);
        instance.inputHandler = handler;

        instance.input.addEventListener("input", handler);
        instance.input.addEventListener("change", handler);
    }

    renderError(instance, message) {
        instance.errorDisplay.classList.remove("tbk-hide");
        instance.errorDisplay.classList.add(
            "tbk-buy-order__format-status--is-visible"
        );
        instance.errorDisplay.textContent = `❌ ${message}`;

        instance.valueDisplay.classList.remove(
            "tbk-buy-order__format-status--is-visible"
        );
        instance.valueDisplay.textContent = "";

        instance.input.classList.remove("tbk-input-valid");
        instance.input.classList.add("tbk-input-error");
    }

    renderSuccess(instance, message) {
        instance.errorDisplay.classList.remove(
            "tbk-buy-order__format-status--is-visible"
        );
        instance.errorDisplay.classList.add("tbk-hide");
        instance.errorDisplay.textContent = "";

        instance.valueDisplay.classList.remove("tbk-hide");
        instance.valueDisplay.classList.add(
            "tbk-buy-order__format-status--is-visible"
        );
        instance.valueDisplay.textContent = message;

        instance.input.classList.remove("tbk-input-error");
        instance.input.classList.add("tbk-input-valid");
    }

    debounce(fn, ms) {
        let t = null;
        return (...args) => {
            if (t) {
                clearTimeout(t);
            }
            t = setTimeout(() => fn(...args), ms);
        };
    }
}
