<?php

use Transbank\WooCommerce\WebpayRest\Controllers\CommitWebpayController;
use PHPUnit\Framework\TestCase;

class TestableCommitController extends CommitWebpayController {
    public function __construct(){}
    public function callGetWebpayFlow(array $request): string {
        return $this->getWebpayFlow($request);
    }
}

class CommitWebpayControllerTest extends TestCase
{
    public function test_get_webpay_flow()
    {
        $controller = new TestableCommitController();

        $this->assertEquals(CommitWebpayController::WEBPAY_ERROR_FLOW,
            $controller->callGetWebpayFlow(['token_ws' => 'abc', 'TBK_TOKEN' => 'def']));

        $this->assertEquals(CommitWebpayController::WEBPAY_ABORTED_FLOW,
            $controller->callGetWebpayFlow(['TBK_TOKEN' => 'def', 'TBK_ID_SESION' => 'ghi']));

        $this->assertEquals(CommitWebpayController::WEBPAY_TIMEOUT_FLOW,
            $controller->callGetWebpayFlow(['TBK_ID_SESION' => 'ghi']));

        $this->assertEquals(CommitWebpayController::WEBPAY_NORMAL_FLOW,
            $controller->callGetWebpayFlow(['token_ws' => 'abc']));

        $this->assertEquals(CommitWebpayController::WEBPAY_INVALID_FLOW,
            $controller->callGetWebpayFlow([]));
    }
}
