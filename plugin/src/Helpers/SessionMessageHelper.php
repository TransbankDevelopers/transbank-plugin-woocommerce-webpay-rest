<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

class SessionMessageHelper
{
    const TBK_MESSAGE_SESSION_KEY = 'tbk_message';
    const TBK_MESSAGE_TYPE_SESSION_KEY = 'tbk_message_type';

    public static function set($message, $type = 'info')
    {
        $_SESSION[static::TBK_MESSAGE_SESSION_KEY] = $message;
        $_SESSION[static::TBK_MESSAGE_TYPE_SESSION_KEY] = $type;
    }

    public static function clear()
    {
        $_SESSION[static::TBK_MESSAGE_SESSION_KEY] = null;
        $_SESSION[static::TBK_MESSAGE_TYPE_SESSION_KEY] = null;
    }

    public static function getMessage()
    {
        return isset($_SESSION[static::TBK_MESSAGE_SESSION_KEY]) ? $_SESSION[static::TBK_MESSAGE_SESSION_KEY] : null;
    }

    public static function getType()
    {
        return isset($_SESSION[static::TBK_MESSAGE_TYPE_SESSION_KEY]) ? $_SESSION[static::TBK_MESSAGE_TYPE_SESSION_KEY] : null;
    }

    public static function exists()
    {
        return isset($_SESSION[static::TBK_MESSAGE_SESSION_KEY]) && $_SESSION[static::TBK_MESSAGE_SESSION_KEY] !== null;
    }

    public static function printMessage()
    {
        if (static::exists()) {
            wc_print_notice(static::getMessage(), static::getType());
            static::clear();
        }
    }
}
