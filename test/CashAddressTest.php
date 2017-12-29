<?php

namespace Test\CashAddr;

use CashAddr\Base32;
use CashAddr\CashAddress;

class CashAddressTest extends TestBase
{
    /**
     * @param $string
     * @param $prefix
     * @param $hex
     * @param array $words
     * @throws \CashAddr\Exception\Base32Exception
     * @throws \CashAddr\Exception\CashAddressException
     * @dataProvider getValidTestCase
     */
    public function testCashAddress($string, $prefix, $hex, array $words, $scriptType)
    {
        list ($retPrefix, $retScriptType, $retHash) = CashAddress::decode($string);
        $this->assertEquals($prefix, $retPrefix);
        $this->assertEquals($scriptType, $retScriptType);

        if ($scriptType === "scripthash" ) {
            $this->assertEquals(20, strlen($retHash));
        } else if ($scriptType === "pubkeyhash") {
            $this->assertEquals(20, strlen($retHash));
        }

        $rebuildPayload = unpack("H*", pack("C*", ...Base32::fromWords(count($words), $words)))[1];
        $this->assertEquals($hex, $rebuildPayload);

        $encodeAgain = CashAddress::encode($retPrefix, $retScriptType, $retHash);
        $this->assertEquals($string, $encodeAgain);
    }
}
