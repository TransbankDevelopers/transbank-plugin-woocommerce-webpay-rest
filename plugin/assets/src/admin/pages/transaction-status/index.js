import { TransactionStatusService } from '../../modules/transaction/transactionStatusService';
import { TransactionRenderer } from '../../modules/transaction/transactionRenderer';
import { createTransactionSchema } from '../../modules/transaction/transactionSchema';
import { ApiService } from '../../modules/api/apiService';
import { ButtonBusyManager } from '../../modules/ui/busyManager';
import { DOMUtils } from '../../utils/dom';
import { getAjaxConfig } from '../../utils/getAjaxConfig';

DOMUtils.ready(() => {
    const buttonStatus = document.querySelector('.get-transaction-status');
    const statusContainer = document.querySelector('#transaction_status_admin');

    if (!buttonStatus || !statusContainer) {
        return;
    }

    const config = getAjaxConfig();
    
    if (!config) {
        return;
    }

    const apiService = new ApiService(config.ajax_url, config.nonce);

    const buttonBusyManager = new ButtonBusyManager();

    const statusSchema = createTransactionSchema();
    const renderer = new TransactionRenderer(statusContainer, statusSchema);

    const transactionService = new TransactionStatusService(
        apiService,
        buttonBusyManager,
        renderer,
        buttonStatus
    );

    transactionService.init();
});
