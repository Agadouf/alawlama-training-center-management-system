<?php

session_start();

require_once '../../config/database.php';
require_once '../../includes/language.php';

// ============================================================
// AUTHORIZATION
// ============================================================

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

if (!in_array($_SESSION['role'], ['admin', 'staff'], true)) {
    die($lang === 'ar' ? 'غير مسموح بالوصول.' : 'Access denied.');
}

// ============================================================
// GET ENROLLMENT ID
// ============================================================

$enrollmentId = filter_input(INPUT_GET, 'enrollment_id', FILTER_VALIDATE_INT);

if (!$enrollmentId || $enrollmentId <= 0) {
    header('Location: index.php');
    exit;
}

// ============================================================
// FETCH COMPLETED ENROLLMENT
// ============================================================

$stmt = $pdo->prepare("
    SELECT
        e.id AS enrollment_id,
        e.enrollment_date,
        e.status AS enrollment_status,

        s.id AS student_id,
        s.student_code,
        s.full_name,
        s.gender,
        s.email,

        b.id AS batch_id,
        b.batch_code,
        b.start_date,
        b.end_date,

        c.id AS course_id,
        c.course_code,
        c.course_name

    FROM enrollments e

    INNER JOIN students s
        ON s.id = e.student_id

    INNER JOIN batches b
        ON b.id = e.batch_id

    INNER JOIN courses c
        ON c.id = b.course_id

    WHERE e.id = ?
      AND e.status = 'completed'

    LIMIT 1
");

$stmt->execute([$enrollmentId]);

$enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$enrollment) {
    die(
        $lang === 'ar'
            ? 'التسجيل غير موجود أو لم يكتمل بعد.'
            : 'Enrollment not found or not completed yet.'
    );
}

// ============================================================
// CHECK EXISTING CERTIFICATE
// ============================================================

