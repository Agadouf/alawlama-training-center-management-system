<?php

session_start();

require_once '../../config/database.php';
require_once '../../includes/language.php';

/*
|--------------------------------------------------------------------------
| Authentication & Authorization
|--------------------------------------------------------------------------
| Admin and Staff can fully manage batches.
*/

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

if (!in_array($_SESSION['role'], ['admin', 'staff'], true)) {
    die((($lang === 'ar') ? 'غير مسموح بالوصول.' : 'Access denied.'));
}


/*
|--------------------------------------------------------------------------
| Get Batch ID
|--------------------------------------------------------------------------
*/

$batchId = (int) ($_GET['id'] ?? $_POST['batch_id'] ?? 0);

if ($batchId <= 0) {
    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Allowed Values
|--------------------------------------------------------------------------
*/

$allowedDays = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday'
];

$allowedStatuses = [
    'planned',
    'active',
    'completed',
    'cancelled'
];


/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$errors = [];

$batchCode = '';
$courseId = '';
$startDate = '';
$endDate = '';
$room = '';
$capacity = '';
$status = 'planned';

$selectedInstructors = [];
$schedules = [];


/*
|--------------------------------------------------------------------------
| Load Existing Batch
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        SELECT
            id,
            batch_code,
            course_id,
            start_date,
            end_date,
            room,
            capacity,
            status
        FROM batches
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$batchId]);

    $batch = $stmt->fetch();

    if (!$batch) {
        header('Location: index.php');
        exit;
    }

} catch (PDOException $e) {

    die((($lang === 'ar') ? 'تعذر تحميل المجموعة.' : 'Unable to load batch.'));
}


/*
|--------------------------------------------------------------------------
| Load Existing Batch Data
|--------------------------------------------------------------------------
*/

$batchCode = $batch['batch_code'];
$courseId = (int) $batch['course_id'];
$startDate = $batch['start_date'];
$endDate = $batch['end_date'];
$room = $batch['room'] ?? '';
$capacity = $batch['capacity'];
$status = $batch['status'];


/*
|--------------------------------------------------------------------------
| Load Assigned Instructors
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        SELECT instructor_id
        FROM batch_instructors
        WHERE batch_id = ?
        ORDER BY id ASC
    ");

    $stmt->execute([$batchId]);

    $selectedInstructors = array_map(
        'intval',
        $stmt->fetchAll(PDO::FETCH_COLUMN)
    );

} catch (PDOException $e) {

    $selectedInstructors = [];

    $errors[] =
        (($lang === 'ar') ? 'تعذر تحميل المدرسين المعيّنين.' : 'Unable to load assigned instructors.');
}


/*
|--------------------------------------------------------------------------
| Load Existing Schedules
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        SELECT
            day_of_week,
            start_time,
            end_time
        FROM batch_schedules
        WHERE batch_id = ?
        ORDER BY
            FIELD(
                day_of_week,
                'Monday',
                'Tuesday',
                'Wednesday',
                'Thursday',
                'Friday',
                'Saturday',
                'Sunday'
            ),
            start_time
    ");

    $stmt->execute([$batchId]);

    $schedules = $stmt->fetchAll();

} catch (PDOException $e) {

    $schedules = [];

    $errors[] =
        (($lang === 'ar') ? 'تعذر تحميل جداول المجموعة.' : 'Unable to load batch schedules.');
}


/*
|--------------------------------------------------------------------------
| Fetch Courses
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->query("
        SELECT
            id,
            course_code,
            course_name,
            status
        FROM courses
        ORDER BY course_name ASC
    ");

    $courses = $stmt->fetchAll();

} catch (PDOException $e) {

    $courses = [];

    $errors[] =
        (($lang === 'ar') ? 'تعذر تحميل الدورات.' : 'Unable to load courses.');
}


/*
|--------------------------------------------------------------------------
| Fetch Instructors
|--------------------------------------------------------------------------
|
| We include:
| 1. Active instructors
| 2. Currently assigned instructors
|
| This prevents an existing inactive instructor from
| disappearing from the edit form.
|
*/

