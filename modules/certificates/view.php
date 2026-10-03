<?php
session_start();

require_once '../../config/database.php';
require_once '../../includes/language.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

if (!in_array($_SESSION['role'], ['admin', 'staff'], true)) {
    http_response_code(403);
    die($lang === 'ar' ? 'غير مسموح بالوصول.' : 'Access denied.');
}

$certificateId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$certificateId || $certificateId <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        cert.id AS certificate_id,
        cert.certificate_number,
        cert.issue_date,
        cert.completion_date,
        cert.enrollment_id,

        s.student_code,
        s.full_name,
        s.photo,

        e.enrollment_date,
        e.batch_id,

        b.batch_code,
        b.start_date,
        b.end_date,

        c.course_code,
        c.course_name

    FROM certificates cert

    INNER JOIN enrollments e
        ON e.id = cert.enrollment_id

    INNER JOIN students s
        ON s.id = e.student_id

    INNER JOIN batches b
        ON b.id = e.batch_id

    INNER JOIN courses c
        ON c.id = b.course_id

    WHERE cert.id = ?
    LIMIT 1
");

$stmt->execute([$certificateId]);
$certificate = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$certificate) {
    http_response_code(404);
    die($lang === 'ar' ? 'الشهادة غير موجودة.' : 'Certificate not found.');
}

/* Instructor */
$instructorStmt = $pdo->prepare("
    SELECT i.full_name
    FROM batch_instructors bi
    INNER JOIN instructors i ON i.id = bi.instructor_id
    WHERE bi.batch_id = ?
    ORDER BY i.full_name ASC
    LIMIT 1
");
$instructorStmt->execute([(int)$certificate['batch_id']]);
$instructorName = $instructorStmt->fetchColumn() ?: '—';

/* Grade */
$gradeLabel = '—';
$gradePercentage = null;

$gradeStmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(sc.score), 0) AS total_score,
        COALESCE(SUM(a.max_score), 0) AS total_maximum
    FROM assessment_scores sc
    INNER JOIN assessments a ON a.id = sc.assessment_id
    WHERE sc.enrollment_id = ?
");
$gradeStmt->execute([(int)$certificate['enrollment_id']]);
$grade = $gradeStmt->fetch(PDO::FETCH_ASSOC);

if ($grade && (float)$grade['total_maximum'] > 0) {
    $gradePercentage = ((float)$grade['total_score'] / (float)$grade['total_maximum']) * 100;

    if ($gradePercentage >= 80) {
        $gradeLabel = $lang === 'ar' ? 'جيد جداً' : 'V. Good';
    } elseif ($gradePercentage >= 70) {
        $gradeLabel = $lang === 'ar' ? 'جيد' : 'Good';
    } elseif ($gradePercentage >= 60) {
        $gradeLabel = $lang === 'ar' ? 'مقبول' : 'Pass';
    } else {
        $gradeLabel = $lang === 'ar' ? 'غير ناجح' : 'Fail';
    }
}

function certMonthYear(?string $date): string
{
    if (!$date) return '—';
    $time = strtotime($date);
    return $time !== false ? date('n/Y', $time) : $date;
}

