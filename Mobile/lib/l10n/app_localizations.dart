import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_ar.dart';
import 'app_localizations_en.dart';
import 'app_localizations_fr.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'l10n/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale)
    : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations? of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations);
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
        delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[
    Locale('ar'),
    Locale('en'),
    Locale('fr'),
  ];

  /// No description provided for @appTitle.
  ///
  /// In en, this message translates to:
  /// **'SchoolHub'**
  String get appTitle;

  /// No description provided for @login.
  ///
  /// In en, this message translates to:
  /// **'Login'**
  String get login;

  /// No description provided for @username.
  ///
  /// In en, this message translates to:
  /// **'Username'**
  String get username;

  /// No description provided for @phone.
  ///
  /// In en, this message translates to:
  /// **'Phone Number'**
  String get phone;

  /// No description provided for @password.
  ///
  /// In en, this message translates to:
  /// **'Password'**
  String get password;

  /// No description provided for @usernameOrPhone.
  ///
  /// In en, this message translates to:
  /// **'Username or Phone'**
  String get usernameOrPhone;

  /// No description provided for @forgotPassword.
  ///
  /// In en, this message translates to:
  /// **'Forgot Password?'**
  String get forgotPassword;

  /// No description provided for @rememberMe.
  ///
  /// In en, this message translates to:
  /// **'Remember me'**
  String get rememberMe;

  /// No description provided for @loginButton.
  ///
  /// In en, this message translates to:
  /// **'Sign In'**
  String get loginButton;

  /// No description provided for @loginSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Sign in to access your school workspace'**
  String get loginSubtitle;

  /// No description provided for @noAccount.
  ///
  /// In en, this message translates to:
  /// **'Contact school administration for account access'**
  String get noAccount;

  /// No description provided for @myChildren.
  ///
  /// In en, this message translates to:
  /// **'My Children'**
  String get myChildren;

  /// No description provided for @grades.
  ///
  /// In en, this message translates to:
  /// **'Grades'**
  String get grades;

  /// No description provided for @attendance.
  ///
  /// In en, this message translates to:
  /// **'Attendance'**
  String get attendance;

  /// No description provided for @schedule.
  ///
  /// In en, this message translates to:
  /// **'Schedule'**
  String get schedule;

  /// No description provided for @payments.
  ///
  /// In en, this message translates to:
  /// **'Payments'**
  String get payments;

  /// No description provided for @more.
  ///
  /// In en, this message translates to:
  /// **'More'**
  String get more;

  /// No description provided for @settings.
  ///
  /// In en, this message translates to:
  /// **'Settings'**
  String get settings;

  /// No description provided for @logout.
  ///
  /// In en, this message translates to:
  /// **'Logout'**
  String get logout;

  /// No description provided for @logoutConfirm.
  ///
  /// In en, this message translates to:
  /// **'Are you sure you want to logout?'**
  String get logoutConfirm;

  /// No description provided for @cancel.
  ///
  /// In en, this message translates to:
  /// **'Cancel'**
  String get cancel;

  /// No description provided for @confirm.
  ///
  /// In en, this message translates to:
  /// **'Confirm'**
  String get confirm;

  /// No description provided for @yes.
  ///
  /// In en, this message translates to:
  /// **'Yes'**
  String get yes;

  /// No description provided for @no.
  ///
  /// In en, this message translates to:
  /// **'No'**
  String get no;

  /// No description provided for @ok.
  ///
  /// In en, this message translates to:
  /// **'OK'**
  String get ok;

  /// No description provided for @error.
  ///
  /// In en, this message translates to:
  /// **'Error'**
  String get error;

  /// No description provided for @retry.
  ///
  /// In en, this message translates to:
  /// **'Retry'**
  String get retry;

  /// No description provided for @noData.
  ///
  /// In en, this message translates to:
  /// **'No data available'**
  String get noData;

  /// No description provided for @loading.
  ///
  /// In en, this message translates to:
  /// **'Loading...'**
  String get loading;

  /// No description provided for @pullToRefresh.
  ///
  /// In en, this message translates to:
  /// **'Pull to refresh'**
  String get pullToRefresh;

  /// No description provided for @reportCard.
  ///
  /// In en, this message translates to:
  /// **'Report Card'**
  String get reportCard;

  /// No description provided for @semester.
  ///
  /// In en, this message translates to:
  /// **'Trimester'**
  String get semester;

  /// No description provided for @semester1.
  ///
  /// In en, this message translates to:
  /// **'Trimester 1'**
  String get semester1;

  /// No description provided for @semester2.
  ///
  /// In en, this message translates to:
  /// **'Trimester 2'**
  String get semester2;

  /// No description provided for @semester3.
  ///
  /// In en, this message translates to:
  /// **'Trimester 3'**
  String get semester3;

  /// No description provided for @allSemesters.
  ///
  /// In en, this message translates to:
  /// **'All Trimesters'**
  String get allSemesters;

  /// No description provided for @allExamTypes.
  ///
  /// In en, this message translates to:
  /// **'All Exam Types'**
  String get allExamTypes;

  /// No description provided for @allSubjects.
  ///
  /// In en, this message translates to:
  /// **'All Subjects'**
  String get allSubjects;

  /// No description provided for @subject.
  ///
  /// In en, this message translates to:
  /// **'Subject'**
  String get subject;

  /// No description provided for @grade.
  ///
  /// In en, this message translates to:
  /// **'Grade'**
  String get grade;

  /// No description provided for @average.
  ///
  /// In en, this message translates to:
  /// **'Average'**
  String get average;

  /// No description provided for @rank.
  ///
  /// In en, this message translates to:
  /// **'Rank'**
  String get rank;

  /// No description provided for @coefficient.
  ///
  /// In en, this message translates to:
  /// **'Coefficient'**
  String get coefficient;

  /// No description provided for @examType.
  ///
  /// In en, this message translates to:
  /// **'Exam Type'**
  String get examType;

  /// No description provided for @teacher.
  ///
  /// In en, this message translates to:
  /// **'Teacher'**
  String get teacher;

  /// No description provided for @comment.
  ///
  /// In en, this message translates to:
  /// **'Comment'**
  String get comment;

  /// No description provided for @present.
  ///
  /// In en, this message translates to:
  /// **'Present'**
  String get present;

  /// No description provided for @absent.
  ///
  /// In en, this message translates to:
  /// **'Absent'**
  String get absent;

  /// No description provided for @late.
  ///
  /// In en, this message translates to:
  /// **'Late'**
  String get late;

  /// No description provided for @excused.
  ///
  /// In en, this message translates to:
  /// **'Excused'**
  String get excused;

  /// No description provided for @total.
  ///
  /// In en, this message translates to:
  /// **'Total'**
  String get total;

  /// No description provided for @totalPaid.
  ///
  /// In en, this message translates to:
  /// **'Total Paid'**
  String get totalPaid;

  /// No description provided for @totalOutstandingBalance.
  ///
  /// In en, this message translates to:
  /// **'Total Outstanding Balance'**
  String get totalOutstandingBalance;

  /// No description provided for @remaining.
  ///
  /// In en, this message translates to:
  /// **'Remaining'**
  String get remaining;

  /// No description provided for @dueDate.
  ///
  /// In en, this message translates to:
  /// **'Due Date'**
  String get dueDate;

  /// No description provided for @contracts.
  ///
  /// In en, this message translates to:
  /// **'Contracts'**
  String get contracts;

  /// No description provided for @contractNumber.
  ///
  /// In en, this message translates to:
  /// **'Contract N°'**
  String get contractNumber;

  /// No description provided for @bills.
  ///
  /// In en, this message translates to:
  /// **'Bills'**
  String get bills;

  /// No description provided for @unpaidBills.
  ///
  /// In en, this message translates to:
  /// **'Unpaid Bills'**
  String get unpaidBills;

  /// No description provided for @paymentHistory.
  ///
  /// In en, this message translates to:
  /// **'Payment History'**
  String get paymentHistory;

  /// No description provided for @paymentDetails.
  ///
  /// In en, this message translates to:
  /// **'Payment Details'**
  String get paymentDetails;

  /// No description provided for @paymentType.
  ///
  /// In en, this message translates to:
  /// **'Payment Type'**
  String get paymentType;

  /// No description provided for @paymentDate.
  ///
  /// In en, this message translates to:
  /// **'Payment Date'**
  String get paymentDate;

  /// No description provided for @amount.
  ///
  /// In en, this message translates to:
  /// **'Amount'**
  String get amount;

  /// No description provided for @status.
  ///
  /// In en, this message translates to:
  /// **'Status'**
  String get status;

  /// No description provided for @contractId.
  ///
  /// In en, this message translates to:
  /// **'Contract ID'**
  String get contractId;

  /// No description provided for @paymentId.
  ///
  /// In en, this message translates to:
  /// **'Payment ID'**
  String get paymentId;

  /// No description provided for @note.
  ///
  /// In en, this message translates to:
  /// **'Note'**
  String get note;

  /// No description provided for @monthlyAmount.
  ///
  /// In en, this message translates to:
  /// **'Monthly Amount'**
  String get monthlyAmount;

  /// No description provided for @paymentProgress.
  ///
  /// In en, this message translates to:
  /// **'Payment Progress'**
  String get paymentProgress;

  /// No description provided for @nextDue.
  ///
  /// In en, this message translates to:
  /// **'Next Due'**
  String get nextDue;

  /// No description provided for @paymentDue.
  ///
  /// In en, this message translates to:
  /// **'Payment Due'**
  String get paymentDue;

  /// No description provided for @upToDate.
  ///
  /// In en, this message translates to:
  /// **'Up to date'**
  String get upToDate;

  /// No description provided for @overdue.
  ///
  /// In en, this message translates to:
  /// **'Overdue'**
  String get overdue;

  /// No description provided for @paid.
  ///
  /// In en, this message translates to:
  /// **'Paid'**
  String get paid;

  /// No description provided for @unpaid.
  ///
  /// In en, this message translates to:
  /// **'Unpaid'**
  String get unpaid;

  /// No description provided for @partial.
  ///
  /// In en, this message translates to:
  /// **'Partial'**
  String get partial;

  /// No description provided for @academicYear.
  ///
  /// In en, this message translates to:
  /// **'Academic Year'**
  String get academicYear;

  /// No description provided for @changePassword.
  ///
  /// In en, this message translates to:
  /// **'Change Password'**
  String get changePassword;

  /// No description provided for @currentPassword.
  ///
  /// In en, this message translates to:
  /// **'Current Password'**
  String get currentPassword;

  /// No description provided for @newPassword.
  ///
  /// In en, this message translates to:
  /// **'New Password'**
  String get newPassword;

  /// No description provided for @confirmPassword.
  ///
  /// In en, this message translates to:
  /// **'Confirm Password'**
  String get confirmPassword;

  /// No description provided for @language.
  ///
  /// In en, this message translates to:
  /// **'Language'**
  String get language;

  /// No description provided for @english.
  ///
  /// In en, this message translates to:
  /// **'English'**
  String get english;

  /// No description provided for @french.
  ///
  /// In en, this message translates to:
  /// **'French'**
  String get french;

  /// No description provided for @arabic.
  ///
  /// In en, this message translates to:
  /// **'Arabic'**
  String get arabic;

  /// No description provided for @about.
  ///
  /// In en, this message translates to:
  /// **'About'**
  String get about;

  /// No description provided for @version.
  ///
  /// In en, this message translates to:
  /// **'Version'**
  String get version;

  /// No description provided for @className.
  ///
  /// In en, this message translates to:
  /// **'Class'**
  String get className;

  /// No description provided for @studentCode.
  ///
  /// In en, this message translates to:
  /// **'Student Code'**
  String get studentCode;

  /// No description provided for @viewGrades.
  ///
  /// In en, this message translates to:
  /// **'View Grades'**
  String get viewGrades;

  /// No description provided for @viewAttendance.
  ///
  /// In en, this message translates to:
  /// **'View Attendance'**
  String get viewAttendance;

  /// No description provided for @viewSchedule.
  ///
  /// In en, this message translates to:
  /// **'View Schedule'**
  String get viewSchedule;

  /// No description provided for @viewBills.
  ///
  /// In en, this message translates to:
  /// **'View Bills'**
  String get viewBills;

  /// No description provided for @noChildren.
  ///
  /// In en, this message translates to:
  /// **'No children found'**
  String get noChildren;

  /// No description provided for @noGrades.
  ///
  /// In en, this message translates to:
  /// **'No grades recorded yet'**
  String get noGrades;

  /// No description provided for @noAttendance.
  ///
  /// In en, this message translates to:
  /// **'No attendance records'**
  String get noAttendance;

  /// No description provided for @noSchedule.
  ///
  /// In en, this message translates to:
  /// **'No schedule available'**
  String get noSchedule;

  /// No description provided for @noContracts.
  ///
  /// In en, this message translates to:
  /// **'No contracts found'**
  String get noContracts;

  /// No description provided for @noBills.
  ///
  /// In en, this message translates to:
  /// **'No bills found'**
  String get noBills;

  /// No description provided for @noPayments.
  ///
  /// In en, this message translates to:
  /// **'No payments recorded'**
  String get noPayments;

  /// No description provided for @notAvailable.
  ///
  /// In en, this message translates to:
  /// **'N/A'**
  String get notAvailable;

  /// No description provided for @selectChild.
  ///
  /// In en, this message translates to:
  /// **'Select a child'**
  String get selectChild;

  /// No description provided for @room.
  ///
  /// In en, this message translates to:
  /// **'Room'**
  String get room;

  /// No description provided for @day.
  ///
  /// In en, this message translates to:
  /// **'Day'**
  String get day;

  /// No description provided for @time.
  ///
  /// In en, this message translates to:
  /// **'Time'**
  String get time;

  /// No description provided for @sunday.
  ///
  /// In en, this message translates to:
  /// **'Sunday'**
  String get sunday;

  /// No description provided for @monday.
  ///
  /// In en, this message translates to:
  /// **'Monday'**
  String get monday;

  /// No description provided for @tuesday.
  ///
  /// In en, this message translates to:
  /// **'Tuesday'**
  String get tuesday;

  /// No description provided for @wednesday.
  ///
  /// In en, this message translates to:
  /// **'Wednesday'**
  String get wednesday;

  /// No description provided for @thursday.
  ///
  /// In en, this message translates to:
  /// **'Thursday'**
  String get thursday;

  /// No description provided for @friday.
  ///
  /// In en, this message translates to:
  /// **'Friday'**
  String get friday;

  /// No description provided for @saturday.
  ///
  /// In en, this message translates to:
  /// **'Saturday'**
  String get saturday;

  /// No description provided for @today.
  ///
  /// In en, this message translates to:
  /// **'Today'**
  String get today;

  /// No description provided for @passwordChanged.
  ///
  /// In en, this message translates to:
  /// **'Password changed successfully'**
  String get passwordChanged;

  /// No description provided for @parentOnly.
  ///
  /// In en, this message translates to:
  /// **'This app supports parent and teacher accounts only'**
  String get parentOnly;

  /// No description provided for @examType_exam.
  ///
  /// In en, this message translates to:
  /// **'Exam'**
  String get examType_exam;

  /// No description provided for @examType_quiz.
  ///
  /// In en, this message translates to:
  /// **'Quiz'**
  String get examType_quiz;

  /// No description provided for @examType_devoir_1.
  ///
  /// In en, this message translates to:
  /// **'Assignment 1'**
  String get examType_devoir_1;

  /// No description provided for @examType_devoir_2.
  ///
  /// In en, this message translates to:
  /// **'Assignment 2'**
  String get examType_devoir_2;

  /// No description provided for @examType_composition.
  ///
  /// In en, this message translates to:
  /// **'Final Exam'**
  String get examType_composition;

  /// No description provided for @examType_evaluation_continue.
  ///
  /// In en, this message translates to:
  /// **'Continuous Assessment'**
  String get examType_evaluation_continue;

  /// No description provided for @performanceOverview.
  ///
  /// In en, this message translates to:
  /// **'Performance Overview'**
  String get performanceOverview;

  /// No description provided for @dashboard.
  ///
  /// In en, this message translates to:
  /// **'Dashboard'**
  String get dashboard;

  /// No description provided for @classes.
  ///
  /// In en, this message translates to:
  /// **'Classes'**
  String get classes;

  /// No description provided for @exams.
  ///
  /// In en, this message translates to:
  /// **'Exams'**
  String get exams;

  /// No description provided for @myClasses.
  ///
  /// In en, this message translates to:
  /// **'My Classes'**
  String get myClasses;

  /// No description provided for @viewAll.
  ///
  /// In en, this message translates to:
  /// **'View all'**
  String get viewAll;

  /// No description provided for @viewDetails.
  ///
  /// In en, this message translates to:
  /// **'View details'**
  String get viewDetails;

  /// No description provided for @levelLabel.
  ///
  /// In en, this message translates to:
  /// **'Level'**
  String get levelLabel;

  /// No description provided for @students.
  ///
  /// In en, this message translates to:
  /// **'Students'**
  String get students;

  /// No description provided for @add.
  ///
  /// In en, this message translates to:
  /// **'Add'**
  String get add;

  /// No description provided for @edit.
  ///
  /// In en, this message translates to:
  /// **'Edit'**
  String get edit;

  /// No description provided for @delete.
  ///
  /// In en, this message translates to:
  /// **'Delete'**
  String get delete;

  /// No description provided for @save.
  ///
  /// In en, this message translates to:
  /// **'Save'**
  String get save;

  /// No description provided for @exercise.
  ///
  /// In en, this message translates to:
  /// **'Exercise'**
  String get exercise;

  /// No description provided for @allClasses.
  ///
  /// In en, this message translates to:
  /// **'All Classes'**
  String get allClasses;

  /// No description provided for @teacherDashboardTitle.
  ///
  /// In en, this message translates to:
  /// **'Teacher Dashboard'**
  String get teacherDashboardTitle;

  /// No description provided for @teacherDashboardSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Teaching workspace'**
  String get teacherDashboardSubtitle;

  /// No description provided for @teacherDashboardHint.
  ///
  /// In en, this message translates to:
  /// **'Track sessions, attendance, grades, and exams'**
  String get teacherDashboardHint;

  /// No description provided for @teacherMySchedule.
  ///
  /// In en, this message translates to:
  /// **'My Schedule'**
  String get teacherMySchedule;

  /// No description provided for @teacherTodaySessions.
  ///
  /// In en, this message translates to:
  /// **'Today\'s Sessions'**
  String get teacherTodaySessions;

  /// No description provided for @teacherNoSessionsToday.
  ///
  /// In en, this message translates to:
  /// **'No sessions today'**
  String get teacherNoSessionsToday;

  /// No description provided for @teacherNoSessionsDay.
  ///
  /// In en, this message translates to:
  /// **'No sessions scheduled for this day'**
  String get teacherNoSessionsDay;

  /// No description provided for @teacherNoSchedule.
  ///
  /// In en, this message translates to:
  /// **'No schedule available'**
  String get teacherNoSchedule;

  /// No description provided for @teacherNoClassesAssigned.
  ///
  /// In en, this message translates to:
  /// **'No classes assigned'**
  String get teacherNoClassesAssigned;

  /// No description provided for @teacherStudentsLabel.
  ///
  /// In en, this message translates to:
  /// **'Students'**
  String get teacherStudentsLabel;

  /// No description provided for @teacherSubjectsLabel.
  ///
  /// In en, this message translates to:
  /// **'Subjects'**
  String get teacherSubjectsLabel;

  /// No description provided for @teacherMarkAllPresent.
  ///
  /// In en, this message translates to:
  /// **'Mark all present'**
  String get teacherMarkAllPresent;

  /// No description provided for @teacherMarkAllAbsent.
  ///
  /// In en, this message translates to:
  /// **'Mark all absent'**
  String get teacherMarkAllAbsent;

  /// No description provided for @teacherMarkAllLate.
  ///
  /// In en, this message translates to:
  /// **'Mark all late'**
  String get teacherMarkAllLate;

  /// No description provided for @teacherSaveAttendance.
  ///
  /// In en, this message translates to:
  /// **'Save Attendance'**
  String get teacherSaveAttendance;

  /// No description provided for @teacherSelectExam.
  ///
  /// In en, this message translates to:
  /// **'Select exam'**
  String get teacherSelectExam;

  /// No description provided for @teacherNoExams.
  ///
  /// In en, this message translates to:
  /// **'No exams found'**
  String get teacherNoExams;

  /// No description provided for @teacherFilledLabel.
  ///
  /// In en, this message translates to:
  /// **'Filled'**
  String get teacherFilledLabel;

  /// No description provided for @teacherSaveGrades.
  ///
  /// In en, this message translates to:
  /// **'Save Grades'**
  String get teacherSaveGrades;

  /// No description provided for @teacherClassNotFound.
  ///
  /// In en, this message translates to:
  /// **'Class not found'**
  String get teacherClassNotFound;

  /// No description provided for @teacherNoProfile.
  ///
  /// In en, this message translates to:
  /// **'No teacher profile linked to your account.'**
  String get teacherNoProfile;

  /// No description provided for @teacherSelectLevel.
  ///
  /// In en, this message translates to:
  /// **'Select level'**
  String get teacherSelectLevel;

  /// No description provided for @teacherSelectClasses.
  ///
  /// In en, this message translates to:
  /// **'Select classes'**
  String get teacherSelectClasses;

  /// No description provided for @teacherStepLevel.
  ///
  /// In en, this message translates to:
  /// **'Step 1: Level'**
  String get teacherStepLevel;

  /// No description provided for @teacherStepClasses.
  ///
  /// In en, this message translates to:
  /// **'Step 2: Classes'**
  String get teacherStepClasses;

  /// No description provided for @teacherSelectAll.
  ///
  /// In en, this message translates to:
  /// **'Select all'**
  String get teacherSelectAll;

  /// No description provided for @teacherStepConfig.
  ///
  /// In en, this message translates to:
  /// **'Step 3: Exam Details'**
  String get teacherStepConfig;

  /// No description provided for @teacherExercises.
  ///
  /// In en, this message translates to:
  /// **'Exercises'**
  String get teacherExercises;

  /// No description provided for @teacherExerciseName.
  ///
  /// In en, this message translates to:
  /// **'Exercise name'**
  String get teacherExerciseName;

  /// No description provided for @teacherMaxNote.
  ///
  /// In en, this message translates to:
  /// **'Max note'**
  String get teacherMaxNote;

  /// No description provided for @teacherOverallGrade.
  ///
  /// In en, this message translates to:
  /// **'Total points'**
  String get teacherOverallGrade;

  /// No description provided for @teacherSubmitExam.
  ///
  /// In en, this message translates to:
  /// **'Create Exam'**
  String get teacherSubmitExam;

  /// No description provided for @teacherCreateExam.
  ///
  /// In en, this message translates to:
  /// **'Create'**
  String get teacherCreateExam;

  /// No description provided for @teacherManageExams.
  ///
  /// In en, this message translates to:
  /// **'Manage'**
  String get teacherManageExams;

  /// No description provided for @teacherExamDetailsRequired.
  ///
  /// In en, this message translates to:
  /// **'Exam details are required'**
  String get teacherExamDetailsRequired;

  /// No description provided for @teacherExerciseRequired.
  ///
  /// In en, this message translates to:
  /// **'Add at least one exercise'**
  String get teacherExerciseRequired;

  /// No description provided for @teacherExamSaved.
  ///
  /// In en, this message translates to:
  /// **'Exam created successfully'**
  String get teacherExamSaved;

  /// No description provided for @teacherEditExam.
  ///
  /// In en, this message translates to:
  /// **'Edit Exam'**
  String get teacherEditExam;

  /// No description provided for @teacherDeleteExamTitle.
  ///
  /// In en, this message translates to:
  /// **'Delete Exam'**
  String get teacherDeleteExamTitle;

  /// No description provided for @teacherDeleteExamMessage.
  ///
  /// In en, this message translates to:
  /// **'Are you sure you want to delete this exam?'**
  String get teacherDeleteExamMessage;

  /// No description provided for @classDetails.
  ///
  /// In en, this message translates to:
  /// **'Class Details'**
  String get classDetails;
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['ar', 'en', 'fr'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'ar':
      return AppLocalizationsAr();
    case 'en':
      return AppLocalizationsEn();
    case 'fr':
      return AppLocalizationsFr();
  }

  throw FlutterError(
    'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.',
  );
}
