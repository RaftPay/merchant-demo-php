# RaftPay PHP 商户接入 Demo

PHP 7.2+ 兼容，无第三方依赖，仅需 `openssl` + `curl` + `json` 扩展。

## 快速开始

```bash
# 1. 复制配置文件并填入真实凭证
cp config.example.php config.php

# 2. 运行示例（调用全部接口）
php example.php
```

## 文件说明

| 文件 | 说明 |
|------|------|
| `config.example.php` | 配置模板，复制为 `config.php` 使用 |
| `RaftPayCrypto.php` | RSA 加解密工具类，可直接复制到你的项目 |
| `RaftPayClient.php` | API 客户端，封装了 4 个接口调用 |
| `callback.php` | 回调通知接收端，部署到可被外网访问的地址 |
| `example.php` | 全部接口调用示例 |

## 接口清单

| # | 接口 | 方法 |
|---|------|------|
| 1 | 代收订单创建 | `$client->createDeposit($params)` |
| 2 | 代付订单创建 | `$client->createPayout($params)` |
| 3 | 订单状态查询 | `$client->queryOrderStatus($merchantOrderNo)` |
| 4 | 余额查询 | `$client->queryBalance()` |
| 5 | 回调通知处理 | 部署 `callback.php` |

## 集成到你的项目

只需复制 `RaftPayCrypto.php` 和 `RaftPayClient.php` 两个文件即可。

```php
require_once 'RaftPayCrypto.php';
require_once 'RaftPayClient.php';

$client = new RaftPayClient('商户ID', '商户私钥Base64', 'https://api-raftpay');

// 创建代收
$result = $client->createDeposit([
    'merchantOrderNo' => 'YOUR_ORDER_NO',
    'amount'          => '100',
    'currency'        => 'PKR',
    'notifyUrl'       => 'https://your-domain.com/callback.php',
]);

// 查询余额
$result = $client->queryBalance();
```

## 回调配置

将 `callback.php` 部署到你的服务器，确保外网可访问，并在创建订单时传入该 URL 作为 `notifyUrl`。

回调重试机制：如未返回 `success`，平台将按 30s、1m、4m、10m、30m、1h、2h、6h、15h、24h 间隔重试，共 10 次。
