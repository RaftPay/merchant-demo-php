<?php
/**
 * RaftPay 全部接口调用示例
 *
 * 使用方法:
 *   1. 复制 config.example.php 为 config.php 并填入真实凭证
 *   2. php example.php
 */

require_once __DIR__ . '/RaftPayClient.php';

// ============================================================
// 加载配置
// ============================================================
$config = require __DIR__ . '/config.php';

$client = new RaftPayClient(
    $config['merchantId'],
    $config['merchantPrivateKey'],
    $config['apiBaseUrl']
);

$statusMap = [0 => '待支付', 1 => '处理中', 2 => '成功', 3 => '失败'];

// ============================================================
// 1. 查询余额
// ============================================================
echo "========== 1. 查询余额 ==========\n";
try {
    $result = $client->queryBalance();
    if ($result['result'] === 0) {
        $data = $result['data'];
        echo "可用余额: {$data['availableMoney']} PKR\n";
        echo "冻结金额: {$data['frozenMoney']} PKR\n";
        echo "未结算:   {$data['unsettledMoney']} PKR\n";
    } else {
        echo "查询失败: {$result['message']}\n";
    }
} catch (Exception $e) {
    echo "异常: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================
// 2. 创建代收订单
// ============================================================
echo "========== 2. 创建代收订单 ==========\n";
$depositOrderNo = 'DEP' . time() . substr(uniqid(), -8);
try {
    $result = $client->createDeposit([
        'merchantOrderNo' => $depositOrderNo,
        'amount'          => '100',
        'currency'        => 'PKR',
        'payType'         => 'JAZZCASH',
        'payerMobile'     => '03001234567',
        'payerEmail'      => 'test@example.com',
        'payerName'       => 'Test User',
        'customerIp'      => '1.2.3.4',
        'notifyUrl'       => $config['notifyUrl'],
        'returnUrl'       => $config['returnUrl'] ?? '',
        'description'     => 'Test deposit',
    ]);
    if ($result['result'] === 0) {
        $data = $result['data'];
        echo "订单创建成功!\n";
        echo "平台订单号: {$data['orderId']}\n";
        echo "商户订单号: {$data['merchantOrderNo']}\n";
        echo "收银台链接: {$data['payUrl']}\n";
        $statusText = $statusMap[$data['status']] ?? '未知';
        echo "订单状态:   {$data['status']} ({$statusText})\n";
    } else {
        echo "创建失败: {$result['message']} (code: {$result['result']})\n";
    }
} catch (Exception $e) {
    echo "异常: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================
// 2b. 创建代收订单（直连模式）
// ============================================================
echo "========== 2b. 创建代收订单（直连模式） ==========\n";
$directOrderNo = 'DEP' . time() . substr(uniqid(), -8) . 'D';
try {
    $result = $client->createDeposit([
        'merchantOrderNo' => $directOrderNo,
        'amount'          => '100',
        'currency'        => 'PKR',
        'payType'         => 'JAZZCASH',
        'payerMobile'     => '03001234567',
        'payerEmail'      => 'test@example.com',
        'payerName'       => 'Test User',
        'customerIp'      => '1.2.3.4',
        'notifyUrl'       => $config['notifyUrl'],
        'returnUrl'       => $config['returnUrl'] ?? '',
        'description'     => 'Test deposit - direct mode',
        'directMode'      => 1,
    ]);
    if ($result['result'] === 0) {
        $data = $result['data'];
        $statusText = $statusMap[$data['status']] ?? '未知';
        echo "订单创建成功! (直连模式，无收银台链接)\n";
        echo "平台订单号: {$data['orderId']}\n";
        echo "商户订单号: {$data['merchantOrderNo']}\n";
        echo "订单状态:   {$data['status']} ({$statusText})\n";
    } else {
        echo "创建失败: {$result['message']} (code: {$result['result']})\n";
    }
} catch (Exception $e) {
    echo "异常: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================
// 4. 创建代付订单（手机钱包 MWALLET）
// ============================================================
echo "========== 4. 创建代付订单 (MWALLET) ==========\n";
$payoutOrderNo = 'WDR' . time() . substr(uniqid(), -8);
try {
    $result = $client->createPayout([
        'merchantOrderNo' => $payoutOrderNo,
        'amount'          => '100',
        'currency'        => 'PKR',
        'payoutMethod'    => 'MWALLET',
        'payType'         => 'JAZZCASH',
        'payerMobile'     => '03001234567',
        'accountNumber'   => '03001234567',
        'accountName'     => 'Test User',
        'customerIp'      => '1.2.3.4',
        'notifyUrl'       => $config['notifyUrl'],
        'description'     => 'Test payout - wallet',
    ]);
    if ($result['result'] === 0) {
        $data = $result['data'];
        echo "代付订单创建成功!\n";
        echo "平台订单号: {$data['orderId']}\n";
        $statusText = $statusMap[$data['status']] ?? '未知';
        echo "订单状态:   {$data['status']} ({$statusText})\n";
    } else {
        echo "创建失败: {$result['message']} (code: {$result['result']})\n";
    }
} catch (Exception $e) {
    echo "异常: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================
// 5. 创建代付订单（银行转账 IBFT）
// ============================================================
echo "========== 5. 创建代付订单 (IBFT) ==========\n";
$ibftOrderNo = 'WDR' . time() . substr(uniqid(), -8) . 'B';
try {
    $result = $client->createPayout([
        'merchantOrderNo' => $ibftOrderNo,
        'amount'          => '500',
        'currency'        => 'PKR',
        'payoutMethod'    => 'IBFT',
        'payerMobile'     => '03001234567',
        'accountNumber'   => '1234567890123',
        'accountName'     => 'Test User',
        'bankCode'        => 'HBL',
        'customerIp'      => '1.2.3.4',
        'notifyUrl'       => $config['notifyUrl'],
        'description'     => 'Test payout - bank transfer',
    ]);
    if ($result['result'] === 0) {
        $data = $result['data'];
        echo "代付订单创建成功!\n";
        echo "平台订单号: {$data['orderId']}\n";
        $statusText = $statusMap[$data['status']] ?? '未知';
        echo "订单状态:   {$data['status']} ({$statusText})\n";
    } else {
        echo "创建失败: {$result['message']} (code: {$result['result']})\n";
    }
} catch (Exception $e) {
    echo "异常: {$e->getMessage()}\n";
}
echo "\n";

// ============================================================
// 6. 查询订单状态
// ============================================================
echo "========== 6. 查询订单状态 ==========\n";
try {
    $result = $client->queryOrderStatus($depositOrderNo);
    if ($result['result'] === 0) {
        $data = $result['data'];
        $statusText = $statusMap[$data['status']] ?? '未知';
        echo "商户订单号: {$data['merchantOrderNo']}\n";
        echo "平台订单号: {$data['orderNo']}\n";
        echo "订单状态:   {$data['status']} ({$statusText})\n";
        echo "订单金额:   {$data['amount']} {$data['currency']}\n";
    } else {
        echo "查询失败: {$result['message']} (code: {$result['result']})\n";
    }
} catch (Exception $e) {
    echo "异常: {$e->getMessage()}\n";
}
echo "\n";

echo "========== 完成 ==========\n";
echo "回调处理请参考 callback.php\n";
