<?php

session_start();

require_once '../../config/database.php';
require_once '../../includes/language.php';

// ========================================
// AUTHORIZATION
// ========================================

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Admin only
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit($lang === 'ar' ? 'تم رفض الوصول.' : 'Access denied.');
}

$error = '';
$message = '';


// ========================================
// GET USER ID
// ========================================

$userId = (int) ($_GET['id'] ?? $_POST['user_id'] ?? 0);

if ($userId <= 0) {
    exit($lang === 'ar' ? 'معرّف المستخدم غير صالح.' : 'Invalid user ID.');
}


// ========================================
// GET USER
// ========================================

try {

    $stmt = $pdo->prepare(
        "SELECT id, username, role, status
         FROM users
         WHERE id = ?
         LIMIT 1"
    );

    $stmt->execute([$userId]);

    $user = $stmt->fetch();

    if (!$user) {
        exit($lang === 'ar' ? 'المستخدم غير موجود.' : 'User not found.');
    }

    // Admin password cannot be reset from this page
    if ($user['role'] === 'admin') {
        exit($lang === 'ar' ? 'لا يمكن إعادة تعيين كلمة مرور حساب المسؤول من هذه الصفحة.' : 'The Admin password cannot be reset from this page.');
    }

} catch (PDOException $e) {

    exit('Database error occurred.');
}


// ========================================
// RESET PASSWORD
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($newPassword === '' || $confirmPassword === '') {

        $error = $lang === 'ar' ? 'يرجى ملء جميع الحقول.' : 'Please fill in all fields.';

    } elseif (strlen($newPassword) < 6) {

        $error = $lang === 'ar' ? 'يجب أن تتكون كلمة المرور المؤقتة من 6 أحرف على الأقل.' : 'Temporary password must be at least 6 characters.';

    } elseif ($newPassword !== $confirmPassword) {

        $error = $lang === 'ar' ? 'كلمتا المرور غير متطابقتين.' : 'Passwords do not match.';

    } else {

        try {

            // Hash password
            $passwordHash = password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );

            // Update password
            $stmt = $pdo->prepare(
                "UPDATE users
                 SET
                    password_hash = ?,
                    updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?
                 AND role IN ('staff', 'instructor')"
            );

            $stmt->execute([
                $passwordHash,
                $userId
            ]);

            if ($stmt->rowCount() > 0) {

                header('Location: index.php?reset=1');
                exit;

            } else {

                $error = $lang === 'ar' ? 'تعذر إعادة تعيين كلمة المرور.' : 'Password could not be reset.';
            }

        } catch (PDOException $e) {

            $error = $lang === 'ar' ? 'حدث خطأ في قاعدة البيانات.' : 'Database error occurred.';
        }
    }
}

?>

<!DOCTYPE html>

<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= htmlspecialchars($dir) ?>">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= $lang === 'ar' ? 'إعادة تعيين كلمة المرور - مركز العولمة للتدريب' : 'Reset Password - AlawlAma Training Center' ?>
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;

            font-family: Arial, sans-serif;

            background: #f4f6f9;

            color: #222;
        }


        .header {
            background: #1f2937;

            color: white;

            padding: 20px 30px;

            display: flex;

            justify-content: space-between;

            align-items: center;
        }


        .header h1 {
            margin: 0;

            font-size: 24px;
        }


        .header a {
            color: white;

            text-decoration: none;

            background: #374151;

            padding: 10px 16px;

            border-radius: 6px;
        }


        .header a:hover {
            background: #4b5563;
        }


        .container {
            max-width: 600px;

            margin: 40px auto;

            padding: 0 20px;
        }


        .card {
            background: white;

            padding: 30px;

            border-radius: 10px;

            box-shadow:
                0 2px 10px rgba(0, 0, 0, 0.08);
        }


        .card h2 {
            margin-top: 0;

            margin-bottom: 8px;
        }


        .description {
            color: #6b7280;

            font-size: 14px;

            margin-bottom: 25px;
        }


        .user-info {
            background: #f3f4f6;

            padding: 15px;

            border-radius: 7px;

            margin-bottom: 25px;
        }


        .user-info div {
            margin-bottom: 7px;

            font-size: 14px;
        }


        .user-info div:last-child {
            margin-bottom: 0;
        }


        .form-group {
            margin-bottom: 20px;
        }


        label {
            display: block;

            margin-bottom: 7px;

            font-weight: bold;

            font-size: 14px;
        }


        input {
            width: 100%;

            padding: 12px;

            border: 1px solid #d1d5db;

            border-radius: 6px;

            font-size: 14px;
        }


        input:focus {
            outline: none;

            border-color: #2563eb;
        }


        .btn {
            width: 100%;

            padding: 12px;

            border: none;

            border-radius: 6px;

            background: #dc2626;

            color: white;

            font-size: 15px;

            font-weight: bold;

            cursor: pointer;
        }


        .btn:hover {
            background: #b91c1c;
        }


        .cancel {
            display: block;

            text-align: center;

            margin-top: 15px;

            color: #4b5563;

            text-decoration: none;

            font-size: 14px;
        }


        .cancel:hover {
            text-decoration: underline;
        }


        .alert {
            padding: 14px 16px;

            border-radius: 6px;

            margin-bottom: 20px;

            font-size: 14px;
        }


        .error {
            background: #fee2e2;

            color: #991b1b;
        }



        [dir="rtl"] .header {
            direction: rtl;
        }

        [dir="rtl"] .header a {
            text-align: right;
        }

        [dir="rtl"] .user-info,
        [dir="rtl"] .form-group,
        [dir="rtl"] .description,
        [dir="rtl"] .warning,
        [dir="rtl"] .card {
            text-align: right;
        }

        [dir="rtl"] .cancel {
            text-align: center;
        }

        .warning {
            margin-top: 20px;

            padding: 14px;

            background: #fef3c7;

            color: #92400e;

            border-radius: 6px;

            font-size: 13px;
        }

    </style>

