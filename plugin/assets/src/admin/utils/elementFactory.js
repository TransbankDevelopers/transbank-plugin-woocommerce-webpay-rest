export function elementFactory(tag, props = {}, children = []) {
    const node = document.createElement(tag);

    Object.entries(props ?? {}).forEach(([key, value]) => {
        if (value === null || value === undefined) return;

        if (key === "className") node.className = value;
        else if (key === "dataset") {
            Object.entries(value).forEach(([dKey, dValue]) => (node.dataset[dKey] = String(dValue)));
        } else if (key.startsWith("on") && typeof value === "function") {
            node.addEventListener(key.substring(2).toLowerCase(), value);
        } else if (key in node) {
            node[key] = value;
        } else {
            node.setAttribute(key, String(value));
        }
    });

    const normalized = (Array.isArray(children) ? children : [children]).flat(Infinity);

    normalized.forEach((child) => {
        if (child === null || child === undefined || child === false)
            return;

        if (typeof child === "string" || typeof child === "number") {
            node.appendChild(document.createTextNode(String(child)));
            return;
        }

        node.appendChild(child);
    });

    return node;
}
