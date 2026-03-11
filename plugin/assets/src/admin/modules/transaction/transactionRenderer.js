import { elementFactory } from "../../utils/elementFactory";

export class TransactionRenderer {
    constructor(container, schema) {
        this.container =
            typeof container === "string"
                ? document.querySelector(container)
                : container;

        this.schema = schema;
    }

    clear() {
        this.container?.replaceChildren?.();
    }

    render(data = {}) {
        if (!this.container)
            return;

        this.clear();

        const fragment = document.createDocumentFragment();
        fragment.appendChild(this.createSeparator());

        const fields = this.schema.fields;

        Object.entries(data || {}).forEach(([key, value]) => {
            const field = fields[key];
            if (!field)
                return;

            fragment.appendChild(
                this.createField({ key, ...field }, value)
            );
        });

        this.container.appendChild(fragment);
    }

    renderError(message) {
        if (!this.container)
            return;

        this.clear();

        this.container.appendChild(this.createSeparator());
        this.container.appendChild(
            elementFactory("div", { className: "tbk-status tbk-status-error" }, [
                elementFactory("i", {
                    className: "fa fa-times"
                }),
                elementFactory("p", {
                    textContent: message,
                }),
            ])
        );
    }

    createSeparator() {
        return elementFactory("div", { className: "tbk-separator" });
    }

    createField(field, value) {
        const valueClass = this.resolveClassName(field, value);

        return elementFactory("div", { className: "tbk-field" }, [
            elementFactory("span", {
                className: "tbk-field-name",
                textContent: field.label || field.key,
            }),
            elementFactory("span", {
                className: valueClass,
                textContent: value,
            }),
        ]);
    }

    resolveClassName(field, value) {
        const base = ["tbk-field-value"];

        if (!field.className)
            return base.join(" ");

        const extra =
            typeof field.className === "function"
                ? field.className(value)
                : field.className;

        const arr = Array.isArray(extra) ? extra : String(extra).split(" ");
        return base.concat(arr.filter(Boolean)).join(" ").trim();
    }
}
