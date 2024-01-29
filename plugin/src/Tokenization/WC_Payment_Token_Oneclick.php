<?php

namespace Transbank\WooCommerce\WebpayRest\Tokenization;

use Transbank\Webpay\Options;

class WC_Payment_Token_Oneclick extends \WC_Payment_Token
{
    protected $type = 'Oneclick';

    protected $extra_data = [
        'last4'        => '',
        'username'     => '',
        'email'        => '',
        'card_type'    => '',
        'environment'  => '',
    ];

    /**
     * Get type to display to user.
     *
     * @since  2.6.0
     *
     * @param string $deprecated Deprecated since WooCommerce 3.0.
     *
     * @return string
     */
    public function get_display_name($deprecated = '')
    {
        $testPrefix = ($this->get_environment() === Options::ENVIRONMENT_PRODUCTION ? '' : '[TEST] ');

        return  $testPrefix.$this->get_card_type().' terminada en '.$this->get_last4();
    }

    public function validate()
    {
        if (false === parent::validate()) {
            return false;
        }
        if (!$this->get_last4('edit')) {
            return false;
        }

        if (!$this->get_card_type('edit')) {
            return false;
        }

        if (!$this->get_username('edit')) {
            return false;
        }

        if (!$this->get_environment('edit')) {
            $this->set_environment(Options::ENVIRONMENT_INTEGRATION);

            return false;
        }

        if (!$this->get_email('edit') || !filter_var($this->get_email('edit'), FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return true;
    }

    /**
     * Hook prefix.
     *
     * @since 3.0.0
     */
    protected function get_hook_prefix()
    {
        return 'woocommerce_payment_token_oneclick_get_';
    }

    /**
     * Returns the card type (mastercard, visa, ...).
     *
     * @since  2.6.0
     *
     * @param string $context What the value is for. Valid values are view and edit.
     *
     * @return string Card type
     */
    public function get_card_type($context = 'view')
    {
        return $this->get_prop('card_type', $context);
    }

    /**
     * Set the card type (mastercard, visa, ...).
     *
     * @since 2.6.0
     *
     * @param string $type Credit card type (mastercard, visa, ...).
     */
    public function set_card_type($type)
    {
        $this->set_prop('card_type', $type);
    }

    /**
     * Returns the last four digits.
     *
     * @since  2.6.0
     *
     * @param string $context What the value is for. Valid values are view and edit.
     *
     * @return string Last 4 digits
     */
    public function get_last4($context = 'view')
    {
        return $this->get_prop('last4', $context);
    }

    /**
     * Set the last four digits.
     *
     * @since 2.6.0
     *
     * @param string $last4 Credit card last four digits.
     */
    public function set_last4($last4)
    {
        $this->set_prop('last4', $last4);
    }

    /**
     * Returns the last four digits.
     *
     * @since  2.6.0
     *
     * @param string $context What the value is for. Valid values are view and edit.
     *
     * @return string Last 4 digits
     */
    public function get_username($context = 'view')
    {
        return $this->get_prop('username', $context);
    }

    /**
     * Set the last four digits.
     *
     * @since 2.6.0
     *
     * @param string $username Credit card last four digits.
     */
    public function set_username($username)
    {
        $this->set_prop('username', $username);
    }

    /**
     * Returns the token oneclick environement.
     *
     * @param string $context What the value is for. Valid values are view and edit.
     *
     * @return string [TEST|LIVE]
     */
    public function get_environment($context = 'view')
    {
        return $this->get_prop('environment', $context);
    }

    /**
     * Set the token environment.
     *
     * @param string $environment Credit card last four digits.
     */
    public function set_environment($environment)
    {
        $this->set_prop('environment', $environment);
    }

    /**
     * Returns the last four digits.
     *
     * @since  2.6.0
     *
     * @param string $context What the value is for. Valid values are view and edit.
     *
     * @return string Last 4 digits
     */
    public function get_email($context = 'view')
    {
        return $this->get_prop('email', $context);
    }

    /**
     * Set the last four digits.
     *
     * @since 2.6.0
     *
     * @param string $email Credit card last four digits.
     */
    public function set_email($email)
    {
        $this->set_prop('email', $email);
    }
}
