import { ApiService } from '../../modules/api/apiService';
import { NoticeManager } from '../../modules/ui/noticeManager';
import { LogDownloader } from '../../modules/logs/logDowloander';
import { DOMUtils } from '../../utils/dom';

DOMUtils.ready(() => {
    if (!document.querySelector('#btnDownload')) {
        return;
    }

    const apiService = new ApiService(
        window.ajax_object?.ajax_url ?? '',
        window.ajax_object?.nonce ?? ''
    );

    const noticeManager = new NoticeManager('#logs-container');

    const logDownloader = new LogDownloader(apiService, '#btnDownload', '#log_file', noticeManager);
    logDownloader.init();
});
