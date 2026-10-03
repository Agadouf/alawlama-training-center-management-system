<?php

session_start();

require_once '../../config/database.php';
require_once '../../includes/language.php';

$isAr = ($lang === 'ar');

// ========================================
// AUTHORIZATION
// ========================================

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Only Admin can edit users
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit($isAr ? 'تم رفض الوصول.' : 'Access denied.');
}


// ========================================
// GET USER ID
// ========================================

$userId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$userId || $userId <= 0) {
    exit($isAr ? 'معرّف المستخدم غير صالح.' : 'Invalid user ID.');
}


// ========================================
// FETCH USER
// ========================================

$stmt = $pdo->prepare("
    SELECT
        id,
        username,
        role,
        status,
        last_login,
        created_at
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$userId]);

$user = $stmt->fetch();

if (!$user) {
    exit($isAr ? 'المستخدم غير موجود.' : 'User not found.');
}


// ========================================
// PROTECT ADMIN ACCOUNT
// ========================================

// The main Admin account cannot be edited
if ($user['role'] === 'admin') {
    exit($isAr ? 'حساب المدير محمي ولا يمكن تعديله.' : 'The Admin account is protected and cannot be edited.');
}


// ========================================
// HANDLE UPDATE
// ========================================

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $status   = $_POST['status'] ?? '';

    // ====================================
    // VALIDATION
    // ====================================

    if ($username === '') {

        $error = $isAr ? 'اسم المستخدم مطلوب.' : 'Username is required.';;

    } elseif (strlen($username) > 50) {

        $error = $isAr ? 'يجب ألا يتجاوز اسم المستخدم 50 حرفًا.' : 'Username must not exceed 50 characters.';;

    } elseif (!in_array($status, ['active', 'inactive'], true)) {

        $error = $isAr ? 'حالة المستخدم المحددة غير صالحة.' : 'Invalid status selected.';;

    }


    // ====================================
    // CHECK USERNAME
    // ====================================

    if ($error === '') {

        $checkStmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE username = ?
            AND id != ?
            LIMIT 1
        ");

        $checkStmt->execute([
            $username,
            $userId
        ]);

        if ($checkStmt->fetch()) {

            $error = $isAr ? 'اسم المستخدم موجود بالفعل. يرجى اختيار اسم مستخدم آخر.' : 'Username already exists. Please choose another username.';;
        }
    }


    // ====================================
    // UPDATE USER
    // ====================================

    if ($error === '') {

        try {

            $updateStmt = $pdo->prepare("
                UPDATE users
                SET
                    username = ?,
                    status = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
                AND role IN ('staff', 'instructor')
            ");

            $updateStmt->execute([
                $username,
                $status,
                $userId
            ]);

            header('Location: index.php?updated=1');
            exit;

        } catch (PDOException $e) {

            $error = $isAr ? 'حدث خطأ في قاعدة البيانات. يرجى المحاولة مرة أخرى.' : 'Database error occurred. Please try again.';;
        }
    }


    // Keep entered values if validation fails
    $user['username'] = $username;
    $user['status']   = $status;
}

?>

<!DOCTYPE html>

