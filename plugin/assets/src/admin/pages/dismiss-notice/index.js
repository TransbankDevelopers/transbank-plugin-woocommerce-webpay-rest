import { ApiService } from '../../modules/api/apiService';
import { NoticeManager } from '../../modules/ui/noticeManager';
import { DOMUtils } from '../../utils/dom';
import { getAjaxConfig } from '../../utils/getAjaxConfig';

DOMUtils.ready(() => {
    const config = getAjaxConfig();
    
    if (!config) {
        return;
    }

    const apiService = new ApiService(config.ajax_url, config.nonce);
    
    const noticeManager = new NoticeManager('#mainform', apiService);
    noticeManager.initDismissListeners('notice-dismiss', '.notice');
});
