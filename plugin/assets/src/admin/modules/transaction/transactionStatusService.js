import { elementFactory } from "../../utils/elementFactory";

export class TransactionStatusService {
    constructor(apiService, buttonBusyManager, renderer, buttonStatus) {
        this.apiService = apiService;
        this.buttonBusyManager = buttonBusyManager;
        this.renderer = renderer;
        this.buttonStatus = buttonStatus;
    }

    init() {
        this.buttonStatus?.addEventListener?.('click', (e) => {
            e.preventDefault();
            this.getStatus();
        });
    }

    async getStatus() {
        const spinner = elementFactory('i', {
            className: 'fa fa-spinner fa-spin'
        });
        const text = document.createTextNode('Consultando estado ');
        const release = this.buttonBusyManager.setBusy(this.buttonStatus, [text, spinner]);

        this.renderer.clear();

        try {
            const data = {
                order_id: this.buttonStatus.dataset.orderId,
                buy_order: this.buttonStatus.dataset.buyOrder,
                token: this.buttonStatus.dataset.token
            };

            const response = await this.apiService.post('get_transaction_status', data);
            this.renderer.render(response);
        } catch (error) {
            const message = error?.message || 'Error al consultar el estado de la transacción';
            this.renderer.renderError(message);
        } finally {
            release();
        }
    }
}