<html lang="<?= $isAr ? 'ar' : 'en' ?>" dir="<?= $isAr ? 'rtl' : 'ltr' ?>">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= $isAr ? 'تعديل المستخدم - مركز العولمة للتدريب' : 'Edit User - AlawlAma Training Center' ?>
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


        /* ========================================
           HEADER
        ======================================== */

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


        .header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .header a:hover {
            background: #4b5563;
        }

        .header a.lang-active {
            background: #2563eb;
        }

        [dir="rtl"] .header {
            flex-direction: row-reverse;
        }

        [dir="rtl"] .header-actions {
            flex-direction: row-reverse;
        }

        [dir="rtl"] .card,
        [dir="rtl"] .form-group,
        [dir="rtl"] label,
        [dir="rtl"] .subtitle,
        [dir="rtl"] .info-box,
        [dir="rtl"] .help-text {
            text-align: right;
        }

        [dir="rtl"] .actions {
            justify-content: flex-start;
        }


        /* ========================================
           CONTAINER
        ======================================== */

        .container {
            max-width: 700px;

            margin: 40px auto;

            padding: 0 20px;
        }


        /* ========================================
           CARD
        ======================================== */

        .card {
            background: white;

            border-radius: 10px;

            padding: 30px;

            box-shadow:
                0 2px 10px rgba(0, 0, 0, 0.08);
        }


        .card h2 {
            margin-top: 0;

            margin-bottom: 8px;

            font-size: 24px;
        }


        .subtitle {
            color: #6b7280;

            font-size: 14px;

            margin-bottom: 25px;
        }


        /* ========================================
           ALERT
        ======================================== */

        .alert {
            padding: 14px 18px;

            border-radius: 6px;

            margin-bottom: 20px;

            font-size: 14px;
        }


        .alert-error {
            background: #fee2e2;

            color: #991b1b;

            border: 1px solid #fecaca;
        }


        .info-box {
            background: #eff6ff;

            color: #1e40af;

            border: 1px solid #bfdbfe;

            padding: 14px;

            border-radius: 6px;

            margin-bottom: 25px;

            font-size: 13px;

            line-height: 1.5;
        }


        /* ========================================
           FORM
        ======================================== */

        .form-group {
            margin-bottom: 20px;
        }


        label {
            display: block;

            margin-bottom: 7px;

            font-weight: bold;

            font-size: 14px;

            color: #374151;
        }


        input,
        select {
            width: 100%;

            padding: 12px;

            border: 1px solid #d1d5db;

            border-radius: 6px;

            font-size: 14px;

            background: white;
        }


        input:focus,
        select:focus {
            outline: none;

            border-color: #2563eb;

            box-shadow:
                0 0 0 2px rgba(37, 99, 235, 0.1);
        }


        .readonly {
            background: #f3f4f6;

            color: #6b7280;

            cursor: not-allowed;
        }


        .help-text {
            margin-top: 5px;

            font-size: 12px;

            color: #6b7280;
        }


        /* ========================================
           ACTIONS
        ======================================== */

        .actions {
            display: flex;

            justify-content: flex-end;

            gap: 10px;

            margin-top: 30px;

            padding-top: 20px;

            border-top: 1px solid #e5e7eb;
        }


        .btn {
            display: inline-block;

            padding: 11px 18px;

            border-radius: 6px;

            border: none;

            text-decoration: none;

            font-size: 14px;

            font-weight: bold;

            cursor: pointer;
        }


        .btn-primary {
            background: #2563eb;

            color: white;
        }


        .btn-primary:hover {
            background: #1d4ed8;
        }


        .btn-secondary {
            background: #e5e7eb;

            color: #374151;
        }


        .btn-secondary:hover {
            background: #d1d5db;
        }


        /* ========================================
           RESPONSIVE
        ======================================== */

        @media (max-width: 600px) {

            .header {
                padding: 18px 20px;
            }


            .header h1 {
                font-size: 19px;
            }


            .container {
                margin: 20px auto;

                padding: 0 15px;
            }


            .card {
                padding: 20px;
            }


            .actions {
                flex-direction: column;
            }


            .actions .btn {
                width: 100%;

                text-align: center;
            }

        }

    </style>

</head>


<body>


<!-- ========================================
     HEADER
======================================== -->

<div class="header">

    <h1>
        <?= $isAr ? 'مركز العولمة للتدريب' : 'ALAW LAMA TRAINING CENTER' ?>
    </h1>


    <div class="header-actions">
        <a href="?id=<?= (int) $userId ?>&lang=en" class="<?= !$isAr ? 'lang-active' : '' ?>">English</a>
        <a href="?id=<?= (int) $userId ?>&lang=ar" class="<?= $isAr ? 'lang-active' : '' ?>">العربية</a>
        <a href="index.php"><?= $isAr ? '→ العودة إلى المستخدمين' : '← Back to Users' ?></a>
    </div>

</div>


<!-- ========================================
     MAIN CONTAINER
======================================== -->

