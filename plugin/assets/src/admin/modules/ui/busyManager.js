export class ButtonBusyManager {
    setBusy(buttonEl, busyNodes = []){
        if (!buttonEl || buttonEl.classList.contains('tbk-is-busy')) {
            return () => { };
        }

        const originalChildren = [...buttonEl.childNodes];

        buttonEl.classList.add("tbk-is-busy");
        buttonEl.dataset.sending = "true";

        const nodes = Array.isArray(busyNodes) ? busyNodes : [busyNodes];

        buttonEl.replaceChildren(...nodes);

        return () => {
            buttonEl.classList.remove("tbk-is-busy");
            buttonEl.dataset.sending = "false";
            buttonEl.replaceChildren(...originalChildren);
        };
    }
}
