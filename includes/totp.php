<?php
/**
 * TOTP (Time-based One-Time Password) — Pure PHP implementation
 * RFC 6238 compliant, no Composer required
 */
class TOTP
{
    private static $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret($length = 20)
    {
        $secret = '';
        $chars = self::$base32Chars;
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }

    public static function getProvisioningUri($secret, $email, $issuer = 'UZDUB')
    {
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ]);
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $email) . '?' . $params;
    }

    public static function getQRCodeUrl($uri)
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode($uri) . '&bgcolor=111927&color=ffffff&margin=10';
    }

    public static function verifyCode($secret, $code, $tolerance = 1)
    {
        $time = floor(time() / 30);

        for ($i = -$tolerance; $i <= $tolerance; $i++) {
            $calculated = self::generateCode($secret, $time + $i);
            if (hash_equals($calculated, str_pad($code, 6, '0', STR_PAD_LEFT))) {
                return true;
            }
        }
        return false;
    }

    private static function generateCode($secret, $time)
    {
        $binary = pack('N*', 0) . pack('N*', $time);
        $hash = hash_hmac('sha1', $binary, self::base32Decode($secret), true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $code = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % 1000000;

        return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
    }

    private static function base32Decode($input)
    {
        $input = strtoupper(rtrim($input, '='));
        $chars = self::$base32Chars;
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        for ($i = 0, $len = strlen($input); $i < $len; $i++) {
            $val = strpos($chars, $input[$i]);
            if ($val === false) continue;

            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }
        return $output;
    }
}
