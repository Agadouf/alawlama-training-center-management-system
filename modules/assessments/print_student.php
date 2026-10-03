<?php
session_start();

require_once '../../config/database.php';

// ======================================================
// AUTHENTICATION
// ======================================================

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../auth/login.php');
    exit;
}

$role = strtolower((string) $_SESSION['role']);

if (!in_array($role, ['admin', 'staff', 'instructor'], true)) {
    http_response_code(403);
    die('Access denied.');
}

// ======================================================
// GET ENROLLMENT
// ======================================================

$enrollmentId = isset($_GET['enrollment_id'])
    ? (int) $_GET['enrollment_id']
    : 0;

if ($enrollmentId <= 0) {
    die('Invalid student enrollment.');
}

// ======================================================
// ======================================================
// GET STUDENT + BATCH
// ======================================================

if ($role === 'instructor') {

    $userId = (int) $_SESSION['user_id'];

    $stmt = $pdo->prepare("
        SELECT
            e.id AS enrollment_id,
            s.id AS student_id,
            s.student_code,
            s.full_name,
            s.gender,
            s.date_of_birth,
            b.id AS batch_id,
            b.batch_code,
            b.start_date,
            b.end_date,
            c.course_name
        FROM enrollments e
        INNER JOIN students s
            ON s.id = e.student_id
        INNER JOIN batches b
            ON b.id = e.batch_id
        INNER JOIN courses c
            ON c.id = b.course_id
        INNER JOIN batch_instructors bi
            ON bi.batch_id = b.id
        INNER JOIN instructors i
            ON i.id = bi.instructor_id
        WHERE e.id = ?
          AND i.user_id = ?
        LIMIT 1
    ");

    $stmt->execute([$enrollmentId, $userId]);

} else {

    $stmt = $pdo->prepare("
        SELECT
            e.id AS enrollment_id,
            s.id AS student_id,
            s.student_code,
            s.full_name,
            s.gender,
            s.date_of_birth,
            b.id AS batch_id,
            b.batch_code,
            b.start_date,
            b.end_date,
            c.course_name
        FROM enrollments e
        INNER JOIN students s
            ON s.id = e.student_id
        INNER JOIN batches b
            ON b.id = e.batch_id
        INNER JOIN courses c
            ON c.id = b.course_id
        WHERE e.id = ?
        LIMIT 1
    ");

    $stmt->execute([$enrollmentId]);
}

$student = $stmt->fetch();

if (!$student) {
    http_response_code(403);
    die(
        $role === 'instructor'
            ? 'You are not authorized to view this student result.'
            : 'Student enrollment not found.'
    );
}

$batchId = (int) $student['batch_id'];


// ======================================================

$stmt = $pdo->prepare("
    SELECT
        id,
        title,
        type,
        max_score,
        due_date
    FROM assessments
    WHERE batch_id = ?
      AND status = 'active'
    ORDER BY
        CASE
            WHEN type = 'assignment' THEN 1
            WHEN type = 'midterm' THEN 2
            WHEN type = 'final' THEN 3
        END,
        id ASC
");

$stmt->execute([$batchId]);

$assessments = $stmt->fetchAll();

// ======================================================
// GET SCORES
// ======================================================

$stmt = $pdo->prepare("
    SELECT
        assessment_id,
        score,
        notes
    FROM assessment_scores
    WHERE enrollment_id = ?
");

$stmt->execute([$enrollmentId]);

$scoreRows = $stmt->fetchAll();

$scores = [];

foreach ($scoreRows as $row) {

    $scores[(int) $row['assessment_id']] = [
        'score' => $row['score'],
        'notes' => $row['notes']
    ];
}

// ======================================================
// SEPARATE ASSESSMENTS
// ======================================================

$assignments = [];
$midterm = null;
$finalExam = null;

foreach ($assessments as $assessment) {

    if ($assessment['type'] === 'assignment') {
        $assignments[] = $assessment;
    }

    if ($assessment['type'] === 'midterm') {
        $midterm = $assessment;
    }

    if ($assessment['type'] === 'final') {
        $finalExam = $assessment;
    }
}

// ======================================================
// ASSIGNMENT AVERAGE
// ======================================================

$assignmentPercentages = [];

foreach ($assignments as $assignment) {

    $assessmentId = (int) $assignment['id'];

    $maxScore = (float) $assignment['max_score'];

    $score = $scores[$assessmentId]['score'] ?? null;

    if (
        $score !== null &&
        $maxScore > 0
    ) {

        $assignmentPercentages[] =
            ((float) $score / $maxScore) * 100;
    }
}

if (!empty($assignmentPercentages)) {

    $assignmentAverage =
        array_sum($assignmentPercentages)
        / count($assignmentPercentages);

} else {

    $assignmentAverage = null;
}

// ======================================================
// MIDTERM
// ======================================================

$midtermPercentage = null;
$midtermScore = null;

if ($midterm) {

    $assessmentId = (int) $midterm['id'];

    $maxScore = (float) $midterm['max_score'];

    $midtermScore =
        $scores[$assessmentId]['score'] ?? null;

    if (
        $midtermScore !== null &&
        $maxScore > 0
    ) {

        $midtermPercentage =
            ((float) $midtermScore / $maxScore) * 100;
    }
}

// ======================================================
// FINAL EXAM
// ======================================================

$finalPercentage = null;
$finalScore = null;

if ($finalExam) {

    $assessmentId = (int) $finalExam['id'];

    $maxScore = (float) $finalExam['max_score'];

    $finalScore =
        $scores[$assessmentId]['score'] ?? null;

    if (
        $finalScore !== null &&
        $maxScore > 0
    ) {

        $finalPercentage =
            ((float) $finalScore / $maxScore) * 100;
    }
}

// ======================================================
// FINAL SCORE
//
// Assignment = 20%
// Midterm    = 35%
// Final      = 45%
// ======================================================

$overallScore = null;

if (
    $assignmentAverage !== null &&
    $midtermPercentage !== null &&
    $finalPercentage !== null
) {

    $overallScore =
        ($assignmentAverage * 0.20)
        +
        ($midtermPercentage * 0.35)
        +
        ($finalPercentage * 0.45);
}

// ======================================================
// LETTER GRADE
// ======================================================

$grade = '-';

if ($overallScore !== null) {

    if ($overallScore >= 90) {
        $grade = 'A';
    } elseif ($overallScore >= 80) {
        $grade = 'B';
    } elseif ($overallScore >= 70) {
        $grade = 'C';
    } elseif ($overallScore >= 60) {
        $grade = 'D';
    } else {
        $grade = 'F';
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Student Result - <?= htmlspecialchars($student['full_name']) ?>
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            font-family: Arial, Helvetica, sans-serif;
            background: #ffffff;
            color: #111827;
        }

        .result-container {
            max-width: 900px;
            margin: auto;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #111827;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }

        .header h1 {
            margin: 0 0 8px;
            font-size: 25px;
        }

        .header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }

        .header p {
            margin: 8px 0 0;
            color: #6b7280;
            font-size: 13px;
        }

        .student-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 30px;
            margin-bottom: 25px;
        }

        .info-item {
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .info-label {
            display: inline-block;
            width: 120px;
            font-weight: 700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 10px;
            font-size: 13px;
        }

        th {
            background: #f3f4f6;
            text-align: left;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .summary {
            margin-top: 25px;
            width: 100%;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 15px;
            border-bottom: 1px solid #e5e7eb;
        }

        .summary-row strong {
            font-size: 14px;
        }

        .final-result {
            margin-top: 15px;
            border: 2px solid #111827;
            padding: 18px;
        }

        .final-result-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .final-score {
            font-size: 26px;
            font-weight: 700;
        }

        .final-grade {
            font-size: 24px;
            font-weight: 700;
        }

        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
        }

        .print-button {
            display: block;
            margin: 25px auto;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            background: #2563eb;
            color: white;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .print-button:hover {
            background: #1d4ed8;
        }

        @media print {

            body {
                padding: 0;
            }

            .print-button {
                display: none;
            }

            .result-container {
                max-width: 100%;
            }

        }

        @media (max-width: 650px) {

            body {
                padding: 15px;
            }

            .student-info {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

<div class="result-container">

    <!-- ================================================
         HEADER
    ================================================= -->

    <div class="header">

        <h1>
            ALAWALAMA TRAINING CENTER
        </h1>

        <h2>
            Student Assessment Result
        </h2>

        <p>
            Final Academic Result
        </p>

    </div>


    <!-- ================================================
         STUDENT INFORMATION
    ================================================= -->

    <div class="student-info">

        <div class="info-item">

            <span class="info-label">
                Student ID:
            </span>

            <?= htmlspecialchars(
                $student['student_code']
            ) ?>

        </div>


        <div class="info-item">

            <span class="info-label">
                Student Name:
            </span>

            <?= htmlspecialchars(
                $student['full_name']
            ) ?>

        </div>


        <div class="info-item">

            <span class="info-label">
                Course:
            </span>

            <?= htmlspecialchars(
                $student['course_name']
            ) ?>

        </div>


        <div class="info-item">

            <span class="info-label">
                Batch:
            </span>

            <?= htmlspecialchars(
                $student['batch_code']
            ) ?>

        </div>

    </div>


    <!-- ================================================
         ASSIGNMENTS
    ================================================= -->

    <h3>
        Assignments
    </h3>

    <table>

        <thead>

            <tr>

                <th>
                    No.
                </th>

                <th>
                    Assignment
                </th>

                <th>
                    Score
                </th>

                <th>
                    Percentage
                </th>

            </tr>

        </thead>

        <tbody>

        <?php if (!empty($assignments)): ?>

            <?php foreach ($assignments as $index => $assignment): ?>

                <?php

                $assessmentId =
                    (int) $assignment['id'];

                $maxScore =
                    (float) $assignment['max_score'];

                $score =
                    $scores[$assessmentId]['score']
                    ?? null;

                $percentage = null;

                if (
                    $score !== null &&
                    $maxScore > 0
                ) {

                    $percentage =
                        ((float) $score / $maxScore) * 100;
                }

                ?>

                <tr>

                    <td>
                        <?= $index + 1 ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            $assignment['title']
                        ) ?>
                    </td>

                    <td class="right">

                        <?= $score !== null
                            ? number_format(
                                (float) $score,
                                2
                            ) . ' / ' .
                            number_format(
                                $maxScore,
                                2
                            )
                            : '-'
                        ?>

                    </td>

                    <td class="right">

                        <?= $percentage !== null
                            ? number_format(
                                $percentage,
                                2
                            ) . '%'
                            : '-'
                        ?>

                    </td>

                </tr>

            <?php endforeach; ?>

        <?php else: ?>

            <tr>

                <td
                    colspan="4"
                    class="center"
                >
                    No assignments found.
                </td>

            </tr>

        <?php endif; ?>

        </tbody>

    </table>


    <!-- ================================================
         MIDTERM + FINAL
    ================================================= -->

    <h3 style="margin-top: 30px;">
        Examinations
    </h3>

    <table>

        <thead>

            <tr>

                <th>
                    Assessment
                </th>

                <th>
                    Score
                </th>

                <th>
                    Percentage
                </th>

                <th>
                    Weight
                </th>

            </tr>

        </thead>

        <tbody>

            <tr>

                <td>
                    <?= $midterm
                        ? htmlspecialchars(
                            $midterm['title']
                        )
                        : 'Midterm Exam'
                    ?>
                </td>

                <td class="right">

                    <?php if ($midterm && $midtermScore !== null): ?>

                        <?= number_format(
                            (float) $midtermScore,
                            2
                        ) ?>

                        /

                        <?= number_format(
                            (float) $midterm['max_score'],
                            2
                        ) ?>

                    <?php else: ?>

                        -

                    <?php endif; ?>

                </td>

                <td class="right">

                    <?= $midtermPercentage !== null
                        ? number_format(
                            $midtermPercentage,
                            2
                        ) . '%'
                        : '-'
                    ?>

                </td>

                <td class="center">
                    35%
                </td>

            </tr>


            <tr>

                <td>
                    <?= $finalExam
                        ? htmlspecialchars(
                            $finalExam['title']
                        )
                        : 'Final Exam'
                    ?>
                </td>

                <td class="right">

                    <?php if ($finalExam && $finalScore !== null): ?>

                        <?= number_format(
                            (float) $finalScore,
                            2
                        ) ?>

                        /

                        <?= number_format(
                            (float) $finalExam['max_score'],
                            2
                        ) ?>

                    <?php else: ?>

                        -

                    <?php endif; ?>

                </td>

                <td class="right">

                    <?= $finalPercentage !== null
                        ? number_format(
                            $finalPercentage,
                            2
                        ) . '%'
                        : '-'
                    ?>

                </td>

                <td class="center">
                    45%
                </td>

            </tr>

        </tbody>

    </table>


    <!-- ================================================
         SUMMARY
    ================================================= -->

    <div class="summary">

        <div class="summary-row">

            <strong>
                Assignment Average
            </strong>

            <span>

                <?= $assignmentAverage !== null
                    ? number_format(
                        $assignmentAverage,
                        2
                    ) . '%'
                    : '-'
                ?>

                &nbsp;

                <small>
                    (20%)
                </small>

            </span>

        </div>


        <div class="summary-row">

            <strong>
                Midterm
            </strong>

            <span>

                <?= $midtermPercentage !== null
                    ? number_format(
                        $midtermPercentage,
                        2
                    ) . '%'
                    : '-'
                ?>

                &nbsp;

                <small>
                    (35%)
                </small>

            </span>

        </div>


        <div class="summary-row">

            <strong>
                Final Exam
            </strong>

            <span>

                <?= $finalPercentage !== null
                    ? number_format(
                        $finalPercentage,
                        2
                    ) . '%'
                    : '-'
                ?>

                &nbsp;

                <small>
                    (45%)
                </small>

            </span>

        </div>

    </div>


    <!-- ================================================
         FINAL RESULT
    ================================================= -->

    <div class="final-result">

        <div class="final-result-row">

            <div>

                <strong>
                    FINAL SCORE
                </strong>

                <div class="final-score">

                    <?= $overallScore !== null
                        ? number_format(
                            $overallScore,
                            2
                        )
                        : '-'
                    ?>

                </div>

            </div>


            <div style="text-align:right;">

                <strong>
                    GRADE
                </strong>

                <div class="final-grade">

                    <?= htmlspecialchars($grade) ?>

                </div>

            </div>

        </div>

    </div>


    <!-- ================================================
         PRINT BUTTON
    ================================================= -->

    <button
        type="button"
        class="print-button"
        onclick="window.print()"
    >
        🖨 Print Student Result
    </button>


    <!-- ================================================
         FOOTER
    ================================================= -->

    <div class="footer">

        ALAWALAMA TRAINING CENTER

        <br>

        This document is generated from the
        Training Center Management System.

    </div>

</div>

</body>
</html>