function eCert($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$studentName = eCert($certificate['full_name']);
$courseName = eCert($certificate['course_name']);
$certificateNumber = eCert($certificate['certificate_number']);
$studentCode = eCert($certificate['student_code']);
$instructorName = eCert($instructorName);

$fromDate = certMonthYear($certificate['start_date'] ?: $certificate['enrollment_date']);
$toDate = certMonthYear($certificate['completion_date'] ?: $certificate['end_date']);

/*
 * The uploaded reference certificate uses these institutional details.
 * Keep them in one place so they can easily be changed later.
 */
$registrationNumber = '155493';
$licenseNumber = '2022:90';
$generalManagerName = 'Almahi Adam almahi Aweda';

/* Student photo */
$photoUrl = '';
$photoValue = trim((string)($certificate['photo'] ?? ''));

if ($photoValue !== '') {
    $candidatePaths = [
        '../../' . ltrim($photoValue, '/\\'),
        '../../uploads/students/' . basename($photoValue),
        '../../uploads/' . basename($photoValue),
        '../../assets/uploads/students/' . basename($photoValue),
    ];

    foreach ($candidatePaths as $candidate) {
        if (is_file(__DIR__ . '/' . $candidate)) {
            $photoUrl = $candidate;
            break;
        }
    }
}

/* The image is the visual template supplied by the user. */
$templatePath = $lang === 'ar' ? 'assets/certificate-template-ar-final3.png' : 'assets/certificate-template.png';
?>
<!DOCTYPE html>
<html lang="<?= eCert($lang) ?>" dir="<?= eCert($dir) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $lang === 'ar' ? 'شهادة' : 'Certificate' ?></title>

    <style>
        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            background: #dfe3e8;
            font-family: Arial, Tahoma, sans-serif;
        }

        .toolbar {
            display: flex;
            justify-content: center;
            gap: 10px;
            padding: 16px;
        }

        .toolbar button,
        .toolbar a {
            border: 0;
            border-radius: 8px;
            padding: 11px 20px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .print-btn { background: #2563eb; color: #fff; }
        .back-btn { background: #fff; color: #374151; }

        .certificate {
            position: relative;
            width: 297mm;
            height: 210mm;
            margin: 0 auto 20px;
            overflow: hidden;
            background: #eeeeee url('<?= eCert($templatePath) ?>') center center / 100% 100% no-repeat;
        }

        .overlay {
            position: absolute;
            z-index: 5;
            color: #1d2939;
        }

        .student-photo,
        .photo-placeholder {
            position: absolute;
            z-index: 6;
            right: 8.8%;
            top: 35.4%;
            width: 15.0%;
            height: 30.6%;
            object-fit: cover;
            border: 0;
            background: transparent;
        }

        /* Only dynamic values are overlaid. The template already contains
           the fixed certificate wording, lines, labels, and decorations. */
        .dynamic-name {
            position: absolute;
            z-index: 7;
            left: 20.7%;
            top: 54.3%;
            width: 46.0%;
            text-align: center;
            color: #087db8;
            font-size: 17px;
            font-weight: 800;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .dynamic-course {
            position: absolute;
            z-index: 7;
            left: 42.3%;
            top: 62.4%;
            width: 24.0%;
            text-align: center;
            color: #087db8;
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dynamic-grade {
            position: absolute;
            z-index: 7;
            left: 31.8%;
            top: 66.4%;
            width: 9.0%;
            text-align: center;
            color: #087db8;
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
        }

        .dynamic-from {
            position: absolute;
            z-index: 7;
            left: 46.5%;
            top: 66.5%;
            width: 10.0%;
            text-align: center;
            color: #087db8;
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
        }

        .dynamic-to {
            position: absolute;
            z-index: 7;
            left: 58.7%;
            top: 66.5%;
            width: 11.5%;
            text-align: center;
            color: #087db8;
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
        }

        .manager-name,
        .trainer-name {
            position: absolute;
            z-index: 7;
            bottom: 17.9%;
            text-align: center;
            font-size: 11px;
            font-weight: 700;
            color: #1f2937;
            white-space: nowrap;
        }

        .manager-name { left: 18.5%; width: 27%; }
        .trainer-name { right: 10.5%; width: 27%; }

        .cert-number {
            position: absolute;
            z-index: 7;
            left: 14.2%;
            top: 15.8%;
            font-size: 8.5px;
            color: #26364a;
        }

        /* Arabic certificate overlay */
        .arabic-layer { display: none; }

        html[dir="rtl"] .arabic-layer {
            display: block;
            position: absolute;
            z-index: 8;
            direction: rtl;
            text-align: center;
            font-family: Tahoma, Arial, sans-serif;
            font-weight: 700;
            background: transparent;
            padding: 0;
            margin: 0;
        }

        html[dir="rtl"] .ar-title {
            left: 24%;
            top: 38.8%;
            width: 52%;
            color: #087db8;
            font-size: 27px;
            line-height: 1.2;
        }

        html[dir="rtl"] .ar-intro {
            left: 28%;
            top: 47.7%;
            width: 44%;
            color: #1f2937;
            font-size: 16px;
            line-height: 1.4;
        }

        html[dir="rtl"] .ar-statement {
            left: 25%;
            top: 57.7%;
            width: 50%;
            color: #1f2937;
            font-size: 15px;
            line-height: 1.4;
        }

        html[dir="rtl"] .ar-program {
            left: 22%;
            top: 61.0%;
            width: 56%;
            color: #1f2937;
            font-size: 15px;
            line-height: 1.4;
        }

        html[dir="rtl"] .ar-grade-line {
            left: 18%;
            top: 66.0%;
            width: 64%;
            color: #1f2937;
            font-size: 14px;
            line-height: 1.4;
        }

        html[dir="rtl"] .ar-manager-label,
        html[dir="rtl"] .ar-trainer-label {
            bottom: 9.0%;
            color: #315b88;
            font-size: 16px;
            line-height: 1.3;
        }

        html[dir="rtl"] .ar-manager-label {
            left: 18.5%;
            width: 27%;
        }

        html[dir="rtl"] .ar-trainer-label {
            right: 10.5%;
            width: 27%;
        }

        html[dir="rtl"] .cert-number {
            direction: ltr;
            text-align: left;
        }

        html[dir="rtl"] .dynamic-name,
        html[dir="rtl"] .dynamic-course,
        html[dir="rtl"] .dynamic-grade,
        html[dir="rtl"] .dynamic-from,
        html[dir="rtl"] .dynamic-to,
        html[dir="rtl"] .manager-name,
        html[dir="rtl"] .trainer-name {
            direction: rtl;
            font-family: Tahoma, Arial, sans-serif;
        }

        @media print {
            @page {
                size: A4 landscape;
                margin: 0;
            }

            html, body {
                width: 297mm;
                height: 210mm;
                background: #fff;
            }

            .toolbar { display: none; }

            .certificate {
                width: 297mm;
                height: 210mm;
                margin: 0;
                box-shadow: none;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button class="print-btn" onclick="window.print()">
        <?= $lang === 'ar' ? 'طباعة الشهادة' : 'Print Certificate' ?>
    </button>
    <a href="index.php" class="back-btn">Back</a>
</div>

<section class="certificate">

    <?php if ($lang === 'ar'): ?>
        <div class="arabic-layer ar-title">شهادة إتمام</div>
        <div class="arabic-layer ar-intro">تشهد هذه الشهادة بأن</div>
        <div class="arabic-layer ar-statement">قد استوفى متطلبات إتمام</div>
        <div class="arabic-layer ar-program">البرنامج التدريبي الموضح أدناه</div>
        <div class="arabic-layer ar-grade-line">بتقدير من <?= eCert($gradeLabel) ?>، خلال الفترة من</div>
        <div class="arabic-layer ar-manager-label">المدير العام</div>
        <div class="arabic-layer ar-trainer-label">المدرب</div>
    <?php endif; ?>

    <div class="cert-number overlay">
        <?= $certificateNumber ?>
    </div>

    <?php if ($photoUrl !== ''): ?>
        <img
            class="student-photo"
            src="<?= eCert($photoUrl) ?>"
            alt="Student Photo"
        >
    <?php endif; ?>

    <!-- Dynamic values only; fixed wording remains in the template. -->
    <div class="dynamic-name">
        <?= $studentName ?>
    </div>

    <div class="dynamic-course">
        <?= $courseName ?>
    </div>

    <div class="dynamic-grade">
        <?= eCert($gradeLabel) ?>
    </div>

    <div class="dynamic-from">
        <?= eCert($fromDate) ?>
    </div>

    <div class="dynamic-to">
        <?= eCert($toDate) ?>
    </div>

    <div class="manager-name">
        <?= eCert($generalManagerName) ?>
    </div>

    <div class="trainer-name">
        <?= $instructorName ?>
    </div>

</section>

</body>
</html>
