import { ApiService } from '../../modules/api/apiService';
import { NoticeManager } from '../../modules/ui/noticeManager';
import { LogDownloader } from '../../modules/logs/logDownloader';
import { DOMUtils } from '../../utils/dom';
import { getAjaxConfig } from '../../utils/getAjaxConfig';

DOMUtils.ready(() => {
    if (!document.querySelector('#btnDownload')) {
        return;
    }
    
    const config = getAjaxConfig();
    
    if (!config) {
        return;
    }

    const apiService = new ApiService(config.ajax_url, config.nonce);

    const noticeManager = new NoticeManager('#logs-container');

    const logDownloader = new LogDownloader(apiService, '#btnDownload', '#log_file', noticeManager);
    logDownloader.init();
});
