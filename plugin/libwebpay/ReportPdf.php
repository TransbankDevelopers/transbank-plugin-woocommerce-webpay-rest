<?php

if (!defined('ABSPATH')) {
    exit;
}

class ReportPdf
{
    
    var $buffer;
    
    public function __construct()
    {
        $this->buffer = '<html>
        <head>
        <link href="' . __DIR__ . '/css/ReportPdf.css" rel="stylesheet" type="text/css" media="all" />
        </head>
        <body>';
    }
    
    private function chain($element, $level)
    {
        if ($level == 0) {
            $this->buffer .= '<table>';
        }
        
        if (is_array($element)) {
            $child_lvl = $level + 1;
            $child = array_keys($element);
            for ($count_child = 0; $count_child < count($child); $count_child++) {
                
                if ($child[$count_child] == 'php_info') {
                    $this->buffer .= '<tr><td colspan="2" class="pdf1">' . $child[$count_child] . '</td></tr>';
                    $this->buffer .= '<tr><td colspan="2" >' . $element['php_info']['string']['content'] . '</td></tr>';
                } else {
                    if ($child[$count_child] == 'log') {
                        $this->buffer .= '<tr><td colspan="2" class="log">' . $element['log'] . '</td></tr>';
                    } else {
                        if ($child[$count_child] == 'public_cert' || $child[$count_child] == 'private_key' || $child[$count_child] == 'webpay_cert') {
                        
                        } else {
                            if ($child_lvl != 3) {
                                $this->buffer .= '<tr><td colspan="2" class="pdf' . $child_lvl . '">' . $child[$count_child] . '</td></tr>';
                            } else {
                                $this->buffer .= '<tr><td class="pdf' . $child_lvl . '">' . $child[$count_child] . '</td>';
                            }
                            
                            $this->chain($element[$child[$count_child]], $child_lvl);
                        }
                    }
                }
            }
        } else {
            $this->buffer .= '<td class="final">' . $element . '</td></tr>';
        }
        if ($level == 0) {
            $this->buffer .= '</table></body></html>';
        }
    }
    
    public function getReport($myJSON)
    {
        $obj = json_decode($myJSON, true);
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);
        $pdf->SetMargins(10, 5, 10, false);
        $pdf->AddPage();
        $pdf->setFontSubsetting(false);
        $this->chain($obj, 0);
        $pdf->writeHTML($this->buffer, 0, 1, 0, true, '');
        $pdf->Output('report_webpay_' . date_timestamp_get(date_create()) . '.pdf', 'D');
    }
}
