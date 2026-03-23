<?php
/**
 * RaftPay 回调通知接收端
 *
 * 部署说明:
 *   将此文件部署到可被外网访问的 URL，并在创建订单时将该 URL 作为 notifyUrl 传入。
 *
 * 回调数据格式:
 *   POST body: {"data": "Base64编码的RSA加密数据"}
 *   使用平台公钥解密后得到业务数据 JSON
 *
 * 解密后字段:
 *   - orderId          string 平台订单号
 *   - merchantOrderNo  string 商户订单号
 *   - orderType        string PayIn(代收) 或 PayOut(代付)
 *   - amount           string 订单金额
 *   - fee              string 手续费
 *   - status           number 2=成功, 3=失败
 *   - payType          string 支付类型
 *   - currency         string 货币代码
 *   - processCurrency  string 实际支付货币
 *   - processAmount    string 实际支付金额
 *   - merchantCustomize string 自定义字段（原样返回）
 *   - createTime       number 毫秒时间戳
 *
 * 响应要求:
 *   HTTP 200，返回 "success" 字符串。否则平台会重试（最多10次，约48小时）。
 */

require_once __DIR__ . '/RaftPayCrypto.php';

// ============================================================
// 1. 加载配置
// ============================================================
$config = require __DIR__ . '/config.php';
$platformPublicKey = $config['platformPublicKey'];

// ============================================================
// 2. 读取回调请求体
// ============================================================
$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true);

if (empty($body['data'])) {
    error_log('[RaftPay Callback] 缺少 data 字段');
    // 仍返回 success，避免无意义的重试
    echo 'success';
    exit;
}

// ============================================================
// 3. 使用平台公钥解密
// ============================================================
try {
    $decrypted = RaftPayCrypto::decryptWithPublicKey($body['data'], $platformPublicKey);
} catch (Exception $e) {
    error_log('[RaftPay Callback] 解密失败: ' . $e->getMessage());
    echo 'success';
    exit;
}

$notice = json_decode($decrypted, true);
if ($notice === null) {
    error_log('[RaftPay Callback] JSON 解析失败: ' . $decrypted);
    echo 'success';
    exit;
}

// ============================================================
// 4. 处理业务逻辑
// ============================================================
$orderId        = $notice['orderId'] ?? '';
$merchantOrderNo = $notice['merchantOrderNo'] ?? '';
$orderType      = $notice['orderType'] ?? '';    // PayIn 或 PayOut
$status         = $notice['status'] ?? -1;       // 2=成功, 3=失败
$amount         = $notice['amount'] ?? '0';
$fee            = $notice['fee'] ?? '0';
$payType        = $notice['payType'] ?? '';

error_log(sprintf(
    '[RaftPay Callback] orderId=%s merchantOrderNo=%s type=%s status=%d amount=%s fee=%s',
    $orderId, $merchantOrderNo, $orderType, $status, $amount, $fee
));

// 注意: 必须做幂等性判断，同一订单可能收到多次回调
// 建议根据 merchantOrderNo 查询本地订单状态，已处理则直接返回 success

if ($status === 2) {
    // ===== 支付成功 =====
    // TODO: 更新本地订单状态为成功
    // TODO: 代收 — 给用户加款 / 代付 — 确认打款完成
    error_log("[RaftPay Callback] 订单 {$merchantOrderNo} 支付成功");

} elseif ($status === 3) {
    // ===== 支付失败 =====
    // TODO: 更新本地订单状态为失败
    // TODO: 代付失败 — 解冻用户余额
    error_log("[RaftPay Callback] 订单 {$merchantOrderNo} 支付失败");

} else {
    error_log("[RaftPay Callback] 订单 {$merchantOrderNo} 未知状态: {$status}");
}

// ============================================================
// 5. 必须返回 "success"，否则平台会重试
// ============================================================
echo 'success';
