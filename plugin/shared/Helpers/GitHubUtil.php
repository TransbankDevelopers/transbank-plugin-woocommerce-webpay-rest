<?php

namespace Transbank\Plugin\Helpers;

class GitHubUtil
{
    /**
     * Este método obtiene las últimas versiones publicas de los ecommerces en github
     * (no compatible con virtuemart) lo ideal es que el :usuario/:repo sean entregados como string,
     * permite un maximo de 60 consultas por hora
     *
     * @param string $ecommerce Es el nombre del ecommerce a validar.
     * @return array
     */
    public static function getLastGitHubReleaseVersion($ecommerce)
    {
        $baseurl = 'https://api.github.com/repos/'.$ecommerce.'/releases/latest';
        $agent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseurl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $agent);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 2);
        $content = curl_exec($ch);
        curl_close($ch);
        $con = json_decode($content, true);
        return array_key_exists('tag_name', $con) ? $con['tag_name'] : '';
    }
}
