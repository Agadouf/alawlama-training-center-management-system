<?php

// Language system
require_once __DIR__ . '/language.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? '';

// Build a clean URL for language switching without duplicate lang parameters
$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: $_SERVER['PHP_SELF'];
$queryParams = [];
parse_str(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY) ?? '', $queryParams);
unset($queryParams['lang']);
$currentQuery = http_build_query($queryParams);
$currentUrl = $requestPath . ($currentQuery !== '' ? '?' . $currentQuery . '&' : '?');

?>

<style>

html, body {
    margin: 0;
    padding: 0;
    width: 100%;
    max-width: 100%;
    overflow-x: hidden;
}

* {
    box-sizing: border-box;
}

/* ========================================
   SIDEBAR
======================================== */

.sidebar {
    width: 250px;
    height: 100vh;
    background: #111827;
    color: white;
    position: fixed;
    left: 0;
    top: 0;
    padding: 18px 14px;
    overflow-y: auto;
    overflow-x: hidden;
    z-index: 1000;
}

/* ========================================
   LOGO
======================================== */

.sidebar-logo {
    padding: 8px 10px 15px;
    margin-bottom: 18px;
    border-bottom: 1px solid #374151;
}

.sidebar-logo h2 {
    margin: 0;
    font-size: 16px;
    line-height: 1.3;
}

.sidebar-logo p {
    margin: 5px 0 0;
    color: #9ca3af;
    font-size: 10px;
}

/* ========================================
   MENU TITLE
======================================== */

.menu-title {
    font-size: 10px;
    color: #9ca3af;
    text-transform: uppercase;
    margin: 18px 9px 7px;
    font-weight: bold;
    letter-spacing: .5px;
}

/* ========================================
   LINKS
======================================== */

.sidebar a {
    display: block;
    color: #d1d5db;
    text-decoration: none;
    padding: 8px 10px;
    margin: 2px 0;
    border-radius: 5px;
    font-size: 13px;
    transition: .15s;
}

.sidebar a:hover {
    background: #1f2937;
    color: white;
}

.sidebar a.active {
    background: #2563eb;
    color: white;
}

/* ========================================
   LANGUAGE SWITCHER
======================================== */
.language-switcher {
    display: flex;
    gap: 6px;
    margin: 0 0 18px;
    padding: 0 2px;
}

.language-switcher a {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 7px 5px;
    margin: 0;
    border: 1px solid #374151;
    background: #1f2937;
    color: #d1d5db;
    border-radius: 5px;
    font-size: 12px;
    font-weight: bold;
    text-align: center;
}

.language-switcher a:hover {
    background: #374151;
    color: white;
}

.language-switcher a.active {
    background: #2563eb;
    border-color: #2563eb;
    color: white;
}

/* ========================================
   LOGOUT
======================================== */

.sidebar .logout {
    margin-top: 18px;
    border-top: 1px solid #374151;
    padding-top: 12px;
    color: #fca5a5;
}

.sidebar .logout:hover {
    background: #7f1d1d;
    color: white;
}

/* ========================================
   MAIN CONTENT
======================================== */

.main-content {
    margin-left: 250px;
    margin-right: 0;
    width: calc(100% - 250px);
    min-height: 100vh;
    box-sizing: border-box;
    overflow-x: hidden;
}

/* ========================================
   RTL SUPPORT
======================================== */
<?php if ($lang === 'ar'): ?>
.sidebar {
    left: auto;
    right: 0;
    direction: rtl;
}

.main-content {
    margin-left: 0;
    margin-right: 250px;
    width: calc(100% - 250px);
    direction: rtl;
}

.sidebar-logo,
.menu-title,
.sidebar a {
    text-align: right;
}

.language-switcher {
    direction: rtl;
}
<?php endif; ?>

/* ========================================
   MOBILE
======================================== */