<div class="container">


    <div class="card">


        <h2>
            <?= $isAr ? 'تعديل المستخدم' : 'Edit User' ?>
        </h2>


        <div class="subtitle">
            <?= $isAr ? 'تحديث معلومات حساب المستخدم.' : 'Update the user\'s account information.' ?>
        </div>


        <?php if ($error): ?>

            <div class="alert alert-error">

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <div class="info-box">

            <strong><?= $isAr ? 'ملاحظة:' : 'Note:' ?></strong>

            <?= $isAr ? 'يمكنك تحديث اسم المستخدم وحالة الحساب.' : 'You can update the username and account status.' ?>

            <?= $isAr ? 'لا يتم تغيير كلمة مرور المستخدم من هذه الصفحة.' : 'The user\'s password is not changed from this page.' ?>

            <?= $isAr ? 'تتم إدارة كلمات المرور بشكل منفصل.' : 'Password management will be handled separately.' ?>

        </div>


        <form method="POST">


            <!-- ====================================
                 USERNAME
            ==================================== -->

            <div class="form-group">

                <label for="username">
                    <?= $isAr ? 'اسم المستخدم' : 'Username' ?>
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    maxlength="50"
                    value="<?= htmlspecialchars(
                        $user['username']
                    ) ?>"
                    required
                >

                <div class="help-text">
                    <?= $isAr ? 'يُستخدم اسم المستخدم هذا لتسجيل الدخول.' : 'This username is used for login.' ?>
                </div>

            </div>


            <!-- ====================================
                 ROLE
            ==================================== -->

            <div class="form-group">

                <label for="role">
                    <?= $isAr ? 'الدور' : 'Role' ?>
                </label>

                <input
                    type="text"
                    id="role"
                    class="readonly"
                    value="<?= htmlspecialchars(
                        ($isAr ? ($user['role'] === 'staff' ? 'موظف' : 'مدرب') : ucfirst($user['role']))
                    ) ?>"
                    readonly
                >

                <div class="help-text">
                    <?= $isAr ? 'لا يمكن تغيير الدور هنا لأن صلاحيات وملفات حسابات الموظفين والمدربين مختلفة في النظام.' : 'The role cannot be changed here because Staff and Instructor accounts have different system permissions and profiles.' ?>
                </div>

            </div>


            <!-- ====================================
                 STATUS
            ==================================== -->

            <div class="form-group">

                <label for="status">
                    <?= $isAr ? 'الحالة' : 'Status' ?>
                </label>

                <select
                    id="status"
                    name="status"
                    required
                >

                    <option
                        value="active"
                        <?= $user['status'] === 'active'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        <?= $isAr ? 'نشط' : 'Active' ?>
                    </option>

                    <option
                        value="inactive"
                        <?= $user['status'] === 'inactive'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        <?= $isAr ? 'غير نشط' : 'Inactive' ?>
                    </option>

                </select>

                <div class="help-text">
                    <?= $isAr ? 'لا يمكن للمستخدمين غير النشطين تسجيل الدخول إلى النظام.' : 'Inactive users cannot log in to the system.' ?>
                </div>

            </div>


            <!-- ====================================
                 LAST LOGIN
            ==================================== -->

            <div class="form-group">

                <label>
                    <?= $isAr ? 'آخر تسجيل دخول' : 'Last Login' ?>
                </label>

                <input
                    type="text"
                    class="readonly"
                    value="<?= $user['last_login']
                        ? htmlspecialchars($user['last_login'])
                        : ($isAr ? 'أبدًا' : 'Never')
                    ?>"
                    readonly
                >

            </div>


            <!-- ====================================
                 CREATED
            ==================================== -->

            <div class="form-group">

                <label>
                    <?= $isAr ? 'تاريخ إنشاء الحساب' : 'Account Created' ?>
                </label>

                <input
                    type="text"
                    class="readonly"
                    value="<?= htmlspecialchars(
                        $user['created_at']
                    ) ?>"
                    readonly
                >

            </div>


            <!-- ====================================
                 ACTION BUTTONS
            ==================================== -->

            <div class="actions">

                <a
                    href="index.php"
                    class="btn btn-secondary"
                >
                    <?= $isAr ? 'إلغاء' : 'Cancel' ?>
                </a>


                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    <?= $isAr ? 'حفظ التغييرات' : 'Save Changes' ?>
                </button>

            </div>


        </form>


    </div>


</div>


</body>

</html>