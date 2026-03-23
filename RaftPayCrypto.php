<?php
/**
 * RaftPay RSA 加解密工具
 *
 * 算法: RSA/ECB/PKCS1Padding
 * 私钥格式: PKCS8 (Base64)
 * 公钥格式: X509/SPKI (Base64)
 * 分段加密块: keySize - 11 字节 (2048位密钥 = 245字节)
 * 分段解密块: keySize 字节 (2048位密钥 = 256字节)
 *
 * PHP 7.2+ 兼容，仅需 openssl 扩展
 */
class RaftPayCrypto
{
    /**
     * 使用商户私钥加密数据（商户 → 平台）
     *
     * @param string $plainText         明文 JSON 字符串
     * @param string $privateKeyBase64  商户私钥（Base64 编码）
     * @return string Base64 编码的加密数据
     * @throws RuntimeException
     */
    public static function encryptWithPrivateKey($plainText, $privateKeyBase64)
    {
        $pem = self::toPemPrivate($privateKeyBase64);
        $privateKey = openssl_pkey_get_private($pem);
        if ($privateKey === false) {
            throw new RuntimeException('无法加载商户私钥: ' . openssl_error_string());
        }

        $keyDetail = openssl_pkey_get_details($privateKey);
        $keySize = $keyDetail['bits'] / 8; // 2048位 = 256字节
        $maxBlock = $keySize - 11;          // PKCS1Padding 占 11 字节

        $data = $plainText;
        $encrypted = '';
        $offset = 0;
        $dataLen = strlen($data);

        while ($offset < $dataLen) {
            $blockLen = min($maxBlock, $dataLen - $offset);
            $block = substr($data, $offset, $blockLen);
            $encryptedBlock = '';
            if (!openssl_private_encrypt($block, $encryptedBlock, $privateKey, OPENSSL_PKCS1_PADDING)) {
                throw new RuntimeException('私钥加密失败: ' . openssl_error_string());
            }
            $encrypted .= $encryptedBlock;
            $offset += $blockLen;
        }

        return base64_encode($encrypted);
    }

    /**
     * 使用平台公钥解密数据（平台 → 商户，用于回调解密）
     *
     * @param string $encryptedBase64   Base64 编码的加密数据
     * @param string $publicKeyBase64   平台公钥（Base64 编码）
     * @return string 解密后的 JSON 字符串
     * @throws RuntimeException
     */
    public static function decryptWithPublicKey($encryptedBase64, $publicKeyBase64)
    {
        $pem = self::toPemPublic($publicKeyBase64);
        $publicKey = openssl_pkey_get_public($pem);
        if ($publicKey === false) {
            throw new RuntimeException('无法加载平台公钥: ' . openssl_error_string());
        }

        $keyDetail = openssl_pkey_get_details($publicKey);
        $keySize = $keyDetail['bits'] / 8; // 2048位 = 256字节
        $maxBlock = $keySize;

        $data = base64_decode($encryptedBase64);
        if ($data === false) {
            throw new RuntimeException('Base64 解码失败');
        }

        $decrypted = '';
        $offset = 0;
        $dataLen = strlen($data);

        while ($offset < $dataLen) {
            $blockLen = min($maxBlock, $dataLen - $offset);
            $block = substr($data, $offset, $blockLen);
            $decryptedBlock = '';
            if (!openssl_public_decrypt($block, $decryptedBlock, $publicKey, OPENSSL_PKCS1_PADDING)) {
                throw new RuntimeException('公钥解密失败: ' . openssl_error_string());
            }
            $decrypted .= $decryptedBlock;
            $offset += $blockLen;
        }

        return $decrypted;
    }

    /**
     * 清理密钥字符串，移除 PEM 头尾和空白
     */
    private static function cleanKey($key)
    {
        $key = preg_replace('/-----BEGIN.*?-----/', '', $key);
        $key = preg_replace('/-----END.*?-----/', '', $key);
        $key = preg_replace('/\s/', '', $key);
        return $key;
    }

    /**
     * 将 Base64 密钥转为 PEM 格式私钥
     */
    private static function toPemPrivate($base64Key)
    {
        $cleaned = self::cleanKey($base64Key);
        return "-----BEGIN PRIVATE KEY-----\n"
            . chunk_split($cleaned, 64, "\n")
            . "-----END PRIVATE KEY-----";
    }

    /**
     * 将 Base64 密钥转为 PEM 格式公钥
     */
    private static function toPemPublic($base64Key)
    {
        $cleaned = self::cleanKey($base64Key);
        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split($cleaned, 64, "\n")
            . "-----END PUBLIC KEY-----";
    }
}
