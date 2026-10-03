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
// GET BATCH ID
// ======================================================

$batchId = isset($_GET['batch_id'])
    ? (int) $_GET['batch_id']
    : 0;

if ($batchId <= 0) {
    die('Invalid batch.');
}

// ======================================================
// ======================================================
// VERIFY BATCH ACCESS
// ======================================================

if ($role === 'instructor') {

    $userId = (int) $_SESSION['user_id'];

    $stmt = $pdo->prepare("
        SELECT
            i.id AS instructor_id,
            b.id,
            b.batch_code,
            b.start_date,
            b.end_date,
            c.course_name
        FROM batches b
        INNER JOIN courses c
            ON c.id = b.course_id
        INNER JOIN batch_instructors bi
            ON bi.batch_id = b.id
        INNER JOIN instructors i
            ON i.id = bi.instructor_id
        WHERE b.id = ?
          AND i.user_id = ?
        LIMIT 1
    ");

    $stmt->execute([$batchId, $userId]);
    $batch = $stmt->fetch();

    if (!$batch) {
        http_response_code(403);
        die('You are not assigned to this batch.');
    }

} else {

    $stmt = $pdo->prepare("
        SELECT
            b.id,
            b.batch_code,
            b.start_date,
            b.end_date,
            c.course_name
        FROM batches b
        INNER JOIN courses c
            ON c.id = b.course_id
        WHERE b.id = ?
        LIMIT 1
    ");

    $stmt->execute([$batchId]);
    $batch = $stmt->fetch();

    if (!$batch) {
        http_response_code(404);
        die('Batch not found.');
    }
}

// ======================================================
// GET STUDENTS
// ======================================================


// ======================================================

$stmt = $pdo->prepare("
    SELECT
        e.id AS enrollment_id,
        s.student_code,
        s.full_name
    FROM enrollments e
    INNER JOIN students s
        ON s.id = e.student_id
    WHERE e.batch_id = ?
      AND e.status != 'cancelled'
    ORDER BY s.full_name ASC
");

$stmt->execute([$batchId]);

$students = $stmt->fetchAll();

// ======================================================
// GET ASSESSMENTS
// ======================================================

$stmt = $pdo->prepare("
    SELECT
        id,
        title,
        type,
        max_score
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
// SEPARATE ASSESSMENTS
// ======================================================

$assignmentAssessments = [];
$midterm = null;
$finalExam = null;

foreach ($assessments as $assessment) {

    if ($assessment['type'] === 'assignment') {
        $assignmentAssessments[] = $assessment;
    }

    if ($assessment['type'] === 'midterm') {
        $midterm = $assessment;
    }

    if ($assessment['type'] === 'final') {
        $finalExam = $assessment;
    }
}

// ======================================================
// GET SCORES
// ======================================================

$stmt = $pdo->prepare("
    SELECT
        assessment_id,
        enrollment_id,
        score
    FROM assessment_scores
    WHERE assessment_id IN (
        SELECT id
        FROM assessments
        WHERE batch_id = ?
    )
");

$stmt->execute([$batchId]);

$scoreRows = $stmt->fetchAll();

// ======================================================
// SCORE LOOKUP
// ======================================================

$scores = [];

foreach ($scoreRows as $row) {

    $scores[
        (int) $row['enrollment_id']
    ][
        (int) $row['assessment_id']
    ] = $row['score'];
}

// ======================================================
// CALCULATE RESULTS
// ======================================================

$results = [];

foreach ($students as $student) {

    $enrollmentId =
        (int) $student['enrollment_id'];

    // --------------------------------------------------
    // ASSIGNMENTS
    // --------------------------------------------------

    $assignmentPercentages = [];

    foreach ($assignmentAssessments as $assignment) {

        $assessmentId =
            (int) $assignment['id'];

        $maxScore =
            (float) $assignment['max_score'];

        $score =
            $scores[$enrollmentId][$assessmentId]
            ?? null;

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

    // --------------------------------------------------
    // MIDTERM
    // --------------------------------------------------

    $midtermPercentage = null;

    if ($midterm) {

        $assessmentId =
            (int) $midterm['id'];

        $maxScore =
            (float) $midterm['max_score'];

        $score =
            $scores[$enrollmentId][$assessmentId]
            ?? null;

        if (
            $score !== null &&
            $maxScore > 0
        ) {

            $midtermPercentage =
                ((float) $score / $maxScore) * 100;
        }
    }

    // --------------------------------------------------
    // FINAL
    // --------------------------------------------------

    $finalPercentage = null;

    if ($finalExam) {

        $assessmentId =
            (int) $finalExam['id'];

        $maxScore =
            (float) $finalExam['max_score'];

        $score =
            $scores[$enrollmentId][$assessmentId]
            ?? null;

        if (
            $score !== null &&
            $maxScore > 0
        ) {

            $finalPercentage =
                ((float) $score / $maxScore) * 100;
        }
    }

    // --------------------------------------------------
    // FINAL SCORE
    //
    // Assignment = 20%
    // Midterm    = 35%
    // Final      = 45%
    // --------------------------------------------------

    $finalScore = null;

    if (
        $assignmentAverage !== null &&
        $midtermPercentage !== null &&
        $finalPercentage !== null
    ) {

        $finalScore =
            ($assignmentAverage * 0.20)
            +
            ($midtermPercentage * 0.35)
            +
            ($finalPercentage * 0.45);
    }

    // --------------------------------------------------
    // GRADE
    // --------------------------------------------------

    $grade = '-';

    if ($finalScore !== null) {

        if ($finalScore >= 90) {
            $grade = 'A';

        } elseif ($finalScore >= 80) {
            $grade = 'B';

        } elseif ($finalScore >= 70) {
            $grade = 'C';

        } elseif ($finalScore >= 60) {
            $grade = 'D';

        } else {
            $grade = 'F';
        }
    }

    $results[] = [
        'enrollment_id' => $enrollmentId,
        'student_code' => $student['student_code'],
        'full_name' => $student['full_name'],
        'assignment_average' => $assignmentAverage,
        'midterm_percentage' => $midtermPercentage,
        'final_percentage' => $finalPercentage,
        'final_score' => $finalScore,
        'grade' => $grade
    ];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>
        Batch Final Results -
        <?= htmlspecialchars($batch['batch_code']) ?>
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            font-family: Arial, Helvetica, sans-serif;
            color: #111827;
            background: #ffffff;
        }

        .report {
            max-width: 1100px;
            margin: auto;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #111827;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0 0 6px;
            font-size: 24px;
        }

        .header h2 {
            margin: 0 0 6px;
            font-size: 18px;
        }

        .header p {
            margin: 0;
            font-size: 13px;
            color: #6b7280;
        }

        .batch-info {
            margin-bottom: 20px;
            font-size: 13px;
        }

        .batch-info strong {
            color: #111827;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 9px 8px;
            font-size: 12px;
        }

        th {
            background: #f3f4f6;
            font-weight: 700;
            text-align: center;
        }

        td {
            text-align: center;
        }

        td.name {
            text-align: left;
        }

        .final-score {
            font-weight: 700;
        }

        .grade {
            font-weight: 700;
            font-size: 14px;
        }

        .footer {
            margin-top: 35px;
            font-size: 11px;
            color: #6b7280;
            text-align: center;
        }

        .print-button {
            display: block;
            margin: 20px auto;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            background: #2563eb;
            color: #ffffff;
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

            .report {
                max-width: 100%;
            }

            @page {
                size: A4 landscape;
                margin: 12mm;
            }
        }

    </style>

</head>

<body>

<div class="report">

    <!-- ================================================
         HEADER
    ================================================= -->

    <div class="header">

        <h1>
            ALAWALAMA TRAINING CENTER
        </h1>

        <h2>
            Batch Final Results
        </h2>

        <p>
            Student Assessment Report
        </p>

    </div>


    <!-- ================================================
         BATCH INFORMATION
    ================================================= -->

    <div class="batch-info">

        <strong>
            Batch:
        </strong>

        <?= htmlspecialchars(
            $batch['batch_code']
        ) ?>

        &nbsp;&nbsp; | &nbsp;&nbsp;

        <strong>
            Course:
        </strong>

        <?= htmlspecialchars(
            $batch['course_name']
        ) ?>

        &nbsp;&nbsp; | &nbsp;&nbsp;

        <strong>
            Period:
        </strong>

        <?= htmlspecialchars(
            $batch['start_date']
        ) ?>

        -

        <?= htmlspecialchars(
            $batch['end_date']
        ) ?>

    </div>


    <!-- ================================================
         RESULTS TABLE
    ================================================= -->

    <?php if (!empty($results)): ?>

        <table>

            <thead>

                <tr>

                    <th>
                        No.
                    </th>

                    <th>
                        Student ID
                    </th>

                    <th>
                        Student Name
                    </th>

                    <th>
                        Assignments<br>(20%)
                    </th>

                    <th>
                        Midterm<br>(35%)
                    </th>

                    <th>
                        Final<br>(45%)
                    </th>

                    <th>
                        Final Score
                    </th>

                    <th>
                        Grade
                    </th>

                </tr>

            </thead>

            <tbody>

            <?php foreach ($results as $index => $result): ?>

                <tr>

                    <td>
                        <?= $index + 1 ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            $result['student_code']
                        ) ?>
                    </td>

                    <td class="name">
                        <?= htmlspecialchars(
                            $result['full_name']
                        ) ?>
                    </td>

                    <td>

                        <?= $result['assignment_average'] !== null
                            ? number_format(
                                $result['assignment_average'],
                                2
                            ) . '%'
                            : '-'
                        ?>

                    </td>

                    <td>

                        <?= $result['midterm_percentage'] !== null
                            ? number_format(
                                $result['midterm_percentage'],
                                2
                            ) . '%'
                            : '-'
                        ?>

                    </td>

                    <td>

                        <?= $result['final_percentage'] !== null
                            ? number_format(
                                $result['final_percentage'],
                                2
                            ) . '%'
                            : '-'
                        ?>

                    </td>

                    <td class="final-score">

                        <?= $result['final_score'] !== null
                            ? number_format(
                                $result['final_score'],
                                2
                            )
                            : '-'
                        ?>

                    </td>

                    <td class="grade">

                        <?= htmlspecialchars(
                            $result['grade']
                        ) ?>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    <?php else: ?>

        <p>
            No students are enrolled in this batch.
        </p>

    <?php endif; ?>


    <!-- ================================================
         PRINT
    ================================================= -->

    <button
        type="button"
        class="print-button"
        onclick="window.print()"
    >
        🖨 Print All Students
    </button>


    <!-- ================================================
         FOOTER
    ================================================= -->

    <div class="footer">

        ALAWALAMA TRAINING CENTER

        <br>

        Assessment weights:
        Assignments 20% |
        Midterm 35% |
        Final Exam 45%

    </div>

</div>

</body>

</html>