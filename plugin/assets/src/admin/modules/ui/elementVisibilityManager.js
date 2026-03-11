export class ElementVisibilityManager {
    constructor(hideClass = 'tbk-hide') {
        this.hideClass = hideClass;
    }

    hide(selector) {
        const element = this.getElement(selector);
        if (element) {
            element.classList.add(this.hideClass);
        }
    }

    show(selector) {
        const element = this.getElement(selector);
        if (element) {
            element.classList.remove(this.hideClass);
        }
    }

    toggle(selector) {
        const element = this.getElement(selector);
        if (element) {
            element.classList.toggle(this.hideClass);
        }
    }

    getElement(target) {
        let element = null;

        if (target) {
            if (target instanceof Element) {
                element = target;
            } else if (typeof target === "string") {
                element = document.querySelector(target);
            }
        }

        return element;
    }
}
