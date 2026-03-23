<?php
require_once __DIR__ . '/RaftPayCrypto.php';

/**
 * RaftPay 商户 API 客户端
 *
 * 支持接口:
 *   1. 代收订单创建  POST /order/api/merchant/v1/order/pay
 *   2. 代付订单创建  POST /order/api/merchant/v1/order/payout
 *   3. 订单状态查询  GET  /order/api/merchant/v1/order/status
 *   4. 余额查询      GET  /order/api/merchant/v1/account/balance
 *
 * PHP 7.2+ 兼容，仅需 curl + openssl + json 扩展
 */
class RaftPayClient
{
    private $merchantId;
    private $merchantPrivateKey;
    private $apiBaseUrl;
    private $timeout;

    /**
     * @param string $merchantId         商户 ID
     * @param string $merchantPrivateKey 商户私钥（Base64）
     * @param string $apiBaseUrl         API 地址
     * @param int    $timeout            请求超时秒数
     */
    public function __construct($merchantId, $merchantPrivateKey, $apiBaseUrl, $timeout = 30)
    {
        $this->merchantId = $merchantId;
        $this->merchantPrivateKey = $merchantPrivateKey;
        $this->apiBaseUrl = rtrim($apiBaseUrl, '/');
        $this->timeout = $timeout;
    }

    // ========================================================================
    // 1. 代收订单创建
    // ========================================================================

    /**
     * 创建代收订单
     *
     * @param array $params 业务参数:
     *   - merchantOrderNo string 必填 商户订单号（最大60字符）
     *   - amount          string 必填 金额，如 "100" 或 "100.00"
     *   - notifyUrl       string 必填 回调地址（最大150字符）
     *   - currency        string 选填 固定 PKR
     *   - payType         string 选填 EASYPAISA 或 JAZZCASH
     *   - description     string 选填 订单描述
     *   - payerMobile     string 选填 格式 03xxxxxxxxx
     *   - returnUrl       string 选填 支付完成后跳转地址
     * @return array API 响应
     */
    public function createDeposit(array $params)
    {
        $url = $this->apiBaseUrl . '/order/api/merchant/v1/order/pay';
        return $this->postEncrypted($url, $params);
    }

    // ========================================================================
    // 2. 代付订单创建
    // ========================================================================

    /**
     * 创建代付订单
     *
     * @param array $params 业务参数:
     *   - merchantOrderNo string 必填 商户订单号
     *   - amount          string 必填 金额
     *   - notifyUrl       string 必填 回调地址
     *   - payoutMethod    string 必填 MWALLET 或 IBFT
     *   - payerMobile     string 必填 格式 03xxxxxxxxx
     *   - currency        string 选填 固定 PKR
     *   - payType         string 条件 MWALLET 时必填: EASYPAISA 或 JAZZCASH
     *   - accountNumber   string 条件 IBFT 时必填
     *   - accountName     string 条件 IBFT 时必填
     *   - bankCode        string 条件 IBFT 时必填
     *   - description     string 选填
     * @return array API 响应
     */
    public function createPayout(array $params)
    {
        $url = $this->apiBaseUrl . '/order/api/merchant/v1/order/payout';
        return $this->postEncrypted($url, $params);
    }

    // ========================================================================
    // 3. 订单状态查询（代收/代付共用）
    // ========================================================================

    /**
     * 查询订单状态
     *
     * @param string $merchantOrderNo 商户订单号
     * @return array API 响应，data.status: 0=待支付 1=处理中 2=成功 3=失败
     */
    public function queryOrderStatus($merchantOrderNo)
    {
        $url = $this->apiBaseUrl . '/order/api/merchant/v1/order/status';
        $data = [
            'merchantOrderNo' => $merchantOrderNo,
            'timestamp'       => time(),
        ];
        return $this->getEncrypted($url, $data);
    }

    // ========================================================================
    // 4. 余额查询
    // ========================================================================

    /**
     * 查询商户余额
     *
     * @return array API 响应，data 包含 availableMoney, frozenMoney, unsettledMoney
     */
    public function queryBalance()
    {
        $url = $this->apiBaseUrl . '/order/api/merchant/v1/account/balance';
        $data = [
            'timestamp' => time(),
        ];
        return $this->getEncrypted($url, $data);
    }

    // ========================================================================
    // 内部方法
    // ========================================================================

    /**
     * POST 请求（加密业务数据）
     */
    private function postEncrypted($url, array $bizData)
    {
        $encryptedData = RaftPayCrypto::encryptWithPrivateKey(
            json_encode($bizData, JSON_UNESCAPED_UNICODE),
            $this->merchantPrivateKey
        );

        $requestBody = [
            'merchantId' => $this->merchantId,
            'data'       => $encryptedData,
            'timestamp'  => time(),
        ];

        return $this->httpPost($url, $requestBody);
    }

    /**
     * GET 请求（加密业务数据作为 query 参数）
     */
    private function getEncrypted($url, array $bizData)
    {
        $encryptedData = RaftPayCrypto::encryptWithPrivateKey(
            json_encode($bizData, JSON_UNESCAPED_UNICODE),
            $this->merchantPrivateKey
        );

        $params = [
            'merchantId' => $this->merchantId,
            'data'       => $encryptedData,
        ];

        $queryString = http_build_query($params);
        return $this->httpGet($url . '?' . $queryString);
    }

    /**
     * 发送 POST 请求
     */
    private function httpPost($url, array $body)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=utf-8'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("HTTP 请求失败: {$error}");
        }

        $result = json_decode($response, true);
        if ($result === null) {
            throw new RuntimeException("JSON 解析失败, HTTP {$httpCode}, 响应: {$response}");
        }

        return $result;
    }

    /**
     * 发送 GET 请求
     */
    private function httpGet($url)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("HTTP 请求失败: {$error}");
        }

        $result = json_decode($response, true);
        if ($result === null) {
            throw new RuntimeException("JSON 解析失败, HTTP {$httpCode}, 响应: {$response}");
        }

        return $result;
    }
}