$existingStmt = $pdo->prepare("
    SELECT id
    FROM certificates
    WHERE enrollment_id = ?
    LIMIT 1
");

$existingStmt->execute([$enrollmentId]);

$existingCertificate = $existingStmt->fetch(PDO::FETCH_ASSOC);

if ($existingCertificate) {
    header(
        'Location: view.php?id=' .
        (int) $existingCertificate['id']
    );
    exit;
}

$error = '';

// ============================================================
// GENERATE CERTIFICATE
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        $pdo->beginTransaction();

        // Re-check inside the transaction
        // to prevent duplicate certificates.
        $lockStmt = $pdo->prepare("
            SELECT id
            FROM certificates
            WHERE enrollment_id = ?
            LIMIT 1
            FOR UPDATE
        ");

        $lockStmt->execute([$enrollmentId]);

        $alreadyIssued = $lockStmt->fetch(PDO::FETCH_ASSOC);

        if ($alreadyIssued) {

            $pdo->rollBack();

            header(
                'Location: view.php?id=' .
                (int) $alreadyIssued['id']
            );

            exit;
        }

        $completionDate = $enrollment['end_date'];

        $issueDate = date('Y-m-d');

        $issuedBy = (int) $_SESSION['user_id'];

        // Temporary unique value.
        // It will be replaced after getting the certificate ID.
        $temporaryNumber =
            'TEMP-' .
            bin2hex(random_bytes(12));

        // ====================================================
        // INSERT CERTIFICATE
        // ====================================================

        $insertStmt = $pdo->prepare("
            INSERT INTO certificates (
                enrollment_id,
                certificate_number,
                issue_date,
                completion_date,
                issued_by
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        $insertStmt->execute([
            $enrollmentId,
            $temporaryNumber,
            $issueDate,
            $completionDate,
            $issuedBy
        ]);

        $certificateId =
            (int) $pdo->lastInsertId();

        // ====================================================
        // GENERATE FINAL CERTIFICATE NUMBER
        // ====================================================

        $certificateNumber =
            'ALU-CERT-' .
            date('Y', strtotime($completionDate)) .
            '-' .
            str_pad(
                (string) $certificateId,
                6,
                '0',
                STR_PAD_LEFT
            );

        // ====================================================
        // UPDATE CERTIFICATE NUMBER
        // ====================================================

        $updateStmt = $pdo->prepare("
            UPDATE certificates
            SET certificate_number = ?
            WHERE id = ?
        ");

        $updateStmt->execute([
            $certificateNumber,
            $certificateId
        ]);

        // ====================================================
        // COMMIT
        // ====================================================

        $pdo->commit();

        // ====================================================
        // OPEN CERTIFICATE
        // ====================================================

        header(
            'Location: view.php?id=' .
            $certificateId
        );

        exit;

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error =
            $lang === 'ar'
                ? 'حدث خطأ أثناء إنشاء الشهادة. يرجى المحاولة مرة أخرى.'
                : 'An error occurred while generating the certificate. Please try again.';
    }
}

?>

<?php require_once '../../includes/sidebar.php'; ?>

<style>

.certificate-page {
    padding: 25px 30px 40px;
    max-width: 1000px;
    margin: 0 auto;
}

.page-header {
    margin-bottom: 22px;
}

.page-header h1 {
    margin: 0 0 6px;
    font-size: 28px;
    color: #111827;
}

.page-header p {
    margin: 0;
    color: #6b7280;
    font-size: 14px;
}

.card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    overflow: hidden;
}

.card-header {
    padding: 18px 22px;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
}

.card-header h2 {
    margin: 0;
    font-size: 18px;
    color: #111827;
}

.card-body {
    padding: 22px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.info-item {
    padding: 14px 16px;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: #fff;
}

.info-label {
    color: #6b7280;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 5px;
}

.info-value {
    color: #111827;
    font-size: 15px;
    font-weight: 600;
}

.notice {
    margin-bottom: 18px;
    padding: 13px 15px;
    border-radius: 9px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
    font-size: 14px;
}

.actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-top: 22px;
}

.actions-right {
    display: flex;
    gap: 10px;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    padding: 0 18px;
    border: 0;
    border-radius: 9px;
    text-decoration: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    box-sizing: border-box;
}

.btn-primary {
    background: #2563eb;
    color: #fff;
}

.btn-secondary {
    background: #f3f4f6;
    color: #374151;
}

@media (max-width: 700px) {

    .certificate-page {
        padding: 20px 15px 30px;
    }

    .info-grid {
        grid-template-columns: 1fr;
    }

    .actions {
        flex-direction: column;
        align-items: stretch;
    }

    .actions-right {
        flex-direction: column;
    }

    .btn {
        width: 100%;
    }
}

</style>

<div class="certificate-page">

    <div class="page-header">

        <h1>
            <?= $lang === 'ar'
                ? 'إنشاء شهادة'
                : 'Generate Certificate' ?>
        </h1>

        <p>
            <?= $lang === 'ar'
                ? 'إنشاء شهادة إتمام للطالب.'
                : 'Generate a certificate of completion for the student.' ?>
        </p>

    </div>

    <div class="card">

        <div class="card-header">

            <h2>
                <?= $lang === 'ar'
                    ? 'بيانات الطالب'
                    : 'Student Information' ?>
            </h2>

        </div>

        <div class="card-body">

            <?php if ($error !== ''): ?>

                <div class="notice">
                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>

            <?php endif; ?>

            <div class="info-grid">

                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar'
                            ? 'اسم الطالب'
                            : 'Student Name' ?>
                    </div>

                    <div class="info-value">
                        <?= htmlspecialchars(
                            $enrollment['full_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </div>

                </div>

                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar'
                            ? 'رمز الطالب'
                            : 'Student Code' ?>
                    </div>

                    <div class="info-value">
                        <?= htmlspecialchars(
                            $enrollment['student_code'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </div>

                </div>

                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar'
                            ? 'الدورة'
                            : 'Course' ?>
                    </div>

                    <div class="info-value">
                        <?= htmlspecialchars(
                            $enrollment['course_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </div>

                </div>

                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar'
                            ? 'رمز الدورة'
                            : 'Course Code' ?>
                    </div>

                    <div class="info-value">
                        <?= htmlspecialchars(
                            $enrollment['course_code'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </div>

                </div>

                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar'
                            ? 'الدفعة'
                            : 'Batch' ?>
                    </div>

                    <div class="info-value">
                        <?= htmlspecialchars(
                            $enrollment['batch_code'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </div>

                </div>

                <div class="info-item">

                    <div class="info-label">
                        <?= $lang === 'ar'
                            ? 'تاريخ إتمام الدورة'
                            : 'Completion Date' ?>
                    </div>

                    <div class="info-value">
                        <?= htmlspecialchars(
                            $enrollment['end_date'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </div>

                </div>

            </div>

            <form method="post" action="">

                <div class="actions">

                    <a
                        href="index.php"
                        class="btn btn-secondary"
                    >
                        <?= $lang === 'ar'
                            ? 'العودة إلى الشهادات'
                            : 'Back to Certificates' ?>
                    </a>

                    <div class="actions-right">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            <?= $lang === 'ar'
                                ? 'إنشاء الشهادة'
                                : 'Generate Certificate' ?>
                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>

</div>