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

// Only Admin can create Instructor accounts
if ($_SESSION['role'] !== 'admin') {
    die(
        $lang === 'ar'
            ? 'تم رفض الوصول. المسؤول فقط يمكنه إضافة المدرسين.'
            : 'Access denied. Only Admin can add instructors.'
    );
}


// ========================================
// VARIABLES
// ========================================

$error = '';


// ========================================
// HANDLE FORM SUBMISSION
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username       = trim($_POST['username'] ?? '');
    $password       = $_POST['password'] ?? '';
    $full_name      = trim($_POST['full_name'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $status         = $_POST['status'] ?? 'active';


    // ========================================
    // VALIDATION
    // ========================================

    if ($username === '') {

        $error = $lang === 'ar'
            ? 'اسم المستخدم مطلوب.'
            : 'Username is required.';

    } elseif ($password === '') {

        $error = $lang === 'ar'
            ? 'كلمة المرور مطلوبة.'
            : 'Password is required.';

    } elseif (strlen($password) < 8) {

        $error = $lang === 'ar'
            ? 'يجب أن تتكون كلمة المرور من 8 أحرف على الأقل.'
            : 'Password must be at least 8 characters.';

    } elseif ($full_name === '') {

        $error = $lang === 'ar'
            ? 'الاسم الكامل مطلوب.'
            : 'Full name is required.';

    } elseif (!in_array($status, ['active', 'inactive'], true)) {

        $error = $lang === 'ar'
            ? 'الحالة غير صالحة.'
            : 'Invalid status.';

    } else {

        try {

            // ========================================
            // CHECK USERNAME
            // ========================================

            $checkUsername = $pdo->prepare("
                SELECT id
                FROM users
                WHERE username = ?
                LIMIT 1
            ");

            $checkUsername->execute([$username]);

            if ($checkUsername->fetch()) {

                $error = $lang === 'ar'
                    ? 'اسم المستخدم موجود بالفعل. يرجى اختيار اسم مستخدم آخر.'
                    : 'Username already exists. Please choose another username.';

            } else {

                // ========================================
                // START TRANSACTION
                // ========================================

                $pdo->beginTransaction();


                // ========================================
                // CREATE USER ACCOUNT
                // ========================================

                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );


                $userStmt = $pdo->prepare("
                    INSERT INTO users
                    (
                        username,
                        password_hash,
                        role,
                        status
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        'instructor',
                        ?
                    )
                ");


                $userStmt->execute([
                    $username,
                    $passwordHash,
                    $status
                ]);


                // Get newly created User ID

                $userId = $pdo->lastInsertId();


                // ========================================
                // CREATE INSTRUCTOR
                // ========================================

                $instructorStmt = $pdo->prepare("
                    INSERT INTO instructors
                    (
                        user_id,
                        full_name,
                        phone,
                        email,
                        specialization,
                        status
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )
                ");


                $instructorStmt->execute([
                    $userId,
                    $full_name,
                    $phone !== '' ? $phone : null,
                    $email !== '' ? $email : null,
                    $specialization !== '' ? $specialization : null,
                    $status
                ]);


                // ========================================
                // COMMIT
                // ========================================

                $pdo->commit();


                // ========================================
                // SUCCESS
                // ========================================

                header(
                    'Location: index.php?success=1'
                );

                exit;
            }


        } catch (PDOException $e) {

            // ========================================
            // ROLLBACK
            // ========================================

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $lang === 'ar'
                ? 'فشل إنشاء حساب المدرس. يرجى المحاولة مرة أخرى.'
                : 'Failed to create instructor. Please try again.';

            // Uncomment temporarily for debugging if needed:
            // $error = $e->getMessage();
        }
    }
}


// ========================================
// SIDEBAR
// ========================================

require_once '../../includes/sidebar.php';

?>

<style>

/* ========================================
   PAGE HEADER
======================================== */

.page-header {
    padding: 25px 30px 10px;
}

.page-header h1 {
    margin: 0;
    font-size: 26px;
    color: #111827;
}

.page-header p {
    margin: 5px 0 0;
    color: #6b7280;
    font-size: 14px;
}


/* ========================================
   BACK BUTTON
======================================== */

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


/* ========================================
   CONTAINER
======================================== */

.container {
    padding: 20px 30px 40px;
}


/* ========================================
   CARD
======================================== */

.card {
    max-width: 850px;
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow:
        0 2px 10px rgba(0, 0, 0, 0.06);
}

.card h2 {
    margin-top: 0;
    margin-bottom: 25px;
    color: #111827;
    font-size: 20px;
}


/* ========================================
   FORM
======================================== */

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
    margin-bottom: 7px;
    font-size: 14px;
    font-weight: 600;
    color: #374151;
}

.required {
    color: #dc2626;
}

input,
select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    outline: none;
    background: white;
}

input:focus,
select:focus {
    border-color: #2563eb;
    box-shadow:
        0 0 0 2px rgba(37, 99, 235, 0.1);
}


/* ========================================
   PASSWORD HELP
======================================== */

.help-text {
    margin-top: 6px;
    font-size: 12px;
    color: #6b7280;
}


/* ========================================
   ERROR
======================================== */

.alert-error {
    margin-bottom: 20px;
    padding: 12px 15px;
    background: #fee2e2;
    color: #991b1b;
    border-radius: 6px;
    font-size: 14px;
    border: 1px solid #fecaca;
}


/* ========================================
   BUTTONS
======================================== */

.form-actions {
    display: flex;
    gap: 10px;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}

.btn {
    display: inline-block;
    padding: 10px 18px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
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
    background: #f3f4f6;
    color: #374151;
}

.btn-secondary:hover {
    background: #e5e7eb;
}


/* ========================================
   INFO BOX
======================================== */

.info-box {
    margin-top: 20px;
    padding: 15px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 6px;
    color: #1e40af;
    font-size: 13px;
    line-height: 1.6;
}


/* ========================================
   RESPONSIVE
======================================== */

@media (max-width: 768px) {

    .page-header {
        padding: 20px;
    }

    .container {
        padding: 15px 20px 30px;
    }

    .card {
        padding: 20px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .form-group.full {
        grid-column: auto;
    }

    .form-actions {
        flex-direction: column;
    }

    .btn {
        width: 100%;
        text-align: center;
    }

}

</style>


<!-- ========================================
     PAGE HEADER
======================================== -->

<div class="page-header">

    <h1>
        <?= htmlspecialchars(
            $lang === 'ar' ? 'إضافة مدرس' : 'Add Instructor'
        ) ?>
    </h1>

    <p>
        <?= htmlspecialchars(
            $lang === 'ar'
                ? 'إنشاء حساب مدرس وملف شخصي للمدرس.'
                : 'Create an instructor account and instructor profile.'
        ) ?>
    </p>

    <div class="top-actions">

        <a
            href="index.php"
            class="btn-back"
        >
            <?= htmlspecialchars(
                $lang === 'ar'
                    ? '← العودة إلى المدرسين'
                    : '← Back to Instructors'
            ) ?>
        </a>

    </div>

</div>


<!-- ========================================
     MAIN CONTENT
======================================== -->

<main class="container">

    <div class="card">

        <h2>
            <?= htmlspecialchars(
                $lang === 'ar'
                    ? 'معلومات المدرس'
                    : 'Instructor Information'
            ) ?>
        </h2>


        <!-- ========================================
             ERROR MESSAGE
        ======================================== -->

        <?php if ($error !== ''): ?>

            <div class="alert-error">

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <!-- ========================================
             FORM
        ======================================== -->

        <form
            method="POST"
            action=""
        >

            <div class="form-grid">


                <!-- ========================================
                     USERNAME
                ======================================== -->

                <div class="form-group">

                    <label>

                        <?= htmlspecialchars(
                            $lang === 'ar'
                                ? 'اسم المستخدم'
                                : 'Username'
                        ) ?>

                        <span class="required">
                            *
                        </span>

                    </label>


                    <input
                        type="text"
                        name="username"
                        maxlength="50"
                        value="<?= htmlspecialchars(
                            $_POST['username'] ?? ''
                        ) ?>"
                        required
                    >


                    <div class="help-text">

                        <?= htmlspecialchars(
                            $lang === 'ar'
                                ? 'سيُستخدم اسم المستخدم هذا لتسجيل دخول المدرس.'
                                : 'This username will be used by the instructor to log in.'
                        ) ?>

                    </div>

                </div>


                <!-- ========================================
                     PASSWORD
                ======================================== -->

                <div class="form-group">

                    <label>

                        <?= htmlspecialchars(
                            $lang === 'ar'
                                ? 'كلمة المرور المؤقتة'
                                : 'Temporary Password'
                        ) ?>

                        <span class="required">
                            *
                        </span>

                    </label>


                    <input
                        type="password"
                        name="password"
                        minlength="8"
                        required
                    >


                    <div class="help-text">

                        <?= htmlspecialchars(
                            $lang === 'ar'
                                ? '8 أحرف على الأقل.'
                                : 'Minimum 8 characters.'
                        ) ?>

                    </div>

                </div>


                <!-- ========================================
                     FULL NAME
                ======================================== -->

                <div class="form-group full">

                    <label>

                        <?= htmlspecialchars(
                            $lang === 'ar'
                                ? 'الاسم الكامل'
                                : 'Full Name'
                        ) ?>

                        <span class="required">
                            *
                        </span>

                    </label>


                    <input
                        type="text"
                        name="full_name"
                        maxlength="150"
                        value="<?= htmlspecialchars(
                            $_POST['full_name'] ?? ''
                        ) ?>"
                        required
                    >

                </div>


                <!-- ========================================
                     PHONE
                ======================================== -->

                <div class="form-group">

                    <label>
                        <?= htmlspecialchars(__('phone')) ?>
                    </label>


                    <input
                        type="text"
                        name="phone"
                        maxlength="30"
                        value="<?= htmlspecialchars(
                            $_POST['phone'] ?? ''
                        ) ?>"
                    >

                </div>


                <!-- ========================================
                     EMAIL
                ======================================== -->

                <div class="form-group">

                    <label>
                        <?= htmlspecialchars(__('email')) ?>
                    </label>


                    <input
                        type="email"
                        name="email"
                        maxlength="150"
                        value="<?= htmlspecialchars(
                            $_POST['email'] ?? ''
                        ) ?>"
                    >

                </div>


                <!-- ========================================
                     SPECIALIZATION
                ======================================== -->

                <div class="form-group">

                    <label>
                        <?= htmlspecialchars(
                            $lang === 'ar'
                                ? 'التخصص'
                                : 'Specialization'
                        ) ?>
                    </label>


                    <input
                        type="text"
                        name="specialization"
                        maxlength="150"
                        value="<?= htmlspecialchars(
                            $_POST['specialization'] ?? ''
                        ) ?>"
                        placeholder="<?= htmlspecialchars(
                            $lang === 'ar'
                                ? 'مثال: اللغة الإنجليزية، IELTS، TOEFL'
                                : 'e.g. English, IELTS, TOEFL'
                        ) ?>"
                    >

                </div>


                <!-- ========================================
                     STATUS
                ======================================== -->

                <div class="form-group">

                    <label>
                        <?= htmlspecialchars(__('status')) ?>
                    </label>


                    <select name="status">

                        <option
                            value="active"
                            <?= (
                                ($_POST['status'] ?? 'active')
                                === 'active'
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= htmlspecialchars(
                                $lang === 'ar'
                                    ? 'نشط'
                                    : 'Active'
                            ) ?>
                        </option>


                        <option
                            value="inactive"
                            <?= (
                                ($_POST['status'] ?? '')
                                === 'inactive'
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= htmlspecialchars(
                                $lang === 'ar'
                                    ? 'غير نشط'
                                    : 'Inactive'
                            ) ?>
                        </option>

                    </select>

                </div>


            </div>


            <!-- ========================================
                 INFO
            ======================================== -->

            <div class="info-box">

                <strong>
                    <?= htmlspecialchars(
                        $lang === 'ar'
                            ? 'مهم:'
                            : 'Important:'
                    ) ?>
                </strong>

                <?= htmlspecialchars(
                    $lang === 'ar'
                        ? 'سيستخدم المدرس اسم المستخدم وكلمة المرور المؤقتة التي تم إنشاؤها هنا لتسجيل الدخول.'
                        : 'The instructor will use the username and temporary password created here to log in.'
                ) ?>

                <?= htmlspecialchars(
                    $lang === 'ar'
                        ? 'يجب على المدرس تغيير كلمة المرور بعد تسجيل الدخول لأول مرة.'
                        : 'The password should be changed by the instructor after the first login.'
                ) ?>

            </div>


            <!-- ========================================
                 ACTIONS
            ======================================== -->

            <div class="form-actions">


                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    <?= htmlspecialchars(
                        $lang === 'ar'
                            ? 'إنشاء مدرس'
                            : 'Create Instructor'
                    ) ?>
                </button>


                <a
                    href="index.php"
                    class="btn btn-secondary"
                >
                    <?= htmlspecialchars(
                        $lang === 'ar'
                            ? 'إلغاء'
                            : 'Cancel'
                    ) ?>
                </a>


            </div>


        </form>

    </div>

</main>


</div>