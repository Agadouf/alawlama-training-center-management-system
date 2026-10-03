<?php

session_start();

require_once '../../config/database.php';
require_once '../../includes/language.php';


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {

    header('Location: ../../login.php');
    exit;
}

$role = strtolower((string) $_SESSION['role']);

if (!in_array($role, ['admin', 'staff', 'instructor'], true)) {

    http_response_code(403);
    die($lang === 'ar' ? 'الوصول مرفوض.' : 'Access denied.');
}

$userId = (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/

function h($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function money($value): string
{
    return number_format(
        (float) $value,
        2
    );
}


/*
|--------------------------------------------------------------------------
| REPORT TYPE
|--------------------------------------------------------------------------
*/

$type = $_GET['type'] ?? 'students';

$allowedTypes = [
    'students',
    'enrollments',
    'payments',
    'attendance',
    'grades'
];

if (!in_array($type, $allowedTypes, true)) {

    http_response_code(400);
    die($lang === 'ar' ? 'نوع التقرير غير صالح.' : 'Invalid report type.');
}


/*
|--------------------------------------------------------------------------
| PAYMENT REPORT PERMISSION
|--------------------------------------------------------------------------
*/

if (
    $type === 'payments'
    && !in_array($role, ['admin', 'staff'], true)
) {

    http_response_code(403);

    die(
        ($lang === 'ar' ? 'الوصول مرفوض. التقارير المالية متاحة للمسؤول والموظفين فقط.' : 'Access denied. Financial reports are available to Admin and Staff only.')
    );
}


/*
|--------------------------------------------------------------------------
| REPORT TITLES
|--------------------------------------------------------------------------
*/

$titleMap = [

    'students'
        => ($lang === 'ar' ? 'تقرير الطلاب' : 'Student Report'),

    'enrollments'
        => ($lang === 'ar' ? 'تقرير التسجيلات' : 'Enrollment Report'),

    'payments'
        => ($lang === 'ar' ? 'تقرير المدفوعات' : 'Payment Report'),

    'attendance'
        => ($lang === 'ar' ? 'تقرير الحضور' : 'Attendance Report'),

    'grades'
        => ($lang === 'ar' ? 'تقرير الدرجات' : 'Grade Report'),

];

$title = $titleMap[$type];


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$selectedBatch = (int) (
    $_GET['batch_id'] ?? 0
);

$selectedStatus = trim(
    (string) (
        $_GET['status'] ?? ''
    )
);

$search = trim(
    (string) (
        $_GET['search'] ?? ''
    )
);

$dateFrom = trim(
    (string) (
        $_GET['date_from'] ?? ''
    )
);

$dateTo = trim(
    (string) (
        $_GET['date_to'] ?? ''
    )
);


/*
|--------------------------------------------------------------------------
| AVAILABLE BATCHES
|--------------------------------------------------------------------------
*/

if ($role === 'instructor') {

    $stmt = $pdo->prepare("
        SELECT DISTINCT
            b.id,
            b.batch_code,
            c.course_name

        FROM batches b

        INNER JOIN courses c
            ON c.id = b.course_id

        INNER JOIN batch_instructors bi
            ON bi.batch_id = b.id

        INNER JOIN instructors i
            ON i.id = bi.instructor_id

        WHERE i.user_id = ?

        ORDER BY
            b.start_date DESC,
            b.batch_code ASC
    ");

    $stmt->execute([
        $userId
    ]);

} else {

    $stmt = $pdo->query("
        SELECT
            b.id,
            b.batch_code,
            c.course_name

        FROM batches b

        INNER JOIN courses c
            ON c.id = b.course_id

        ORDER BY
            b.start_date DESC,
            b.batch_code ASC
    ");
}

$batches = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| DATA
|--------------------------------------------------------------------------
*/

$rows = [];

$summary = [];


/*
|--------------------------------------------------------------------------
| STUDENT REPORT
|--------------------------------------------------------------------------
*/

if ($type === 'students') {

    $sql = "
        SELECT

            student_code,
            full_name,
            gender,
            date_of_birth,
            phone,
            email,
            registration_date,
            status

        FROM students

        WHERE 1 = 1
    ";

    $params = [];


    if ($selectedStatus !== '') {

        $sql .= "
            AND status = ?
        ";

        $params[] = $selectedStatus;
    }


    if ($search !== '') {

        $sql .= "
            AND (
                student_code LIKE ?
                OR full_name LIKE ?
                OR phone LIKE ?
                OR email LIKE ?
            )
        ";

        $like = "%{$search}%";

        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }


    $sql .= "
        ORDER BY
            full_name ASC
    ";


    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $rows = $stmt->fetchAll();


    $summary[$lang === 'ar' ? 'إجمالي الطلاب' : 'Total Students'] = count($rows);
}


/*
|--------------------------------------------------------------------------
| ENROLLMENT REPORT
|--------------------------------------------------------------------------
*/

elseif ($type === 'enrollments') {

    $sql = "
        SELECT

            s.student_code,
            s.full_name,

            c.course_name,

            b.batch_code,

            e.enrollment_date,

            e.original_fee,
            e.discount,
            e.actual_fee,

            e.status

        FROM enrollments e

        INNER JOIN students s
            ON s.id = e.student_id

        INNER JOIN batches b
            ON b.id = e.batch_id

        INNER JOIN courses c
            ON c.id = b.course_id

        WHERE 1 = 1
    ";

    $params = [];


    /*
    | Instructor restriction
    */

    if ($role === 'instructor') {

        $sql .= "
            AND EXISTS (

                SELECT 1

                FROM batch_instructors bi

                INNER JOIN instructors i
                    ON i.id = bi.instructor_id

                WHERE
                    bi.batch_id = b.id
                    AND i.user_id = ?

            )
        ";

        $params[] = $userId;
    }


    if ($selectedBatch > 0) {

        $sql .= "
            AND b.id = ?
        ";

        $params[] = $selectedBatch;
    }


    if ($selectedStatus !== '') {

        $sql .= "
            AND e.status = ?
        ";

        $params[] = $selectedStatus;
    }


    if ($search !== '') {

        $sql .= "
            AND (
                s.student_code LIKE ?
                OR s.full_name LIKE ?
                OR c.course_name LIKE ?
                OR b.batch_code LIKE ?
            )
        ";

        $like = "%{$search}%";

        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }


    if ($dateFrom !== '') {

        $sql .= "
            AND e.enrollment_date >= ?
        ";

        $params[] = $dateFrom;
    }


    if ($dateTo !== '') {

        $sql .= "
            AND e.enrollment_date <= ?
        ";

        $params[] = $dateTo;
    }


    $sql .= "
        ORDER BY
            e.enrollment_date DESC,
            s.full_name ASC
    ";


    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $rows = $stmt->fetchAll();


    $summary[$lang === 'ar' ? 'إجمالي التسجيلات' : 'Total Enrollments'] = count($rows);


    $summary[$lang === 'ar' ? 'إجمالي الرسوم' : 'Total Fees'] = array_sum(
        array_map(
            fn($row) => (float) $row['actual_fee'],
            $rows
        )
    );
}


/*
|--------------------------------------------------------------------------
| PAYMENT REPORT
|--------------------------------------------------------------------------
*/

elseif ($type === 'payments') {

    $sql = "
        SELECT

            p.receipt_number,
            p.payment_date,

            s.student_code,
            s.full_name,

            c.course_name,

            b.batch_code,

            p.amount,
            p.payment_method,

            u.username AS recorded_by,

            p.notes

        FROM payments p

        INNER JOIN enrollments e
            ON e.id = p.enrollment_id

        INNER JOIN students s
            ON s.id = e.student_id

        INNER JOIN batches b
            ON b.id = e.batch_id

        INNER JOIN courses c
            ON c.id = b.course_id

        LEFT JOIN users u
            ON u.id = p.recorded_by

        WHERE 1 = 1
    ";

    $params = [];


    if ($selectedBatch > 0) {

        $sql .= "
            AND b.id = ?
        ";

        $params[] = $selectedBatch;
    }


    if ($search !== '') {

        $sql .= "
            AND (
                p.receipt_number LIKE ?
                OR s.student_code LIKE ?
                OR s.full_name LIKE ?
            )
        ";

        $like = "%{$search}%";

        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }


    if ($dateFrom !== '') {

        $sql .= "
            AND p.payment_date >= ?
        ";

        $params[] = $dateFrom;
    }


    if ($dateTo !== '') {

        $sql .= "
            AND p.payment_date <= ?
        ";

        $params[] = $dateTo;
    }


    $sql .= "
        ORDER BY
            p.payment_date DESC,
            p.id DESC
    ";


    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $rows = $stmt->fetchAll();


    $summary[$lang === 'ar' ? 'سجلات المدفوعات' : 'Payment Records'] = count($rows);


    $summary[$lang === 'ar' ? 'إجمالي المدفوع' : 'Total Paid'] = array_sum(
        array_map(
            fn($row) => (float) $row['amount'],
            $rows
        )
    );
}


/*
|--------------------------------------------------------------------------
| ATTENDANCE REPORT
|--------------------------------------------------------------------------
*/

elseif ($type === 'attendance') {

    $sql = "
        SELECT

            s.student_code,
            s.full_name,

            c.course_name,

            b.batch_code,

            COUNT(a.id) AS sessions,

            SUM(
                CASE
                    WHEN a.status = 'Present'
                    THEN 1
                    ELSE 0
                END
            ) AS present_count,

            SUM(
                CASE
                    WHEN a.status = 'Absent'
                    THEN 1
                    ELSE 0
                END
            ) AS absent_count,

            ROUND(

                CASE

                    WHEN COUNT(a.id) = 0
                    THEN 0

                    ELSE

                        SUM(
                            CASE
                                WHEN a.status = 'Present'
                                THEN 1
                                ELSE 0
                            END
                        )
                        * 100.0
                        / COUNT(a.id)

                END,

                2

            ) AS attendance_percentage

        FROM enrollments e

        INNER JOIN students s
            ON s.id = e.student_id

        INNER JOIN batches b
            ON b.id = e.batch_id

        INNER JOIN courses c
            ON c.id = b.course_id

        LEFT JOIN attendance a
            ON a.enrollment_id = e.id

        WHERE e.status != 'cancelled'
    ";

    $params = [];


    /*
    | Instructor restriction
    */

    if ($role === 'instructor') {

        $sql .= "
            AND EXISTS (

                SELECT 1

                FROM batch_instructors bi

                INNER JOIN instructors i
                    ON i.id = bi.instructor_id

                WHERE

                    bi.batch_id = b.id

                    AND i.user_id = ?

            )
        ";

        $params[] = $userId;
    }


    if ($selectedBatch > 0) {

        $sql .= "
            AND b.id = ?
        ";

        $params[] = $selectedBatch;
    }


    if ($search !== '') {

        $sql .= "
            AND (
                s.student_code LIKE ?
                OR s.full_name LIKE ?
                OR b.batch_code LIKE ?
            )
        ";

        $like = "%{$search}%";

        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }


    /*
    | Date filters
    */

    if ($dateFrom !== '') {

        $sql .= "
            AND (
                a.attendance_date >= ?
                OR a.id IS NULL
            )
        ";

        $params[] = $dateFrom;
    }


    if ($dateTo !== '') {

        $sql .= "
            AND (
                a.attendance_date <= ?
                OR a.id IS NULL
            )
        ";

        $params[] = $dateTo;
    }


    $sql .= "
        GROUP BY

            e.id,
            s.student_code,
            s.full_name,
            c.course_name,
            b.batch_code

        ORDER BY
            s.full_name ASC
    ";


    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $rows = $stmt->fetchAll();


    $summary[$lang === 'ar' ? 'الطلاب في التقرير' : 'Students in Report'] = count($rows);
}


/*
|--------------------------------------------------------------------------
| GRADE REPORT
|--------------------------------------------------------------------------
*/

elseif ($type === 'grades') {

    $sql = "
        SELECT

            e.id AS enrollment_id,

            s.student_code,
            s.full_name,

            c.course_name,

            b.batch_code,

            b.id AS batch_id

        FROM enrollments e

        INNER JOIN students s
            ON s.id = e.student_id

        INNER JOIN batches b
            ON b.id = e.batch_id

        INNER JOIN courses c
            ON c.id = b.course_id

        WHERE e.status != 'cancelled'
    ";

    $params = [];


    /*
    | Instructor restriction
    */

    if ($role === 'instructor') {

        $sql .= "
            AND EXISTS (

                SELECT 1

                FROM batch_instructors bi

                INNER JOIN instructors i
                    ON i.id = bi.instructor_id

                WHERE

                    bi.batch_id = b.id

                    AND i.user_id = ?

            )
        ";

        $params[] = $userId;
    }


    if ($selectedBatch > 0) {

        $sql .= "
            AND b.id = ?
        ";

        $params[] = $selectedBatch;
    }


    if ($search !== '') {

        $sql .= "
            AND (
                s.student_code LIKE ?
                OR s.full_name LIKE ?
                OR b.batch_code LIKE ?
            )
        ";

        $like = "%{$search}%";

        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }


    $sql .= "
        ORDER BY
            s.full_name ASC
    ";


    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $enrollments = $stmt->fetchAll();


    /*
    |--------------------------------------------------------------------------
    | Calculate final grades
    |--------------------------------------------------------------------------
    */

    foreach ($enrollments as $enrollment) {

        $stmt = $pdo->prepare("
            SELECT

                a.id,
                a.type,
                a.max_score,

                sc.score

            FROM assessments a

            LEFT JOIN assessment_scores sc

                ON sc.assessment_id = a.id

                AND sc.enrollment_id = ?

            WHERE

                a.batch_id = ?

                AND a.status = 'active'

            ORDER BY
                a.id ASC
        ");


        $stmt->execute([

            (int) $enrollment['enrollment_id'],

            (int) $enrollment['batch_id']

        ]);


        $assessments =
            $stmt->fetchAll();


        $assignmentScores = [];

        $midterm = null;

        $finalExam = null;


        foreach ($assessments as $assessment) {

            if ($assessment['score'] === null) {
                continue;
            }


            $maxScore =
                (float) $assessment['max_score'];


            if ($maxScore <= 0) {
                continue;
            }


            $percentage =

                (
                    (float) $assessment['score']
                    /
                    $maxScore
                )
                * 100;


            if (
                $assessment['type']
                === 'assignment'
            ) {

                $assignmentScores[] =
                    $percentage;
            }

            elseif (
                $assessment['type']
                === 'midterm'
            ) {

                $midterm =
                    $percentage;
            }

            elseif (
                $assessment['type']
                === 'final'
            ) {

                $finalExam =
                    $percentage;
            }
        }


        /*
        | Assignment average
        */

        $assignmentAverage =
            count($assignmentScores) > 0

            ?

            array_sum(
                $assignmentScores
            )
            /
            count($assignmentScores)

            :

            0;


        $midtermPercentage =
            $midterm ?? 0;


        $finalPercentage =
            $finalExam ?? 0;


        /*
        |--------------------------------------------------------------------------
        | Final score
        |--------------------------------------------------------------------------
        |
        | Assignments = 20%
        | Midterm     = 35%
        | Final       = 45%
        |
        */

        $finalScore =

            (
                $assignmentAverage
                * 0.20
            )

            +

            (
                $midtermPercentage
                * 0.35
            )

            +

            (
                $finalPercentage
                * 0.45
            );


        /*
        |--------------------------------------------------------------------------
        | Letter grade
        |--------------------------------------------------------------------------
        */

        if ($finalScore >= 90) {

            $grade = 'A';

        }

        elseif ($finalScore >= 80) {

            $grade = 'B';

        }

        elseif ($finalScore >= 70) {

            $grade = 'C';

        }

        elseif ($finalScore >= 60) {

            $grade = 'D';

        }

        else {

            $grade = 'F';
        }


        $rows[] = [

            'student_code'
                => $enrollment['student_code'],

            'full_name'
                => $enrollment['full_name'],

            'course_name'
                => $enrollment['course_name'],

            'batch_code'
                => $enrollment['batch_code'],

            'assignment_average'
                => $assignmentAverage,

            'midterm'
                => $midtermPercentage,

            'final_exam'
                => $finalPercentage,

            'final_score'
                => $finalScore,

            'grade'
                => $grade,

        ];
    }


    $summary[$lang === 'ar' ? 'الطلاب في التقرير' : 'Students in Report'] =
        count($rows);
}


/*
|--------------------------------------------------------------------------
| TABLE COLUMNS
|--------------------------------------------------------------------------
*/

$columns = [

    'students' => [
        ($lang === 'ar' ? 'رقم الطالب' : 'Student ID') => 'student_code',
        ($lang === 'ar' ? 'اسم الطالب' : 'Student Name') => 'full_name',
        ($lang === 'ar' ? 'الجنس' : 'Gender') => 'gender',
        ($lang === 'ar' ? 'تاريخ الميلاد' : 'Date of Birth') => 'date_of_birth',
        ($lang === 'ar' ? 'الهاتف' : 'Phone') => 'phone',
        ($lang === 'ar' ? 'البريد الإلكتروني' : 'Email') => 'email',
        ($lang === 'ar' ? 'تاريخ التسجيل' : 'Registration Date') => 'registration_date',
        ($lang === 'ar' ? 'الحالة' : 'Status') => 'status',
    ],

    'enrollments' => [
        ($lang === 'ar' ? 'رقم الطالب' : 'Student ID') => 'student_code',
        ($lang === 'ar' ? 'اسم الطالب' : 'Student Name') => 'full_name',
        ($lang === 'ar' ? 'المقرر' : 'Course') => 'course_name',
        ($lang === 'ar' ? 'الدفعة' : 'Batch') => 'batch_code',
        ($lang === 'ar' ? 'تاريخ التسجيل' : 'Enrollment Date') => 'enrollment_date',
        ($lang === 'ar' ? 'الرسوم الأصلية' : 'Original Fee') => 'original_fee',
        ($lang === 'ar' ? 'الخصم' : 'Discount') => 'discount',
        ($lang === 'ar' ? 'الرسوم الفعلية' : 'Actual Fee') => 'actual_fee',
        ($lang === 'ar' ? 'الحالة' : 'Status') => 'status',
    ],

    'payments' => [
        ($lang === 'ar' ? 'رقم الإيصال' : 'Receipt No.') => 'receipt_number',
        ($lang === 'ar' ? 'تاريخ الدفع' : 'Payment Date') => 'payment_date',
        ($lang === 'ar' ? 'رقم الطالب' : 'Student ID') => 'student_code',
        ($lang === 'ar' ? 'اسم الطالب' : 'Student Name') => 'full_name',
        ($lang === 'ar' ? 'المقرر' : 'Course') => 'course_name',
        ($lang === 'ar' ? 'الدفعة' : 'Batch') => 'batch_code',
        ($lang === 'ar' ? 'المبلغ' : 'Amount') => 'amount',
        ($lang === 'ar' ? 'طريقة الدفع' : 'Method') => 'payment_method',
        ($lang === 'ar' ? 'سجل بواسطة' : 'Recorded By') => 'recorded_by',
        ($lang === 'ar' ? 'ملاحظات' : 'Notes') => 'notes',
    ],

    'attendance' => [
        ($lang === 'ar' ? 'رقم الطالب' : 'Student ID') => 'student_code',
        ($lang === 'ar' ? 'اسم الطالب' : 'Student Name') => 'full_name',
        ($lang === 'ar' ? 'المقرر' : 'Course') => 'course_name',
        ($lang === 'ar' ? 'الدفعة' : 'Batch') => 'batch_code',
        ($lang === 'ar' ? 'الجلسات' : 'Sessions') => 'sessions',
        ($lang === 'ar' ? 'حاضر' : 'Present') => 'present_count',
        ($lang === 'ar' ? 'غائب' : 'Absent') => 'absent_count',
        ($lang === 'ar' ? 'نسبة الحضور' : 'Attendance %') => 'attendance_percentage',
    ],

    'grades' => [
        ($lang === 'ar' ? 'رقم الطالب' : 'Student ID') => 'student_code',
        ($lang === 'ar' ? 'اسم الطالب' : 'Student Name') => 'full_name',
        ($lang === 'ar' ? 'المقرر' : 'Course') => 'course_name',
        ($lang === 'ar' ? 'الدفعة' : 'Batch') => 'batch_code',
        ($lang === 'ar' ? 'الواجبات 20%' : 'Assignments 20%') => 'assignment_average',
        ($lang === 'ar' ? 'منتصف الفصل 35%' : 'Midterm 35%') => 'midterm',
        ($lang === 'ar' ? 'النهائي 45%' : 'Final 45%') => 'final_exam',
        ($lang === 'ar' ? 'الدرجة النهائية' : 'Final Score') => 'final_score',
        ($lang === 'ar' ? 'التقدير' : 'Grade') => 'grade',
    ],

];


/*
|--------------------------------------------------------------------------
| CELL FORMATTING
|--------------------------------------------------------------------------
*/

function cellValue(
    string $key,
    $value
): string {

    if (
        in_array(
            $key,
            [
                'original_fee',
                'discount',
                'actual_fee',
                'amount'
            ],
            true
        )
    ) {

        return number_format(
            (float) $value,
            2
        );
    }


    if (
        in_array(
            $key,
            [
                'assignment_average',
                'midterm',
                'final_exam',
                'final_score',
                'attendance_percentage'
            ],
            true
        )
    ) {

        return number_format(
            (float) $value,
            2
        ) . '%';
    }


    return h($value);
}

?>

<!DOCTYPE html>

<html lang="<?= $lang === 'ar' ? 'ar' : 'en' ?>" dir="<?= $dir ?>">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>

    <?= h($title) ?>

    -

    <?= $lang === 'ar' ? 'مركز العولمة للتدريب' : 'ALAWALAMA TRAINING CENTER' ?>

</title>


<style>

/*
|--------------------------------------------------------------------------
| GENERAL
|--------------------------------------------------------------------------
*/

* {
    box-sizing: border-box;
}


body {

    margin: 0;

    background: #f4f6f9;

    color: #1f2937;

    font-family:
        Arial,
        Helvetica,
        sans-serif;
}


.main-page {

    padding: 30px;

    max-width: 1450px;

    margin: 0 auto;
}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.page-header {

    display: flex;

    justify-content:
        space-between;

    align-items:
        flex-start;

    gap: 20px;

    margin-bottom: 20px;
}


.page-header h1 {

    margin: 0 0 7px;

    font-size: 27px;
}


.page-header p {

    margin: 0;

    color: #64748b;
}


/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/

.actions {

    display: flex;

    gap: 10px;

    flex-wrap: wrap;
}


.btn {

    border: 0;

    border-radius: 8px;

    padding: 10px 15px;

    text-decoration: none;

    font-weight: 600;

    cursor: pointer;

    font-size: 14px;
}


.btn-primary {

    background: #2563eb;

    color: #ffffff;
}


.btn-primary:hover {

    background: #1d4ed8;
}


.btn-secondary {

    background: #ffffff;

    color: #334155;

    border:
        1px solid #cbd5e1;
}


/*
|--------------------------------------------------------------------------
| CARD
|--------------------------------------------------------------------------
*/

.card {

    background: #ffffff;

    border:
        1px solid #e5e7eb;

    border-radius: 12px;

    padding: 20px;

    margin-bottom: 20px;
}


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

.filters {

    display: grid;

    grid-template-columns:
        repeat(
            4,
            minmax(0, 1fr)
        );

    gap: 14px;
}


.field label {

    display: block;

    font-size: 13px;

    font-weight: 600;

    margin-bottom: 6px;
}


.field input,
.field select {

    width: 100%;

    padding: 10px 11px;

    border:
        1px solid #cbd5e1;

    border-radius: 7px;

    background: #ffffff;
}


.filter-actions {

    display: flex;

    align-items: flex-end;

    gap: 8px;
}


/*
|--------------------------------------------------------------------------
| SUMMARY
|--------------------------------------------------------------------------
*/

.summary {

    display: flex;

    gap: 12px;

    flex-wrap: wrap;

    margin-bottom: 18px;
}


.summary-item {

    background: #f8fafc;

    border:
        1px solid #e2e8f0;

    border-radius: 8px;

    padding: 10px 14px;

    font-size: 13px;
}


.summary-item strong {

    display: block;

    font-size: 16px;

    margin-top: 3px;
}


/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

.table-wrap {

    overflow-x: auto;
}


table {

    width: 100%;

    border-collapse:
        collapse;

    min-width: 850px;
}


th,
td {

    border:
        1px solid #e2e8f0;

    padding: 9px 8px;

    font-size: 12px;
}


th {

    background: #f8fafc;

    text-align: left;

    white-space: nowrap;
}


td {

    vertical-align: top;
}


/*
|--------------------------------------------------------------------------
| EMPTY
|--------------------------------------------------------------------------
*/

.empty {

    text-align: center;

    padding: 40px;

    color: #64748b;
}


/*
|--------------------------------------------------------------------------
| PRINT HEADER
|--------------------------------------------------------------------------
*/

.report-header {

    display: none;
}


/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (max-width: 900px) {

    .filters {

        grid-template-columns:
            repeat(
                2,
                minmax(0, 1fr)
            );
    }


    .page-header {

        flex-direction:
            column;
    }

}


@media (max-width: 600px) {

    .main-page {

        padding: 18px;
    }


    .filters {

        grid-template-columns:
            1fr;
    }


    .filter-actions {

        align-items:
            stretch;
    }


    .filter-actions .btn {

        width: 100%;
    }

}


/*
|--------------------------------------------------------------------------
| PRINT
|--------------------------------------------------------------------------
*/

@media print {

    /*
    |--------------------------------------------------------------------------
    | HIDE SIDEBAR / MENU
    |--------------------------------------------------------------------------
    */

    .sidebar,
    aside,
    nav,
    .sidebar-menu,
    .sidebar-header,
    .sidebar-footer {

        display: none !important;
    }


    /*
    |--------------------------------------------------------------------------
    | REMOVE SIDEBAR SPACE
    |--------------------------------------------------------------------------
    */

    .main-content {

        margin-left: 0 !important;

        margin-right: 0 !important;

        width: 100% !important;

        max-width: none !important;
    }


    /*
    |--------------------------------------------------------------------------
    | FULL PAGE REPORT
    |--------------------------------------------------------------------------
    */

    .main-page {

        max-width: none !important;

        width: 100% !important;

        margin: 0 !important;

        padding: 0 !important;
    }


    /*
    |--------------------------------------------------------------------------
    | HIDE SCREEN ELEMENTS
    |--------------------------------------------------------------------------
    */

    .page-header,
    .filters-card,
    .screen-only,
    .summary {

        display: none !important;
    }


    /*
    |--------------------------------------------------------------------------
    | SHOW PRINT HEADER
    |--------------------------------------------------------------------------
    */

    .report-header {

        display: block !important;

        text-align: center;

        margin-bottom: 16px;
    }


    .report-header h1 {

        margin: 0 0 5px;

        font-size: 20px;
    }


    .report-header h2 {

        margin: 0 0 4px;

        font-size: 16px;
    }


    .report-header p {

        margin: 0;

        font-size: 11px;

        color: #64748b;
    }


    /*
    |--------------------------------------------------------------------------
    | REMOVE CARD STYLING
    |--------------------------------------------------------------------------
    */

    .card {

        border: 0 !important;

        box-shadow: none !important;

        padding: 0 !important;

        margin: 0 !important;

        width: 100% !important;
    }


    /*
    |--------------------------------------------------------------------------
    | TABLE
    |--------------------------------------------------------------------------
    */

    .table-wrap {

        overflow: visible !important;

        width: 100% !important;
    }


    table {

        width: 100% !important;

        min-width: 0 !important;

        border-collapse: collapse !important;

        font-size: 10px;
    }


    th,
    td {

        font-size: 9px;

        padding: 6px;

        border: 1px solid #000;
    }


    th {

        background: #f1f1f1 !important;

        color: #000 !important;
    }


    /*
    |--------------------------------------------------------------------------
    | A4 LANDSCAPE
    |--------------------------------------------------------------------------
    */

    @page {

        size: A4 landscape;

        margin: 10mm;
    }

}

</style>

</head>


<body>


<?php require_once '../../includes/sidebar.php'; ?>


<div class="main-page">


    <!-- ==================================================
         PAGE HEADER
    ================================================== -->

    <div class="page-header">

        <div>

            <h1>

                <?= h($title) ?>

            </h1>

            <p>

                <?= $lang === 'ar' ? 'مركز العولمة للتدريب' : 'ALAWALAMA TRAINING CENTER' ?>

            </p>

        </div>


        <div class="actions screen-only">

            <a
                class="btn btn-secondary"
                href="index.php"
            >
                ← <?= $lang === 'ar' ? 'التقارير' : 'Reports' ?>
            </a>


            <button
                class="btn btn-primary"
                onclick="window.print()"
            >
                🖨 <?= $lang === 'ar' ? 'طباعة التقرير' : 'Print Report' ?>
            </button>

        </div>

    </div>


    <!-- ==================================================
         FILTERS
    ================================================== -->

    <div class="card filters-card screen-only">

        <form method="GET">

            <input
                type="hidden"
                name="type"
                value="<?= h($type) ?>"
            >


            <div class="filters">


                <!-- BATCH -->

                <?php if (
                    in_array(
                        $type,
                        [
                            'enrollments',
                            'payments',
                            'attendance',
                            'grades'
                        ],
                        true
                    )
                ): ?>

                    <div class="field">

                        <label
                            for="batch_id"
                        >
                            <?= $lang === 'ar' ? 'الدفعة' : 'Batch' ?>
                        </label>


                        <select
                            id="batch_id"
                            name="batch_id"
                        >

                            <option value="0">

                                <?= $lang === 'ar' ? 'كل الدفعات' : 'All Batches' ?>

                            </option>


                            <?php foreach (
                                $batches
                                as $batch
                            ): ?>

                                <option
                                    value="<?= (int) $batch['id'] ?>"
                                    <?= $selectedBatch ===
                                        (int) $batch['id']
                                            ? 'selected'
                                            : ''
                                    ?>
                                >

                                    <?= h(
                                        $batch['batch_code']
                                    ) ?>

                                    -

                                    <?= h(
                                        $batch['course_name']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                <?php endif; ?>


                <!-- STATUS -->

                <?php if (
                    in_array(
                        $type,
                        [
                            'students',
                            'enrollments'
                        ],
                        true
                    )
                ): ?>

                    <div class="field">

                        <label
                            for="status"
                        >
                            <?= $lang === 'ar' ? 'الحالة' : 'Status' ?>
                        </label>


                        <select
                            id="status"
                            name="status"
                        >

                            <option value="">

                                <?= $lang === 'ar' ? 'كل الحالات' : 'All Statuses' ?>

                            </option>


                            <?php

                            $statusOptions =
                                $type === 'students'

                                ?

                                [
                                    'Active',
                                    'Suspended',
                                    'Inactive',
                                    'Graduated'
                                ]

                                :

                                [
                                    'active',
                                    'completed',
                                    'suspended',
                                    'cancelled'
                                ];

                            ?>


                            <?php foreach (
                                $statusOptions
                                as $status
                            ): ?>

                                <option
                                    value="<?= h($status) ?>"
                                    <?= $selectedStatus ===
                                        $status
                                            ? 'selected'
                                            : ''
                                    ?>
                                >

                                    <?= h(
                                        $lang === 'ar'
                                            ? (
                                                $status === 'Active' ? 'نشط' :
                                                ($status === 'Suspended' ? 'موقوف' :
                                                ($status === 'Inactive' ? 'غير نشط' :
                                                ($status === 'Graduated' ? 'متخرج' :
                                                ($status === 'active' ? 'نشط' :
                                                ($status === 'completed' ? 'مكتمل' :
                                                ($status === 'suspended' ? 'موقوف' :
                                                ($status === 'cancelled' ? 'ملغى' : $status)))))))
                                            )
                                            : $status
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                <?php endif; ?>


                <!-- SEARCH -->

                <div class="field">

                    <label
                        for="search"
                    >
                        <?= $lang === 'ar' ? 'بحث' : 'Search' ?>
                    </label>


                    <input
                        id="search"
                        name="search"
                        value="<?= h($search) ?>"
                        placeholder="<?= $lang === 'ar' ? 'الاسم، الرقم، الدفعة...' : 'Name, ID, batch...' ?>"
                    >

                </div>


                <!-- DATE FILTERS -->

                <?php if (
                    in_array(
                        $type,
                        [
                            'enrollments',
                            'payments',
                            'attendance'
                        ],
                        true
                    )
                ): ?>

                    <div class="field">

                        <label
                            for="date_from"
                        >
                            <?= $lang === 'ar' ? 'من تاريخ' : 'Date From' ?>
                        </label>


                        <input
                            id="date_from"
                            type="date"
                            name="date_from"
                            value="<?= h($dateFrom) ?>"
                        >

                    </div>


                    <div class="field">

                        <label
                            for="date_to"
                        >
                            <?= $lang === 'ar' ? 'إلى تاريخ' : 'Date To' ?>
                        </label>


                        <input
                            id="date_to"
                            type="date"
                            name="date_to"
                            value="<?= h($dateTo) ?>"
                        >

                    </div>

                <?php endif; ?>


                <!-- FILTER ACTIONS -->

                <div class="filter-actions">

                    <button
                        class="btn btn-primary"
                        type="submit"
                    >
                        <?= $lang === 'ar' ? 'تطبيق الفلاتر' : 'Apply Filters' ?>
                    </button>


                    <a
                        class="btn btn-secondary"
                        href="report.php?type=<?= h($type) ?>"
                    >
                        <?= $lang === 'ar' ? 'إعادة تعيين' : 'Reset' ?>
                    </a>

                </div>

            </div>

        </form>

    </div>


    <!-- ==================================================
         PRINT HEADER
    ================================================== -->

    <div class="report-header">

        <h1>
            <?= $lang === 'ar' ? 'مركز العولمة للتدريب' : 'ALAWALAMA TRAINING CENTER' ?>
        </h1>


        <h2>
            <?= h($title) ?>
        </h2>


        <p>

            <?= $lang === 'ar' ? 'تم إنشاء التقرير في' : 'Generated on' ?>
            <?= h(
                date('Y-m-d H:i')
            ) ?>

        </p>

    </div>


    <!-- ==================================================
         SUMMARY
    ================================================== -->

    <?php if (!empty($summary)): ?>

        <div class="summary">

            <?php foreach (
                $summary
                as $label => $value
            ): ?>

                <div class="summary-item">

                    <?= h($label) ?>


                    <strong>

                        <?php

                        if (
                            is_numeric($value)
                            &&
                            (
                                str_contains(
                                    $label,
                                    'Fee'
                                )

                                ||

                                str_contains(
                                    $label,
                                    'Paid'
                                )
                            )
                        ) {

                            echo money($value);

                        } else {

                            echo h($value);
                        }

                        ?>

                    </strong>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>


    <!-- ==================================================
         REPORT TABLE
    ================================================== -->

    <div class="card">

        <?php if (empty($rows)): ?>

            <div class="empty">

                No records found
                for the selected filters.

            </div>

        <?php else: ?>

            <div class="table-wrap">

                <table>

                    <thead>

                        <tr>

                            <th>
                                No.
                            </th>


                            <?php foreach (
                                $columns[$type]
                                as $label => $key
                            ): ?>

                                <th>

                                    <?= h($label) ?>

                                </th>

                            <?php endforeach; ?>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach (
                        $rows
                        as $index => $row
                    ): ?>

                        <tr>

                            <td>

                                <?= $index + 1 ?>

                            </td>


                            <?php foreach (
                                $columns[$type]
                                as $key
                            ): ?>

                                <td>

                                    <?= cellValue(
                                        $key,
                                        $row[$key] ?? ''
                                    ) ?>

                                </td>

                            <?php endforeach; ?>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>


</div>


</body>

</html>