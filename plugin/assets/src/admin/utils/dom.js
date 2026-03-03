export class DOMUtils {
    static ready(callback) {
       if (typeof callback !== "function") return;

        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", callback, { once: true });
            return;
        }

        Promise.resolve().then(callback);
    }
}
