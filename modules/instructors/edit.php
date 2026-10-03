<?php

session_start();

require_once '../../config/database.php';
require_once '../../includes/language.php';

// =========================
// AUTHORIZATION
// =========================

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Only Admin can edit instructors
if ($_SESSION['role'] !== 'admin') {
    die($lang === 'ar' ? 'غير مسموح بالوصول.' : 'Access denied.');
}


// =========================
// GET INSTRUCTOR ID
// =========================

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    die($lang === 'ar' ? 'معرّف المدرّس غير صالح.' : 'Invalid instructor ID.');
}


// =========================
// FETCH INSTRUCTOR
// =========================

$stmt = $pdo->prepare("
    SELECT
        i.id,
        i.user_id,
        i.full_name,
        i.phone,
        i.email,
        i.specialization,
        i.status,
        u.username,
        u.status AS user_status
    FROM instructors i
    INNER JOIN users u ON i.user_id = u.id
    WHERE i.id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$instructor = $stmt->fetch();

if (!$instructor) {
    die($lang === 'ar' ? 'المدرّس غير موجود.' : 'Instructor not found.');
}


// =========================
// HANDLE UPDATE
// =========================

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username       = trim($_POST['username'] ?? '');
    $full_name      = trim($_POST['full_name'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $status         = $_POST['status'] ?? 'active';


    // =========================
    // VALIDATION
    // =========================

    if ($username === '') {

        $error = $lang === 'ar' ? 'اسم المستخدم مطلوب.' : 'Username is required.';

    } elseif (strlen($username) > 50) {

        $error = $lang === 'ar' ? 'يجب ألا يتجاوز اسم المستخدم 50 حرفًا.' : 'Username must not exceed 50 characters.';

    } elseif ($full_name === '') {

        $error = $lang === 'ar' ? 'الاسم الكامل مطلوب.' : 'Full name is required.';

    } elseif (strlen($full_name) > 150) {

        $error = $lang === 'ar' ? 'يجب ألا يتجاوز الاسم الكامل 150 حرفًا.' : 'Full name must not exceed 150 characters.';

    } elseif (!in_array($status, ['active', 'inactive'], true)) {

        $error = $lang === 'ar' ? 'الحالة غير صالحة.' : 'Invalid status.';
    }


    // =========================
    // CHECK USERNAME
    // =========================

    if ($error === '') {

        $checkUsername = $pdo->prepare("
            SELECT id
            FROM users
            WHERE username = ?
            AND id != ?
            LIMIT 1
        ");

        $checkUsername->execute([
            $username,
            $instructor['user_id']
        ]);

        if ($checkUsername->fetch()) {

            $error =
                $lang === 'ar' ? 'اسم المستخدم موجود بالفعل. يرجى اختيار اسم مستخدم آخر.' : 'Username already exists. Please choose another username.';
        }
    }


    // =========================
    // UPDATE
    // =========================

    if ($error === '') {

        try {

            $pdo->beginTransaction();


            // =========================
            // UPDATE USER ACCOUNT
            // =========================

            $updateUser = $pdo->prepare("
                UPDATE users
                SET
                    username = ?,
                    status = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
                AND role = 'instructor'
            ");

            $updateUser->execute([
                $username,
                $status,
                $instructor['user_id']
            ]);


            // =========================
            // UPDATE INSTRUCTOR PROFILE
            // =========================

            $updateInstructor = $pdo->prepare("
                UPDATE instructors
                SET
                    full_name = ?,
                    phone = ?,
                    email = ?,
                    specialization = ?,
                    status = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");

            $updateInstructor->execute([
                $full_name,
                $phone !== '' ? $phone : null,
                $email !== '' ? $email : null,
                $specialization !== '' ? $specialization : null,
                $status,
                $id
            ]);


            // =========================
            // COMMIT
            // =========================

            $pdo->commit();


            header('Location: index.php?updated=1');

            exit;


        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error =
                $lang === 'ar' ? 'فشل تحديث بيانات المدرّس. يرجى المحاولة مرة أخرى.' : 'Failed to update instructor. Please try again.';
        }
    }


    // Keep entered values if validation fails

    $instructor['username']       = $username;
    $instructor['full_name']      = $full_name;
    $instructor['phone']          = $phone;
    $instructor['email']          = $email;
    $instructor['specialization'] = $specialization;
    $instructor['status']         = $status;
}


// =========================
// SIDEBAR
// =========================

require_once '../../includes/sidebar.php';

?>

<style>

    * {
        box-sizing: border-box;
    }


    body {
        margin: 0;
        font-family: Arial, sans-serif;
        background: #f3f4f6;
        color: #111827;
    }


    /* =========================
       PAGE HEADER
    ========================= */

    .page-header {
        background: white;
        padding: 20px 30px;
        border-bottom: 1px solid #e5e7eb;
    }


    .page-header h1 {
        margin: 0;
        font-size: 24px;
    }


    .page-header p {
        margin: 5px 0 0;
        color: #6b7280;
        font-size: 14px;
    }


    /* =========================
       BACK BUTTON
    ========================= */

    .top-actions {
        margin-top: 12px;
    }


    .btn-back {
        display: inline-block;
        padding: 9px 14px;
        background: #6b7280;
        color: white;
        text-decoration: none;
        border-radius: 6px;
        font-size: 13px;
        font-weight: bold;
    }


    .btn-back:hover {
        background: #4b5563;
    }


    /* =========================
       CONTAINER
    ========================= */

    .container {
        padding: 30px;
        max-width: 900px;
    }


    /* =========================
       FORM CARD
    ========================= */

    .form-card {
        background: white;
        border-radius: 10px;
        padding: 30px;
        box-shadow:
            0 2px 8px rgba(0, 0, 0, 0.05);
    }


    .form-card h2 {
        margin-top: 0;
        margin-bottom: 25px;
        font-size: 20px;
    }


    /* =========================
       ALERT
    ========================= */

    .alert {
        padding: 12px 15px;
        border-radius: 6px;
        margin-bottom: 20px;
        font-size: 14px;
    }


    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }


    /* =========================
       INFO BOX
    ========================= */

    .info-box {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        color: #1e40af;
        padding: 14px 16px;
        border-radius: 6px;
        margin-bottom: 25px;
        font-size: 14px;
    }


    /* =========================
       FORM GRID
    ========================= */

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }


    .form-group {
        display: flex;
        flex-direction: column;
    }


    .form-group.full {
        grid-column: 1 / -1;
    }


    label {
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 7px;
        color: #374151;
    }


    input,
    select {
        width: 100%;
        padding: 11px 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 14px;
        outline: none;
    }


    input:focus,
    select:focus {
        border-color: #2563eb;
        box-shadow:
            0 0 0 2px rgba(37, 99, 235, 0.1);
    }


    .readonly {
        background: #f9fafb;
        color: #6b7280;
    }


    /* =========================
       ACTIONS
    ========================= */

    .actions {
        margin-top: 30px;
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        border-top: 1px solid #e5e7eb;
        padding-top: 20px;
    }


    .btn {
        display: inline-block;
        padding: 11px 18px;
        border-radius: 6px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        border: none;
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


    .required {
        color: #dc2626;
    }


    .help-text {
        font-size: 12px;
        color: #6b7280;
        margin-top: 5px;
    }


    /* =========================
       RESPONSIVE
    ========================= */

    @media (max-width: 700px) {

        .container {
            padding: 20px;
        }


        .form-card {
            padding: 20px;
        }


        .form-grid {
            grid-template-columns: 1fr;
        }


        .form-group.full {
            grid-column: auto;
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


<!-- =========================
     PAGE HEADER
========================= -->

<header class="page-header">

    <h1>
        <?= $lang === 'ar' ? 'تعديل المدرّس' : 'Edit Instructor' ?>
    </h1>

    <p>
        <?= $lang === 'ar' ? 'تحديث بيانات حساب وملف المدرّس.' : 'Update instructor profile and account information.' ?>
    </p>


    <div class="top-actions">

        <a
            href="index.php"
            class="btn-back"
        >
            <?= $lang === 'ar' ? '← العودة إلى المدرّسين' : '← Back to Instructors' ?>
        </a>

    </div>

</header>


<!-- =========================
     MAIN CONTENT
========================= -->

<main class="container">


    <div class="form-card">


        <h2>
            <?= $lang === 'ar' ? 'معلومات المدرّس' : 'Instructor Information' ?>
        </h2>


        <?php if ($error): ?>

            <div class="alert alert-error">

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <div class="info-box">

            <strong>
                <?= $lang === 'ar' ? 'ملاحظة:' : 'Note:' ?>
            </strong>

            <?= $lang === 'ar' ? 'يمكنك تحديث معلومات حساب وملف المدرّس من هنا.' : "You can update the instructor's account and profile information here." ?>

            <?= $lang === 'ar' ? 'لا يتم تغيير كلمة المرور من هذه الصفحة.' : 'The password is not changed from this page.' ?>

        </div>


        <form method="POST">


            <div class="form-grid">


                <!-- =========================
                     USERNAME
                ========================== -->

                <div class="form-group">

                    <label for="username">

                        <?= $lang === 'ar' ? 'اسم المستخدم' : 'Username' ?>

                        <span class="required">
                            *
                        </span>

                    </label>


                    <input
                        type="text"
                        id="username"
                        name="username"
                        maxlength="50"
                        value="<?= htmlspecialchars(
                            $instructor['username']
                        ) ?>"
                        required
                    >


                    <div class="help-text">

                        <?= $lang === 'ar' ? 'يستخدم المدرّس اسم المستخدم هذا لتسجيل الدخول.' : 'This username is used by the instructor to log in.' ?>

                    </div>

                </div>


                <!-- =========================
                     ROLE
                ========================== -->

                <div class="form-group">

                    <label for="role">
                        <?= $lang === 'ar' ? 'الدور' : 'Role' ?>
                    </label>


                    <input
                        type="text"
                        id="role"
                        value="<?= $lang === 'ar' ? 'مدرّس' : 'Instructor' ?>"
                        class="readonly"
                        readonly
                    >


                    <div class="help-text">

                        <?= $lang === 'ar' ? 'لا يمكن تغيير دور المدرّس من هنا.' : 'Instructor role cannot be changed here.' ?>

                    </div>

                </div>


                <!-- =========================
                     FULL NAME
                ========================== -->

                <div class="form-group full">

                    <label for="full_name">

                        <?= $lang === 'ar' ? 'الاسم الكامل' : 'Full Name' ?>

                        <span class="required">
                            *
                        </span>

                    </label>


                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        maxlength="150"
                        value="<?= htmlspecialchars(
                            $instructor['full_name']
                        ) ?>"
                        required
                    >

                </div>


                <!-- =========================
                     PHONE
                ========================== -->

                <div class="form-group">

                    <label for="phone">
                        <?= $lang === 'ar' ? 'الهاتف' : 'Phone' ?>
                    </label>


                    <input
                        type="text"
                        id="phone"
                        name="phone"
                        maxlength="30"
                        value="<?= htmlspecialchars(
                            $instructor['phone'] ?? ''
                        ) ?>"
                    >

                </div>


                <!-- =========================
                     EMAIL
                ========================== -->

                <div class="form-group">

                    <label for="email">
                        <?= $lang === 'ar' ? 'البريد الإلكتروني' : 'Email' ?>
                    </label>


                    <input
                        type="email"
                        id="email"
                        name="email"
                        maxlength="150"
                        value="<?= htmlspecialchars(
                            $instructor['email'] ?? ''
                        ) ?>"
                    >

                </div>


                <!-- =========================
                     SPECIALIZATION
                ========================== -->

                <div class="form-group full">

                    <label for="specialization">
                        <?= $lang === 'ar' ? 'التخصص' : 'Specialization' ?>
                    </label>


                    <input
                        type="text"
                        id="specialization"
                        name="specialization"
                        maxlength="150"
                        value="<?= htmlspecialchars(
                            $instructor['specialization'] ?? ''
                        ) ?>"
                        placeholder="<?= $lang === 'ar' ? 'مثال: اللغة الإنجليزية، IELTS، TOEFL' : 'e.g. English Language, IELTS, TOEFL' ?>"
                    >

                </div>


                <!-- =========================
                     STATUS
                ========================== -->

                <div class="form-group">

                    <label for="status">

                        <?= $lang === 'ar' ? 'الحالة' : 'Status' ?>

                        <span class="required">
                            *
                        </span>

                    </label>


                    <select
                        id="status"
                        name="status"
                        required
                    >

                        <option
                            value="active"
                            <?= $instructor['status'] === 'active'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= $lang === 'ar' ? 'نشط' : 'Active' ?>
                        </option>


                        <option
                            value="inactive"
                            <?= $instructor['status'] === 'inactive'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= $lang === 'ar' ? 'غير نشط' : 'Inactive' ?>
                        </option>

                    </select>


                    <div class="help-text">

                        <?= $lang === 'ar' ? 'المدرّسون غير النشطين لا يمكنهم تسجيل الدخول.' : 'Inactive instructors cannot log in.' ?>

                    </div>

                </div>


            </div>


            <!-- =========================
                 ACTION BUTTONS
            ========================== -->

            <div class="actions">


                <a
                    href="index.php"
                    class="btn btn-secondary"
                >
                    <?= $lang === 'ar' ? 'إلغاء' : 'Cancel' ?>
                </a>


                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    <?= $lang === 'ar' ? 'حفظ التغييرات' : 'Save Changes' ?>
                </button>


            </div>


        </form>


    </div>


</main>


</div>