</head>


<body>


<!-- ========================================
     HEADER
======================================== -->

<div class="header">

    <h1>
        <?= $lang === 'ar' ? 'إعادة تعيين كلمة المرور' : 'Reset Password' ?>
    </h1>


    <a href="index.php">
        ← <?= $lang === 'ar' ? 'إدارة المستخدمين' : 'Manage Users' ?>
    </a>

</div>


<!-- ========================================
     CONTENT
======================================== -->

<div class="container">


    <div class="card">


        <h2>
            <?= $lang === 'ar' ? 'إعادة تعيين كلمة مرور المستخدم' : 'Reset User Password' ?>
        </h2>


        <p class="description">
            <?= $lang === 'ar' ? 'قم بتعيين كلمة مرور مؤقتة جديدة لهذا المستخدم.' : 'Set a new temporary password for this user.' ?>
        </p>


        <?php if ($error): ?>

            <div class="alert error">

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <!-- USER INFORMATION -->

        <div class="user-info">

            <div>
                <strong><?= $lang === 'ar' ? 'اسم المستخدم:' : 'Username:' ?></strong>
                <?= htmlspecialchars($user['username']) ?>
            </div>


            <div>
                <strong><?= $lang === 'ar' ? 'الدور:' : 'Role:' ?></strong>
                <?= htmlspecialchars(
                    ucfirst($user['role'])
                ) ?>
            </div>


            <div>
                <strong><?= $lang === 'ar' ? 'الحالة:' : 'Status:' ?></strong>
                <?= htmlspecialchars(
                    ucfirst($user['status'])
                ) ?>
            </div>

        </div>


        <!-- FORM -->

        <form method="POST">


            <input
                type="hidden"
                name="user_id"
                value="<?= (int) $user['id'] ?>"
            >


            <!-- NEW PASSWORD -->

            <div class="form-group">

                <label for="new_password">
                    <?= $lang === 'ar' ? 'كلمة المرور المؤقتة' : 'Temporary Password' ?>
                </label>

                <input
                    type="password"
                    id="new_password"
                    name="new_password"
                    minlength="6"
                    required
                    autocomplete="new-password"
                >

            </div>


            <!-- CONFIRM -->

            <div class="form-group">

                <label for="confirm_password">
                    <?= $lang === 'ar' ? 'تأكيد كلمة المرور المؤقتة' : 'Confirm Temporary Password' ?>
                </label>

                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    minlength="6"
                    required
                    autocomplete="new-password"
                >

            </div>


            <button
                type="submit"
                class="btn"
                onclick="return confirmReset();"
            >
                <?= $lang === 'ar' ? 'إعادة تعيين كلمة المرور' : 'Reset Password' ?>
            </button>


        </form>


        <a
            href="index.php"
            class="cancel"
        >
            <?= $lang === 'ar' ? 'إلغاء' : 'Cancel' ?>
        </a>


        <div class="warning">

            <strong><?= $lang === 'ar' ? 'مهم:' : 'Important:' ?></strong>
            <?= $lang === 'ar'
                ? 'قم بتسليم كلمة المرور المؤقتة للمستخدم بطريقة آمنة. يجب على المستخدم تسجيل الدخول وتغيير كلمة المرور المؤقتة فورًا.'
                : 'Give the temporary password to the user securely. The user should log in and change the temporary password immediately.' ?>

        </div>


    </div>


</div>


<script>

function confirmReset() {

    return confirm(
        <?= json_encode($lang === 'ar' ? 'هل أنت متأكد من أنك تريد إعادة تعيين كلمة مرور هذا المستخدم؟' : 'Are you sure you want to reset this user password?') ?>
    );

}

</script>


</body>

</html>