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
    exit($lang === 'ar' ? 'غير مسموح بالوصول.' : 'Access denied.');
}

$message = '';
$error = '';


// ========================================
// ADD USER
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    if ($username === '' || $password === '' || $role === '') {

        $error = $lang === 'ar' ? 'يرجى ملء جميع الحقول المطلوبة.' : 'Please fill in all required fields.';

    } elseif (!in_array($role, ['staff', 'instructor'], true)) {

        $error = $lang === 'ar' ? 'الدور المحدد غير صالح.' : 'Invalid role selected.';

    } elseif (strlen($password) < 6) {

        $error = $lang === 'ar' ? 'يجب أن تتكون كلمة المرور من 6 أحرف على الأقل.' : 'Password must be at least 6 characters.';

    } else {

        try {

            // Check username
            $stmt = $pdo->prepare(
                "SELECT id
                 FROM users
                 WHERE username = ?
                 LIMIT 1"
            );

            $stmt->execute([$username]);

            if ($stmt->fetch()) {

                $error = $lang === 'ar' ? 'اسم المستخدم موجود بالفعل.' : 'Username already exists.';

            } else {

                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $stmt = $pdo->prepare(
                    "INSERT INTO users
                    (username, password_hash, role, status)
                    VALUES (?, ?, ?, 'active')"
                );

                $stmt->execute([
                    $username,
                    $passwordHash,
                    $role
                ]);

                $message = $lang === 'ar' ? 'تم إنشاء المستخدم بنجاح.' : 'User created successfully.';
            }

        } catch (PDOException $e) {

            $error = $lang === 'ar' ? 'حدث خطأ في قاعدة البيانات.' : 'Database error occurred.';
        }
    }
}


// ========================================
// ACTIVATE / DEACTIVATE USER
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {

    $userId = (int) ($_POST['user_id'] ?? 0);

    if ($userId > 0) {

        try {

            // Never allow changing current admin account
            if ($userId === (int) $_SESSION['user_id']) {

                $error = $lang === 'ar' ? 'لا يمكنك إلغاء تنشيط حسابك الخاص.' : 'You cannot deactivate your own account.';

            } else {

                $stmt = $pdo->prepare(
                    "SELECT status
                     FROM users
                     WHERE id = ?
                     AND role IN ('staff', 'instructor')
                     LIMIT 1"
                );

                $stmt->execute([$userId]);

                $user = $stmt->fetch();

                if (!$user) {

                    $error = $lang === 'ar' ? 'المستخدم غير موجود.' : 'User not found.';

                } else {

                    $newStatus =
                        $user['status'] === 'active'
                        ? 'inactive'
                        : 'active';

                    $stmt = $pdo->prepare(
                        "UPDATE users
                         SET
                            status = ?,
                            updated_at = CURRENT_TIMESTAMP
                         WHERE id = ?
                         AND role IN ('staff', 'instructor')"
                    );

                    $stmt->execute([
                        $newStatus,
                        $userId
                    ]);

                    $message = $lang === 'ar' ? 'تم تحديث حالة المستخدم بنجاح.' : 'User status updated successfully.';
                }
            }

        } catch (PDOException $e) {

            $error = $lang === 'ar' ? 'حدث خطأ في قاعدة البيانات.' : 'Database error occurred.';
        }
    }
}


// ========================================
// GET USERS
// ========================================

$stmt = $pdo->query(
    "SELECT
        id,
        username,
        role,
        status,
        last_login,
        created_at
     FROM users
     ORDER BY
        CASE role
            WHEN 'admin' THEN 1
            WHEN 'staff' THEN 2
            WHEN 'instructor' THEN 3
        END,
        id ASC"
);

$users = $stmt->fetchAll();

?>

<!DOCTYPE html>

