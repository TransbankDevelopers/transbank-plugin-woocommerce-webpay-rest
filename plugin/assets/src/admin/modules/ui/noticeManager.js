import { elementFactory } from '../../utils/elementFactory';

export class NoticeManager {
    constructor(containerSelector = '', apiService = null) {
        this.containerSelector = containerSelector;
        this.apiService = apiService;
    }

    renderNotice(options = {}) {
        return this.createNotice(this.containerSelector, options);
    }

    createNotice(target, options = {}) {
        const {
            type = "info",
            message = "",
            title = "",
            dismissible = true,
            timeoutMs = 0
        } = options;

        const container =
            typeof target === "string" ? document.querySelector(target) : target;

        if (!container) return { destroy: () => { }, el: null };

        const noticeChildren = [];

        if (title) {
            const messageNode = document.createTextNode(message);
            const strong = elementFactory("strong", {}, [String(title)]);
            const divider = elementFactory("br", {}, []);
            const titleP = elementFactory("p", {}, [strong, divider, messageNode]);
            noticeChildren.push(titleP);
        }

        let dismissBtn = null;

        if (dismissible) {
            dismissBtn = elementFactory("button", {
                type: "button",
                className: "notice-dismiss",
                ariaLabel: "Dismiss"
            }, []);

            noticeChildren.push(dismissBtn);
        }

        const notice = elementFactory(
            "div",
            {
                className: `notice notice-${type} ${dismissible ? "is-dismissible" : ""} tbk-admin-notice`,
                role: "alert"
            },
            noticeChildren
        );

        container.prepend(notice);

        let timeoutId = null;

        if (timeoutMs && Number(timeoutMs) > 0) {
            timeoutId = setTimeout(() => this.destroy(timeoutId, notice), Number(timeoutMs));
        }

        if (dismissible && dismissBtn) {
            dismissBtn.addEventListener("click", () => this.destroy(timeoutId, notice));
        }

        return { destroy: () => this.destroy(timeoutId, notice), el: notice };
    }

    destroy(timeoutId, noticeElement) {
        if (timeoutId)
            clearTimeout(timeoutId);

        if (!noticeElement || noticeElement?.dataset?.removing === '1')
            return;

        noticeElement.dataset.removing = '1';
        
        noticeElement.addEventListener("transitionend", this.handleTransitionEnd);

        noticeElement.classList.add("tbk-admin-notice--fade-out");

        setTimeout(() => {
            if (noticeElement.isConnected) {
                noticeElement.removeEventListener("transitionend", this.handleTransitionEnd);
                noticeElement.remove();
            }
        }, 350);
    };

    handleTransitionEnd(event) {
        if (event.propertyName && event.propertyName !== "opacity")
            return;

        const el = event.currentTarget;
        el.removeEventListener("transitionend", this.handleTransitionEnd);
        el.remove();
    }

    async dismiss(noticeId) {
        try {
            await this.apiService?.post?.('dismiss_notice', { notice_id: noticeId });
        } catch {

        }
    }

    initDismissListeners(noticeDismissClass, noticeSelector) {
        const container = document.querySelector(this.containerSelector);

        if (!container)
            return;

        container.addEventListener('click', (e) => {
            const dismissButton = e.target.closest(`.${noticeDismissClass}`);
            if (!dismissButton)
                return;

            const notice = dismissButton.closest(noticeSelector);

            if (notice?.id) {
                this.dismiss(notice.id);
            }
        });
    }
}
