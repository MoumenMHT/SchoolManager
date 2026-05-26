import 'package:flutter/material.dart';

class AppColors {
  // ── Primary Palette ─────────────────────────────────────────────
  static const Color primary = Color(0xFF4F46E5);       // Indigo 600
  static const Color primaryLight = Color(0xFF818CF8);   // Indigo 400
  static const Color primaryDark = Color(0xFF3730A3);    // Indigo 800

  // ── Accent ──────────────────────────────────────────────────────
  static const Color accent = Color(0xFF06B6D4);         // Cyan 500
  static const Color accentLight = Color(0xFF67E8F9);    // Cyan 300

  // ── Semantic Colors ─────────────────────────────────────────────
  static const Color success = Color(0xFF10B981);        // Emerald 500
  static const Color warning = Color(0xFFF59E0B);        // Amber 500
  static const Color error = Color(0xFFEF4444);          // Red 500
  static const Color info = Color(0xFF3B82F6);           // Blue 500

  // ── Grade Colors ────────────────────────────────────────────────
  static const Color gradeExcellent = Color(0xFF10B981); // ≥16/20
  static const Color gradeGood = Color(0xFF3B82F6);      // 14-16
  static const Color gradeAverage = Color(0xFFF59E0B);   // 10-14
  static const Color gradePoor = Color(0xFFEF4444);      // <10

  // ── Attendance Colors ───────────────────────────────────────────
  static const Color present = Color(0xFF10B981);
  static const Color absent = Color(0xFFEF4444);
  static const Color late = Color(0xFFF59E0B);
  static const Color excused = Color(0xFF3B82F6);

  // ── Bill Status Colors ──────────────────────────────────────────
  static const Color paid = Color(0xFF10B981);
  static const Color unpaid = Color(0xFFEF4444);
  static const Color partial = Color(0xFFF59E0B);
  static const Color overdue = Color(0xFFDC2626);

  // ── Surfaces (Light) ───────────────────────────────────────────
  static const Color background = Color(0xFFF8FAFC);
  static const Color surface = Color(0xFFFFFFFF);
  static const Color surfaceVariant = Color(0xFFF1F5F9);
  static const Color cardBorder = Color(0xFFE2E8F0);

  // ── Surfaces (Dark) ────────────────────────────────────────────
  static const Color backgroundDark = Color(0xFF0F172A);
  static const Color surfaceDark = Color(0xFF1E293B);
  static const Color surfaceVariantDark = Color(0xFF334155);
  static const Color cardBorderDark = Color(0xFF475569);

  // ── Text ────────────────────────────────────────────────────────
  static const Color textPrimary = Color(0xFF1E293B);
  static const Color textSecondary = Color(0xFF64748B);
  static const Color textTertiary = Color(0xFF94A3B8);
  static const Color textOnPrimary = Color(0xFFFFFFFF);

  static const Color textPrimaryDark = Color(0xFFF1F5F9);
  static const Color textSecondaryDark = Color(0xFF94A3B8);

  // ── Gradient ────────────────────────────────────────────────────
  static const LinearGradient primaryGradient = LinearGradient(
    colors: [primary, Color(0xFF7C3AED)],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  static const LinearGradient headerGradient = LinearGradient(
    colors: [Color(0xFF4F46E5), Color(0xFF7C3AED), Color(0xFF9333EA)],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  static Color gradeColor(double grade, double maxGrade) {
    final normalized = (grade / maxGrade) * 20;
    if (normalized >= 16) return gradeExcellent;
    if (normalized >= 14) return gradeGood;
    if (normalized >= 10) return gradeAverage;
    return gradePoor;
  }

  static Color attendanceColor(String status) {
    switch (status.toLowerCase()) {
      case 'present':
        return present;
      case 'absent':
        return absent;
      case 'late':
        return late;
      case 'excused':
        return excused;
      default:
        return textSecondary;
    }
  }

  static Color billStatusColor(String status) {
    switch (status.toLowerCase()) {
      case 'paid':
        return paid;
      case 'unpaid':
        return unpaid;
      case 'partial':
        return partial;
      case 'late':
        return overdue;
      default:
        return textSecondary;
    }
  }
}