<html lang="<?= $lang ?>" dir="<?= $dir ?>">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= $lang === 'ar' ? 'إدارة المستخدمين - مركز العولمة للتدريب' : 'Manage Users - AlawlAma Training Center' ?>
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;

            font-family: Arial, sans-serif;

            background: #f4f6f9;

            color: #1f2937;

            font-size: 14px;
        }


        /* ========================================
           HEADER
        ======================================== */

        .header {
            background: #1f2937;

            color: white;

            padding: 15px 24px;

            display: flex;

            justify-content: space-between;

            align-items: center;
        }


        .header h1 {
            margin: 0;

            font-size: 20px;

            font-weight: 700;
        }


        .header a {
            color: white;

            text-decoration: none;

            background: #374151;

            padding: 7px 12px;

            border-radius: 5px;

            font-size: 12px;
        }


        .header a:hover {
            background: #4b5563;
        }


        /* ========================================
           MAIN CONTAINER
        ======================================== */

        .container {
            max-width: 1180px;

            margin: 22px auto;

            padding: 0 16px;
        }


        /* ========================================
           LAYOUT
        ======================================== */

        .top-section {
            display: grid;

            grid-template-columns: 285px 1fr;

            gap: 18px;

            align-items: start;
        }


        /* ========================================
           CARD
        ======================================== */

        .card {
            background: white;

            border-radius: 8px;

            padding: 18px;

            box-shadow:
                0 2px 8px rgba(0, 0, 0, 0.06);
        }


        .card h2 {
            margin: 0 0 15px;

            font-size: 17px;

            color: #111827;
        }


        /* ========================================
           FORM
        ======================================== */

        .form-group {
            margin-bottom: 12px;
        }


        label {
            display: block;

            margin-bottom: 5px;

            font-weight: 600;

            font-size: 13px;
        }


        input,
        select {
            width: 100%;

            height: 38px;

            padding: 8px 10px;

            border: 1px solid #d1d5db;

            border-radius: 5px;

            font-size: 13px;

            background: white;
        }


        input:focus,
        select:focus {
            outline: none;

            border-color: #2563eb;

            box-shadow:
                0 0 0 2px rgba(37, 99, 235, 0.08);
        }


        /* ========================================
           BUTTONS
        ======================================== */

        .btn {
            border: none;

            padding: 7px 10px;

            border-radius: 5px;

            cursor: pointer;

            font-weight: 600;

            font-size: 12px;

            text-decoration: none;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            white-space: nowrap;

            line-height: 1.2;
        }


        .btn-primary {
            background: #2563eb;

            color: white;

            width: 100%;

            height: 38px;

            font-size: 13px;
        }


        .btn-primary:hover {
            background: #1d4ed8;
        }


        /* EDIT */

        .btn-edit {
            background: #eff6ff;

            color: #1d4ed8;

            border: 1px solid #bfdbfe;
        }


        .btn-edit:hover {
            background: #dbeafe;
        }


        /* RESET */

        .btn-reset {
            background: #fff7ed;

            color: #c2410c;

            border: 1px solid #fed7aa;
        }


        .btn-reset:hover {
            background: #ffedd5;
        }


        /* DEACTIVATE */

        .btn-danger {
            background: #dc2626;

            color: white;
        }


        .btn-danger:hover {
            background: #b91c1c;
        }


        /* ACTIVATE */

        .btn-success {
            background: #16a34a;

            color: white;
        }


        .btn-success:hover {
            background: #15803d;
        }


        /* ========================================
           ALERTS
        ======================================== */

        .alert {
            padding: 10px 13px;

            border-radius: 5px;

            margin-bottom: 15px;

            font-size: 13px;
        }


        .success {
            background: #dcfce7;

            color: #166534;
        }


        .error {
            background: #fee2e2;

            color: #991b1b;
        }


        /* ========================================
           TABLE
        ======================================== */

        .table-wrapper {
            overflow-x: auto;
        }


        table {
            width: 100%;

            border-collapse: collapse;

            min-width: 720px;
        }


        th,
        td {
            padding: 10px 8px;

            border-bottom: 1px solid #e5e7eb;

            text-align: left;

            white-space: nowrap;
        }


        th {
            background: #f8fafc;

            color: #374151;

            font-size: 12px;

            font-weight: 700;
        }


        td {
            font-size: 13px;
        }


        tbody tr:hover td {
            background: #f9fafb;
        }


        /* ========================================
           BADGES
        ======================================== */

        .badge {
            display: inline-block;

            padding: 4px 8px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: 700;
        }


        .badge-admin {
            background: #ede9fe;

            color: #6d28d9;
        }


        .badge-staff {
            background: #dbeafe;

            color: #1d4ed8;
        }


        .badge-instructor {
            background: #fef3c7;

            color: #92400e;
        }


        .badge-active {
            background: #dcfce7;

            color: #166534;
        }


        .badge-inactive {
            background: #fee2e2;

            color: #991b1b;
        }


        /* ========================================
           ACTIONS
        ======================================== */

        .actions {
            display: flex;

            align-items: center;

            gap: 5px;

            flex-wrap: nowrap;
        }


        .action-form {
            display: inline;
        }


        /* ========================================
           ADMIN NOTE
        ======================================== */

        .admin-note {
            margin-top: 12px;

            padding: 10px;

            background: #f3f4f6;

            border-radius: 5px;

            font-size: 11px;

            line-height: 1.5;

            color: #4b5563;
        }


        /* ========================================
           RESPONSIVE
        ======================================== */

        @media (max-width: 1000px) {

            .top-section {
                grid-template-columns: 250px 1fr;

                gap: 15px;
            }

        }


        @media (max-width: 800px) {

            .top-section {
                grid-template-columns: 1fr;
            }


            .card {
                padding: 15px;
            }

        }


        @media (max-width: 600px) {

            .header {
                padding: 13px 16px;
            }


            .header h1 {
                font-size: 17px;
            }


            .header a {
                padding: 6px 9px;

                font-size: 11px;
            }


            .container {
                margin: 15px auto;

                padding: 0 10px;
            }


            .card {
                padding: 14px;
            }

        }

        [dir="rtl"] body { direction: rtl; }
        [dir="rtl"] .header { direction: rtl; }
        [dir="rtl"] .header h1 { text-align: right; }
        [dir="rtl"] .container { direction: rtl; }
        [dir="rtl"] th, [dir="rtl"] td { text-align: right; }
        [dir="rtl"] .actions { direction: rtl; }
        [dir="rtl"] input, [dir="rtl"] select { text-align: right; }
        [dir="rtl"] .top-section { direction: rtl; }

    </style>

