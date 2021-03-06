<?php

namespace Tests\Common\Factory\HttpRequest;

use Nails\Common\Factory\HttpRequest\Get;
use Nails\Testing;
use PHPUnit\Framework\TestCase;

class GetTest extends TestCase
{
    /**
     * @covers \Nails\Common\Factory\HttpRequest\Get::setHeader
     * @covers \Nails\Common\Factory\HttpRequest\Get::getHeader
     */
    public function test_http_get_request_can_set_and_get_header(): void
    {
        $oRequest = new Get();
        $oRequest->setHeader('X-header', 'foo');
        $this->assertEquals('foo', $oRequest->getHeader('X-header'));
    }

    // --------------------------------------------------------------------------

    /**
     * @covers \Nails\Common\Factory\HttpRequest\Get::asUser
     * @covers \Nails\Common\Factory\HttpRequest\Get::getHeader
     */
    public function test_http_get_request_can_set_user(): void
    {
        $iUserId  = 1;
        $oRequest = new Get();
        $oRequest->asUser($iUserId);
        $this->assertEquals(Testing::TEST_HEADER_VALUE, $oRequest->getHeader(Testing::TEST_HEADER_NAME));
        $this->assertEquals($iUserId, $oRequest->getHeader(Testing::TEST_HEADER_USER_NAME));
    }

    // --------------------------------------------------------------------------

    /**
     * @todo (Pablo - 2019-01-12) - complete this test suite
     * Any tests which cause a response to be created cause the suite to crash due
     * to the Factory expecting to be used within an app context.
     */
}
