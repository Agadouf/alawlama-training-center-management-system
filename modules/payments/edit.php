<?php
session_start();

require_once '../../config/database.php';
require_once '../../includes/language.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$allowedRoles = ['admin', 'staff'];
if (!in_array($_SESSION['role'] ?? '', $allowedRoles, true)) {
    http_response_code(403);
    die($lang === 'ar' ? 'غير مسموح بالوصول.' : 'Access denied.');
}

/*
 * Payment records are immutable after they are recorded.
 * No UPDATE operation is allowed here, even if someone opens edit.php directly.
 */

$indexUrl = 'index.php';

http_response_code(403);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars((string)$lang, ENT_QUOTES, 'UTF-8') ?>" dir="<?= htmlspecialchars((string)$dir, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $lang === 'ar' ? 'الدفعة مقفلة' : 'Payment Locked' ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f8fafc;
            color: #111827;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            width: min(560px, 100%);
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 36px;
            text-align: center;
            box-shadow: 0 8px 30px rgba(0,0,0,.08);
        }
        .icon {
            width: 58px;
            height: 58px;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: #dbeafe;
            color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: bold;
        }
        h1 { margin: 0 0 12px; font-size: 28px; }
        p { margin: 0 0 24px; color: #6b7280; line-height: 1.7; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 20px;
            border-radius: 8px;
            background: #2563eb;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
        }
        .btn:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">🔒</div>
        <h1><?= $lang === 'ar' ? 'الدفعة مقفلة' : 'Payment Record Locked' ?></h1>
        <p>
            <?= $lang === 'ar'
                ? 'لا يمكن تعديل الدفعات بعد تسجيلها. إذا تم تسجيل مبلغ غير صحيح، يرجى الرجوع إلى المسؤول لإجراء التصحيح بالطريقة المعتمدة.'
                : 'Payment records cannot be edited after they have been recorded. If a payment was entered incorrectly, please contact the administrator for the approved correction process.' ?>
        </p>
        <a href="<?= htmlspecialchars($indexUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn">
            ← <?= $lang === 'ar' ? 'العودة إلى المدفوعات' : 'Back to Payments' ?>
        </a>
    </div>
</body>
</html>
