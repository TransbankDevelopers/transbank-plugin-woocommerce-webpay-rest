export function setText(target, value = "") {
    const el =
        typeof target === "string"
            ? document.querySelector(target)
            : target;

    if (!el) return;

    el.textContent = value;
}
