<?php

namespace Transbank\Plugin\Model;

class OneclickConfig extends ProductConfig {
    public $childCommerceCode = null;
    public $childBuyOrderFormat = null;

    public function __construct($data = null) {
        parent::__construct($data);
        if (!is_null($data)) {
            $this->childCommerceCode = $data['childCommerceCode'] ?? null;
            $this->childBuyOrderFormat = $data['childBuyOrderFormat'] ?? null;
        }
    }

    public function getChildCommerceCode()
    {
        return $this->childCommerceCode;
    }

    public function getChildBuyOrderFormat()
    {
        return $this->childBuyOrderFormat;
    }
}