try {

    $stmt = $pdo->prepare("
        SELECT DISTINCT
            i.id,
            i.full_name,
            i.specialization,
            i.status AS instructor_status,
            u.username,
            u.status AS user_status

        FROM instructors i

        INNER JOIN users u
            ON u.id = i.user_id

        LEFT JOIN batch_instructors bi
            ON bi.instructor_id = i.id
            AND bi.batch_id = ?

        WHERE
            (
                i.status = 'active'
                AND u.status = 'active'
            )
            OR bi.id IS NOT NULL

        ORDER BY i.full_name ASC
    ");

    $stmt->execute([$batchId]);

    $instructors = $stmt->fetchAll();

} catch (PDOException $e) {

    $instructors = [];

    $errors[] =
        (($lang === 'ar') ? 'تعذر تحميل المدرسين.' : 'Unable to load instructors.');
}


/*
|--------------------------------------------------------------------------
| Handle Form Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | Get Form Data
    |--------------------------------------------------------------------------
    */

    $batchCode =
        trim($_POST['batch_code'] ?? '');

    $courseId =
        (int) ($_POST['course_id'] ?? 0);

    $startDate =
        trim($_POST['start_date'] ?? '');

    $endDate =
        trim($_POST['end_date'] ?? '');

    $room =
        trim($_POST['room'] ?? '');

    $capacity =
        trim($_POST['capacity'] ?? '');

    $status =
        $_POST['status'] ?? 'planned';


    /*
    |--------------------------------------------------------------------------
    | Selected Instructors
    |--------------------------------------------------------------------------
    */

    $selectedInstructors =
        $_POST['instructors'] ?? [];

    if (!is_array($selectedInstructors)) {
        $selectedInstructors = [];
    }

    $selectedInstructors = array_values(
        array_unique(
            array_filter(
                array_map(
                    'intval',
                    $selectedInstructors
                )
            )
        )
    );


    /*
    |--------------------------------------------------------------------------
    | Schedule Data
    |--------------------------------------------------------------------------
    */

    $scheduleDays =
        $_POST['schedule_day'] ?? [];

    $scheduleStartTimes =
        $_POST['schedule_start_time'] ?? [];

    $scheduleEndTimes =
        $_POST['schedule_end_time'] ?? [];


    if (!is_array($scheduleDays)) {
        $scheduleDays = [];
    }

    if (!is_array($scheduleStartTimes)) {
        $scheduleStartTimes = [];
    }

    if (!is_array($scheduleEndTimes)) {
        $scheduleEndTimes = [];
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Batch Code
    |--------------------------------------------------------------------------
    */

    if ($batchCode === '') {

        $errors[] =
            (($lang === 'ar') ? 'رمز المجموعة مطلوب.' : 'Batch code is required.');

    } elseif (strlen($batchCode) > 50) {

        $errors[] =
            (($lang === 'ar') ? 'يجب ألا يتجاوز رمز المجموعة 50 حرفًا.' : 'Batch code must not exceed 50 characters.');
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Course
    |--------------------------------------------------------------------------
    */

    if ($courseId <= 0) {

        $errors[] =
            (($lang === 'ar') ? 'يرجى اختيار دورة.' : 'Please select a course.');
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Dates
    |--------------------------------------------------------------------------
    */

    if ($startDate === '') {

        $errors[] =
            (($lang === 'ar') ? 'تاريخ البدء مطلوب.' : 'Start date is required.');
    }

    if ($endDate === '') {

        $errors[] =
            (($lang === 'ar') ? 'تاريخ الانتهاء مطلوب.' : 'End date is required.');
    }

    if (
        $startDate !== '' &&
        $endDate !== ''
    ) {

        if ($endDate < $startDate) {

            $errors[] =
                (($lang === 'ar') ? 'لا يمكن أن يكون تاريخ الانتهاء قبل تاريخ البدء.' : 'End date cannot be earlier than start date.');
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Capacity
    |--------------------------------------------------------------------------
    */

    if ($capacity === '') {

        $errors[] =
            (($lang === 'ar') ? 'السعة مطلوبة.' : 'Capacity is required.');

    } elseif (!ctype_digit($capacity)) {

        $errors[] =
            (($lang === 'ar') ? 'يجب أن تكون السعة رقمًا صحيحًا موجبًا.' : 'Capacity must be a positive whole number.');

    } elseif ((int) $capacity < 1) {

        $errors[] =
            (($lang === 'ar') ? 'يجب أن تكون السعة 1 على الأقل.' : 'Capacity must be at least 1.');
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Status
    |--------------------------------------------------------------------------
    */

    if (!in_array(
        $status,
        $allowedStatuses,
        true
    )) {

        $errors[] =
            (($lang === 'ar') ? 'حالة المجموعة غير صالحة.' : 'Invalid batch status.');

        $status = 'planned';
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Course
    |--------------------------------------------------------------------------
    */

    if ($courseId > 0) {

        try {

            $stmt = $pdo->prepare("
                SELECT id
                FROM courses
                WHERE id = ?
                  AND status = 'active'
                LIMIT 1
            ");

            $stmt->execute([
                $courseId
            ]);

            if (!$stmt->fetch()) {

                $errors[] =
                    (($lang === 'ar') ? 'الدورة المحددة غير متاحة.' : 'Selected course is not available.');
            }

        } catch (PDOException $e) {

            $errors[] =
                (($lang === 'ar') ? 'تعذر التحقق من الدورة المحددة.' : 'Unable to validate selected course.');
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Check Duplicate Batch Code
    |--------------------------------------------------------------------------
    */

    if ($batchCode !== '') {

        try {

            $stmt = $pdo->prepare("
                SELECT id
                FROM batches
                WHERE batch_code = ?
                  AND id != ?
                LIMIT 1
            ");

            $stmt->execute([
                $batchCode,
                $batchId
            ]);

            if ($stmt->fetch()) {

                $errors[] =
                    (($lang === 'ar') ? 'رمز المجموعة موجود بالفعل.' : 'Batch code already exists.');
            }

        } catch (PDOException $e) {

            $errors[] =
                (($lang === 'ar') ? 'تعذر التحقق من رمز المجموعة.' : 'Unable to validate batch code.');
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Instructors
    |--------------------------------------------------------------------------
    */

    if (!empty($selectedInstructors)) {

        try {

            $placeholders = implode(
                ',',
                array_fill(
                    0,
                    count($selectedInstructors),
                    '?'
                )
            );

            $stmt = $pdo->prepare("
                SELECT
                    i.id

                FROM instructors i

                INNER JOIN users u
                    ON u.id = i.user_id

                WHERE i.id IN ($placeholders)

                  AND i.status = 'active'

                  AND u.status = 'active'
            ");

            $stmt->execute(
                $selectedInstructors
            );

            $validInstructorIds =
                array_map(
                    'intval',
                    $stmt->fetchAll(
                        PDO::FETCH_COLUMN
                    )
                );

            foreach (
                $selectedInstructors
                as $instructorId
            ) {

                if (!in_array(
                    (int) $instructorId,
                    $validInstructorIds,
                    true
                )) {

                    $errors[] =
                        (($lang === 'ar') ? 'واحد أو أكثر من المدرسين المحددين غير نشط.' : 'One or more selected instructors are not active.');

                    break;
                }
            }

        } catch (PDOException $e) {

            $errors[] =
                (($lang === 'ar') ? 'تعذر التحقق من المدرسين المحددين.' : 'Unable to validate selected instructors.');
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Schedules
    |--------------------------------------------------------------------------
    */

    $schedules = [];

    $scheduleCount = max(
        count($scheduleDays),
        count($scheduleStartTimes),
        count($scheduleEndTimes)
    );

    for ($i = 0; $i < $scheduleCount; $i++) {

        $day =
            trim($scheduleDays[$i] ?? '');

        $startTime =
            trim($scheduleStartTimes[$i] ?? '');

        $endTime =
            trim($scheduleEndTimes[$i] ?? '');


        /*
        |--------------------------------------------------------------------------
        | Ignore Completely Empty Row
        |--------------------------------------------------------------------------
        */

        if (
            $day === '' &&
            $startTime === '' &&
            $endTime === ''
        ) {
            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Validate Day
        |--------------------------------------------------------------------------
        */

        if ($day === '') {

            $errors[] =
                (($lang === 'ar') ? 'يرجى اختيار يوم لكل صف في الجدول.' : 'Please select a day for every schedule row.');

            continue;
        }

        if (!in_array(
            $day,
            $allowedDays,
            true
        )) {

            $errors[] =
                (($lang === 'ar') ? 'تم اختيار يوم غير صالح للجدول.' : 'Invalid schedule day selected.');

            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Validate Start Time
        |--------------------------------------------------------------------------
        */

        if ($startTime === '') {

            $errors[] =
                (($lang === 'ar') ? 'يرجى إدخال وقت البدء لكل صف في الجدول.' : 'Please enter a start time for every schedule row.');

            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Validate End Time
        |--------------------------------------------------------------------------
        */

        if ($endTime === '') {

            $errors[] =
                (($lang === 'ar') ? 'يرجى إدخال وقت الانتهاء لكل صف في الجدول.' : 'Please enter an end time for every schedule row.');

            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Validate Time Order
        |--------------------------------------------------------------------------
        */

        if ($endTime <= $startTime) {

            $errors[] =
                (($lang === 'ar') ? 'يجب أن يكون وقت انتهاء الجدول بعد وقت البدء.' : 'Schedule end time must be later than start time.');

            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Store Schedule
        |--------------------------------------------------------------------------
        */

        $schedules[] = [
            'day' => $day,
            'start_time' => $startTime,
            'end_time' => $endTime
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Update Database
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            /*
            |--------------------------------------------------------------------------
            | Start Transaction
            |--------------------------------------------------------------------------
            */

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Update Batch
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE batches

                SET
                    batch_code = ?,
                    course_id = ?,
                    start_date = ?,
                    end_date = ?,
                    room = ?,
                    capacity = ?,
                    status = ?

                WHERE id = ?
            ");

            $stmt->execute([
                $batchCode,
                $courseId,
                $startDate,
                $endDate,
                $room !== '' ? $room : null,
                (int) $capacity,
                $status,
                $batchId
            ]);


            /*
            |--------------------------------------------------------------------------
            | Remove Old Instructor Assignments
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                DELETE FROM batch_instructors
                WHERE batch_id = ?
            ");

            $stmt->execute([
                $batchId
            ]);


            /*
            |--------------------------------------------------------------------------
            | Add New Instructor Assignments
            |--------------------------------------------------------------------------
            */

            if (!empty($selectedInstructors)) {

                $stmt = $pdo->prepare("
                    INSERT INTO batch_instructors (
                        batch_id,
                        instructor_id
                    )

                    VALUES (?, ?)
                ");

                foreach (
                    $selectedInstructors
                    as $instructorId
                ) {

                    $stmt->execute([
                        $batchId,
                        $instructorId
                    ]);
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Remove Old Schedules
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                DELETE FROM batch_schedules
                WHERE batch_id = ?
            ");

            $stmt->execute([
                $batchId
            ]);


            /*
            |--------------------------------------------------------------------------
            | Add New Schedules
            |--------------------------------------------------------------------------
            */

            if (!empty($schedules)) {

                $stmt = $pdo->prepare("
                    INSERT INTO batch_schedules (
                        batch_id,
                        day_of_week,
                        start_time,
                        end_time
                    )

                    VALUES (?, ?, ?, ?)
                ");

                foreach (
                    $schedules
                    as $schedule
                ) {

                    $stmt->execute([
                        $batchId,
                        $schedule['day'],
                        $schedule['start_time'],
                        $schedule['end_time']
                    ]);
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Commit
            |--------------------------------------------------------------------------
            */

            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | Redirect
            |--------------------------------------------------------------------------
            */

            header(
                'Location: index.php?updated=1'
            );

            exit;

        } catch (PDOException $e) {

            /*
            |--------------------------------------------------------------------------
            | Rollback
            |--------------------------------------------------------------------------
            */

            if ($pdo->inTransaction()) {

                $pdo->rollBack();
            }

            $errors[] =
                (($lang === 'ar') ? 'فشل تحديث المجموعة. يرجى المحاولة مرة أخرى.' : 'Failed to update batch. Please try again.');

            /*
             * Uncomment temporarily for debugging:
             *
             * $errors[] = $e->getMessage();
             */
        }
    }
}


/*
|--------------------------------------------------------------------------
| Sidebar
|--------------------------------------------------------------------------
*/

require_once '../../includes/sidebar.php';

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
        <?= $lang === 'ar' ? 'تعديل المجموعة - مركز ALAW LAMA للتدريب' : 'Edit Batch - ALAW LAMA Training Center' ?>
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f5f7fb;

            color: #1f2937;
        }


        /*
        |--------------------------------------------------------------------------
        | Page
        |--------------------------------------------------------------------------
        */

        .page-container {

            width: 100%;

            max-width: 1200px;

            margin: 0 auto;

            padding: 30px;
        }


        /*
        |--------------------------------------------------------------------------
        | Header
        |--------------------------------------------------------------------------
        */

        .page-header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 20px;

            margin-bottom: 25px;
        }


        .page-header h1 {

            margin: 0 0 6px 0;

            font-size: 28px;

            color: #111827;
        }


        .page-header p {

            margin: 0;

            color: #6b7280;

            font-size: 14px;
        }


        .header-actions {

            display: flex;

            gap: 10px;

            flex-wrap: wrap;
        }


        /*
        |--------------------------------------------------------------------------
        | Buttons
        |--------------------------------------------------------------------------
        */

        .btn {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            padding: 10px 16px;

            border-radius: 8px;

            text-decoration: none;

            border: none;

            cursor: pointer;

            font-size: 14px;

            font-weight: 600;

            transition: 0.2s ease;
        }


        .btn-back {

            background: #e5e7eb;

            color: #374151;
        }


        .btn-back:hover {

            background: #d1d5db;
        }


        .btn-save {

            background: #2563eb;

            color: white;
        }


        .btn-save:hover {

            background: #1d4ed8;
        }


        /*
        |--------------------------------------------------------------------------
        | Cards
        |--------------------------------------------------------------------------
        */

        .card {

            background: white;

            border-radius: 12px;

            padding: 25px;

            margin-bottom: 20px;

            box-shadow:
                0 2px 8px rgba(
                    0,
                    0,
                    0,
                    0.05
                );
        }


        .card-title {

            margin: 0 0 20px 0;

            font-size: 18px;

            font-weight: 700;

            color: #111827;

            padding-bottom: 12px;

            border-bottom:
                1px solid #e5e7eb;
        }


        /*
        |--------------------------------------------------------------------------
        | Form
        |--------------------------------------------------------------------------
        */

        .form-grid {

            display: grid;

            grid-template-columns:
                repeat(
                    2,
                    minmax(0, 1fr)
                );

            gap: 20px;
        }


        .form-group {

            display: flex;

            flex-direction: column;

            gap: 7px;
        }


        .form-group.full-width {

            grid-column: 1 / -1;
        }


        .form-group label {

            font-size: 13px;

            font-weight: 600;

            color: #374151;
        }


        .required {

            color: #dc2626;
        }


        input,
        select {

            width: 100%;

            padding: 11px 12px;

            border:
                1px solid #d1d5db;

            border-radius: 8px;

            font-size: 14px;

            background: white;

            outline: none;
        }


        input:focus,
        select:focus {

            border-color: #2563eb;

            box-shadow:
                0 0 0 3px
                rgba(
                    37,
                    99,
                    235,
                    0.1
                );
        }


        .help-text {

            font-size: 12px;

            color: #6b7280;
        }


        /*
        |--------------------------------------------------------------------------
        | Instructor List
        |--------------------------------------------------------------------------
        */

        .instructor-list {

            border:
                1px solid #d1d5db;

            border-radius: 8px;

            padding: 12px;

            max-height: 280px;

            overflow-y: auto;

            background: #fafafa;
        }


        .instructor-item {

            display: flex;

            align-items: flex-start;

            gap: 10px;

            padding: 10px;

            border-radius: 6px;

            cursor: pointer;
        }


        .instructor-item:hover {

            background: #f3f4f6;
        }


        .instructor-item input {

            width: auto;

            margin-top: 3px;
        }


        .instructor-info {

            display: flex;

            flex-direction: column;

            gap: 3px;
        }


        .instructor-name {

            font-weight: 600;

            color: #111827;
        }


        .instructor-meta {

            font-size: 12px;

            color: #6b7280;
        }


        .inactive-label {

            color: #dc2626;

            font-weight: 600;
        }


        .no-instructors {

            padding: 15px;

            color: #6b7280;

            text-align: center;

            font-size: 14px;
        }


        /*
        |--------------------------------------------------------------------------
        | Schedule
        |--------------------------------------------------------------------------
        */

        .schedule-container {

            display: flex;

            flex-direction: column;

            gap: 12px;
        }


        .schedule-row {

            display: grid;

            grid-template-columns:
                minmax(0, 1.2fr)
                minmax(0, 1fr)
                minmax(0, 1fr)
                auto;

            gap: 10px;

            align-items: end;

            padding: 12px;

            background: #f9fafb;

            border:
                1px solid #e5e7eb;

            border-radius: 8px;
        }


        .schedule-field {

            display: flex;

            flex-direction: column;

            gap: 6px;
        }


        .schedule-field label {

            font-size: 12px;

            font-weight: 600;

            color: #6b7280;
        }


        .btn-remove-schedule {

            height: 42px;

            padding: 0 12px;

            border: none;

            border-radius: 8px;

            background: #fee2e2;

            color: #991b1b;

            cursor: pointer;

            font-weight: 600;
        }


        .btn-remove-schedule:hover {

            background: #fecaca;
        }


        .btn-add-schedule {

            align-self: flex-start;

            margin-top: 8px;

            padding: 9px 14px;

            border: none;

            border-radius: 8px;

            background: #eff6ff;

            color: #1d4ed8;

            cursor: pointer;

            font-size: 13px;

            font-weight: 600;
        }


        .btn-add-schedule:hover {

            background: #dbeafe;
        }


        /*
        |--------------------------------------------------------------------------
        | Errors
        |--------------------------------------------------------------------------
        */

        .error-box {

            background: #fee2e2;

            color: #991b1b;

            border:
                1px solid #fecaca;

            border-radius: 8px;

            padding: 15px 18px;

            margin-bottom: 20px;
        }


        .error-box strong {

            display: block;

            margin-bottom: 8px;
        }


        .error-box ul {

            margin: 0;

            padding-left: 20px;
        }


        .error-box li {

            margin-bottom: 4px;

            font-size: 14px;
        }


        /*
        |--------------------------------------------------------------------------
        | Bottom Actions
        |--------------------------------------------------------------------------
        */

        .form-actions {

            display: flex;

            justify-content: flex-end;

            gap: 10px;

            margin-bottom: 30px;
        }


        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 800px) {

            .page-container {

                padding: 20px 15px;
            }


            .page-header {

                flex-direction: column;

                align-items: flex-start;
            }


            .form-grid {

                grid-template-columns: 1fr;
            }


            .form-group.full-width {

                grid-column: auto;
            }


            .schedule-row {

                grid-template-columns: 1fr;
            }


            .btn-remove-schedule {

                width: 100%;
            }


            .form-actions {

                flex-direction: column;
            }


            .form-actions .btn {

                width: 100%;
            }
        }


        @media (max-width: 500px) {

            .card {

                padding: 18px;
            }


            .page-header h1 {

                font-size: 23px;
            }
        }

    </style>

</head>


<body>

<div class="page-container">


    <!-- ============================================================
         HEADER
    ============================================================= -->

    <div class="page-header">

        <div>

            <h1>
                <?= $lang === 'ar' ? 'تعديل المجموعة' : 'Edit Batch' ?>
            </h1>

            <p>
                <?= $lang === 'ar' ? 'تحديث معلومات المجموعة والمدرسين والجداول.' : 'Update batch information, instructors and schedules.' ?>
            </p>

        </div>


        <div class="header-actions">

            <a
                href="index.php"
                class="btn btn-back"
            >
                <?= $lang === 'ar' ? '← العودة إلى المجموعات' : '← Back to Batches' ?>
            </a>

        </div>

    </div>


    <!-- ============================================================
         ERRORS
    ============================================================= -->

    <?php if (!empty($errors)): ?>

        <div class="error-box">

            <strong>
                <?= $lang === 'ar' ? 'يرجى تصحيح الأخطاء التالية:' : 'Please fix the following errors:' ?>
            </strong>

            <ul>

                <?php foreach ($errors as $error): ?>

                    <li>
                        <?= htmlspecialchars($error) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <!-- ============================================================
         FORM
    ============================================================= -->

    <form
        method="POST"
        action="edit.php?id=<?= $batchId ?>"
        id="batch-form"
    >

        <input
            type="hidden"
            name="batch_id"
            value="<?= $batchId ?>"
        >


        <!-- ========================================================
             BATCH INFORMATION
        ========================================================= -->

        <div class="card">

            <h2 class="card-title">
                <?= $lang === 'ar' ? 'معلومات المجموعة' : 'Batch Information' ?>
            </h2>


            <div class="form-grid">


                <!-- Batch Code -->

                <div class="form-group">

                    <label for="batch_code">

                        <?= $lang === 'ar' ? 'رمز المجموعة' : 'Batch Code' ?>

                        <span class="required">
                            *
                        </span>

                    </label>


                    <input
                        type="text"
                        id="batch_code"
                        name="batch_code"
                        value="<?= htmlspecialchars($batchCode) ?>"
                        maxlength="50"
                        placeholder="Example: ALW-2026-001"
                        required
                    >


                    <div class="help-text">
                        <?= $lang === 'ar' ? 'يجب أن يكون رمز المجموعة فريدًا.' : 'Batch code must be unique.' ?>
                    </div>

                </div>


                <!-- Course -->

                <div class="form-group">

                    <label for="course_id">

                        <?= $lang === 'ar' ? 'الدورة' : 'Course' ?>

                        <span class="required">
                            *
                        </span>

                    </label>


                    <select
                        id="course_id"
                        name="course_id"
                        required
                    >

                        <option value="">
                            <?= $lang === 'ar' ? '-- اختر الدورة --' : '-- Select Course --' ?>
                        </option>


                        <?php foreach ($courses as $course): ?>

                            <option
                                value="<?= (int) $course['id'] ?>"
                                <?= (
                                    (int) $courseId ===
                                    (int) $course['id']
                                )
                                    ? 'selected'
                                    : '' ?>
                            >

                                <?= htmlspecialchars(
                                    $course['course_code']
                                ) ?>

                                -

                                <?= htmlspecialchars(
                                    $course['course_name']
                                ) ?>

                                <?php if (
                                    $course['status'] !== 'active'
                                ): ?>

                                    <?= $lang === 'ar' ? '(غير نشط)' : '(Inactive)' ?>

                                <?php endif; ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- Start Date -->

                <div class="form-group">

                    <label for="start_date">

                        <?= $lang === 'ar' ? 'تاريخ البدء' : 'Start Date' ?>

                        <span class="required">
                            *
                        </span>

                    </label>


                    <input
                        type="date"
                        id="start_date"
                        name="start_date"
                        value="<?= htmlspecialchars($startDate) ?>"
                        required
                    >

                </div>


                <!-- End Date -->

                <div class="form-group">

                    <label for="end_date">

                        <?= $lang === 'ar' ? 'تاريخ الانتهاء' : 'End Date' ?>

                        <span class="required">
                            *
                        </span>

                    </label>


                    <input
                        type="date"
                        id="end_date"
                        name="end_date"
                        value="<?= htmlspecialchars($endDate) ?>"
                        required
                    >

                </div>


                <!-- Room -->

                <div class="form-group">

                    <label for="room">
                        <?= $lang === 'ar' ? 'القاعة' : 'Room' ?>
                    </label>


                    <input
                        type="text"
                        id="room"
                        name="room"
                        value="<?= htmlspecialchars($room) ?>"
                        maxlength="100"
                        placeholder="Example: Room A"
                    >

                </div>


                <!-- Capacity -->

                <div class="form-group">

                    <label for="capacity">

                        <?= $lang === 'ar' ? 'السعة' : 'Capacity' ?>

                        <span class="required">
                            *
                        </span>

                    </label>


                    <input
                        type="number"
                        id="capacity"
                        name="capacity"
                        value="<?= htmlspecialchars($capacity) ?>"
                        min="1"
                        step="1"
                        required
                    >

                </div>


                <!-- Status -->

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
                            value="planned"
                            <?= $status === 'planned'
                                ? 'selected'
                                : '' ?>
                        >
                            <?= $lang === 'ar' ? 'مخطط' : 'Planned' ?>
                        </option>


                        <option
                            value="active"
                            <?= $status === 'active'
                                ? 'selected'
                                : '' ?>
                        >
                            <?= $lang === 'ar' ? 'نشط' : 'Active' ?>
                        </option>


                        <option
                            value="completed"
                            <?= $status === 'completed'
                                ? 'selected'
                                : '' ?>
                        >
                            <?= $lang === 'ar' ? 'مكتمل' : 'Completed' ?>
                        </option>


                        <option
                            value="cancelled"
                            <?= $status === 'cancelled'
                                ? 'selected'
                                : '' ?>
                        >
                            <?= $lang === 'ar' ? 'ملغى' : 'Cancelled' ?>
                        </option>

                    </select>

                </div>

            </div>

        </div>


        <!-- ========================================================
             INSTRUCTORS
        ========================================================= -->

        <div class="card">

            <h2 class="card-title">
                <?= $lang === 'ar' ? 'المدرسون' : 'Instructors' ?>
            </h2>


            <div class="form-group">

                <label>
                    <?= $lang === 'ar' ? 'تعيين المدرسين' : 'Assign Instructors' ?>
                </label>


                <div class="help-text">

                    <?= $lang === 'ar' ? 'اختر مدرسًا واحدًا أو أكثر لهذه المجموعة.' : 'Select one or more instructors for this batch.' ?>

                </div>


                <div class="instructor-list">


                    <?php if (empty($instructors)): ?>

                        <div class="no-instructors">

                            <?= $lang === 'ar' ? 'لا يوجد مدرسون متاحون.' : 'No instructors available.' ?>

                        </div>

                    <?php else: ?>


                        <?php foreach (
                            $instructors
                            as $instructor
                        ): ?>


                            <label class="instructor-item">


                                <input
                                    type="checkbox"
                                    name="instructors[]"
                                    value="<?= (int) $instructor['id'] ?>"
                                    <?= in_array(
                                        (int) $instructor['id'],
                                        $selectedInstructors,
                                        true
                                    )
                                        ? 'checked'
                                        : '' ?>
                                >


                                <span class="instructor-info">


                                    <span class="instructor-name">

                                        <?= htmlspecialchars(
                                            $instructor['full_name']
                                        ) ?>

                                    </span>


                                    <span class="instructor-meta">

                                        <?php if (
                                            !empty(
                                                $instructor[
                                                    'specialization'
                                                ]
                                            )
                                        ): ?>

                                            <?= htmlspecialchars(
                                                $instructor[
                                                    'specialization'
                                                ]
                                            ) ?>

                                            •

                                        <?php endif; ?>


                                        @<?= htmlspecialchars(
                                            $instructor['username']
                                        ) ?>


                                        <?php if (
                                            $instructor[
                                                'instructor_status'
                                            ] !== 'active'
                                        ): ?>

                                            •

                                            <span class="inactive-label">
                                                <?= $lang === 'ar' ? 'غير نشط' : 'Inactive' ?>
                                            </span>

                                        <?php endif; ?>

                                    </span>

                                </span>

                            </label>


                        <?php endforeach; ?>


                    <?php endif; ?>

                </div>

            </div>

        </div>


        <!-- ========================================================
             WEEKLY SCHEDULE
        ========================================================= -->

        <div class="card">

            <h2 class="card-title">
                <?= $lang === 'ar' ? 'الجدول الأسبوعي' : 'Weekly Schedule' ?>
            </h2>


            <div class="form-group">

                <label>
                    <?= $lang === 'ar' ? 'جدول الحصص' : 'Class Schedule' ?>
                </label>


                <div class="help-text">

                    <?= $lang === 'ar' ? 'أضف أو عدّل أو احذف الأيام والأوقات' : 'Add, edit or remove the days and times' ?>
                    <?= $lang === 'ar' ? 'التي تعقد فيها هذه المجموعة حصصها.' : 'when this batch meets.' ?>

                </div>


                <div
                    class="schedule-container"
                    id="schedule-container"
                >


                    <?php if (!empty($schedules)): ?>


                        <?php foreach (
                            $schedules
                            as $schedule
                        ): ?>


                            <div class="schedule-row">


                                <!-- Day -->

                                <div class="schedule-field">

                                    <label>
                                        <?= $lang === 'ar' ? 'اليوم' : 'Day' ?>
                                    </label>


                                    <select
                                        name="schedule_day[]"
                                    >

                                        <option value="">
                                            <?= $lang === 'ar' ? '-- اليوم --' : '-- Day --' ?>
                                        </option>


                                        <?php foreach (
                                            $allowedDays
                                            as $day
                                        ): ?>

                                            <option
                                                value="<?= htmlspecialchars($day) ?>"
                                                <?= (
                                                    $schedule[
                                                        'day_of_week'
                                                    ] === $day
                                                )
                                                    ? 'selected'
                                                    : '' ?>
                                            >

                                                <?= htmlspecialchars(
                                                    $day
                                                ) ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>


                                <!-- Start Time -->

                                <div class="schedule-field">

                                    <label>
                                        <?= $lang === 'ar' ? 'وقت البدء' : 'Start Time' ?>
                                    </label>


                                    <input
                                        type="time"
                                        name="schedule_start_time[]"
                                        value="<?= htmlspecialchars(
                                            substr(
                                                $schedule['start_time'],
                                                0,
                                                5
                                            )
                                        ) ?>"
                                    >

                                </div>


                                <!-- End Time -->

                                <div class="schedule-field">

                                    <label>
                                        <?= $lang === 'ar' ? 'وقت الانتهاء' : 'End Time' ?>
                                    </label>


                                    <input
                                        type="time"
                                        name="schedule_end_time[]"
                                        value="<?= htmlspecialchars(
                                            substr(
                                                $schedule['end_time'],
                                                0,
                                                5
                                            )
                                        ) ?>"
                                    >

                                </div>


                                <!-- Remove -->

                                <button
                                    type="button"
                                    class="btn-remove-schedule"
                                    onclick="removeSchedule(this)"
                                >
                                    <?= $lang === 'ar' ? 'حذف' : 'Remove' ?>
                                </button>

                            </div>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <!-- Empty Schedule Row -->

                        <div class="schedule-row">


                            <div class="schedule-field">

                                <label>
                                    <?= $lang === 'ar' ? 'اليوم' : 'Day' ?>
                                </label>


                                <select
                                    name="schedule_day[]"
                                >

                                    <option value="">
                                        <?= $lang === 'ar' ? '-- اليوم --' : '-- Day --' ?>
                                    </option>


                                    <?php foreach (
                                        $allowedDays
                                        as $day
                                    ): ?>

                                        <option
                                            value="<?= htmlspecialchars($day) ?>"
                                        >

                                            <?= htmlspecialchars(
                                                $day
                                            ) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <div class="schedule-field">

                                <label>
                                    <?= $lang === 'ar' ? 'وقت البدء' : 'Start Time' ?>
                                </label>


                                <input
                                    type="time"
                                    name="schedule_start_time[]"
                                >

                            </div>


                            <div class="schedule-field">

                                <label>
                                    <?= $lang === 'ar' ? 'وقت الانتهاء' : 'End Time' ?>
                                </label>


                                <input
                                    type="time"
                                    name="schedule_end_time[]"
                                >

                            </div>


                            <button
                                type="button"
                                class="btn-remove-schedule"
                                onclick="removeSchedule(this)"
                            >
                                <?= $lang === 'ar' ? 'حذف' : 'Remove' ?>
                            </button>

                        </div>


                    <?php endif; ?>

                </div>


                <button
                    type="button"
                    class="btn-add-schedule"
                    onclick="addSchedule()"
                >
                    <?= $lang === 'ar' ? '+ إضافة جدول آخر' : '+ Add Another Schedule' ?>
                </button>

            </div>

        </div>


        <!-- ========================================================
             FORM ACTIONS
        ========================================================= -->

        <div class="form-actions">

            <a
                href="index.php"
                class="btn btn-back"
            >
                <?= $lang === 'ar' ? 'إلغاء' : 'Cancel' ?>
            </a>


            <button
                type="submit"
                class="btn btn-save"
            >
                <?= $lang === 'ar' ? 'تحديث المجموعة' : 'Update Batch' ?>
            </button>

        </div>

    </form>

</div>


<script>

/*
|--------------------------------------------------------------------------
| Days
|--------------------------------------------------------------------------
*/

const days = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday'
];


/*
|--------------------------------------------------------------------------
| Add Schedule
|--------------------------------------------------------------------------
*/

function addSchedule() {

    const container =
        document.getElementById(
            'schedule-container'
        );


    const row =
        document.createElement('div');


    row.className =
        'schedule-row';


    let dayOptions =
        '<option value="">' + <?= $lang === 'ar' ? "'-- اليوم --'" : "'-- Day --'" ?> + '</option>';


    days.forEach(function(day) {

        dayOptions +=
            '<option value="' +
            day +
            '">' +
            day +
            '</option>';

    });


    row.innerHTML = `

        <div class="schedule-field">

            <label>
                <?= $lang === 'ar' ? 'اليوم' : 'Day' ?>
            </label>

            <select name="schedule_day[]">

                ${dayOptions}

            </select>

        </div>


        <div class="schedule-field">

            <label>
                <?= $lang === 'ar' ? 'وقت البدء' : 'Start Time' ?>
            </label>

            <input
                type="time"
                name="schedule_start_time[]"
            >

        </div>


        <div class="schedule-field">

            <label>
                <?= $lang === 'ar' ? 'وقت الانتهاء' : 'End Time' ?>
            </label>

            <input
                type="time"
                name="schedule_end_time[]"
            >

        </div>


        <button
            type="button"
            class="btn-remove-schedule"
            onclick="removeSchedule(this)"
        >
            <?= $lang === 'ar' ? 'حذف' : 'Remove' ?>
        </button>

    `;


    container.appendChild(row);
}


/*
|--------------------------------------------------------------------------
| Remove Schedule
|--------------------------------------------------------------------------
*/

function removeSchedule(button) {

    const row =
        button.closest(
            '.schedule-row'
        );


    if (!row) {
        return;
    }


    const container =
        document.getElementById(
            'schedule-container'
        );


    const rows =
        container.querySelectorAll(
            '.schedule-row'
        );


    /*
    |--------------------------------------------------------------------------
    | Keep At Least One Row
    |--------------------------------------------------------------------------
    */

    if (rows.length <= 1) {

        const day =
            row.querySelector(
                'select[name="schedule_day[]"]'
            );

        const start =
            row.querySelector(
                'input[name="schedule_start_time[]"]'
            );

        const end =
            row.querySelector(
                'input[name="schedule_end_time[]"]'
            );


        if (day) {
            day.value = '';
        }

        if (start) {
            start.value = '';
        }

        if (end) {
            end.value = '';
        }

        return;
    }


    row.remove();
}


/*
|--------------------------------------------------------------------------
| Date Validation
|--------------------------------------------------------------------------
*/

const startDate =
    document.getElementById(
        'start_date'
    );


const endDate =
    document.getElementById(
        'end_date'
    );


function updateEndDateMin() {

    if (startDate.value !== '') {

        endDate.min =
            startDate.value;
    }
}


updateEndDateMin();


startDate.addEventListener(
    'change',
    function() {

        updateEndDateMin();


        if (
            endDate.value !== '' &&
            endDate.value < startDate.value
        ) {

            endDate.value = '';
        }

    }
);


/*
|--------------------------------------------------------------------------
| Form Validation
|--------------------------------------------------------------------------
*/

document
    .getElementById('batch-form')
    .addEventListener(
        'submit',
        function(event) {

            /*
            |--------------------------------------------------------------------------
            | Validate Dates
            |--------------------------------------------------------------------------
            */

            const start =
                startDate.value;

            const end =
                endDate.value;


            if (
                start !== '' &&
                end !== '' &&
                end < start
            ) {

                event.preventDefault();

                alert(
                    <?= $lang === 'ar' ? "'لا يمكن أن يكون تاريخ الانتهاء قبل تاريخ البدء.'" : "'End date cannot be earlier than start date.'" ?>
                );

                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Validate Schedule Rows
            |--------------------------------------------------------------------------
            */

            const rows =
                document.querySelectorAll(
                    '#schedule-container .schedule-row'
                );


            for (
                const row of rows
            ) {

                const day =
                    row.querySelector(
                        'select[name="schedule_day[]"]'
                    ).value;


                const startTime =
                    row.querySelector(
                        'input[name="schedule_start_time[]"]'
                    ).value;


                const endTime =
                    row.querySelector(
                        'input[name="schedule_end_time[]"]'
                    ).value;


                /*
                |--------------------------------------------------------------------------
                | Empty Row
                |--------------------------------------------------------------------------
                */

                if (
                    day === '' &&
                    startTime === '' &&
                    endTime === ''
                ) {

                    continue;
                }


                /*
                |--------------------------------------------------------------------------
                | Incomplete Row
                |--------------------------------------------------------------------------
                */

                if (
                    day === '' ||
                    startTime === '' ||
                    endTime === ''
                ) {

                    event.preventDefault();

                    alert(
                        <?= $lang === 'ar' ? "'يرجى إكمال كل صف في الجدول.'" : "'Please complete every schedule row.'" ?>
                    );

                    return;
                }


                /*
                |--------------------------------------------------------------------------
                | Invalid Time
                |--------------------------------------------------------------------------
                */

                if (
                    endTime <= startTime
                ) {

                    event.preventDefault();

                    alert(
                        <?= $lang === 'ar' ? "'يجب أن يكون وقت انتهاء الجدول بعد وقت البدء.'" : "'Schedule end time must be later than start time.'" ?>
                    );

                    return;
                }

            }

        }
    );

</script>

</body>

</html>
