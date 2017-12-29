<?php

namespace CashAddr;

use CashAddr\Exception\Base32Exception;
use CashAddr\Exception\CashAddressException;
use CashAddr\Exception\InvalidChecksumException;

class CashAddress
{
    /**
     * @var array
     */
    protected static $hashBits = [
        160 => 0,
        192 => 1,
        224 => 2,
        256 => 3,
        320 => 4,
        384 => 5,
        448 => 6,
        512 => 7,
    ];

    /**
     * @var array
     */
    protected static $versionBits = [
        "pubkeyhash" => 0,
        "scripthash" => 1,
    ];

    /**
     * @param string $prefix - prefix for address
     * @param string $scriptType - what type of address
     * @param string $hash - base256 binary, not HEX.
     * @return string
     * @throws Base32Exception
     * @throws CashAddressException
     */
    public static function encode($prefix, $scriptType, $hash)
    {
        if (!array_key_exists($scriptType, self::$versionBits)) {
            throw new \RuntimeException("Unsupported script type");
        }

        $hashLength = strlen($hash);
        $addressVersion = self::createVersion($scriptType, $hashLength * 8);

        $bytes = array_merge([$addressVersion], array_values(unpack("C*", $hash)));
        $words = Base32::toWords(1 + $hashLength, $bytes);
        return Base32::encode($prefix, $words);
    }

    /**
     * @param $string - cashaddr string
     * @return array<string, string, string> - prefix, scriptType, hash
     * @throws Base32Exception
     * @throws CashAddressException
     */
    public static function decode($string)
    {
        try {
            /**
             * @var string $prefix
             * @var int[] $words
             */
            list ($prefix, $words) = Base32::decode($string);
        } catch (InvalidChecksumException $e) {
            throw new CashAddressException("Checksum failed to verify", 0, $e);
        } catch (Base32Exception $e) {
            throw new CashAddressException("Failed to decode address", 0, $e);
        }

        $numWords = count($words);
        $bytes = Base32::fromWords($numWords, $words);
        $numBytes = count($bytes);

        list ($scriptType, $hash) = self::extractPayload($numBytes, $bytes);

        return [$prefix, $scriptType, $hash];
    }

    /**
     * @param string $scriptType
     * @param int $hashLengthBits
     * @return int
     * @throws CashAddressException
     */
    protected static function createVersion($scriptType, $hashLengthBits)
    {
        if (($scriptType === "pubkeyhash" || $scriptType === "scripthash") && $hashLengthBits !== 160) {
            throw new CashAddressException("Invalid hash length [$hashLengthBits bits] for {$scriptType}");
        }

        return (self::$versionBits[$scriptType] << 3) | self::$hashBits[$hashLengthBits];
    }

    /**
     * @param int $version
     * @return array
     * @throws CashAddressException
     */
    protected static function decodeVersion($version)
    {
        if (($version >> 7) & 1) {
            throw new CashAddressException("Invalid version - MSB is reserved");
        }

        $scriptMarkerBits = ($version >> 3) & 0x1f;
        $hashMarkerBits = ($version & 0x07);

        $hashBitsMap = array_flip(self::$hashBits);
        if (!array_key_exists($hashMarkerBits, $hashBitsMap)) {
            throw new CashAddressException("Invalid version or hash length");
        }
        $hashLength = $hashBitsMap[$hashMarkerBits];

        switch ($scriptMarkerBits) {
            case 0:
                $scriptType = "pubkeyhash";
                break;
            case 1:
                $scriptType = "scripthash";
                break;
            default:
                throw new CashAddressException('Invalid version or script type');
        }

        return [
            $scriptType, $hashLength
        ];
    }

    /**
     * @param int $numBytes
     * @param int[] $payloadBytes
     * @return array<string, string> - script type and hash
     * @throws CashAddressException
     */
    public static function extractPayload($numBytes, $payloadBytes)
    {
        if ($numBytes < 1) {
            throw new CashAddressException("Empty base32 string");
        }

        list ($scriptType, $hashLengthBits) = self::decodeVersion($payloadBytes[0]);

        if (($hashLengthBits / 8) !== $numBytes - 1) {
            throw new CashAddressException("Hash length does not match version");
        }

        $hash = pack("C*", ...array_slice($payloadBytes, 1));

        return [$scriptType, $hash];
    }

}