@media (max-width: 768px) {

    .sidebar {
        width: 210px;
    }

    .main-content {
        margin-left: 210px;
        margin-right: 0;
        width: calc(100% - 210px);
    }

    <?php if ($lang === 'ar'): ?>
    .sidebar {
        left: auto;
        right: 0;
    }

    .main-content {
        margin-left: 0;
        margin-right: 210px;
        width: calc(100% - 210px);
    }
    <?php endif; ?>

}

</style>


<!-- ========================================
     SIDEBAR
======================================== -->

<aside class="sidebar">

    <!-- ====================================
         LOGO
    ==================================== -->

    <div class="sidebar-logo">

        <h2>
            ALAW LAMA
        </h2>

        <p >
            <?= __('app_name') ?>
        </p>

    </div>


    <!-- ====================================
         LANGUAGE SWITCHER
    ==================================== -->

    <div class="language-switcher">
        <a
            href="<?= htmlspecialchars($currentUrl) ?>lang=en"
            class="<?= $lang === 'en' ? 'active' : '' ?>"
        >
            English
        </a>

        <a
            href="<?= htmlspecialchars($currentUrl) ?>lang=ar"
            class="<?= $lang === 'ar' ? 'active' : '' ?>"
        >
            العربية
        </a>
    </div>


    <!-- ====================================
         MAIN
    ==================================== -->

    <div class="menu-title" >
        <?= $lang === 'ar' ? 'الرئيسية' : 'Main' ?>
    </div>


    <a
        href="../dashboard/index.php"
        class="<?= $currentPage === 'index.php'
            && strpos($_SERVER['REQUEST_URI'], '/dashboard/') !== false
            ? 'active'
            : '' ?>"
     >
        <?= __('dashboard') ?>
    </a>


    <!-- ====================================
         MANAGEMENT
    ==================================== -->

    <div class="menu-title" >
        <?= $lang === 'ar' ? 'الإدارة' : 'Management' ?>
    </div>


    <!-- ====================================
         STUDENTS
    ==================================== -->

    <?php if (
        $role === 'admin' ||
        $role === 'staff'
    ): ?>

        <a
            href="../students/index.php"
            class="<?= strpos(
                $_SERVER['REQUEST_URI'],
                '/students/'
            ) !== false ? 'active' : '' ?>"
         >
            <?= __('students') ?>
        </a>

    <?php endif; ?>


    <!-- ====================================
         INSTRUCTORS
    ==================================== -->

    <?php if (
        $role === 'admin' ||
        $role === 'staff'
    ): ?>

        <a
            href="../instructors/index.php"
            class="<?= strpos(
                $_SERVER['REQUEST_URI'],
                '/instructors/'
            ) !== false ? 'active' : '' ?>"
         >
            <?= __('instructors') ?>
        </a>

    <?php endif; ?>


    <!-- ====================================
         COURSES / BATCHES / ENROLLMENTS /
         PAYMENTS
    ==================================== -->

    <?php if (
        $role === 'admin' ||
        $role === 'staff'
    ): ?>

        <a
            href="../courses/index.php"
            class="<?= strpos(
                $_SERVER['REQUEST_URI'],
                '/courses/'
            ) !== false ? 'active' : '' ?>"
         >
            <?= __('courses') ?>
        </a>


        <a
            href="../batches/index.php"
            class="<?= strpos(
                $_SERVER['REQUEST_URI'],
                '/batches/'
            ) !== false ? 'active' : '' ?>"
         >
            <?= __('batches') ?>
        </a>


        <a
            href="../enrollments/index.php"
            class="<?= strpos(
                $_SERVER['REQUEST_URI'],
                '/enrollments/'
            ) !== false ? 'active' : '' ?>"
         >
            <?= __('enrollments') ?>
        </a>


        <a
            href="../payments/index.php"
            class="<?= strpos(
                $_SERVER['REQUEST_URI'],
                '/payments/'
            ) !== false ? 'active' : '' ?>"
         >
            <?= __('payments') ?>
        </a>

    <?php endif; ?>


    <!-- ====================================
         ATTENDANCE
         Admin/Staff = View
         Instructor = View + Manage
    ==================================== -->

    <?php if (
        $role === 'admin' ||
        $role === 'staff' ||
        $role === 'instructor'
    ): ?>

        <a
            href="../attendance/index.php"
            class="<?= strpos(
                $_SERVER['REQUEST_URI'],
                '/attendance/'
            ) !== false ? 'active' : '' ?>"
         >
            <?= __('attendance') ?>
        </a>

    <?php endif; ?>


    <!-- ====================================
         ASSESSMENTS
         Instructor = Manage
    ==================================== -->

    <?php if ($role === 'instructor'): ?>

        <a
            href="../assessments/index.php"
            class="<?= strpos(
                $_SERVER['REQUEST_URI'],
                '/assessments/'
            ) !== false ? 'active' : '' ?>"
         >
            <?= __('assessments') ?>
        </a>

    <?php endif; ?>


    <!-- ====================================
         GRADES / RESULTS
         Admin/Staff = View
         Instructor = View + Manage
    ==================================== -->

    <?php if (
        $role === 'admin' ||
        $role === 'staff' ||
        $role === 'instructor'
    ): ?>

        <a
            href="../assessments/results.php"
            class="<?= strpos(
                $_SERVER['REQUEST_URI'],
                '/assessments/results.php'
            ) !== false ? 'active' : '' ?>"
         >
            <?= __('grades_results') ?>
        </a>

    <?php endif; ?>


    <!-- ====================================
         CERTIFICATES
         Admin/Staff = Manage/View
    ==================================== -->

    <?php if (
        $role === 'admin' ||
        $role === 'staff'
    ): ?>

        <a
            href="../certificates/index.php"
            class="<?= strpos(
                $_SERVER['REQUEST_URI'],
                '/certificates/'
            ) !== false ? 'active' : '' ?>"
        >
            <?= $lang === 'ar' ? 'الشهادات' : 'Certificates' ?>
        </a>

    <?php endif; ?>


    <!-- ====================================
         REPORTS
    ==================================== -->

    <?php if (
        $role === 'admin' ||
        $role === 'staff' ||
        $role === 'instructor'
    ): ?>

        <div class="menu-title" >
            <?= __('reports') ?>
        </div>


        <a
            href="../reports/index.php"
            class="<?= strpos(
                $_SERVER['REQUEST_URI'],
                '/reports/'
            ) !== false ? 'active' : '' ?>"
         >
            <?= __('reports') ?>
        </a>

    <?php endif; ?>


    <!-- ====================================
         ADMINISTRATION
    ==================================== -->

    <?php if ($role === 'admin'): ?>

        <div class="menu-title" >
            <?= $lang === 'ar' ? 'إدارة النظام' : 'Administration' ?>
        </div>


        <a
            href="../users/index.php"
            class="<?= strpos(
                $_SERVER['REQUEST_URI'],
                '/users/'
            ) !== false ? 'active' : '' ?>"
         >
            <?= __('manage_users') ?>
        </a>

    <?php endif; ?>


    <!-- ====================================
         ACCOUNT
    ==================================== -->

    <div class="menu-title" >
        <?= $lang === 'ar' ? 'الحساب' : 'Account' ?>
    </div>


    <a
        href="../auth/change-password.php"
        class="<?= strpos(
            $_SERVER['REQUEST_URI'],
            '/auth/change-password.php'
        ) !== false ? 'active' : '' ?>"
     >
        <?= __('change_password') ?>
    </a>


    <a
        href="../../logout.php"
        class="logout"
        onclick="return confirm('Are you sure you want to logout?');"
     >
        <?= __('logout') ?>
    </a>

</aside>


<!-- ========================================
     MAIN CONTENT
======================================== -->

<div class="main-content">
