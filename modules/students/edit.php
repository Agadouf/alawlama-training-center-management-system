<?php
session_start();

require_once '../../config/database.php';
require_once '../../includes/language.php';

// =========================
// AUTHENTICATION
// =========================

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$allowedRoles = ['admin', 'staff'];

if (!in_array($_SESSION['role'], $allowedRoles, true)) {
    http_response_code(403);
    exit('Access denied.');
}


// =========================
// GET STUDENT ID
// =========================

$studentId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($studentId <= 0) {
    header('Location: index.php');
    exit;
}


// =========================
// GET STUDENT
// =========================

$stmt = $pdo->prepare("
    SELECT
        id,
        student_code,
        full_name,
        gender,
        date_of_birth,
        phone,
        email,
        address,
        guardian_name,
        guardian_phone,
        registration_date,
        status,
        photo
    FROM students
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$studentId]);

$student = $stmt->fetch();

if (!$student) {
    header('Location: index.php');
    exit;
}


$message = '';
$error = '';

/*
|--------------------------------------------------------------------------
| Page Translation Helper
|--------------------------------------------------------------------------
| Keeps all existing functionality intact while translating this page.
|--------------------------------------------------------------------------
*/
function student_t($en, $ar)
{
    global $lang;
    return ($lang ?? 'en') === 'ar' ? $ar : $en;
}


