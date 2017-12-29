<?php

namespace Test\CashAddr;

use CashAddr\Base32;
use CashAddr\Exception\CashAddrException;
use CashAddr\Exception\InvalidChecksumException;

class CashAddressTest extends \PHPUnit_Framework_TestCase
{
    public function readTest()
    {
        $decoded = json_decode(file_get_contents(__DIR__ . "/fixtures.json"), true);
        if (false === $decoded) {
            throw new \RuntimeException("Invalid json in test fixture");
        }

        return $decoded;
    }

    public function getValidTestCase()
    {
        $fixtures = [];
        foreach ($this->readTest()['valid'] as $valid) {
            $fixtures[] = [$valid['string'], $valid['prefix'], $valid['hex'], $valid['words'],];
        }

        return $fixtures;
    }

    public function getDecodeFailTestCase()
    {
        $fixtures = [];
        foreach ($this->readTest()['invalid'] as $invalid) {
            if (!array_key_exists('string', $invalid)) {
                continue;
            }
            $fixtures[] = [$invalid['string'], $invalid['exception'],];
        }

        return $fixtures;
    }

    /**
     * @throws \CashAddr\Exception\CashAddrException
     * @param string $string
     * @param string $prefix
     * @param string $hex
     * @param array $words
     * @dataProvider getValidTestCase
     */
    public function testFromAndToWords($string, $prefix, $hex, array $words)
    {
        $binary = hex2bin($hex);
        $vBytes = array_values(unpack("C*", $binary));
        $numBytes = count($vBytes);
        $genWords = Base32::toWords($numBytes, $vBytes);
        $origBytes = Base32::fromWords(count($genWords), $words);
        $this->assertEquals($words, $genWords);
        $this->assertEquals($binary, pack("C*", ...$origBytes));
    }

    /**
     * @param string $string
     * @param string $prefix
     * @param string $hex
     * @param array $words
     * @throws \CashAddr\Exception\CashAddrException
     * @dataProvider getValidTestCase
     */
    public function testEncode($string, $prefix, $hex, array $words)
    {
        $this->assertEquals($string, Base32::encode($prefix, $words));
    }

    /**
     * @param string $string
     * @param string $prefix
     * @param string $hex
     * @param array $words
     * @throws \CashAddr\Exception\CashAddrException
     * @dataProvider getValidTestCase
     */
    public function testFailsForStringWith1BitFlipped($string, $prefix, $hex, array $words)
    {
        $sepIdx = strrpos($string, Base32::SEPARATOR);
        $this->assertNotEquals(-1, $sepIdx, "separator was not found in fixture");

        $vchArray = str_split($string, 1);
        $vchArray[$sepIdx + 1] = ord($vchArray[$sepIdx + 1]) ^ 1;
        $string = implode($vchArray);

        $this->expectException(InvalidChecksumException::class);

        Base32::decode($string);
    }

    /**
     * @param string $string
     * @param string $exception
     * @dataProvider getDecodeFailTestCase
     */
    public function testDecodeFails($string, $exception = "")
    {
        $this->expectException(CashAddrException::class);
        if ($exception !== "") {
            $this->expectExceptionMessage($exception);
        }

        Base32::decode($string);
    }
}
