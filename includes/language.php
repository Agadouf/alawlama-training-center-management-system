<?php

// ========================================
// ALAWALAMA LANGUAGE SYSTEM
// ========================================


// ========================================
// START SESSION
// ========================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ========================================
// DEFAULT LANGUAGE
// ========================================

if (!isset($_SESSION['language'])) {

    $_SESSION['language'] = 'en';

}


// ========================================
// CHANGE LANGUAGE
// ========================================

if (isset($_GET['lang'])) {

    $requestedLanguage = strtolower(
        trim($_GET['lang'])
    );


    if (
        in_array(
            $requestedLanguage,
            ['en', 'ar'],
            true
        )
    ) {

        $_SESSION['language'] =
            $requestedLanguage;

    }

}


// ========================================
// CURRENT LANGUAGE
// ========================================

$lang = $_SESSION['language'];


// ========================================
// TEXT DIRECTION
// ========================================

$dir = ($lang === 'ar')
    ? 'rtl'
    : 'ltr';


// ========================================
// TRANSLATIONS
// ========================================

$translations = [

    // ====================================
    // ENGLISH
    // ====================================

    'en' => [

        // -------------------------------
        // GENERAL
        // -------------------------------

        'app_name' =>
            'ALAWALAMA TRAINING CENTER',

        'dashboard' =>
            'Dashboard',

        'students' =>
            'Students',

        'instructors' =>
            'Instructors',

        'courses' =>
            'Courses',

        'batches' =>
            'Batches',

        'enrollments' =>
            'Enrollments',

        'payments' =>
            'Payments',

        'attendance' =>
            'Attendance',

        'assessments' =>
            'Assessments',

        'grades_results' =>
            'Grades / Results',

        'reports' =>
            'Reports',

        'manage_users' =>
            'Manage Users',

        'change_password' =>
            'Change Password',

        'logout' =>
            'Logout',


        // -------------------------------
        // COMMON
        // -------------------------------

        'search' =>
            'Search',

        'clear' =>
            'Clear',

        'add' =>
            'Add',

        'edit' =>
            'Edit',

        'delete' =>
            'Delete',

        'save' =>
            'Save',

        'cancel' =>
            'Cancel',

        'update' =>
            'Update',

        'view' =>
            'View',

        'print' =>
            'Print',

        'back' =>
            'Back',

        'actions' =>
            'Actions',

        'status' =>
            'Status',

        'date' =>
            'Date',

        'name' =>
            'Name',

        'phone' =>
            'Phone',

        'email' =>
            'Email',

        'address' =>
            'Address',

        'notes' =>
            'Notes',


        // -------------------------------
        // STUDENTS
        // -------------------------------

        'student_list' =>
            'Student List',

        'add_student' =>
            'Add Student',

        'student_id' =>
            'Student ID',

        'full_name' =>
            'Full Name',

        'gender' =>
            'Gender',

        'male' =>
            'Male',

        'female' =>
            'Female',

        'date_of_birth' =>
            'Date of Birth',

        'guardian_name' =>
            'Guardian Name',

        'guardian_phone' =>
            'Guardian Phone',

        'registration_date' =>
            'Registration Date',

        'registration_from' =>
            'Registration From',

        'registration_to' =>
            'Registration To',

        'all_genders' =>
            'All Genders',

        'all_statuses' =>
            'All Statuses',


        // -------------------------------
        // STUDENT STATUS
        // -------------------------------

        'active' =>
            'Active',

        'suspended' =>
            'Suspended',

        'inactive' =>
            'Inactive',

        'graduated' =>
            'Graduated',


        // -------------------------------
        // COURSES
        // -------------------------------

        'course' =>
            'Course',

        'course_code' =>
            'Course Code',

        'course_name' =>
            'Course Name',

        'category' =>
            'Category',

        'level' =>
            'Level',

        'description' =>
            'Description',

        'default_fee' =>
            'Default Fee',

        'duration' =>
            'Duration',


        // -------------------------------
        // BATCHES
        // -------------------------------

        'batch' =>
            'Batch',

        'batch_code' =>
            'Batch Code',

        'start_date' =>
            'Start Date',

        'end_date' =>
            'End Date',

        'room' =>
            'Room',

        'capacity' =>
            'Capacity',

        'instructor' =>
            'Instructor',


        // -------------------------------
        // ENROLLMENTS
        // -------------------------------

        'enrollment' =>
            'Enrollment',

        'enrollment_date' =>
            'Enrollment Date',

        'original_fee' =>
            'Original Fee',

        'discount' =>
            'Discount',

        'actual_fee' =>
            'Actual Fee',

        'enrollment_status' =>
            'Enrollment Status',


        // -------------------------------
        // PAYMENTS
        // -------------------------------

        'payment' =>
            'Payment',

        'payment_date' =>
            'Payment Date',

        'payment_method' =>
            'Payment Method',

        'amount' =>
            'Amount',

        'receipt_number' =>
            'Receipt Number',

        'total_paid' =>
            'Total Paid',

        'remaining_balance' =>
            'Remaining Balance',

        'cash' =>
            'Cash',

        'bank_transfer' =>
            'Bank Transfer',

        'unpaid' =>
            'Unpaid',

        'partially_paid' =>
            'Partially Paid',

        'paid' =>
            'Paid',


        // -------------------------------
        // ATTENDANCE
        // -------------------------------

        'attendance_date' =>
            'Attendance Date',

        'present' =>
            'Present',

        'absent' =>
            'Absent',


        // -------------------------------
        // ASSESSMENTS
        // -------------------------------

        'assignment' =>
            'Assignment',

        'midterm' =>
            'Midterm',

        'final' =>
            'Final',

        'score' =>
            'Score',

        'max_score' =>
            'Maximum Score',

        'due_date' =>
            'Due Date',

        'overall_grade' =>
            'Overall Grade',


        // -------------------------------
        // REPORTS
        // -------------------------------

        'student_report' =>
            'Student Report',

        'enrollment_report' =>
            'Enrollment Report',

        'payment_report' =>
            'Payment Report',

        'attendance_report' =>
            'Attendance Report',

        'grade_report' =>
            'Grade Report',


        // -------------------------------
        // DASHBOARD
        // -------------------------------

        'total_students' =>
            'Total Students',

        'total_instructors' =>
            'Total Instructors',

        'total_courses' =>
            'Total Courses',

        'active_batches' =>
            'Active Batches',

        'total_enrollments' =>
            'Total Enrollments',

        'total_payments' =>
            'Total Payments',

        'outstanding_balances' =>
            'Outstanding Balances',

        'recent_enrollments' =>
            'Recent Enrollments',

        'recent_payments' =>
            'Recent Payments',


        // -------------------------------
        // AUTHENTICATION
        // -------------------------------

        'login' =>
            'Login',

        'username' =>
            'Username',

        'password' =>
            'Password',

        'current_password' =>
            'Current Password',

        'new_password' =>
            'New Password',

        'confirm_password' =>
            'Confirm Password',


        // -------------------------------
        // MESSAGES
        // -------------------------------

        'no_students_found' =>
            'No students found.',

        'no_matching_students' =>
            'No students match your search or filters.',

        'showing' =>
            'Showing',

        'student_count' =>
            'student(s)',

        // -------------------------------
        // SIDEBAR / NAVIGATION
        // -------------------------------
        'management' => 'Management',
        'administration' => 'Administration',
        'account' => 'Account',

        // -------------------------------
        // ASSESSMENTS PAGE
        // -------------------------------
        'assessments_page_description' =>
            'Manage assignments, midterm exams, final exams, and student scores for your assigned batches.',
        'select_batch' => 'Select Batch',
        'load_batch' => 'Load Batch',
        'no_batches' => 'No batches are currently assigned to you.',
        'assignments' => 'Assignments',
        'assignments_description' =>
            'Create and manage assignments and enter student scores.',
        'final_results' => 'Final Results',
        'add_assignment' => 'Add Assignment',
        'assignment_title' => 'Assignment Title',
        'max_score_short' => 'Max Score',
        'exam_date' => 'Exam Date',
        'enter_scores' => 'Enter Scores',
        'remove' => 'Remove',
        'remove_assignment_confirm' =>
            'Are you sure you want to remove this assignment?',
        'no_assignments' => 'No assignments have been added yet.',
        'save_all_assignments' => 'Save All Assignments',
        'midterm_exam' => 'Midterm Exam',
        'midterm_description' =>
            'Manage the midterm examination and student scores.',
        'save_midterm' => 'Save Midterm',
        'final_exam' => 'Final Exam',
        'final_description' =>
            'Manage the final examination and student scores.',
        'save_final' => 'Save Final',
        'no_assigned_batches' =>
            'You do not have any assigned batches yet.',
        'course' => 'Course',
        'start_date' => 'Start Date',
        'end_date' => 'End Date',
        'title' => 'Title',
        'optional_description' => 'Optional description',
        'assignments_batch_description' => 'Create and manage multiple assignments for this batch.',
        'midterm_batch_description' => 'Create one midterm exam for this batch.',
        'final_batch_description' => 'Create one final exam for this batch.',
        'no_assigned_batches_title' => 'No Assigned Batches',
        'no_assigned_batches_description' => 'You currently do not have any batches assigned to you. Please contact the administrator.',
        'logout_confirm' => 'Are you sure you want to logout?',


        // -------------------------------
        // FINAL RESULTS
        // -------------------------------
        'batch_final_results' => 'Batch Final Results',
        'student_assessment_report' => 'Student Assessment Report',
        'period' => 'Period',
        'student_name' => 'Student Name',
        'final_score' => 'Final Score',
        'print_all_students' => 'Print All Students',
        'assessment_weights' => 'Assessment Weights',
        'assignments_weight' => 'Assignments',
        'midterm_weight' => 'Midterm',
        'final_exam_weight' => 'Final Exam',
        'no_students_enrolled' => 'No students are enrolled in this batch.',
        'access_denied' => 'Access denied.',
        'invalid_batch' => 'Invalid batch.',
        'not_assigned_batch' => 'You are not assigned to this batch.',
        'batch_not_found' => 'Batch not found.',

    ],


    // ====================================
    // ARABIC
    // ====================================

    'ar' => [

        // -------------------------------
        // GENERAL
        // -------------------------------

        'app_name' =>
            'مركز العلوامة للتدريب',

        'dashboard' =>
            'لوحة التحكم',

        'students' =>
            'الطلاب',

        'instructors' =>
            'المدربون',

        'courses' =>
            'الدورات',

        'batches' =>
            'الفصول',

        'enrollments' =>
            'التسجيلات',

        'payments' =>
            'المدفوعات',

        'attendance' =>
            'الحضور',

        'assessments' =>
            'التقييمات',

        'grades_results' =>
            'الدرجات والنتائج',

        'reports' =>
            'التقارير',

        'manage_users' =>
            'إدارة المستخدمين',

        'change_password' =>
            'تغيير كلمة المرور',

        'logout' =>
            'تسجيل الخروج',


        // -------------------------------
        // COMMON
        // -------------------------------

        'search' =>
            'بحث',

        'clear' =>
            'مسح',

        'add' =>
            'إضافة',

        'edit' =>
            'تعديل',

        'delete' =>
            'حذف',

        'save' =>
            'حفظ',

        'cancel' =>
            'إلغاء',

        'update' =>
            'تحديث',

        'view' =>
            'عرض',

        'print' =>
            'طباعة',

        'back' =>
            'رجوع',

        'actions' =>
            'الإجراءات',

        'status' =>
            'الحالة',

        'date' =>
            'التاريخ',

        'name' =>
            'الاسم',

        'phone' =>
            'الهاتف',

        'email' =>
            'البريد الإلكتروني',

        'address' =>
            'العنوان',

        'notes' =>
            'ملاحظات',


        // -------------------------------
        // STUDENTS
        // -------------------------------

        'student_list' =>
            'قائمة الطلاب',

        'add_student' =>
            'إضافة طالب',

        'student_id' =>
            'رقم الطالب',

        'full_name' =>
            'الاسم الكامل',

        'gender' =>
            'الجنس',

        'male' =>
            'ذكر',

        'female' =>
            'أنثى',

        'date_of_birth' =>
            'تاريخ الميلاد',

        'guardian_name' =>
            'اسم ولي الأمر',

        'guardian_phone' =>
            'هاتف ولي الأمر',

        'registration_date' =>
            'تاريخ التسجيل',

        'registration_from' =>
            'التسجيل من',

        'registration_to' =>
            'التسجيل إلى',

        'all_genders' =>
            'كل أنواع الجنس',

        'all_statuses' =>
            'كل الحالات',


        // -------------------------------
        // STUDENT STATUS
        // -------------------------------

        'active' =>
            'نشط',

        'suspended' =>
            'موقوف',

        'inactive' =>
            'غير نشط',

        'graduated' =>
            'متخرج',


        // -------------------------------
        // COURSES
        // -------------------------------

        'course' =>
            'الدورة',

        'course_code' =>
            'رمز الدورة',

        'course_name' =>
            'اسم الدورة',

        'category' =>
            'التصنيف',

        'level' =>
            'المستوى',

        'description' =>
            'الوصف',

        'default_fee' =>
            'الرسوم الأساسية',

        'duration' =>
            'المدة',


        // -------------------------------
        // BATCHES
        // -------------------------------

        'batch' =>
            'الفصل',

        'batch_code' =>
            'رمز الفصل',

        'start_date' =>
            'تاريخ البداية',

        'end_date' =>
            'تاريخ النهاية',

        'room' =>
            'القاعة',

        'capacity' =>
            'السعة',

        'instructor' =>
            'المدرب',


        // -------------------------------
        // ENROLLMENTS
        // -------------------------------

        'enrollment' =>
            'التسجيل',

        'enrollment_date' =>
            'تاريخ التسجيل',

        'original_fee' =>
            'الرسوم الأصلية',

        'discount' =>
            'الخصم',

        'actual_fee' =>
            'الرسوم الفعلية',

        'enrollment_status' =>
            'حالة التسجيل',


        // -------------------------------
        // PAYMENTS
        // -------------------------------

        'payment' =>
            'الدفع',

        'payment_date' =>
            'تاريخ الدفع',

        'payment_method' =>
            'طريقة الدفع',

        'amount' =>
            'المبلغ',

        'receipt_number' =>
            'رقم الإيصال',

        'total_paid' =>
            'إجمالي المدفوع',

        'remaining_balance' =>
            'المبلغ المتبقي',

        'cash' =>
            'نقدًا',

        'bank_transfer' =>
            'تحويل بنكي',

        'unpaid' =>
            'غير مدفوع',

        'partially_paid' =>
            'مدفوع جزئيًا',

        'paid' =>
            'مدفوع',


        // -------------------------------
        // ATTENDANCE
        // -------------------------------

        'attendance_date' =>
            'تاريخ الحضور',

        'present' =>
            'حاضر',

        'absent' =>
            'غائب',


        // -------------------------------
        // ASSESSMENTS
        // -------------------------------

        'assignment' =>
            'الواجب',

        'midterm' =>
            'الاختبار النصفي',

        'final' =>
            'الاختبار النهائي',

        'score' =>
            'الدرجة',

        'max_score' =>
            'الدرجة القصوى',

        'due_date' =>
            'تاريخ التسليم',

        'overall_grade' =>
            'الدرجة النهائية',


        // -------------------------------
        // REPORTS
        // -------------------------------

        'student_report' =>
            'تقرير الطلاب',

        'enrollment_report' =>
            'تقرير التسجيلات',

        'payment_report' =>
            'تقرير المدفوعات',

        'attendance_report' =>
            'تقرير الحضور',

        'grade_report' =>
            'تقرير الدرجات',


        // -------------------------------
        // DASHBOARD
        // -------------------------------

        'total_students' =>
            'إجمالي الطلاب',

        'total_instructors' =>
            'إجمالي المدربين',

        'total_courses' =>
            'إجمالي الدورات',

        'active_batches' =>
            'الفصول النشطة',

        'total_enrollments' =>
            'إجمالي التسجيلات',

        'total_payments' =>
            'إجمالي المدفوعات',

        'outstanding_balances' =>
            'الأرصدة المستحقة',

        'recent_enrollments' =>
            'أحدث التسجيلات',

        'recent_payments' =>
            'أحدث المدفوعات',


        // -------------------------------
        // AUTHENTICATION
        // -------------------------------

        'login' =>
            'تسجيل الدخول',

        'username' =>
            'اسم المستخدم',

        'password' =>
            'كلمة المرور',

        'current_password' =>
            'كلمة المرور الحالية',

        'new_password' =>
            'كلمة المرور الجديدة',

        'confirm_password' =>
            'تأكيد كلمة المرور',


        // -------------------------------
        // MESSAGES
        // -------------------------------

        'no_students_found' =>
            'لم يتم العثور على طلاب.',

        'no_matching_students' =>
            'لا يوجد طلاب مطابقون للبحث أو الفلاتر.',

        'showing' =>
            'عرض',

        'student_count' =>
            'طالب',

        // -------------------------------
        // SIDEBAR / NAVIGATION
        // -------------------------------
        'management' => 'الإدارة',
        'administration' => 'إدارة النظام',
        'account' => 'الحساب',

        // -------------------------------
        // ASSESSMENTS PAGE
        // -------------------------------
        'assessments_page_description' =>
            'إدارة الواجبات والاختبارات النصفية والنهائية ودرجات الطلاب في الفصول المخصصة لك.',
        'select_batch' => 'اختر الفصل',
        'load_batch' => 'تحميل الفصل',
        'no_batches' => 'لا توجد فصول مخصصة لك حاليًا.',
        'assignments' => 'الواجبات',
        'assignments_description' =>
            'إنشاء وإدارة الواجبات وإدخال درجات الطلاب.',
        'final_results' => 'النتائج النهائية',
        'add_assignment' => 'إضافة واجب',
        'assignment_title' => 'عنوان الواجب',
        'max_score_short' => 'الدرجة القصوى',
        'exam_date' => 'تاريخ الاختبار',
        'enter_scores' => 'إدخال الدرجات',
        'remove' => 'إزالة',
        'remove_assignment_confirm' =>
            'هل أنت متأكد من أنك تريد إزالة هذا الواجب؟',
        'no_assignments' => 'لم تتم إضافة أي واجبات بعد.',
        'save_all_assignments' => 'حفظ جميع الواجبات',
        'midterm_exam' => 'الاختبار النصفي',
        'midterm_description' =>
            'إدارة الاختبار النصفي ودرجات الطلاب.',
        'save_midterm' => 'حفظ الاختبار النصفي',
        'final_exam' => 'الاختبار النهائي',
        'final_description' =>
            'إدارة الاختبار النهائي ودرجات الطلاب.',
        'save_final' => 'حفظ الاختبار النهائي',
        'no_assigned_batches' =>
            'لا توجد فصول مخصصة لك حاليًا.',
        'course' => 'الدورة',
        'start_date' => 'تاريخ البداية',
        'end_date' => 'تاريخ النهاية',
        'logout_confirm' => 'هل أنت متأكد أنك تريد تسجيل الخروج؟',


        // -------------------------------
        // FINAL RESULTS
        // -------------------------------
        'batch_final_results' => 'النتائج النهائية للفصل',
        'student_assessment_report' => 'تقرير تقييم الطلاب',
        'period' => 'الفترة',
        'student_name' => 'اسم الطالب',
        'final_score' => 'الدرجة النهائية',
        'print_all_students' => 'طباعة جميع الطلاب',
        'assessment_weights' => 'أوزان التقييم',
        'assignments_weight' => 'الواجبات',
        'midterm_weight' => 'الاختبار النصفي',
        'final_exam_weight' => 'الاختبار النهائي',
        'no_students_enrolled' => 'لا يوجد طلاب مسجلون في هذا الفصل.',
        'access_denied' => 'غير مسموح بالوصول.',
        'invalid_batch' => 'الفصل غير صالح.',
        'not_assigned_batch' => 'أنت غير مكلّف بهذا الفصل.',
        'batch_not_found' => 'لم يتم العثور على الفصل.',

    ],

];


// ========================================
// TRANSLATION FUNCTION
// ========================================

function __($key)
{

    global $translations, $lang;


    // Current language
    if (
        isset(
            $translations[$lang][$key]
        )
    ) {

        return $translations[$lang][$key];

    }


    // Fallback to English
    if (
        isset(
            $translations['en'][$key]
        )
    ) {

        return $translations['en'][$key];

    }


    // If translation does not exist
    return $key;

}