// =========================
// UPDATE STUDENT
// =========================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_student'])
) {

    $fullName = trim($_POST['full_name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $dateOfBirth = $_POST['date_of_birth'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $guardianName = trim($_POST['guardian_name'] ?? '');
    $guardianPhone = trim($_POST['guardian_phone'] ?? '');
    $registrationDate = $_POST['registration_date'] ?? '';
    $status = $_POST['status'] ?? 'active';

    $newPhotoPath = null;
    $oldPhotoPath = $student['photo'] ?? null;


    // =========================
    // PHOTO UPLOAD
    // =========================

    if (
        isset($_FILES['photo'])
        && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE
    ) {
        if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $error = student_t(
                'Failed to upload student photo.',
                'فشل رفع صورة الطالب.'
            );
        } elseif ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
            $error = student_t(
                'Student photo must not exceed 5 MB.',
                'يجب ألا يتجاوز حجم صورة الطالب 5 ميجابايت.'
            );
        } else {
            $imageInfo = @getimagesize($_FILES['photo']['tmp_name']);

            $allowedTypes = [
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_PNG  => 'png',
                IMAGETYPE_WEBP => 'webp'
            ];

            if (
                !$imageInfo
                || !isset($allowedTypes[$imageInfo[2]])
            ) {
                $error = student_t(
                    'Invalid photo. Please upload a JPG, PNG, or WEBP image.',
                    'صورة غير صالحة. يرجى رفع صورة بصيغة JPG أو PNG أو WEBP.'
                );
            } else {
                $uploadDir = __DIR__ . '/../../uploads/students/';

                if (
                    !is_dir($uploadDir)
                    && !mkdir($uploadDir, 0755, true)
                ) {
                    $error = student_t(
                        'Unable to create the student photo upload directory.',
                        'تعذر إنشاء مجلد صور الطلاب.'
                    );
                } else {
                    $filename =
                        'student_'
                        . bin2hex(random_bytes(16))
                        . '.'
                        . $allowedTypes[$imageInfo[2]];

                    $destination = $uploadDir . $filename;

                    if (
                        !move_uploaded_file(
                            $_FILES['photo']['tmp_name'],
                            $destination
                        )
                    ) {
                        $error = student_t(
                            'Failed to save the student photo.',
                            'فشل حفظ صورة الطالب.'
                        );
                    } else {
                        $newPhotoPath =
                            'uploads/students/' . $filename;
                    }
                }
            }
        }
    }


    // =========================
    // VALIDATION
    // =========================

    if (
        $error === ''
        && (
            $fullName === ''
            || $gender === ''
            || $registrationDate === ''
        )
    ) {

        $error =
            'Full name, gender, and registration date are required.';

    } elseif (
        $error === ''
        && !in_array($gender, ['Male', 'Female'], true)
    ) {

        $error = student_t('Invalid gender selected.', 'تم اختيار جنس غير صالح.');

    } elseif (
        $error === ''
        && !in_array(
            $status,
            ['active', 'suspended', 'inactive', 'graduated'],
            true
        )
    ) {

        $error = student_t('Invalid student status selected.', 'تم اختيار حالة طالب غير صالحة.');

    } else {

        try {

            // =========================
            // UPDATE
            // =========================

            $stmt = $pdo->prepare("
                UPDATE students
                SET
                    full_name = ?,
                    gender = ?,
                    date_of_birth = ?,
                    phone = ?,
                    email = ?,
                    address = ?,
                    guardian_name = ?,
                    guardian_phone = ?,
                    registration_date = ?,
                    status = ?,
                    photo = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");

            $stmt->execute([

                $fullName,

                $gender,

                $dateOfBirth !== ''
                    ? $dateOfBirth
                    : null,

                $phone !== ''
                    ? $phone
                    : null,

                $email !== ''
                    ? $email
                    : null,

                $address !== ''
                    ? $address
                    : null,

                $guardianName !== ''
                    ? $guardianName
                    : null,

                $guardianPhone !== ''
                    ? $guardianPhone
                    : null,

                $registrationDate,

                $status,

                $newPhotoPath !== null
                    ? $newPhotoPath
                    : $oldPhotoPath,

                $studentId
            ]);

            // Delete the previous photo only after the database update succeeds.
            if (
                $newPhotoPath !== null
                && !empty($oldPhotoPath)
            ) {
                $oldPhotoFile = __DIR__ . '/../../' . ltrim($oldPhotoPath, '/');

                if (
                    is_file($oldPhotoFile)
                    && strpos(realpath($oldPhotoFile) ?: '', realpath(__DIR__ . '/../../uploads/students/') ?: '') === 0
                ) {
                    @unlink($oldPhotoFile);
                }
            }


            $message = student_t('Student information updated successfully.', 'تم تحديث بيانات الطالب بنجاح.');


            // Reload updated student

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    student_code,
                    full_name,
                    gender,
                    date_of_birth,
                    phone,
                    email,
                    address,
                    guardian_name,
                    guardian_phone,
                    registration_date,
                    status,
                    photo
                FROM students
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([$studentId]);

            $student = $stmt->fetch();


        } catch (PDOException $e) {

            $error =
                student_t('Database error: ', 'خطأ في قاعدة البيانات: ') . $e->getMessage();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang ?? 'en') ?>" dir="<?= htmlspecialchars($dir ?? 'ltr') ?>">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Edit Student - ALAW LAMA Training Center
    </title>


    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }


        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            color: #1f2937;
        }


        /* =========================
           HEADER
        ========================== */

        .header {

            background: #1f2937;

            color: white;

            padding: 18px 30px;

            display: flex;

            justify-content: space-between;

            align-items: center;

            min-height: 70px;
        }


        .header h1 {
            font-size: 22px;
        }


        .header-right {

            display: flex;

            align-items: center;

            gap: 15px;
        }


        .user-info {

            color: #d1d5db;

            font-size: 14px;
        }


        .logout-button {

            background: #dc2626;

            color: white;

            text-decoration: none;

            padding: 9px 15px;

            border-radius: 6px;

            font-size: 14px;
        }


        .logout-button:hover {

            background: #b91c1b;
        }


        /* =========================
           CONTENT
        ========================== */

        .container {

            width: 100%;

            padding: 30px;
        }


        .page-title {

            margin-bottom: 25px;
        }


        .page-title h2 {

            font-size: 28px;

            margin-bottom: 7px;
        }


        .page-title p {

            color: #6b7280;

            font-size: 16px;
        }


        .top-actions {
            margin-top: 12px;
        }


        /* =========================
           CARD
        ========================== */

        .card {

            max-width: 850px;

            background: white;

            padding: 30px;

            border-radius: 10px;

            box-shadow:
                0 2px 8px rgba(0, 0, 0, 0.05);
        }


        .student-id {

            background: #eff6ff;

            border: 1px solid #bfdbfe;

            color: #1d4ed8;

            padding: 12px 15px;

            border-radius: 6px;

            margin-bottom: 25px;

            font-size: 14px;
        }


        .student-id strong {

            font-size: 15px;
        }


        /* =========================
           FORM
        ========================== */

        .form-grid {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 20px;
        }


        .form-group {

            margin-bottom: 5px;
        }


        .form-group.full {

            grid-column: 1 / -1;
        }


        label {

            display: block;

            margin-bottom: 6px;

            font-weight: bold;

            font-size: 14px;
        }


        .required {

            color: #dc2626;
        }


        input,
        select,
        textarea {

            width: 100%;

            padding: 11px;

            border:
                1px solid #d1d5db;

            border-radius: 6px;

            font-size: 14px;

            background: white;
        }


        textarea {

            min-height: 90px;

            resize: vertical;
        }


        input:focus,
        select:focus,
        textarea:focus {

            outline: none;

            border-color: #2563eb;
        }


        /* =========================
           ALERTS
        ========================== */

        .alert {

            padding: 14px 18px;

            border-radius: 6px;

            margin-bottom: 20px;

            max-width: 850px;
        }


        .success {

            background: #dcfce7;

            color: #166534;
        }


        .error {

            background: #fee2e2;

            color: #991b1b;
        }


        /* =========================
           BUTTONS
        ========================== */

        .actions {

            display: flex;

            gap: 12px;

            margin-top: 25px;
        }


        .btn {

            border: none;

            padding: 11px 20px;

            border-radius: 6px;

            cursor: pointer;

            font-weight: bold;

            font-size: 14px;

            text-decoration: none;

            display: inline-block;
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


        .btn-back {

            background: #6b7280;

            color: white;
        }


        .btn-back:hover {

            background: #4b5563;
        }


        .photo-section {
            margin-top: 5px;
        }

        .photo-upload {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 15px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #f9fafb;
        }

        .photo-preview {
            width: 110px;
            height: 130px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: white;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .photo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-placeholder {
            padding: 10px;
            text-align: center;
            color: #6b7280;
            font-size: 13px;
        }

        .photo-controls {
            flex: 1;
        }

        .photo-controls input[type="file"] {
            padding: 8px;
            background: white;
        }

        .photo-controls small {
            display: block;
            margin-top: 7px;
            color: #6b7280;
            line-height: 1.5;
        }


        /* =========================
           RESPONSIVE
        ========================== */

        @media (max-width: 700px) {

            .form-grid {

                grid-template-columns: 1fr;
            }

            .form-group.full {

                grid-column: auto;
            }

            .container {

                padding: 20px;
            }

            .photo-upload {
                flex-direction: column;
                align-items: stretch;
            }

            .photo-preview {
                margin: 0 auto;
            }

            .header {

                padding: 15px 20px;
            }

            .header h1 {

                font-size: 18px;
            }

            .user-info {

                display: none;
            }
        }

    </style>

</head>


<body>


<?php

require_once '../../includes/sidebar.php';

?>


<!-- =========================
     HEADER
========================== -->

<header class="header">

    <h1>
        ALAW LAMA TRAINING CENTER
    </h1>


    <div class="header-right">

        <span class="user-info">

            <?= htmlspecialchars($_SESSION['username']) ?>

            |

            <?= htmlspecialchars(
                ucfirst($_SESSION['role'])
            ) ?>

        </span>


        <a
            href="../../logout.php"
            class="logout-button"
        >
            <?= htmlspecialchars(student_t('Logout', 'تسجيل الخروج')) ?>
        </a>

    </div>

</header>


<!-- =========================
     CONTENT
========================== -->

<main class="container">


    <div class="page-title">

        <h2>
            <?= htmlspecialchars(student_t('Edit Student', 'تعديل الطالب')) ?>
        </h2>

        <p>
            <?= htmlspecialchars(student_t('Update student information.', 'تحديث بيانات الطالب.')) ?>
        </p>

        <div class="top-actions">

            <a
                href="index.php"
                class="btn btn-back"
            >
                <?= htmlspecialchars(student_t('← Back to Students', '← العودة إلى الطلاب')) ?>
            </a>

        </div>

    </div>


    <!-- =========================
         ALERTS
    ========================== -->

    <?php if ($message): ?>

        <div class="alert success">

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="alert error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- =========================
         FORM CARD
    ========================== -->

    <div class="card">


        <div class="student-id">

            <?= htmlspecialchars(student_t('Student ID:', 'رقم الطالب:')) ?>

            <strong>
                <?= htmlspecialchars(
                    $student['student_code']
                ) ?>
            </strong>

            <br>

            <small>
                <?= htmlspecialchars(student_t('Student ID cannot be changed.', 'لا يمكن تغيير رقم الطالب.')) ?>
            </small>

        </div>


        <form method="POST" enctype="multipart/form-data">


            <div class="form-grid">


                <!-- FULL NAME -->

                <div class="form-group">

                    <label>

                        <?= htmlspecialchars(student_t('Full Name', 'الاسم الكامل')) ?>

                        <span class="required">
                            *
                        </span>

                    </label>

                    <input
                        type="text"
                        name="full_name"
                        maxlength="150"
                        value="<?= htmlspecialchars(
                            $student['full_name']
                        ) ?>"
                        required
                    >

                </div>


                <!-- GENDER -->

                <div class="form-group">

                    <label>

                        <?= htmlspecialchars(student_t('Gender', 'الجنس')) ?>

                        <span class="required">
                            *
                        </span>

                    </label>

                    <select
                        name="gender"
                        required
                    >

                        <option value="">
                            <?= htmlspecialchars(student_t('Select Gender', 'اختر الجنس')) ?>
                        </option>

                        <option
                            value="Male"
                            <?= $student['gender'] === 'Male'
                                ? 'selected'
                                : '' ?>
                        >
                            <?= htmlspecialchars(student_t('Male', 'ذكر')) ?>
                        </option>

                        <option
                            value="Female"
                            <?= $student['gender'] === 'Female'
                                ? 'selected'
                                : '' ?>
                        >
                            <?= htmlspecialchars(student_t('Female', 'أنثى')) ?>
                        </option>

                    </select>

                </div>


                <!-- DATE OF BIRTH -->

                <div class="form-group">

                    <label>
                        <?= htmlspecialchars(student_t('Date of Birth', 'تاريخ الميلاد')) ?>
                    </label>

                    <input
                        type="date"
                        name="date_of_birth"
                        value="<?= htmlspecialchars(
                            $student['date_of_birth'] ?? ''
                        ) ?>"
                    >

                </div>


                <!-- PHONE -->

                <div class="form-group">

                    <label>
                        <?= htmlspecialchars(student_t('Phone', 'الهاتف')) ?>
                    </label>

                    <input
                        type="text"
                        name="phone"
                        maxlength="30"
                        value="<?= htmlspecialchars(
                            $student['phone'] ?? ''
                        ) ?>"
                    >

                </div>


                <!-- EMAIL -->

                <div class="form-group">

                    <label>
                        <?= htmlspecialchars(student_t('Email', 'البريد الإلكتروني')) ?>
                    </label>

                    <input
                        type="email"
                        name="email"
                        maxlength="150"
                        value="<?= htmlspecialchars(
                            $student['email'] ?? ''
                        ) ?>"
                    >

                </div>


                <!-- REGISTRATION DATE -->

                <div class="form-group">

                    <label>

                        <?= htmlspecialchars(student_t('Registration Date', 'تاريخ التسجيل')) ?>

                        <span class="required">
                            *
                        </span>

                    </label>

                    <input
                        type="date"
                        name="registration_date"
                        value="<?= htmlspecialchars(
                            $student['registration_date']
                        ) ?>"
                        required
                    >

                </div>


                <!-- STATUS -->

                <div class="form-group">

                    <label>
                        <?= htmlspecialchars(student_t('Status', 'الحالة')) ?>
                    </label>

                    <select name="status">

                        <option
                            value="active"
                            <?= $student['status'] === 'active'
                                ? 'selected'
                                : '' ?>
                        >
                            <?= htmlspecialchars(student_t('Active', 'نشط')) ?>
                        </option>

                        <option
                            value="suspended"
                            <?= $student['status'] === 'suspended'
                                ? 'selected'
                                : '' ?>
                        >
                            <?= htmlspecialchars(student_t('Suspended', 'موقوف')) ?>
                        </option>

                        <option
                            value="inactive"
                            <?= $student['status'] === 'inactive'
                                ? 'selected'
                                : '' ?>
                        >
                            <?= htmlspecialchars(student_t('Inactive', 'غير نشط')) ?>
                        </option>

                        <option
                            value="graduated"
                            <?= $student['status'] === 'graduated'
                                ? 'selected'
                                : '' ?>
                        >
                            <?= htmlspecialchars(student_t('Graduated', 'متخرج')) ?>
                        </option>

                    </select>

                </div>


                <!-- GUARDIAN NAME -->

                <div class="form-group">

                    <label>
                        <?= htmlspecialchars(student_t('Guardian Name', 'اسم ولي الأمر')) ?>
                    </label>

                    <input
                        type="text"
                        name="guardian_name"
                        maxlength="150"
                        value="<?= htmlspecialchars(
                            $student['guardian_name'] ?? ''
                        ) ?>"
                    >

                </div>


                <!-- GUARDIAN PHONE -->

                <div class="form-group">

                    <label>
                        <?= htmlspecialchars(student_t('Guardian Phone', 'هاتف ولي الأمر')) ?>
                    </label>

                    <input
                        type="text"
                        name="guardian_phone"
                        maxlength="30"
                        value="<?= htmlspecialchars(
                            $student['guardian_phone'] ?? ''
                        ) ?>"
                    >

                </div>


                <!-- ADDRESS -->

                <div class="form-group full">

                    <label>
                        <?= htmlspecialchars(student_t('Address', 'العنوان')) ?>
                    </label>

                    <textarea
                        name="address"
                    ><?= htmlspecialchars(
                        $student['address'] ?? ''
                    ) ?></textarea>

                </div>


            </div>


            <!-- STUDENT PHOTO -->

            <div class="form-group full photo-section">

                <label>
                    <?= htmlspecialchars(student_t('Profile Photo', 'صورة الملف الشخصي')) ?>
                </label>

                <div class="photo-upload">

                    <div class="photo-preview">
                        <?php if (!empty($student['photo'])): ?>
                            <img
                                src="../../<?= htmlspecialchars(ltrim($student['photo'], '/')) ?>"
                                alt="<?= htmlspecialchars(student_t('Student Photo', 'صورة الطالب')) ?>"
                            >
                        <?php else: ?>
                            <div class="photo-placeholder">
                                <?= htmlspecialchars(student_t('No photo uploaded', 'لا توجد صورة')) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="photo-controls">

                        <input
                            type="file"
                            name="photo"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                        >

                        <small>
                            <?= htmlspecialchars(
                                student_t(
                                    'JPG, PNG, or WEBP. Maximum 5 MB. Leave empty to keep the current photo.',
                                    'JPG أو PNG أو WEBP. الحد الأقصى 5 ميجابايت. اتركه فارغًا للاحتفاظ بالصورة الحالية.'
                                )
                            ) ?>
                        </small>

                    </div>

                </div>

            </div>


            <!-- ACTIONS -->

            <div class="actions">

                <button
                    type="submit"
                    name="update_student"
                    class="btn btn-primary"
                >
                    <?= htmlspecialchars(student_t('Save Changes', 'حفظ التغييرات')) ?>
                </button>


                <a
                    href="index.php"
                    class="btn btn-secondary"
                >
                    <?= htmlspecialchars(student_t('Cancel', 'إلغاء')) ?>
                </a>

            </div>


        </form>


    </div>


</main>


</div>


</body>

</html>