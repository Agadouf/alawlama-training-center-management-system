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

$userId = (int) $_SESSION['user_id'];

$message = '';
$error = '';


// ========================================
// CHANGE PASSWORD
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Basic validation
    if (
        $currentPassword === '' ||
        $newPassword === '' ||
        $confirmPassword === ''
    ) {

        $error = $lang === 'ar'
            ? 'يرجى ملء جميع الحقول.'
            : 'Please fill in all fields.';

    } elseif (strlen($newPassword) < 6) {

        $error = $lang === 'ar'
            ? 'يجب أن تتكون كلمة المرور الجديدة من 6 أحرف على الأقل.'
            : 'New password must be at least 6 characters.';

    } elseif ($newPassword !== $confirmPassword) {

        $error = $lang === 'ar'
            ? 'كلمتا المرور الجديدتان غير متطابقتين.'
            : 'New passwords do not match.';

    } else {

        try {

            // ========================================
            // GET CURRENT PASSWORD HASH
            // ========================================

            $stmt = $pdo->prepare(
                "SELECT password_hash
                 FROM users
                 WHERE id = ?
                 LIMIT 1"
            );

            $stmt->execute([$userId]);

            $user = $stmt->fetch();

            if (!$user) {

                $error = $lang === 'ar'
                    ? 'لم يتم العثور على حساب المستخدم.'
                    : 'User account not found.';

            } elseif (
                !password_verify(
                    $currentPassword,
                    $user['password_hash']
                )
            ) {

                $error = $lang === 'ar'
                    ? 'كلمة المرور الحالية غير صحيحة.'
                    : 'Current password is incorrect.';

            } elseif (
                password_verify(
                    $newPassword,
                    $user['password_hash']
                )
            ) {

                $error = $lang === 'ar'
                    ? 'يجب أن تكون كلمة المرور الجديدة مختلفة عن كلمة المرور الحالية.'
                    : 'New password must be different from the current password.';

            } else {

                // ========================================
                // HASH NEW PASSWORD
                // ========================================

                $newPasswordHash = password_hash(
                    $newPassword,
                    PASSWORD_DEFAULT
                );


                // ========================================
                // UPDATE PASSWORD
                // ========================================

                $stmt = $pdo->prepare(
                    "UPDATE users
                     SET
                        password_hash = ?,
                        updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?"
                );

                $stmt->execute([
                    $newPasswordHash,
                    $userId
                ]);


                $message = $lang === 'ar'
                    ? 'تم تغيير كلمة المرور بنجاح.'
                    : 'Password changed successfully.';

            }

        } catch (PDOException $e) {

            $error = $lang === 'ar'
                ? 'حدث خطأ في قاعدة البيانات.'
                : 'Database error occurred.';
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
        <?= $lang === 'ar'
            ? 'تغيير كلمة المرور - مركز العولمة للتدريب'
            : 'Change Password - AlawlAma Training Center' ?>
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


        .page-header {
            background: #1f2937;

            color: white;

            padding: 20px 30px;

            display: flex;

            justify-content: space-between;

            align-items: center;
        }


        .page-header h1 {
            margin: 0;

            font-size: 24px;
        }


        .page-header a {
            color: white;

            text-decoration: none;

            background: #374151;

            padding: 10px 16px;

            border-radius: 6px;
        }


        .page-header a:hover {
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

            margin-bottom: 10px;
        }


        .description {
            color: #6b7280;

            font-size: 14px;

            margin-bottom: 25px;
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

            background: #2563eb;

            color: white;

            font-size: 15px;

            font-weight: bold;

            cursor: pointer;
        }


        .btn:hover {
            background: #1d4ed8;
        }


        .alert {
            padding: 14px 16px;

            border-radius: 6px;

            margin-bottom: 20px;

            font-size: 14px;
        }


        .success {
            background: #dcfce7;

            color: #166534;
        }


        .error {
            background: #fee2e2;

            color: #991b1b;
        }


        .security-note {
            margin-top: 20px;

            padding: 14px;

            background: #f3f4f6;

            border-radius: 6px;

            font-size: 13px;

            color: #4b5563;
        }


        [dir="rtl"] .page-header {
            text-align: right;
        }


        [dir="rtl"] .page-header h1 {
            text-align: right;
        }


        [dir="rtl"] .card {
            text-align: right;
        }


        [dir="rtl"] label {
            text-align: right;
        }


        [dir="rtl"] input {
            text-align: right;
        }


        [dir="rtl"] .security-note {
            text-align: right;
        }


        @media (max-width: 600px) {

            .page-header {
                padding: 18px 20px;
            }


            .page-header h1 {
                font-size: 19px;
            }


            .container {
                margin: 25px auto;
            }


            .card {
                padding: 22px;
            }

        }

    </style>

</head>


<body>


<!-- ========================================
     HEADER
======================================== -->

<div class="page-header">

    <h1>
        <?= $lang === 'ar' ? 'تغيير كلمة المرور' : 'Change Password' ?>
    </h1>


    <a href="../dashboard/index.php">
        <?= $lang === 'ar' ? 'لوحة التحكم ←' : '← Dashboard' ?>
    </a>

</div>


<!-- ========================================
     CONTENT
======================================== -->

<div class="container">


    <div class="card">


        <h2>
            <?= $lang === 'ar'
                ? 'تغيير كلمة المرور الخاصة بك'
                : 'Change Your Password' ?>
        </h2>


        <p class="description">

            <?= $lang === 'ar'
                ? 'قم بتحديث كلمة مرور حسابك بإدخال كلمة المرور الحالية واختيار كلمة مرور جديدة.'
                : 'Update your account password by entering your current password and choosing a new one.' ?>

        </p>


        <!-- SUCCESS -->

        <?php if ($message): ?>

            <div class="alert success">

                <?= htmlspecialchars($message) ?>

            </div>

        <?php endif; ?>


        <!-- ERROR -->

        <?php if ($error): ?>

            <div class="alert error">

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <!-- FORM -->

        <form method="POST">


            <!-- CURRENT PASSWORD -->

            <div class="form-group">

                <label for="current_password">
                    <?= $lang === 'ar' ? 'كلمة المرور الحالية' : 'Current Password' ?>
                </label>

                <input
                    type="password"
                    id="current_password"
                    name="current_password"
                    required
                    autocomplete="current-password"
                >

            </div>


            <!-- NEW PASSWORD -->

            <div class="form-group">

                <label for="new_password">
                    <?= $lang === 'ar' ? 'كلمة المرور الجديدة' : 'New Password' ?>
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
                    <?= $lang === 'ar' ? 'تأكيد كلمة المرور الجديدة' : 'Confirm New Password' ?>
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
            >
                <?= $lang === 'ar' ? 'تغيير كلمة المرور' : 'Change Password' ?>
            </button>


        </form>


        <div class="security-note">

            <strong>
                <?= $lang === 'ar' ? 'الأمان:' : 'Security:' ?>
            </strong>

            <?= $lang === 'ar'
                ? 'يتم تشفير كلمة المرور الخاصة بك بشكل آمن قبل تخزينها في قاعدة البيانات. لا يقوم النظام أبداً بتخزين كلمة المرور كنص عادي.'
                : 'Your password is securely hashed before being stored in the database. The system never stores your password as plain text.' ?>

        </div>


    </div>


</div>


</body>

</html>
