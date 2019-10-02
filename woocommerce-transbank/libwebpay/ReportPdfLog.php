<?php
require_once('LogHandler.php');
require_once('ReportPdf.php');

class ReportPdfLog {

    public function __construct($document){
        $this->document = $document;
    }

    function getReport($myJSON){
        $loghandler = new LogHandler();
        $json = json_decode($loghandler->getLastLog(),true);
        $obj = json_decode($myJSON,true);
        if (isset($json['log_content']) && $this->document == 'report'){
            $html = str_replace("\r\n","<br>",$json['log_content']);
            $html = str_replace("\n","<br>",$json['log_content']);
            $text = explode ("<br>" ,$html);
            $html='';
            foreach ($text as $row){
                $html .= '<b>'.substr($row,0,21).'</b> '.substr($row,22).'<br>';
            }
            $obj += array('logs' => array('log' => $html));
        }
        $report = new ReportPdf();
        $report->getReport(json_encode($obj));
    }
}
