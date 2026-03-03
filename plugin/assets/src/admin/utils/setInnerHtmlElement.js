import { elementFactory } from "./elementFactory";

export function setInnerHtmlElement(selector, element, props, children = []) {
    const container =
        typeof selector === "string"
            ? document.querySelector(selector)
            : selector;

    if (!container) return;

    const node = elementFactory(element, props, children);
    container.replaceChildren(node);
    return node;
}