</head>


<body>


<!-- ========================================
     HEADER
======================================== -->

<div class="header">

    <h1>
        <?= $lang === 'ar' ? 'مركز العولمة للتدريب' : 'ALAW LAMA TRAINING CENTER' ?>
    </h1>


    <div style="display:flex; gap:8px; align-items:center;">
        <a href="?lang=en" style="<?= $lang === 'en' ? 'background:#2563eb;' : '' ?>">
            English
        </a>
        <a href="?lang=ar" style="<?= $lang === 'ar' ? 'background:#2563eb;' : '' ?>">
            العربية
        </a>
        <a href="../dashboard/index.php">
            <?= $lang === 'ar' ? '← لوحة التحكم' : '← Dashboard' ?>
        </a>
    </div>

</div>


<!-- ========================================
     MAIN CONTAINER
======================================== -->

<div class="container">


    <!-- ========================================
         SUCCESS MESSAGE
    ======================================== -->

    <?php if ($message): ?>

        <div class="alert success">

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>


    <!-- ========================================
         USER UPDATED MESSAGE
    ======================================== -->

    <?php if (isset($_GET['updated'])): ?>

        <div class="alert success">

            <?= $lang === 'ar' ? 'تم تحديث معلومات المستخدم بنجاح.' : 'User information updated successfully.' ?>

        </div>

    <?php endif; ?>


    <!-- ========================================
         PASSWORD RESET MESSAGE
    ======================================== -->

    <?php if (isset($_GET['reset'])): ?>

        <div class="alert success">

            <?= $lang === 'ar' ? 'تم إعادة تعيين كلمة مرور المستخدم بنجاح.' : 'User password has been reset successfully.' ?>

        </div>

    <?php endif; ?>


    <!-- ========================================
         ERROR MESSAGE
    ======================================== -->

    <?php if ($error): ?>

        <div class="alert error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- ========================================
         MAIN GRID
    ======================================== -->

    <div class="top-section">


        <!-- ====================================
             ADD USER
        ==================================== -->

        <div class="card">

            <h2>
                <?= $lang === 'ar' ? 'إضافة مستخدم' : 'Add User' ?>
            </h2>


            <form method="POST">


                <!-- USERNAME -->

                <div class="form-group">

                    <label for="username">
                        <?= $lang === 'ar' ? 'اسم المستخدم' : 'Username' ?>
                    </label>

                    <input
                        type="text"
                        id="username"
                        name="username"
                        maxlength="50"
                        required
                    >

                </div>


                <!-- PASSWORD -->

                <div class="form-group">

                    <label for="password">
                        <?= $lang === 'ar' ? 'كلمة مرور مؤقتة' : 'Temporary Password' ?>
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        minlength="6"
                        required
                    >

                </div>


                <!-- ROLE -->

                <div class="form-group">

                    <label for="role">
                        <?= $lang === 'ar' ? 'الدور' : 'Role' ?>
                    </label>

                    <select
                        id="role"
                        name="role"
                        required
                    >

                        <option value="">
                            Select <?= $lang === 'ar' ? 'الدور' : 'Role' ?>
                        </option>

                        <option value="staff">
                            <?= $lang === 'ar' ? 'موظف' : 'Staff' ?>
                        </option>

                        <option value="instructor">
                            <?= $lang === 'ar' ? 'مدرس' : 'Instructor' ?>
                        </option>

                    </select>

                </div>


                <!-- CREATE USER -->

                <button
                    type="submit"
                    name="add_user"
                    class="btn btn-primary"
                >
                    <?= $lang === 'ar' ? 'إنشاء مستخدم' : 'Create User' ?>
                </button>


            </form>


            <div class="admin-note">

                <strong><?= $lang === 'ar' ? 'ملاحظة:' : 'Note:' ?></strong>

                Only <?= $lang === 'ar' ? 'موظف' : 'Staff' ?> and <?= $lang === 'ar' ? 'مدرس' : 'Instructor' ?> accounts can be
                

            </div>


        </div>


        <!-- ====================================
             SYSTEM USERS
        ==================================== -->

        <div class="card">

            <h2>
                <?= $lang === 'ar' ? 'مستخدمو النظام' : 'System Users' ?>
            </h2>


            <div class="table-wrapper">

                <table>


                    <thead>

                        <tr>

                            <th>
                                ID
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'اسم المستخدم' : 'Username' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'الدور' : 'Role' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'الحالة' : 'Status' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'آخر تسجيل دخول' : 'Last Login' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'تاريخ الإنشاء' : 'Created' ?>
                            </th>

                            <th>
                                <?= $lang === 'ar' ? 'الإجراءات' : 'Actions' ?>
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (!$users): ?>

                        <tr>

                            <td
                                colspan="7"
                                style="
                                    text-align:center;
                                    padding:25px;
                                    color:#6b7280;
                                "
                            >
                                <?= $lang === 'ar' ? 'لا يوجد مستخدمون.' : 'No users found.' ?>
                            </td>

                        </tr>


                    <?php else: ?>


                        <?php foreach ($users as $user): ?>

                            <tr>


                                <!-- ID -->

                                <td>

                                    <?= (int) $user['id'] ?>

                                </td>


                                <!-- USERNAME -->

                                <td>

                                    <strong>
                                        <?= htmlspecialchars(
                                            $user['username']
                                        ) ?>
                                    </strong>

                                </td>


                                <!-- ROLE -->

                                <td>

                                    <?php if ($user['role'] === 'admin'): ?>

                                        <span class="badge badge-admin">
                                            <?= $lang === 'ar' ? 'مسؤول' : 'Admin' ?>
                                        </span>

                                    <?php elseif ($user['role'] === 'staff'): ?>

                                        <span class="badge badge-staff">
                                            <?= $lang === 'ar' ? 'موظف' : 'Staff' ?>
                                        </span>

                                    <?php else: ?>

                                        <span class="badge badge-instructor">
                                            <?= $lang === 'ar' ? 'مدرس' : 'Instructor' ?>
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <?php if ($user['status'] === 'active'): ?>

                                        <span class="badge badge-active">
                                            <?= $lang === 'ar' ? 'نشط' : 'Active' ?>
                                        </span>

                                    <?php else: ?>

                                        <span class="badge badge-inactive">
                                            <?= $lang === 'ar' ? 'غير نشط' : 'Inactive' ?>
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- LAST LOGIN -->

                                <td>

                                    <?= $user['last_login']
                                        ? htmlspecialchars(
                                            $user['last_login']
                                        )
                                        : ($lang === 'ar' ? 'لم يسجل الدخول' : 'Never')
                                    ?>

                                </td>


                                <!-- CREATED -->

                                <td>

                                    <?= htmlspecialchars(
                                        $user['created_at']
                                    ) ?>

                                </td>


                                <!-- ACTIONS -->

                                <td>

                                    <div class="actions">


                                        <?php if ($user['role'] !== 'admin'): ?>


                                            <!-- EDIT -->

                                            <a
                                                href="edit.php?id=<?= (int) $user['id'] ?>"
                                                class="btn btn-edit"
                                            >
                                                <?= $lang === 'ar' ? 'تعديل' : 'Edit' ?>
                                            </a>


                                            <!-- RESET PASSWORD -->

                                            <a
                                                href="reset-password.php?id=<?= (int) $user['id'] ?>"
                                                class="btn btn-reset"
                                            >
                                                <?= $lang === 'ar' ? 'إعادة تعيين كلمة المرور' : 'Reset Password' ?>
                                            </a>


                                            <!-- ACTIVATE / DEACTIVATE -->

                                            <form
                                                method="POST"
                                                class="action-form"
                                                onsubmit="return confirmStatus(
                                                    '<?= htmlspecialchars(
                                                        $user['username'],
                                                        ENT_QUOTES
                                                    ) ?>',
                                                    '<?= htmlspecialchars(
                                                        $user['status'],
                                                        ENT_QUOTES
                                                    ) ?>'
                                                );"
                                            >


                                                <input
                                                    type="hidden"
                                                    name="user_id"
                                                    value="<?= (int) $user['id'] ?>"
                                                >


                                                <?php if ($user['status'] === 'active'): ?>

                                                    <button
                                                        type="submit"
                                                        name="toggle_status"
                                                        class="btn btn-danger"
                                                    >
                                                        <?= $lang === 'ar' ? 'إلغاء التنشيط' : 'Deactivate' ?>
                                                    </button>

                                                <?php else: ?>

                                                    <button
                                                        type="submit"
                                                        name="toggle_status"
                                                        class="btn btn-success"
                                                    >
                                                        <?= $lang === 'ar' ? 'تنشيط' : 'Activate' ?>
                                                    </button>

                                                <?php endif; ?>


                                            </form>


                                        <?php else: ?>


                                            <span
                                                style="
                                                    color:#6b7280;
                                                    font-size:12px;
                                                "
                                            >
                                                <?= $lang === 'ar' ? 'محمي' : 'Protected' ?>
                                            </span>


                                        <?php endif; ?>


                                    </div>

                                </td>


                            </tr>

                        <?php endforeach; ?>


                    <?php endif; ?>


                    </tbody>

                </table>

            </div>


        </div>


    </div>


</div>


<!-- ========================================
     JAVASCRIPT
======================================== -->

<script>

function confirmStatus(username, status) {

    if (status === 'active') {

        return confirm(
            <?= json_encode($lang === 'ar' ? 'هل أنت متأكد من رغبتك في إلغاء تنشيط "' : 'Are you sure you want to deactivate "') ?> +
            username +
            <?= json_encode($lang === 'ar' ? '"؟' : '"?') ?>
        );

    }

    return confirm(
        <?= json_encode($lang === 'ar' ? 'هل أنت متأكد من رغبتك في تنشيط "' : 'Are you sure you want to activate "') ?> +
        username +
        <?= json_encode($lang === 'ar' ? '"؟' : '"?') ?>
    );
}

</script>


</body>

</html>