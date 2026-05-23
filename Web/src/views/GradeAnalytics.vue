<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import GradeService, {
  type GradeRecord, type GradeAnalyticsOverview, type ExerciseAverageRow, type SubjectExerciseAverageRow,
  examType, maxGrade, semester as getSemester, academicYr, subjectId, teacherId,
  subjectName as getSubjectNameHelper, teacherName as getTeacherNameHelper, normalizeGrade
} from '@/service/GradeService';
import TeacherService, { type Teacher } from '@/service/TeacherService';
import SubjectService, { type Subject } from '@/service/SubjectService';
import ClassesService, { type SchoolClass } from '@/service/ClassesService';
import StudentService, { type Student as StudentRecord } from '@/service/StudentService';

interface AggregatedRow {
  id: string;
  label: string;
  count: number;
  average: number;
  passRate: number;
  min: number;
  max: number;
  stdDev: number;
}

interface EnrichedGrade extends GradeRecord {
  normalized_grade: number;
  student_name: string;
  subject_name: string;
  teacher_name: string;
  class_id: number | null;
  class_name: string;
}

const studentRankingsFilters = ref({
  global: { value: null, matchMode: 'contains' }
});

const { t } = useI18n();

const loading = ref(false);
const error = ref<string | null>(null);

const allGrades = ref<EnrichedGrade[]>([]);

const studentGrades = ref<EnrichedGrade[]>([]);
const studentGradesLoading = ref(false);

const selectedStudentInfo = computed(() => {
  if (!selectedStudentId.value) return null;
  return allStudents.value.find(s => s.id === selectedStudentId.value) || null;
});

const bulletinTypeColumns = computed(() => {
  const subjects = studentReportCardData.value?.data?.subjects || [];
  if (subjects.length === 0) return [];
  
  const ignoredKeys = ['subject', 'teacher', 'coefficient', 'average', 'weighted_average', 'rank', 'appreciation'];
  const allKeys = new Set<string>();
  
  subjects.forEach((sub: any) => {
    Object.keys(sub).forEach(key => {
      if (!ignoredKeys.includes(key) && sub[key] !== undefined && sub[key] !== null) {
        allKeys.add(key);
      }
    });
  });
  
  const order = ['evaluation_continue', 'devoir_1', 'devoir_2', 'composition'];
  return Array.from(allKeys).sort((a, b) => {
    const idxA = order.indexOf(a);
    const idxB = order.indexOf(b);
    if (idxA !== -1 && idxB !== -1) return idxA - idxB;
    if (idxA !== -1) return -1;
    if (idxB !== -1) return 1;
    return a.localeCompare(b);
  });
});

const studentAverage = computed(() => {
  if (studentReportCardData.value?.data?.overall_average !== undefined) {
    return round2(Number(studentReportCardData.value.data.overall_average));
  }
  if (studentGrades.value.length === 0) return 0;
  const sum = studentGrades.value.reduce((acc, g) => acc + normalizedGrade(g), 0);
  return round2(sum / studentGrades.value.length);
});

const studentPassRate = computed(() => {
  if (studentGrades.value.length === 0) return 0;
  const threshold = bulletinFailThreshold.value || 10;
  const passed = studentGrades.value.filter(g => normalizedGrade(g) >= threshold).length;
  return round2((passed / studentGrades.value.length) * 100);
});

const studentRadarChartData = computed(() => {
  const subjectMap = new Map<string, { sum: number; count: number }>();
  
  studentGrades.value.forEach(g => {
    const subj = getSubjectName(g);
    const norm = normalizedGrade(g);
    if (!subjectMap.has(subj)) {
      subjectMap.set(subj, { sum: 0, count: 0 });
    }
    const curr = subjectMap.get(subj)!;
    curr.sum += norm;
    curr.count += 1;
  });

  const labels: string[] = [];
  const data: number[] = [];

  subjectMap.forEach((val, key) => {
    labels.push(key);
    data.push(round2(val.sum / val.count));
  });

  return {
    labels,
    datasets: [
      {
        label: t('grade_analytics.student_subject_average'),
        backgroundColor: 'rgba(59, 130, 246, 0.2)',
        borderColor: 'rgba(59, 130, 246, 1)',
        pointBackgroundColor: 'rgba(59, 130, 246, 1)',
        pointBorderColor: '#fff',
        pointHoverBackgroundColor: '#fff',
        pointHoverBorderColor: 'rgba(59, 130, 246, 1)',
        data
      }
    ]
  };
});

const radarChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  scales: {
    r: {
      min: 0,
      max: 20,
      ticks: { stepSize: 5 }
    }
  }
};

const selectedClassInfo = computed(() => {
  if (!selectedClassId.value) return null;
  return allClasses.value.find(c => c.id === selectedClassId.value) || null;
});

const selectedTeacherInfo = computed(() => {
  if (!selectedTeacherId.value) return null;
  return allTeachers.value.find(t => t.id === selectedTeacherId.value) || null;
});

const selectedSubjectInfo = computed(() => {
  if (!selectedSubjectId.value) return null;
  return allSubjects.value.find(s => s.id === selectedSubjectId.value) || null;
});

const classGrades = ref<EnrichedGrade[]>([]);
const classGradesLoading = ref(false);

const studentReportCardData = ref<any>(null);
const studentReportCardLoading = ref(false);

// Failing threshold: Primary = 5/20, CEM & Lycée = 10/20 (Algerian system)
const bulletinFailThreshold = computed(() => {
  const cycle = studentReportCardData.value?.data?.student?.class?.level_profile?.cycle;
  return cycle === 'primaire' ? 5 : 10;
});

// Helper: returns true if a numeric grade is below the failing threshold
const isFailing = (val: any): boolean => {
  if (val === '-' || val === null || val === undefined) return false;
  return Number(val) < bulletinFailThreshold.value;
};

const classStudentRankings = computed(() => {
  if (classGrades.value.length === 0) return [];
  const map = new Map<number, { student_name: string, sum: number, count: number }>();
  classGrades.value.forEach(g => {
    if (!map.has(g.student_id)) {
      map.set(g.student_id, { student_name: g.student_name, sum: 0, count: 0 });
    }
    const curr = map.get(g.student_id)!;
    curr.sum += normalizedGrade(g);
    curr.count += 1;
  });

  const arr = Array.from(map.values()).map(m => ({
    student_name: m.student_name,
    average: round2(m.sum / m.count)
  }));
  return arr.sort((a, b) => b.average - a.average);
});

// ── Exercise columns for the class rankings table ──────────────────────────
// Builds a list of unique exercises (with their max_note) present in classGrades
const classRankingExercises = computed(() => {
  if (!showExerciseAnalytics.value) return [];
  const map = new Map<number, { id: number; level_name: string; max_note: number }>();
  classGrades.value.forEach((g: any) => {
    (g.exercise_grades || []).forEach((eg: any) => {
      if (eg.exam_exercise_id && !map.has(eg.exam_exercise_id)) {
        map.set(eg.exam_exercise_id, {
          id: eg.exam_exercise_id,
          level_name: eg.exercise?.level_name || `Ex ${eg.exam_exercise_id}`,
          max_note: eg.exercise?.max_note ?? 0
        });
      }
    });
  });
  return Array.from(map.values());
});

// Enriches studentAggregatesData rows with per-exercise grades keyed by exercise id
const classRankingRows = computed(() => {
  if (!showExerciseAnalytics.value || classRankingExercises.value.length === 0) {
    return studentAggregatesData.value;
  }
  // Build a per-student exercise note map from classGrades
  const exMap = new Map<number, Map<number, number>>(); // studentId -> exerciseId -> note
  classGrades.value.forEach((g: any) => {
    if (!exMap.has(g.student_id)) exMap.set(g.student_id, new Map());
    const eMap = exMap.get(g.student_id)!;
    (g.exercise_grades || []).forEach((eg: any) => {
      if (eg.exam_exercise_id != null) eMap.set(eg.exam_exercise_id, eg.note);
    });
  });

  return studentAggregatesData.value.map((row: any) => {
    const eMap = exMap.get(row.id) || new Map();
    const extras: Record<string, number | null> = {};
    classRankingExercises.value.forEach(ex => {
      extras[`ex_${ex.id}`] = eMap.has(ex.id) ? eMap.get(ex.id)! : null;
    });
    return { ...row, ...extras };
  });
});

// ── Customizable grade-range donut chart ────────────────────────────────────
// Each range: { label, from, to } where from/to are /20 scores
const gradeRanges = ref([
  { label: '', from: 0, to: 5 },
  { label: '', from: 5, to: 10 },
  { label: '', from: 10, to: 14 },
  { label: '', from: 14, to: 17 },
  { label: '', from: 17, to: 20 }
]);
const showRangeEditor = ref(false);
const rangeEditorError = ref('');

const addGradeRange = () => {
  gradeRanges.value.push({ label: '', from: 0, to: 20 });
};
const removeGradeRange = (idx: number) => {
  if (gradeRanges.value.length > 1) gradeRanges.value.splice(idx, 1);
};

const DONUT_COLORS = [
  'rgba(239, 68, 68, 0.8)',
  'rgba(245, 158, 11, 0.8)',
  'rgba(59, 130, 246, 0.8)',
  'rgba(16, 185, 129, 0.8)',
  'rgba(139, 92, 246, 0.8)',
  'rgba(236, 72, 153, 0.8)',
  'rgba(14, 165, 233, 0.8)',
  'rgba(249, 115, 22, 0.8)'
];

// Selected exercise id for filtering the donut chart
const selectedDonutExerciseId = ref<number | null>(null);

// Collect unique exercises available in the current classGrades for the donut filter
const donutExerciseOptions = computed(() => {
  const map = new Map<number, { id: number; level_name: string; max_note: number }>();
  const source = classGrades.value.length > 0 ? classGrades.value : allGrades.value;
  source.forEach((g: any) => {
    (g.exercise_grades || []).forEach((eg: any) => {
      if (eg.exam_exercise_id && !map.has(eg.exam_exercise_id)) {
        map.set(eg.exam_exercise_id, {
          id: eg.exam_exercise_id,
          level_name: eg.exercise?.level_name || `Ex ${eg.exam_exercise_id}`,
          max_note: eg.exercise?.max_note ?? 0
        });
      }
    });
  });
  return [
    { id: null as number | null, level_name: t('grade_analytics.all_grades'), max_note: 20 },
    ...Array.from(map.values())
  ];
});

// When an exercise is selected, scale gradeRanges proportionally to the exercise's max_note.
// e.g. a 5pt exercise with default ranges [0-5, 5-10, 10-14, 14-17, 17-20] becomes [0-1.25, 1.25-2.5, 2.5-3.5, 3.5-4.25, 4.25-5]
const exerciseAdaptedRanges = computed(() => {
  if (selectedDonutExerciseId.value === null) return gradeRanges.value;
  const exerciseOption = donutExerciseOptions.value.find(o => o.id === selectedDonutExerciseId.value);
  const maxNote = exerciseOption?.max_note || 20;
  const scale = maxNote / 20;
  const r2 = (v: number) => Math.round(v * 100) / 100;
  return gradeRanges.value.map((r, i) => {
    const from = r2(r.from * scale);
    const to   = r2(r.to   * scale);
    const isLast = i === gradeRanges.value.length - 1;
    return { label: isLast ? `${from} – ${to}` : `${from} – <${to}`, from, to };
  });
});

// Active ranges used by the donut chart: exercise-adapted when filtering by exercise, else user-defined
const activeGradeRanges = computed(() =>
  selectedDonutExerciseId.value !== null ? exerciseAdaptedRanges.value : gradeRanges.value
);

const currentMaxNote = computed(() => {
  if (selectedDonutExerciseId.value === null) return 20;
  const exerciseOption = donutExerciseOptions.value.find(o => o.id === selectedDonutExerciseId.value);
  return exerciseOption?.max_note || 20;
});

const updateRangeValue = (idx: number, field: 'from' | 'to', value: number | null) => {
  if (value === null || value === undefined) return;
  const max = currentMaxNote.value;
  const scale = max > 0 ? 20 / max : 1;
  gradeRanges.value[idx][field] = value * scale;
};

// Helper: given a range and its position, return the display label using half-open interval format
const rangeLabel = (range: { label: string; from: number; to: number }, idx: number, total: number): string => {
  if (range.label) return range.label;
  return idx === total - 1 ? `${range.from} – ${range.to}` : `${range.from} – <${range.to}`;
};

// Grades to use for the donut: classGrades when in class view, otherwise all grades
const gradesForDistribution = computed(() => {
  if (classGrades.value.length > 0) return classGrades.value;
  return allGrades.value;
});

const classGradeDistributionData = computed(() => {
  const ranges = activeGradeRanges.value;
  const total  = ranges.length;

  // Helper: half-open interval filter — [from, to) for all but the last which is [from, to]
  const inRange = (v: number, range: { from: number; to: number }, idx: number) =>
    v >= range.from && (idx === total - 1 ? v <= range.to : v < range.to);

  // Exercise selected: use RAW exercise notes against the exercise-adapted ranges
  if (selectedDonutExerciseId.value !== null) {
    const exId = selectedDonutExerciseId.value;
    const notes: number[] = [];
    gradesForDistribution.value.forEach((g: any) => {
      const eg = (g.exercise_grades || []).find((e: any) => e.exam_exercise_id === exId);
      if (eg !== undefined) notes.push(Number(eg.note));
    });
    const counts = ranges.map((range, i) => notes.filter(v => inRange(v, range, i)).length);
    return {
      labels: ranges.map((r, i) => rangeLabel(r, i, total)),
      datasets: [{
        data: counts,
        backgroundColor: ranges.map((_, i) => DONUT_COLORS[i % DONUT_COLORS.length]),
        borderWidth: 2, borderColor: 'transparent', hoverOffset: 8
      }]
    };
  }

  // Default: overall grade distribution (normalized to /20)
  const grades = gradesForDistribution.value.map(g => normalizedGrade(g));
  const counts = ranges.map((range, i) => grades.filter(v => inRange(v, range, i)).length);
  return {
    labels: ranges.map((r, i) => rangeLabel(r, i, total)),
    datasets: [{
      data: counts,
      backgroundColor: ranges.map((_, i) => DONUT_COLORS[i % DONUT_COLORS.length]),
      borderWidth: 2, borderColor: 'transparent', hoverOffset: 8
    }]
  };
});

const classGradeDistributionOptions = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: '62%',
  plugins: {
    legend: { position: 'right' as const, labels: { font: { size: 11 }, padding: 10 } },
    tooltip: {
      callbacks: {
        label: (ctx: any) => {
          const total = ctx.dataset.data.reduce((a: number, b: number) => a + b, 0);
          const pct = total > 0 ? Math.round((ctx.parsed / total) * 100) : 0;
          return ` ${ctx.label}: ${ctx.parsed} students (${pct}%)`;
        }
      }
    }
  }
};

const classTopStudents = computed(() => classStudentRankings.value.slice(0, 5));
const classBottomStudents = computed(() => {
  const sorted = [...classStudentRankings.value].sort((a, b) => a.average - b.average);
  return sorted.slice(0, 5);
});

// Teacher Impact table: teacher → subjects they teach + their avg/pass rate in this class
const classTeacherBreakdown = computed(() => {
  if (classGrades.value.length === 0) return [];

  const map = new Map<string, {
    teacher_name: string;
    subjects: Set<string>;
    sum: number;
    count: number;
    passed: number;
  }>();

  classGrades.value.forEach(g => {
    const tid = teacherId(g);
    const key = tid ? String(tid) : ((g as any).teacher_name || t('grade_analytics.unknown'));
    if (!map.has(key)) {
      map.set(key, {
        teacher_name: getTeacherName(g) || t('grade_analytics.unknown'),
        subjects: new Set(),
        sum: 0,
        count: 0,
        passed: 0
      });
    }
    const entry = map.get(key)!;
    const sname = getSubjectName(g);
    if (sname) entry.subjects.add(sname);
    const norm = normalizedGrade(g);
    entry.sum += norm;
    entry.count += 1;
    if (norm >= 10) entry.passed += 1;
  });

  return Array.from(map.values()).map(e => ({
    teacher_name: e.teacher_name,
    subjects: Array.from(e.subjects).join(', '),
    average: round2(e.sum / e.count),
    passRate: round2((e.passed / e.count) * 100)
  })).sort((a, b) => b.average - a.average);
});

// Fail threshold helper: 5/20 for primaire, 10/20 for CEM/Lycée
const getClassFailThreshold = (classId: number | null): number => {
  if (!classId) return 10;
  const cls = allClasses.value.find(c => c.id === classId) as any;
  return cls?.level_profile?.cycle === 'primaire' ? 5 : 10;
};

// Subject view: per-student summary sorted worst → best
const subjectStudentBreakdown = computed(() => {
  if (subjectGrades.value.length === 0) return [];

  interface StudentEntry {
    student_id: number;
    student_name: string;
    class_id: number | null;
    class_name: string;
    typeGrades: Record<string, number[]>;
    allNorms: number[];
  }

  const map = new Map<number, StudentEntry>();

  subjectGrades.value.forEach(g => {
    const sid = g.student_id;
    if (!map.has(sid)) {
      map.set(sid, {
        student_id: sid,
        student_name: g.student_name,
        class_id: g.class_id,
        class_name: g.class_name,
        typeGrades: {},
        allNorms: []
      });
    }
    const entry = map.get(sid)!;
    const norm = normalizedGrade(g);
    entry.allNorms.push(norm);
    
    const et = examType(g);
    if (!entry.typeGrades[et]) entry.typeGrades[et] = [];
    entry.typeGrades[et].push(norm);
  });

  const avg = (arr: number[]) => arr.length ? round2(arr.reduce((a, b) => a + b, 0) / arr.length) : null;

  return Array.from(map.values()).map(e => {
    const row: any = {
      student_name: e.student_name,
      class_name: e.class_name,
      class_id: e.class_id,
      average: avg(e.allNorms),
      threshold: getClassFailThreshold(e.class_id)
    };
    
    // Add dynamic averages for each type
    Object.keys(e.typeGrades).forEach(type => {
      row[type] = avg(e.typeGrades[type]);
    });
    
    return row;
  }).sort((a, b) => (a.average ?? 99) - (b.average ?? 99));
});

const teacherDistributionChartData = computed(() => {
  const labels = distributionRows.value.map(r => r.label);
  const data = distributionRows.value.map(r => r.count);
  return {
    labels,
    datasets: [{
      data,
      backgroundColor: [
        'rgba(239, 68, 68, 0.7)',
        'rgba(245, 158, 11, 0.7)',
        'rgba(59, 130, 246, 0.7)',
        'rgba(16, 185, 129, 0.7)',
        'rgba(139, 92, 246, 0.7)',
      ]
    }]
  };
});
const teacherPieOptions = {
  responsive: true,
  maintainAspectRatio: false,
};

const allStudents = ref<StudentRecord[]>([]);
const allTeachers = ref<Teacher[]>([]);
const allSubjects = ref<Subject[]>([]);
const allClasses = ref<SchoolClass[]>([]);

const statsData = ref({
  records: 0,
  students: 0,
  teachers: 0,
  subjects: 0,
  classes: 0,
  average: 0,
  median: 0,
  highest: 0,
  lowest: 0,
  passRate: 0,
  excellenceRate: 0,
  stdDev: 0
});

const subjectAggregatesData = ref<AggregatedRow[]>([]);
const classAggregatesData = ref<AggregatedRow[]>([]);
const teacherAggregatesData = ref<AggregatedRow[]>([]);
const studentAggregatesData = ref<any[]>([]);
const distributionRows = ref<AggregatedRow[]>([]);
const teacherDrilldownRows = ref<AggregatedRow[]>([]);
const subjectDrilldownRows = ref<AggregatedRow[]>([]);

// Raw grades for the selected subject (used for student needs-work table)
const subjectGrades = ref<EnrichedGrade[]>([]);
const subjectGradesLoading = ref(false);

// Exercise analytics — shown when a specific exam is drillable
const exerciseAverages = ref<ExerciseAverageRow[]>([]);
const exerciseAveragesLoading = ref(false);
const selectedExamIdForExercises = ref<number | null>(null);

const loadExerciseAverages = async (examId: number) => {
  selectedExamIdForExercises.value = examId;
  exerciseAveragesLoading.value = true;
  try {
    const classAverages = await GradeService.getExamExerciseAverages(examId);
    
    // Find the exam row from studentGrades
    const examRow = studentGrades.value.find((g: any) => g.exam_id === examId);
    const studentExerciseGrades = examRow?.exercise_grades || [];

    // Map over classAverages and inject the student's note
    exerciseAverages.value = classAverages.map((avg: any) => {
      const studentEg = studentExerciseGrades.find((eg: any) => eg.exam_exercise_id === avg.exercise_id);
      return {
        ...avg,
        student_note: studentEg ? studentEg.note : null
      };
    });
  } catch (e) {
    exerciseAverages.value = [];
  } finally {
    exerciseAveragesLoading.value = false;
  }
};

// Global Subject Exercise Analytics (Subject Breakdown View)
const subjectExerciseAverages = ref<SubjectExerciseAverageRow[]>([]);
const subjectExerciseLoading = ref(false);

const exerciseAnalyticsDatasetLabel = computed(() => {
  return selectedClassId.value ? t('grade_analytics.class_avg_20') : t('grade_analytics.avg_note');
});

const showExerciseAnalytics = computed(() => {
  return selectedSemester.value !== 'all' && selectedExamType.value !== 'all';
});

const loadSubjectExerciseAverages = async () => {
  if (
    selectedStudentId.value ||
    (!selectedClassId.value && !selectedSubjectId.value && !selectedTeacherId.value) ||
    // Only skip when class is selected alone (no subject/teacher) AND showExerciseAnalytics is false
    (selectedClassId.value && !selectedSubjectId.value && !selectedTeacherId.value && !showExerciseAnalytics.value)
  ) {
    subjectExerciseAverages.value = [];
    return;
  }
  subjectExerciseLoading.value = true;
  try {
    subjectExerciseAverages.value = await GradeService.getSubjectExerciseAverages({
      subject_id: selectedSubjectId.value || undefined,
      class_id: selectedClassId.value || undefined,
      academic_year: selectedAcademicYear.value,
      semester: selectedSemester.value,
      exam_type: selectedExamType.value,
      teacher_id: selectedTeacherId.value || undefined
    });
  } catch (err: any) {
    console.error('Failed to load subject exercise averages', err);
  } finally {
    subjectExerciseLoading.value = false;
  }
};

const selectedAcademicYear = ref<string>('');
const selectedSemester = ref<string>('all');
const selectedExamType = ref<string>('all');
const selectedClassId = ref<number | null>(null);
const selectedSubjectId = ref<number | null>(null);
const selectedTeacherId = ref<number | null>(null);
const selectedStudentId = ref<number | null>(null);

const examTypes = ref<string[]>([]);

// Monotonic counter — incremented before every request so stale responses are discarded
let examTypesReqId = 0;

const loadExamTypes = async () => {
  const reqId = ++examTypesReqId;
  try {
    const types = await GradeService.getExamTypes({
      semester: selectedSemester.value,
      academic_year: selectedAcademicYear.value,
      class_id: selectedClassId.value || undefined,
      student_id: selectedStudentId.value || undefined
    });

    // Discard stale response if a newer request has already been issued
    if (reqId !== examTypesReqId) return;

    examTypes.value = types;

    // Reset selected exam type if it's no longer available in the new list
    if (selectedExamType.value !== 'all' && !types.includes(selectedExamType.value)) {
      selectedExamType.value = 'all';
    }
  } catch (err: any) {
    if (reqId !== examTypesReqId) return; // ignore stale errors too
    console.error('Failed to load exam types', err);
    examTypes.value = [];
  }
};

const selectedTeacherForChart = ref<number | null>(null);
const selectedSubjectForChart = ref<number | null>(null);

const semesterOptions = computed(() => [
  { label: t('grade_analytics.all_trimesters'), value: 'all' },
  { label: t('grade_analytics.trimester_1'), value: 'Trimester 1' },
  { label: t('grade_analytics.trimester_2'), value: 'Trimester 2' },
  { label: t('grade_analytics.trimester_3'), value: 'Trimester 3' }
]);

const examTypeOptions = computed(() => {
  const options = [
    { label: t('grade_analytics.all_exam_types'), value: 'all' }
  ];

  // Use only dynamic types from DB
  const allTypes = [...new Set(examTypes.value)];

  allTypes.forEach(type => {
    // Use the formatExamType helper we created earlier
    const label = formatExamType(type);
    options.push({ label, value: type });
  });

  return options;
});

const formatExamType = (type: string): string => {
  if (!type) return '';
  if (type === 'evaluation_continue') return t('grade_analytics.eval_continue');
  if (type === 'devoir_1') return t('grade_analytics.devoir_1_label');
  if (type === 'devoir_2') return t('grade_analytics.devoir_2_label');
  if (type === 'composition') return t('grade_analytics.composition_label');
  
  const labelKey = `grade_analytics.${type}_label`;
  const fallbackLabelKey = `grade_analytics.${type}`;
  const translated = t(labelKey);
  if (translated !== labelKey) return translated;
  const altTranslated = t(fallbackLabelKey);
  if (altTranslated !== fallbackLabelKey) return altTranslated;
  
  return type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const formatExamTypeHeader = (type: string): string => {
  const headerKey = `grade_analytics.col_${type}`;
  const translated = t(headerKey);
  if (translated !== headerKey) return translated;
  return formatExamType(type);
};

const getCurrentAcademicYear = (): string => {
  const now = new Date();
  const year = now.getFullYear();
  return now.getMonth() >= 8 ? `${year}-${year + 1}` : `${year - 1}-${year}`;
};

const academicYearOptions = computed(() => {
  const years = new Set<string>();
  // academic_year is now on exam relation
  allGrades.value.forEach((grade) => {
    const yr = academicYr(grade);
    if (yr) years.add(yr);
  });
  // Always include current year even if no grades loaded yet
  if (selectedAcademicYear.value) years.add(selectedAcademicYear.value);
  const sorted = Array.from(years).sort().reverse();
  return sorted.map((year) => ({ label: year, value: year }));
});

const classOptions = computed(() => {
  return [
    { label: t('grade_analytics.all_classes'), value: null as number | null },
    ...allClasses.value.map((classItem) => ({
      label: classItem.name,
      value: classItem.id
    }))
  ];
});

const teacherOptions = computed(() => {
  return [
    { label: t('grade_analytics.all_teachers'), value: null as number | null },
    ...allTeachers.value.map((teacher) => ({
      label: `${teacher.first_name} ${teacher.last_name}`,
      value: teacher.id
    }))
  ];
});

const subjectOptions = computed(() => {
  return [
    { label: t('grade_analytics.all_subjects'), value: null as number | null },
    ...allSubjects.value.map((subject) => ({
      label: subject.name,
      value: subject.id
    }))
  ];
});

const studentOptions = computed(() => {
  const filtered = selectedClassId.value
    ? allStudents.value.filter((student) => student.class_id === selectedClassId.value)
    : allStudents.value;

  return [
    { label: t('grade_analytics.all_students'), value: null as number | null },
    ...filtered
      .map((student) => ({
        label: `${student.first_name} ${student.last_name}`.trim(),
        value: student.id
      }))
      .sort((a, b) => a.label.localeCompare(b.label))
  ];
});

// normalizedGrade — delegates to the service helper which reads exam.max_grade
const normalizedGrade = (grade: GradeRecord): number => normalizeGrade(grade);

const round2 = (value: number): number => Number(value.toFixed(2));

const getTeacherName = (grade: GradeRecord): string => {
  // Try exam.teacher first, then fall back to top-level teacher, then allTeachers list
  const fromExam = getTeacherNameHelper(grade);
  if (fromExam) return fromExam;
  const tid = teacherId(grade);
  const teacher = allTeachers.value.find((item) => item.id === tid);
  if (teacher) return `${teacher.first_name} ${teacher.last_name}`;
  return tid ? `${t('grade_analytics.teacher_id_prefix')}${tid}` : '';
};

const getSubjectName = (grade: GradeRecord): string => {
  const fromExam = getSubjectNameHelper(grade);
  if (fromExam) return fromExam;
  const sid = subjectId(grade);
  const subject = allSubjects.value.find((item) => item.id === sid);
  return subject?.name || (sid ? `${t('grade_analytics.subject_id_prefix')}${sid}` : '');
};

const getClassInfo = (grade: GradeRecord): { class_id: number | null; class_name: string } => {
  const classId = grade.student?.class_id ?? null;
  if (!classId) return { class_id: null, class_name: t('grade_analytics.unknown') };
  const classItem = allClasses.value.find((item) => item.id === classId);
  return {
    class_id: classId,
    class_name: classItem?.name || `${t('grade_analytics.class_id_prefix')}${classId}`
  };
};

const mapAggregateRows = (rows: any[] | undefined): AggregatedRow[] => {
  return (rows || []).map((row) => ({
    id: String(row.id),
    label: String(row.label),
    count: Number(row.count ?? 0),
    average: round2(Number(row.average ?? 0)),
    passRate: round2(Number(row.pass_rate ?? row.passRate ?? 0)),
    min: round2(Number(row.min ?? 0)),
    max: round2(Number(row.max ?? 0)),
    stdDev: round2(Number(row.std_dev ?? row.stdDev ?? 0))
  }));
};

const mapDistributionRows = (rows: Array<{ label: string; count: number }> | undefined): AggregatedRow[] => {
  return (rows || []).map((row) => ({
    id: row.label,
    label: row.label,
    count: Number(row.count ?? 0),
    average: 0,
    passRate: 0,
    min: 0,
    max: 0,
    stdDev: 0
  }));
};

const buildAnalyticsParams = () => {
  const params: {
    academic_year?: string;
    semester?: string;
    exam_type?: string;
    class_id?: number;
    subject_id?: number;
    teacher_id?: number;
    student_id?: number;
  } = {};

  if (selectedAcademicYear.value) params.academic_year = selectedAcademicYear.value;
  if (selectedSemester.value !== 'all') params.semester = selectedSemester.value;
  if (selectedExamType.value !== 'all') params.exam_type = selectedExamType.value;
  if (selectedClassId.value) params.class_id = selectedClassId.value;
  if (selectedSubjectId.value) params.subject_id = selectedSubjectId.value;
  if (selectedTeacherId.value) params.teacher_id = selectedTeacherId.value;
  if (selectedStudentId.value) params.student_id = selectedStudentId.value;

  return params;
};

const applyOverviewData = (overview: GradeAnalyticsOverview) => {
  statsData.value = {
    ...statsData.value,
    ...(overview.stats || {})
  };
  subjectAggregatesData.value = mapAggregateRows(overview.subject_aggregates);
  classAggregatesData.value = mapAggregateRows(overview.class_aggregates);
  teacherAggregatesData.value = mapAggregateRows(overview.teacher_aggregates);
  studentAggregatesData.value = (overview.student_aggregates || []).map(row => ({
    id: row.id,
    student_name: row.label,
    class_name: (row as any).class_name || '',
    average: round2(Number(row.average ?? 0)),
    passRate: round2(Number(row.pass_rate ?? 0)),
    best_subject: (row as any).best_subject || '—',
    best_subject_avg: (row as any).best_subject_avg ?? null
  }));
  distributionRows.value = mapDistributionRows(overview.distribution);
};

const globalFilteredGrades = computed(() => {
  return allGrades.value;
});

const subjectAggregates = computed(() => {
  return subjectAggregatesData.value;
});

const classAggregates = computed(() => {
  return classAggregatesData.value;
});

const teacherAggregates = computed(() => {
  return teacherAggregatesData.value;
});

const teacherChartRows = computed(() => {
  if (!selectedTeacherForChart.value) {
    return teacherAggregates.value.slice(0, 12);
  }

  return teacherDrilldownRows.value;
});

const subjectChartRows = computed(() => {
  if (!selectedSubjectForChart.value) {
    return distributionRows.value;
  }

  return subjectDrilldownRows.value;
});

const subjectAverageChartData = computed(() => ({
  labels: subjectAggregates.value.slice(0, 12).map((item) => item.label),
  datasets: [
    {
      label: t('grade_analytics.avg_grade_20'),
      data: subjectAggregates.value.slice(0, 12).map((item) => item.average),
      backgroundColor: 'rgba(59, 130, 246, 0.7)',
      borderColor: 'rgba(59, 130, 246, 1)',
      borderWidth: 1,
      borderRadius: 8
    }
  ]
}));

const classAverageChartData = computed(() => ({
  labels: classAggregates.value.map((item) => item.label),
  datasets: [
    {
      label: t('grade_analytics.avg_grade_20'),
      data: classAggregates.value.map((item) => item.average),
      backgroundColor: 'rgba(16, 185, 129, 0.7)',
      borderColor: 'rgba(16, 185, 129, 1)',
      borderWidth: 1,
      borderRadius: 8
    }
  ]
}));

const teacherChartData = computed(() => ({
  labels: teacherChartRows.value.map((item) => item.label),
  datasets: [
    {
      label: selectedTeacherForChart.value ? t('grade_analytics.subject_avg_20') : t('grade_analytics.teacher_avg_20'),
      data: teacherChartRows.value.map((item) => item.average),
      backgroundColor: 'rgba(249, 115, 22, 0.7)',
      borderColor: 'rgba(249, 115, 22, 1)',
      borderWidth: 1,
      borderRadius: 8
    }
  ]
}));

const subjectDrilldownChartData = computed(() => ({
  labels: subjectChartRows.value.map((item) => item.label),
  datasets: [
    {
      label: selectedSubjectForChart.value ? t('grade_analytics.class_avg_20') : t('grade_analytics.grade_count'),
      data: selectedSubjectForChart.value
        ? subjectChartRows.value.map((item) => item.average)
        : subjectChartRows.value.map((item) => item.count),
      backgroundColor: selectedSubjectForChart.value ? 'rgba(139, 92, 246, 0.7)' : 'rgba(14, 165, 233, 0.7)',
      borderColor: selectedSubjectForChart.value ? 'rgba(139, 92, 246, 1)' : 'rgba(14, 165, 233, 1)',
      borderWidth: 1,
      borderRadius: 8
    }
  ]
}));

const subjectExerciseRadarData = computed(() => {
  const labels = subjectExerciseAverages.value.map(e => `${e.level_name} (/${e.max_note})`);
  const data = subjectExerciseAverages.value.map(e => e.avg_note);
  
  return {
    labels,
    datasets: [
      {
        label: exerciseAnalyticsDatasetLabel.value,
        backgroundColor: 'rgba(139, 92, 246, 0.2)',
        borderColor: 'rgba(139, 92, 246, 1)',
        pointBackgroundColor: 'rgba(139, 92, 246, 1)',
        pointBorderColor: '#fff',
        pointHoverBackgroundColor: '#fff',
        pointHoverBorderColor: 'rgba(139, 92, 246, 1)',
        data
      }
    ]
  };
});

const subjectExerciseBarData = computed(() => {
  const labels = subjectExerciseAverages.value.map(e => `${e.level_name} (/${e.max_note})`);
  const data = subjectExerciseAverages.value.map(e => e.avg_note);
  const percentages = subjectExerciseAverages.value.map(e => e.max_note > 0 ? (e.avg_note / e.max_note) * 100 : 0);
  
  return {
    labels,
    datasets: [
      {
        label: exerciseAnalyticsDatasetLabel.value,
        data,
        backgroundColor: percentages.map(val => val >= 70 ? 'rgba(16, 185, 129, 0.7)' : val >= 40 ? 'rgba(245, 158, 11, 0.7)' : 'rgba(244, 63, 94, 0.7)'),
        borderColor: percentages.map(val => val >= 70 ? 'rgba(16, 185, 129, 1)' : val >= 40 ? 'rgba(245, 158, 11, 1)' : 'rgba(244, 63, 94, 1)'),
        borderWidth: 1,
        borderRadius: 6
      }
    ]
  };
});

// Tune grade chart Y-axis from one place.
const gradeAxisMin = 1;
const gradeAxisMax = 20;
const gradeAxisStep = 2;

const baseChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  layout: {
    padding: {
      top: 8,
      right: 8,
      bottom: 0,
      left: 8
    }
  },
  plugins: {
    legend: {
      position: 'bottom' as const
    }
  },
  scales: {
    y: {
      min: gradeAxisMin,
      beginAtZero: true,
      max: gradeAxisMax,
      ticks: {
        stepSize: gradeAxisStep,
        autoSkip: false,
        includeBounds: true,
        font: {
          size: 10
        },
        precision: 0
      }
    }
  }
};

const subjectDistributionChartOptions = computed(() => {
  return baseChartOptions;
});

const stats = computed(() => {
  return statsData.value;
});

const topSubjects = computed(() => subjectAggregates.value.slice(0, 5));
const bottomSubjects = computed(() => [...subjectAggregates.value].sort((a, b) => a.average - b.average).slice(0, 5));
const toughestClasses = computed(() => [...classAggregates.value].sort((a, b) => a.average - b.average).slice(0, 5));
const highVarianceSubjects = computed(() => [...subjectAggregates.value].sort((a, b) => b.stdDev - a.stdDev).slice(0, 5));

const loadMetadata = async () => {
  const [teachers, subjects, classes, students] = await Promise.all([
    TeacherService.getTeachers(),
    SubjectService.getSubjects(),
    ClassesService.getClasses(),
    StudentService.getStudents()
  ]);

  allTeachers.value = teachers;
  allSubjects.value = subjects;
  allClasses.value = classes;
  allStudents.value = students;
};

const loadChartDrilldowns = async () => {
  const baseParams = buildAnalyticsParams();
  const requests: Promise<void>[] = [];

  if (selectedTeacherForChart.value) {
    requests.push(
      GradeService.getAnalyticsOverview({
        ...baseParams,
        teacher_id: selectedTeacherForChart.value
      }).then((overview) => {
        teacherDrilldownRows.value = mapAggregateRows(overview.subject_aggregates).slice(0, 12);
      })
    );
  } else {
    teacherDrilldownRows.value = [];
  }

  if (selectedSubjectForChart.value) {
    requests.push(
      GradeService.getAnalyticsOverview({
        ...baseParams,
        subject_id: selectedSubjectForChart.value
      }).then((overview) => {
        subjectDrilldownRows.value = mapAggregateRows(overview.class_aggregates);
      })
    );
  } else {
    subjectDrilldownRows.value = [];
  }

  await Promise.all(requests);
};

const loadStudentGrades = async () => {
  if (!selectedStudentId.value) {
    studentGrades.value = [];
    return;
  }
  studentGradesLoading.value = true;
  try {
    const rawGrades = await GradeService.getGrades(buildAnalyticsParams());
    studentGrades.value = rawGrades.map((g: any) => ({
      ...g,
      normalized_grade: normalizedGrade(g),
      student_name: `${g.student?.first_name || ''} ${g.student?.last_name || ''}`,
      subject_name: getSubjectName(g),
      teacher_name: getTeacherName(g),
      ...getClassInfo(g)
    }));
  } catch (err: any) {
    console.error(err);
  } finally {
    studentGradesLoading.value = false;
  }
};

const loadClassGrades = async () => {
  // Run for: class-only, class+subject, teacher-only, subject-only (no student selected)
  const hasClass = !!selectedClassId.value && !selectedStudentId.value;
  const teacherOnly = !!selectedTeacherId.value && !selectedClassId.value && !selectedStudentId.value;
  const subjectOnly = !!selectedSubjectId.value && !selectedClassId.value && !selectedTeacherId.value && !selectedStudentId.value;
  if (!hasClass && !teacherOnly && !subjectOnly) {
    classGrades.value = [];
    return;
  }
  classGradesLoading.value = true;
  try {
    const params = { ...buildAnalyticsParams() };
    // Always include class_id when a class is selected
    if (hasClass) params.class_id = selectedClassId.value!;
    // Include subject_id when both class + subject are selected for subject-scoped distribution
    if (hasClass && selectedSubjectId.value) params.subject_id = selectedSubjectId.value;
    const rawGrades = await GradeService.getGrades(params);
    classGrades.value = rawGrades.map((g: any) => ({
      ...g,
      normalized_grade: normalizedGrade(g),
      student_name: `${g.student?.first_name || ''} ${g.student?.last_name || ''}`.trim(),
      teacher_name: getTeacherName(g),
      subject_name: getSubjectName(g),
      ...getClassInfo(g)
    }));
  } catch (err: any) {
    console.error(err);
  } finally {
    classGradesLoading.value = false;
  }
};

const loadStudentReportCard = async () => {
  const studentId = selectedStudentId.value;
  if (!studentId) {
    studentReportCardData.value = null;
    return;
  }

  studentReportCardLoading.value = true;
  try {
    // ── Annual mode: fetch all 3 trimesters in parallel ──────────────────
    if (selectedSemester.value === 'all') {
      const TRIMESTERS = ['Trimester 1', 'Trimester 2', 'Trimester 3'];
      const results = await Promise.allSettled(
        TRIMESTERS.map(sem =>
          GradeService.getStudentReportCard(studentId, {
            semester: sem,
            academic_year: selectedAcademicYear.value,
          })
        )
      );

      // Collect the `data` payload from every fulfilled response
      const trimesterPayloads: any[] = results
        .filter(r => r.status === 'fulfilled')
        .map(r => (r as PromiseFulfilledResult<any>).value?.data);

      if (trimesterPayloads.length === 0) {
        studentReportCardData.value = null;
        return;
      }

      // Use the first successful payload for student / year metadata
      const basePayload = trimesterPayloads[0];

      // Build a per-subject accumulator keyed by subject id
      const subjectMap = new Map<number, {
        subject: any;
        teacher: any;
        coefficient: number;
        sumAvg: number;       // sum of per-trimester subject averages
        typeGrades: Record<string, number[]>;
      }>();

      trimesterPayloads.forEach(payload => {
        (payload?.subjects || []).forEach((sub: any) => {
          const id = sub.subject?.id;
          if (!id) return;

          if (!subjectMap.has(id)) {
            subjectMap.set(id, {
              subject: sub.subject,
              teacher: sub.teacher,
              coefficient: Number(sub.coefficient) || 1,
              sumAvg: 0,
              typeGrades: {},
            });
          }

          const entry = subjectMap.get(id)!;
          // Always add to the sum; missing trimesters count as 0 (handled by dividing by 3)
          entry.sumAvg += Number(sub.average || 0);
          
          // Map all keys except subject, teacher, coefficient, average, weighted_average, etc.
          const ignoredKeys = ['subject', 'teacher', 'coefficient', 'average', 'weighted_average', 'rank', 'appreciation'];
          Object.keys(sub).forEach(key => {
            if (!ignoredKeys.includes(key) && sub[key] !== '-' && sub[key] !== null) {
              if (!entry.typeGrades[key]) entry.typeGrades[key] = [];
              entry.typeGrades[key].push(Number(sub[key]));
            }
          });
        });
      });

      // Compute annual averages — always divide by 3 as per school rules
      const annualSubjects = Array.from(subjectMap.values()).map(entry => {
        const annualAvg = Math.round((entry.sumAvg / 3) * 100) / 100;
        const avg = (arr: number[]) =>
          arr.length ? +(arr.reduce((a, b) => a + b, 0) / 3).toFixed(2) : '-';
          
        const subRow: any = {
          subject: entry.subject,
          teacher: entry.teacher,
          coefficient: entry.coefficient,
          average: annualAvg,
          weighted_average: Math.round(annualAvg * entry.coefficient * 100) / 100,
        };
        
        Object.keys(entry.typeGrades).forEach(key => {
          subRow[key] = avg(entry.typeGrades[key]);
        });
        
        return subRow;
      });

      // Overall annual average = sum of the 3 trimester overalls / 3
      const overallAverages = trimesterPayloads.map(p => Number(p?.overall_average || 0));
      const annualOverall = Math.round(
        (overallAverages.reduce((a, b) => a + b, 0) / 3) * 100
      ) / 100;

      studentReportCardData.value = {
        data: {
          student: basePayload.student,
          semester: 'Bilan Annuel',
          academic_year: basePayload.academic_year,
          subjects: annualSubjects,
          overall_average: annualOverall,
        },
      };

    } else {
      // ── Single trimester mode ──────────────────────────────────────────
      const data = await GradeService.getStudentReportCard(studentId, {
        semester: selectedSemester.value,
        academic_year: selectedAcademicYear.value,
      });
      studentReportCardData.value = data;
    }
  } catch (err: any) {
    console.error('Failed to load report card:', err);
  } finally {
    studentReportCardLoading.value = false;
  }
};

const loadSubjectGrades = async () => {
  if (!selectedSubjectId.value || selectedStudentId.value || selectedClassId.value || selectedTeacherId.value) {
    subjectGrades.value = [];
    return;
  }
  subjectGradesLoading.value = true;
  try {
    const rawGrades = await GradeService.getGrades({ ...buildAnalyticsParams(), subject_id: selectedSubjectId.value });
    subjectGrades.value = rawGrades.map((g: any) => ({
      ...g,
      normalized_grade: normalizedGrade(g),
      student_name: `${g.student?.first_name || ''} ${g.student?.last_name || ''}`.trim(),
      teacher_name: getTeacherName(g),
      subject_name: getSubjectName(g),
      ...getClassInfo(g)
    }));
  } catch (err: any) {
    console.error('Failed to load subject grades:', err);
  } finally {
    subjectGradesLoading.value = false;
  }
};

const loadAnalytics = async () => {
  loading.value = true;
  error.value = null;

  try {
    const overview = await GradeService.getAnalyticsOverview(buildAnalyticsParams());
    applyOverviewData(overview);
    await loadChartDrilldowns();
    await loadStudentGrades();
    await loadClassGrades();
    await loadSubjectGrades();
    await loadSubjectExerciseAverages();
    await loadStudentReportCard();
  } catch (err: any) {
    error.value = err.response?.data?.message || t('grade_analytics.failed_load_analytics');
  } finally {
    loading.value = false;
  }
};

const reload = async () => {
  await loadAnalytics();
};

const resetFilters = async () => {
  selectedSemester.value = 'all';
  selectedExamType.value = 'all';
  selectedClassId.value = null;
  selectedSubjectId.value = null;
  selectedTeacherId.value = null;
  selectedStudentId.value = null;
  selectedTeacherForChart.value = null;
  selectedSubjectForChart.value = null;
  await loadAnalytics();
};

watch(selectedAcademicYear, async () => {
  await loadAnalytics();
});

watch(selectedSemester, async () => {
  selectedDonutExerciseId.value = null; // reset exercise filter on trimester change
  await loadAnalytics();
});

watch([selectedExamType, selectedClassId, selectedSubjectId, selectedTeacherId, selectedStudentId], async () => {
  selectedDonutExerciseId.value = null; // reset exercise filter on main filter change
  await loadAnalytics();
});

watch([selectedTeacherForChart, selectedSubjectForChart], async () => {
  await loadChartDrilldowns();
});

watch(
  [selectedSemester, selectedAcademicYear, selectedClassId, selectedStudentId],
  () => {
    loadExamTypes();
  }
);

onMounted(async () => {
  loading.value = true;
  selectedAcademicYear.value = getCurrentAcademicYear();
  await Promise.all([
    loadMetadata(),
    loadExamTypes()
  ]);
  await loadAnalytics();
  loading.value = false;
});
</script>

<template>
  <div class="grid grid-cols-12 gap-6">
    <div class="col-span-12">
      <div class="card">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
          <div>
            <h3 class="text-2xl font-semibold m-0">{{ $t('grade_analytics.title') }}</h3>
            <p class="text-muted-color mt-2 mb-0">{{ $t('grade_analytics.subtitle') }}</p>
          </div>
          <div class="flex items-center gap-2">
            <Button :label="$t('grade_analytics.refresh')" icon="pi pi-refresh" severity="secondary" outlined @click="reload" :loading="loading" />
            <Button :label="$t('grade_analytics.reset_filters')" icon="pi pi-filter-slash" text @click="resetFilters" :disabled="loading" />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3 mb-3">
          <Select
            v-model="selectedAcademicYear"
            :options="academicYearOptions"
            optionLabel="label"
            optionValue="value"
            :placeholder="$t('grade_analytics.academic_year')"
            class="w-full"
            filter
          />
          <Select
            v-model="selectedSemester"
            :options="semesterOptions"
            optionLabel="label"
            optionValue="value"
            :placeholder="$t('grade_analytics.trimester')"
            class="w-full"
            filter
          />
          <Select
            v-model="selectedExamType"
            :options="examTypeOptions"
            optionLabel="label"
            optionValue="value"
            :placeholder="$t('grade_analytics.exam_type')"
            class="w-full"
            filter
          />
          <Select
            v-model="selectedClassId"
            :options="classOptions"
            optionLabel="label"
            optionValue="value"
            :placeholder="$t('grade_analytics.class')"
            class="w-full"
            filter
          />
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <Select
            v-model="selectedSubjectId"
            :options="subjectOptions"
            optionLabel="label"
            optionValue="value"
            :placeholder="$t('grade_analytics.subject')"
            class="w-full"
            filter
          />
          <Select
            v-model="selectedTeacherId"
            :options="teacherOptions"
            optionLabel="label"
            optionValue="value"
            :placeholder="$t('grade_analytics.teacher')"
            class="w-full"
            filter
          />
          <Select
            v-model="selectedStudentId"
            :options="studentOptions"
            optionLabel="label"
            optionValue="value"
            :placeholder="$t('grade_analytics.student')"
            class="w-full"
            filter
          />
        </div>
      </div>
    </div>

    <div v-if="error" class="col-span-12">
      <div class="card border border-red-300 bg-red-50 text-red-700">
        <i class="pi pi-exclamation-triangle mr-2"></i>
        {{ error }}
      </div>
    </div>

    <template v-if="!loading && selectedStudentId && selectedStudentInfo">
      <!-- STUDENT BREAKDOWN VIEW -->
      <div class="col-span-12">
        <div class="card bg-blue-50 border-blue-200">
          <div class="flex items-center gap-4">
            <div class="w-16 h-16 rounded-full bg-blue-500 text-white flex items-center justify-center text-2xl font-bold">
              {{ selectedStudentInfo.first_name.charAt(0) }}{{ selectedStudentInfo.last_name.charAt(0) }}
            </div>
            <div>
              <h2 class="text-2xl font-bold m-0 text-blue-900">{{ selectedStudentInfo.first_name }} {{ selectedStudentInfo.last_name }}</h2>
              <p class="text-blue-700 m-0 mt-1">
                <i class="pi pi-id-card mr-1"></i> {{ selectedStudentInfo.code }} 
                <span v-if="selectedStudentInfo.class_id" class="ml-3"><i class="pi pi-users mr-1"></i> {{ $t('grade_analytics.class_info_available') }}</span>
              </p>
            </div>
            <div class="ml-auto flex gap-4 text-center">
              <div>
                <p class="text-sm font-semibold text-blue-700 uppercase m-0">{{ $t('grade_analytics.overall_average') }}</p>
                <p class="text-2xl font-bold text-blue-900 m-0 mt-1" :class="{'text-red-600': studentAverage < bulletinFailThreshold}">
                  {{ studentAverage }} / 20
                </p>
              </div>
              <div class="w-px bg-blue-200"></div>
              <div>
                <p class="text-sm font-semibold text-blue-700 uppercase m-0">{{ $t('grade_analytics.pass_rate') }}</p>
                <p class="text-2xl font-bold text-blue-900 m-0 mt-1" :class="{'text-red-600': studentPassRate < 50}">
                  {{ studentPassRate }}%
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-span-12 xl:col-span-4">
        <div class="card chart-card">
          <h5 class="mb-3">{{ $t('grade_analytics.subject_performance_radar') }}</h5>
          <div class="chart-wrap flex justify-center items-center">
            <Chart v-if="studentRadarChartData.labels.length > 0" type="radar" :data="studentRadarChartData" :options="radarChartOptions" class="w-full h-full" />
            <div v-else class="text-muted-color text-center py-6">{{ $t('grade_analytics.no_subject_data') }}</div>
          </div>
        </div>
      </div>

      <div class="col-span-12 xl:col-span-8">
        <div class="card">
          <div class="flex justify-between items-center mb-4">
            <h5 class="m-0">{{ $t('grade_analytics.detailed_grades_log') }}</h5>
            <span class="text-sm text-muted-color">{{ $t('grade_analytics.highlighting_low') }}</span>
          </div>
          <DataTable
            :value="studentGrades" :loading="studentGradesLoading"
            size="small" stripedRows paginator :rows="10"
            :rowClass="(data: any) => data.exam_id === selectedExamIdForExercises ? 'bg-violet-50 ring-1 ring-violet-300' : ''"
            @row-click="(e: any) => { if (e.data?.exam_id) loadExerciseAverages(e.data.exam_id) }"
            class="cursor-pointer"
          >
            <Column field="subject_name" :header="$t('grade_analytics.col_subject')" sortable></Column>
            <Column :header="$t('grade_analytics.col_exam_type')" sortable>
              <template #body="{ data }">
                <span class="font-medium text-surface-700">{{ formatExamType(data.exam?.exam_type ?? data.exam_type) }}</span>
              </template>
            </Column>
            <Column field="teacher_name" :header="$t('grade_analytics.col_teacher')" sortable></Column>
            <Column :header="$t('grade_analytics.col_grade_max')" sortable sortField="normalized_grade">
              <template #body="{ data }">
                <div class="font-semibold">
                  {{ Number(data.grade).toFixed(2) }} / {{ data.exam?.max_grade ?? data.max_grade }}
                </div>
              </template>
            </Column>
            <Column :header="$t('grade_analytics.col_status')" sortable sortField="normalized_grade">
              <template #body="{ data }">
                <Tag v-if="data.normalized_grade < bulletinFailThreshold" severity="danger" :value="$t('grade_analytics.needs_work')" rounded />
                <Tag v-else-if="data.normalized_grade >= 16" severity="success" :value="$t('grade_analytics.excellent')" rounded />
                <Tag v-else severity="info" :value="$t('grade_analytics.passing')" rounded />
              </template>
            </Column>
            <!-- Exercise breakdown per row -->
            <Column :header="$t('grade_analytics.col_exercises')" style="min-width:180px">
              <template #body="{ data }">
                <div v-if="data.exercise_grades?.length" class="flex flex-wrap gap-1">
                  <span
                    v-for="eg in data.exercise_grades"
                    :key="eg.id"
                    class="text-xs px-2 py-0.5 rounded-full bg-surface-100"
                  >
                    {{ eg.exercise?.level_name }}: <strong>{{ eg.note }}</strong>/{{ eg.exercise?.max_note }}
                  </span>
                </div>
                <span v-else class="text-xs text-muted-color">—</span>
              </template>
            </Column>
            <template #empty>
              <div class="text-center py-4 text-muted-color">{{ $t('grade_analytics.no_grades') }}</div>
            </template>
          </DataTable>
        </div>
      </div>

      <!-- ── Exercise Analytics Drilldown ─────────────────────────────────── -->
      <div class="col-span-12" v-if="selectedStudentId">
        <div class="card">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h5 class="m-0"><i class="pi pi-list-check mr-2 text-violet-500"></i>{{ $t('grade_analytics.exercise_analytics_title') }}</h5>
              <p class="text-sm text-muted-color mt-1 mb-0">{{ $t('grade_analytics.exercise_analytics_subtitle') }}</p>
            </div>
            <span v-if="exerciseAverages.length" class="text-xs text-muted-color">
              {{ exerciseAverages.length }} {{ $t('grade_analytics.exercises_count') }}
            </span>
          </div>

          <div v-if="!selectedExamIdForExercises" class="text-center py-8 text-muted-color">
            <i class="pi pi-info-circle text-2xl mb-2 block"></i>
            {{ $t('grade_analytics.exercise_select_hint') }}
          </div>

          <div v-else-if="exerciseAveragesLoading" class="text-center py-8">
            <i class="pi pi-spin pi-spinner text-2xl"></i>
          </div>

          <template v-else-if="exerciseAverages.length">
            <!-- Mini bar chart of exercise averages -->
            <div class="chart-wrap mb-4" style="height:180px">
              <Chart type="bar" :data="{
                labels: exerciseAverages.map((e: any) => e.level_name),
                datasets: [
                  {
                    label: $t('grade_analytics.col_student_note'),
                    data: exerciseAverages.map((e: any) => e.student_note !== null ? e.student_note : 0),
                    backgroundColor: 'rgba(59, 130, 246, 0.7)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1, borderRadius: 6
                  },
                  {
                    label: $t('grade_analytics.avg_note'),
                    data: exerciseAverages.map((e: any) => e.avg_note),
                    backgroundColor: 'rgba(139, 92, 246, 0.7)',
                    borderColor: 'rgba(139, 92, 246, 1)',
                    borderWidth: 1, borderRadius: 6
                  }
                ]
              }" :options="{
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: true } },
                scales: { y: { beginAtZero: true } }
              }" />
            </div>

            <!-- Exercise table -->
            <DataTable :value="exerciseAverages" size="small" stripedRows>
              <Column field="level_name" :header="t('grade_analytics.col_exercise_name')" />
              <Column field="max_note" :header="t('grade_analytics.col_max_note')" />
              <Column :header="t('grade_analytics.avg_note')">
                <template #body="{ data }">
                  <span class="font-bold text-blue-600">{{ data.student_note !== null ? data.student_note : '—' }}</span>
                </template>
              </Column>
              <Column :header="$t('grade_analytics.col_avg_note')">
                <template #body="{ data }">
                  <span class="font-bold text-violet-600">{{ data.avg_note }}</span>
                </template>
              </Column>
              <Column :header="$t('grade_analytics.col_pass_rate_ex')">
                <template #body="{ data }">
                  <div class="flex items-center gap-2">
                    <div class="flex-1 bg-surface-200 rounded-full h-1.5" style="min-width:60px">
                      <div class="h-1.5 rounded-full" :style="`width:${data.pass_rate}%;background:${data.pass_rate >= 50 ? '#10b981' : '#ef4444'}`"></div>
                    </div>
                    <span class="text-xs font-semibold">{{ data.pass_rate }}%</span>
                  </div>
                </template>
              </Column>
              <Column :header="$t('grade_analytics.col_difficulty')">
                <template #body="{ data }">
                  <Tag
                    :severity="data.pass_rate >= 70 ? 'success' : data.pass_rate >= 40 ? 'warn' : 'danger'"
                    :value="data.pass_rate >= 70 ? $t('grade_analytics.easy') : data.pass_rate >= 40 ? $t('grade_analytics.medium') : $t('grade_analytics.hard')"
                    rounded
                  />
                </template>
              </Column>
            </DataTable>
          </template>

          <!-- No exercises for this exam -->
          <div v-else-if="selectedExamIdForExercises" class="text-center py-6 text-muted-color">
            <i class="pi pi-inbox text-2xl mb-2 block"></i>
            {{ $t('grade_analytics.no_exercises') }}
          </div>
        </div>
      </div>

      <!-- Bulletin Scolaire / Report Card -->
      <div class="col-span-12" v-if="studentReportCardData">
        <div class="card shadow-lg bulletin-card">
          <div class="flex flex-col items-center mb-6 pb-4 bulletin-header-divider">
            <!-- Annual mode banner -->
            <div v-if="studentReportCardData.data?.semester === 'Bilan Annuel'"
                 class="bulletin-annual-badge mb-3">
              <i class="pi pi-chart-bar mr-2"></i>
              {{ $t('grade_analytics.bulletin_annual') }}
            </div>
            <h3 class="text-2xl font-bold uppercase text-center w-full bulletin-title">
              <!-- Single trimester -->
              <span v-if="studentReportCardData.data?.semester !== 'Bilan Annuel'">
                {{ $t('grade_analytics.bulletin_title') }} &mdash; {{ studentReportCardData.data?.semester }}
              </span>
              <!-- Annual -->
              <span v-else>
                {{ $t('grade_analytics.bulletin_title') }} &mdash; {{ studentReportCardData.data?.academic_year }}
              </span>
            </h3>
            <p class="text-muted-color mt-2">{{ $t('grade_analytics.bulletin_subtitle') }}</p>
            <!-- Annual note -->
            <p v-if="studentReportCardData.data?.semester === 'Bilan Annuel'"
               class="text-xs mt-1 bulletin-annual-note">
              ∑ (T1 + T2 + T3) ÷ 3
            </p>
          </div>

          <div class="grid grid-cols-2 gap-4 mb-6 text-sm font-semibold p-4 rounded bulletin-info-bar">
            <div>
              <p class="mb-2">
                <span class="text-muted-color mr-2">{{ $t('grade_analytics.bulletin_student') }} :</span>
                <span class="text-lg text-primary font-bold">{{ studentReportCardData.data?.student?.first_name }} {{ studentReportCardData.data?.student?.last_name }}</span>
              </p>
              <p><span class="text-muted-color mr-2">{{ $t('grade_analytics.bulletin_class') }} :</span> {{ studentReportCardData.data?.student?.class?.name || $t('grade_analytics.unknown') }}</p>
            </div>
            <div class="text-right">
              <p class="mb-2"><span class="text-muted-color mr-2">{{ $t('grade_analytics.bulletin_year') }} :</span> {{ studentReportCardData.data?.academic_year }}</p>
              <p v-if="studentReportCardData.data?.student?.code"><span class="text-muted-color mr-2">{{ $t('grade_analytics.bulletin_id') }} :</span> {{ studentReportCardData.data?.student?.code }}</p>
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg bulletin-table-wrap">
            <table class="w-full text-center border-collapse bulletin-table">
              <thead class="bulletin-thead">
                <tr>
                  <th class="bulletin-th text-left" style="width: 25%;">{{ $t('grade_analytics.col_subject_matter') }}</th>
                  <th class="bulletin-th" style="width: 7%;">{{ $t('grade_analytics.col_coef') }}</th>
                  <!-- Dynamic Exam Type Headers -->
                  <th v-for="type in bulletinTypeColumns" :key="type" class="bulletin-th">
                    {{ formatExamTypeHeader(type) }}
                  </th>
                  <th class="bulletin-th bulletin-avg-th">{{ $t('grade_analytics.col_avg') }}</th>
                  <th class="bulletin-th bulletin-total-header">{{ $t('grade_analytics.col_weighted') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(sub, index) in studentReportCardData.data?.subjects || []" :key="index" class="bulletin-row">
                  <td class="bulletin-td text-left">
                    <div class="font-bold">{{ sub.subject?.name || $t('grade_analytics.unknown') }}</div>
                    <div class="text-xs text-muted-color mt-1" v-if="sub.teacher">{{ $t('grade_analytics.prof_prefix') }} {{ sub.teacher?.last_name }}</div>
                  </td>
                  <td class="bulletin-td font-bold">{{ sub.coefficient }}</td>
                  
                  <!-- Dynamic Exam Type Grades -->
                  <td v-for="type in bulletinTypeColumns" :key="type" class="bulletin-td" 
                      :class="{ 'bulletin-grade-fail': type === 'composition' ? (sub[type] !== '-' && Number(sub[type]) * 2 < bulletinFailThreshold * 2) : isFailing(sub[type]) }">
                    <template v-if="type === 'composition'">
                      {{ sub[type] !== '-' ? (Number(sub[type]) * 2).toFixed(2) : '-' }}
                    </template>
                    <template v-else>
                      {{ sub[type] }}
                    </template>
                  </td>
                  <!-- Average /20 -->
                  <td class="bulletin-td font-bold bulletin-avg-td" :class="{ 'bulletin-grade-fail': isFailing(sub.average) }">
                    {{ sub.average }}
                  </td>
                  <!-- Weighted total -->
                  <td class="bulletin-td font-bold bulletin-total-cell" :class="{ 'bulletin-grade-fail-total': isFailing(sub.average) }">
                    {{ sub.weighted_average }}
                  </td>
                </tr>
              </tbody>
              <tfoot class="bulletin-tfoot">
                <tr>
                  <td colspan="6" class="p-4 text-right font-bold uppercase bulletin-td" style="letter-spacing: 0.05em; border-right: 1px solid var(--p-content-border-color);">
                    {{ $t('grade_analytics.overall_trimester_avg') }} :
                  </td>
                  <td class="p-4 text-center bulletin-td">
                    <div class="text-xl font-black" :style="Number(studentReportCardData.data?.overall_average || 0) >= bulletinFailThreshold ? 'color: var(--p-green-500)' : 'color: var(--p-red-500)'">
                      {{ Number(studentReportCardData.data?.overall_average || 0).toFixed(2) }}
                      <span class="text-sm font-normal text-muted-color">/ 20</span>
                    </div>
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>

          <div class="mt-5 p-4 rounded italic text-sm bulletin-appreciation">
            <strong>{{ $t('grade_analytics.appreciation_label') }}</strong>
            <span v-if="Number(studentReportCardData.data?.overall_average || 0) >= 16"> {{ $t('grade_analytics.appreciation_excellent') }}</span>
            <span v-else-if="Number(studentReportCardData.data?.overall_average || 0) >= 14"> {{ $t('grade_analytics.appreciation_very_good') }}</span>
            <span v-else-if="Number(studentReportCardData.data?.overall_average || 0) >= 12"> {{ $t('grade_analytics.appreciation_good') }}</span>
            <span v-else-if="Number(studentReportCardData.data?.overall_average || 0) >= bulletinFailThreshold"> {{ $t('grade_analytics.appreciation_satisfactory') }}</span>
            <span v-else> {{ $t('grade_analytics.appreciation_insufficient') }}</span>
          </div>
        </div>
      </div>
    </template>

    <template v-else-if="!loading && selectedClassId && selectedClassInfo && !selectedStudentId">
      <!-- CLASS BREAKDOWN VIEW -->
      <div class="col-span-12">
        <div class="card bg-green-50 border-green-200">
          <div class="flex items-center gap-4">
            <div class="w-16 h-16 rounded-full bg-green-500 text-white flex items-center justify-center text-2xl font-bold">
              <i class="pi pi-users"></i>
            </div>
            <div>
              <h2 class="text-2xl font-bold m-0 text-green-900">{{ selectedClassInfo.name }}</h2>
              <p class="text-green-700 m-0 mt-1">{{ $t('grade_analytics.class_profile') }}</p>
              <p v-if="selectedSubjectInfo || selectedTeacherInfo" class="text-green-700/80 m-0 mt-1 text-sm">
                <span v-if="selectedSubjectInfo">{{ selectedSubjectInfo.name }}</span>
                <span v-if="selectedSubjectInfo && selectedTeacherInfo"> · </span>
                <span v-if="selectedTeacherInfo">{{ $t('grade_analytics.prof_prefix') }} {{ selectedTeacherInfo.first_name }} {{ selectedTeacherInfo.last_name }}</span>
              </p>
            </div>
            <div class="ml-auto flex gap-4 text-center">
              <div>
                <p class="text-sm font-semibold text-green-700 uppercase m-0">{{ $t('grade_analytics.class_average') }}</p>
                <p class="text-2xl font-bold text-green-900 m-0 mt-1">{{ stats.average }} / 20</p>
              </div>
              <div class="w-px bg-green-200"></div>
              <div>
                <p class="text-sm font-semibold text-green-700 uppercase m-0">{{ $t('grade_analytics.pass_rate') }}</p>
                <p class="text-2xl font-bold text-green-900 m-0 mt-1">{{ stats.passRate }}%</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Top and Bottom Students -->
       <div class="col-span-12 lg:col-span-6">
        <div class="card">
          <h5 class="mb-3 text-emerald-600 border-b pb-2"><i class="pi pi-star mr-2"></i> {{ $t('grade_analytics.top_students') }}</h5>
          <DataTable :value="classTopStudents" :loading="classGradesLoading" size="small" stripedRows>
            <Column field="student_name" :header="$t('grade_analytics.student')" />
            <Column :header="$t('grade_analytics.average')" sortable sortField="average">
               <template #body="{ data }">
                 <span class="font-bold text-emerald-600">{{ data.average }}</span>
               </template>
            </Column>
          </DataTable>
        </div>
      </div>

      <div class="col-span-12 lg:col-span-6">
        <div class="card">
          <h5 class="mb-3 text-rose-600 border-b pb-2"><i class="pi pi-exclamation-triangle mr-2"></i> {{ $t('grade_analytics.needs_attention') }}</h5>
          <DataTable :value="classBottomStudents" :loading="classGradesLoading" size="small" stripedRows>
            <Column field="student_name" :header="$t('grade_analytics.student')" />
            <Column :header="$t('grade_analytics.average')" sortable sortField="average">
               <template #body="{ data }">
                 <span class="font-bold" :class="{'text-rose-600': data.average < 10}">{{ data.average }}</span>
               </template>
            </Column>
            <Column :header="$t('grade_analytics.col_status')">
               <template #body="{ data }">
                  <Tag v-if="data.average < 10" severity="danger" :value="$t('grade_analytics.failing_status')" />
                  <Tag v-else severity="warning" :value="$t('grade_analytics.at_risk')" />
               </template>
            </Column>
          </DataTable>
        </div>
      </div>

      <!-- Subject Breakdown Chart for Class -->
      <div class="col-span-12 xl:col-span-6">
        <div class="card chart-card">
          <div class="flex justify-between items-center mb-3">
             <h5 class="m-0">{{ $t('grade_analytics.subject_averages') }}</h5>
          </div>
          <div class="chart-wrap">
            <Chart type="bar" :data="subjectAverageChartData" :options="baseChartOptions" />
          </div>
        </div>
      </div>

      <!-- Teacher Impact for Class -->
      <div class="col-span-12 xl:col-span-6">
         <div class="card chart-card">
          <div class="flex justify-between items-center mb-3">
            <h5 class="m-0">{{ $t('grade_analytics.teacher_impact') }}</h5>
          </div>
          <DataTable :value="classTeacherBreakdown" size="small" stripedRows>
            <Column field="teacher_name" :header="$t('grade_analytics.teacher')" />
            <Column field="subjects" :header="$t('grade_analytics.col_subject_lbl')" />
            <Column field="average" :header="$t('grade_analytics.col_avg_20')" />
            <Column field="passRate" :header="$t('grade_analytics.col_pass_pct')" />
          </DataTable>
        </div>
      </div>

      <div v-if="(selectedSubjectId || selectedTeacherId) && showExerciseAnalytics" class="col-span-12">
        <div class="card">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h5 class="m-0"><i class="pi pi-compass mr-2 text-violet-500"></i>{{ $t('grade_analytics.exercise_analytics_title') }}</h5>
              <p class="text-sm text-muted-color mt-1 mb-0">
                {{ selectedClassInfo.name }}
                <span v-if="selectedSubjectInfo"> · {{ selectedSubjectInfo.name }}</span>
                <span v-if="selectedTeacherInfo"> · {{ $t('grade_analytics.prof_prefix') }} {{ selectedTeacherInfo.first_name }} {{ selectedTeacherInfo.last_name }}</span>
              </p>
            </div>
            <span v-if="subjectExerciseAverages.length" class="text-xs text-muted-color">
              {{ subjectExerciseAverages.length }} {{ $t('grade_analytics.exercises_count') }}
            </span>
          </div>

          <div v-if="subjectExerciseLoading" class="text-center py-8">
            <i class="pi pi-spin pi-spinner text-2xl"></i>
          </div>

          <template v-else-if="subjectExerciseAverages.length">
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-4">
              <div class="chart-wrap" style="height:250px">
                <Chart type="radar" :data="subjectExerciseRadarData" :options="{ responsive: true, maintainAspectRatio: false, scales: { r: { min: 0 } } }" />
              </div>
              <div class="chart-wrap" style="height:250px">
                <Chart type="bar" :data="subjectExerciseBarData" :options="{ responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { min: 0 } } }" />
              </div>
            </div>

            <DataTable :value="subjectExerciseAverages" size="small" stripedRows>
              <Column field="level_name" :header="t('grade_analytics.col_exercise_name')" />
              <Column field="records_count" :header="t('grade_analytics.col_records')" />
              <Column :header="t('grade_analytics.avg_note')">
                <template #body="{ data }">
                  <div class="flex items-center gap-2">
                    <div class="flex-1 bg-surface-200 rounded-full h-1.5" style="min-width:60px">
                      <div class="h-1.5 rounded-full" :style="`width:${data.max_note > 0 ? (data.avg_note / data.max_note) * 100 : 0}%;background:${(data.max_note > 0 ? (data.avg_note / data.max_note) * 100 : 0) >= 70 ? '#10b981' : (data.max_note > 0 ? (data.avg_note / data.max_note) * 100 : 0) >= 40 ? '#f59e0b' : '#ef4444'}`"></div>
                    </div>
                    <span class="text-xs font-semibold" :class="(data.max_note > 0 ? (data.avg_note / data.max_note) * 100 : 0) >= 70 ? 'text-emerald-600' : (data.max_note > 0 ? (data.avg_note / data.max_note) * 100 : 0) >= 40 ? 'text-amber-500' : 'text-rose-600'">
                      {{ data.avg_note }} / {{ data.max_note }}
                    </span>
                  </div>
                </template>
              </Column>
              <Column :header="t('grade_analytics.col_difficulty')">
                <template #body="{ data }">
                  <Tag
                    :severity="(data.max_note > 0 ? (data.avg_note / data.max_note) * 100 : 0) >= 70 ? 'success' : (data.max_note > 0 ? (data.avg_note / data.max_note) * 100 : 0) >= 40 ? 'warn' : 'danger'"
                    :value="(data.max_note > 0 ? (data.avg_note / data.max_note) * 100 : 0) >= 70 ? t('grade_analytics.easy') : (data.max_note > 0 ? (data.avg_note / data.max_note) * 100 : 0) >= 40 ? t('grade_analytics.medium') : t('grade_analytics.hard')"
                    rounded
                  />
                </template>
              </Column>
            </DataTable>
          </template>

          <div v-else class="text-center py-6 text-muted-color">
            <i class="pi pi-inbox text-2xl mb-2 block"></i>
            {{ t('grade_analytics.no_exercises') }}
          </div>
        </div>
      </div>

      <!-- Full Class Student Rankings table + Grade Distribution Donut -->
      <div v-if="showExerciseAnalytics" class="col-span-12">
        <!-- Grade Distribution Donut Chart with Customizable Ranges -->
        <div class="card mb-4">
          <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
            <div>
              <h5 class="m-0"><i class="pi pi-chart-pie mr-2 text-violet-500"></i>{{ $t('grade_analytics.grade_distribution') }}</h5>
              <p class="text-sm text-muted-color mt-1 mb-0">
                <span v-if="selectedDonutExerciseId">
                  {{ donutExerciseOptions.find(o => o.id === selectedDonutExerciseId)?.level_name }}
                  &mdash; {{ $t('grade_analytics.distribution_subtitle') }}
                </span>
                <span v-else>{{ $t('grade_analytics.distribution_subtitle') }}</span>
              </p>
            </div>
              <div class="flex items-center gap-2">
              <!-- Exercise filter for the donut chart -->
              <Select
                v-if="donutExerciseOptions.length > 1"
                v-model="selectedDonutExerciseId"
                :options="donutExerciseOptions"
                optionLabel="level_name"
                optionValue="id"
                :placeholder="$t('grade_analytics.filter_by_exercise')"
                size="small"
                class="w-52"
              />
              <Button
                :icon="showRangeEditor ? 'pi pi-times' : 'pi pi-sliders-h'"
                :label="showRangeEditor ? $t('grade_analytics.close_ranges') : $t('grade_analytics.customize_ranges')"
                severity="secondary"
                outlined
                size="small"
                @click="showRangeEditor = !showRangeEditor"
              />
            </div>
          </div>

          <!-- Range editor panel -->
          <div v-if="showRangeEditor" class="range-editor mb-4 p-4 rounded-lg border">
            <p class="text-sm font-semibold mb-3"><i class="pi pi-sliders-h mr-2"></i>{{ $t('grade_analytics.ranges_editor_title') }}</p>
            <div class="flex flex-col gap-2">
              <div v-for="(range, idx) in activeGradeRanges" :key="idx" class="flex items-center gap-2">
                <span class="text-sm text-muted-color font-medium">{{ $t('grade_analytics.range_from') }}</span>
                <InputNumber :modelValue="range.from" @update:modelValue="updateRangeValue(idx, 'from', $event)" :min="0" :max="currentMaxNote" :step="0.5" inputClass="w-16" size="small" />
                <span class="text-sm text-muted-color font-medium">{{ $t('grade_analytics.range_to') }} &lt;</span>
                <InputNumber :modelValue="range.to" @update:modelValue="updateRangeValue(idx, 'to', $event)" :min="0" :max="currentMaxNote" :step="0.5" inputClass="w-16" size="small" />
                <Button icon="pi pi-trash" severity="danger" text size="small" @click="removeGradeRange(idx)" :disabled="gradeRanges.length <= 1" />
                <div
                  class="w-4 h-4 rounded-full flex-shrink-0"
                  :style="{ background: DONUT_COLORS[idx % DONUT_COLORS.length] }"
                ></div>
              </div>
            </div>
            <Button
              icon="pi pi-plus"
              :label="$t('grade_analytics.add_range')"
              severity="secondary"
              text
              size="small"
              class="mt-3"
              @click="addGradeRange"
              :disabled="gradeRanges.length >= 8"
            />
          </div>

          <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 items-center">
            <!-- Donut chart -->
            <div class="xl:col-span-2 flex justify-center" style="height:260px">
              <Chart
                type="doughnut"
                :data="classGradeDistributionData"
                :options="classGradeDistributionOptions"
                class="w-full h-full"
              />
            </div>
            <!-- Legend / stats sidebar -->
            <div class="flex flex-col gap-2">
              <div
                v-for="(range, idx) in activeGradeRanges"
                :key="idx"
                class="flex items-center justify-between p-2 rounded-lg text-sm"
                :style="{ background: DONUT_COLORS[idx % DONUT_COLORS.length].replace('0.8', '0.12'), borderLeft: `4px solid ${DONUT_COLORS[idx % DONUT_COLORS.length]}` }"
              >
                <span class="font-medium">{{ rangeLabel(range, idx, activeGradeRanges.length) }}</span>
                <span class="font-bold ml-2">{{ classGradeDistributionData.datasets[0].data[idx] }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Rankings Table -->
        <div class="card">
          <div class="flex flex-wrap justify-between items-center gap-2 mb-4">
            <h5 class="m-0">
              <i class="pi pi-users mr-2 text-primary"></i>
              {{ $t('grade_analytics.class_student_rankings') }}
            </h5>
            <div class="flex gap-4 items-center">
              <IconField>
                <InputIcon class="pi pi-search" />
                <InputText v-model="studentRankingsFilters['global'].value" :placeholder="t('grade_analytics.search_students')" />
              </IconField>
              <span class="text-sm text-muted-color">{{ classRankingRows.length }} {{ t('common.students') }}</span>
            </div>
          </div>
          <DataTable
            :value="classRankingRows"
            size="small"
            stripedRows
            paginator
            :rows="20"
            :loading="loading"
            v-model:filters="studentRankingsFilters"
            :globalFilterFields="['student_name', 'class_name', 'best_subject']"
            scrollable
          >
            <Column :header="t('grade_analytics.col_rank')" style="width: 3rem" frozen>
              <template #body="{ index }">
                <span
                  class="font-bold"
                  :class="index === 0 ? 'text-amber-500' : index === 1 ? 'text-slate-400' : index === 2 ? 'text-orange-600' : 'text-muted-color'"
                >
                  {{ index + 1 }}
                </span>
              </template>
            </Column>
            <Column field="student_name" :header="t('grade_analytics.student')" sortable frozen>
              <template #body="{ data, index }">
                <div class="flex items-center gap-2">
                  <i v-if="index === 0" class="pi pi-trophy text-amber-500"></i>
                  <span class="font-medium">{{ data.student_name }}</span>
                </div>
              </template>
            </Column>
            <Column field="average" :header="t('grade_analytics.overall_average')" sortable>
              <template #body="{ data }">
                <div
                  class="font-bold px-2 py-1 rounded text-center inline-block min-w-12"
                  :class="data.average >= 16 ? 'bg-emerald-100 text-emerald-700'
                    : data.average >= getClassFailThreshold(data.class_id || selectedClassId) ? 'bg-blue-50 text-blue-700'
                    : 'bg-rose-100 text-rose-700'"
                >
                  {{ data.average }}
                </div>
              </template>
            </Column>
            <!-- Dynamic exercise columns -->
            <Column
              v-for="ex in classRankingExercises"
              :key="ex.id"
              :header="ex.level_name + ' (/' + ex.max_note + ')'"
              :sortField="`ex_${ex.id}`"
              sortable
            >
              <template #body="{ data }">
                <span
                  v-if="data[`ex_${ex.id}`] !== null && data[`ex_${ex.id}`] !== undefined"
                  class="font-semibold px-2 py-0.5 rounded text-xs"
                  :class="data[`ex_${ex.id}`] < ex.max_note / 2
                    ? 'bg-rose-100 text-rose-700'
                    : 'bg-emerald-50 text-emerald-700'"
                >
                  {{ data[`ex_${ex.id}`] }}
                </span>
                <span v-else class="text-muted-color text-xs">—</span>
              </template>
            </Column>
            <Column field="best_subject" :header="t('grade_analytics.best_subject')" sortable />
            <Column field="passRate" :header="t('grade_analytics.pass_rate')" sortable>
              <template #body="{ data }">
                <div class="flex items-center gap-2">
                  <div class="flex-1 bg-surface-200 rounded-full h-1.5" style="min-width:50px">
                    <div
                      class="h-1.5 rounded-full"
                      :class="data.passRate >= 70 ? 'bg-emerald-500' : data.passRate >= 40 ? 'bg-amber-400' : 'bg-rose-500'"
                      :style="{ width: data.passRate + '%' }"
                    ></div>
                  </div>
                  <span class="text-xs font-semibold w-10 text-right">{{ data.passRate }}%</span>
                </div>
              </template>
            </Column>
            <template #empty>
              <div class="text-center py-4 text-muted-color">{{ t('common.no_data_available') }}</div>
            </template>
          </DataTable>
        </div>
      </div>
    </template>

    <template v-else-if="!loading && selectedTeacherId && selectedTeacherInfo && !selectedStudentId && !selectedClassId && !selectedSubjectId">
      <!-- TEACHER BREAKDOWN VIEW -->
      <div class="col-span-12">
        <div class="card bg-orange-50 border-orange-200">
          <div class="flex items-center gap-4">
            <div class="w-16 h-16 rounded-full bg-orange-500 text-white flex items-center justify-center text-2xl font-bold">
              {{ selectedTeacherInfo.first_name.charAt(0) }}{{ selectedTeacherInfo.last_name.charAt(0) }}
            </div>
            <div>
              <h2 class="text-2xl font-bold m-0 text-orange-900">{{ $t('grade_analytics.prof_prefix') }} {{ selectedTeacherInfo.first_name }} {{ selectedTeacherInfo.last_name }}</h2>
              <p class="text-orange-700 m-0 mt-1">{{ $t('grade_analytics.teacher_analytics_profile') }}</p>
            </div>
            <div class="ml-auto flex gap-4 text-center">
              <div>
                <p class="text-sm font-semibold text-orange-700 uppercase m-0">{{ $t('grade_analytics.average') }}</p>
                <p class="text-2xl font-bold text-orange-900 m-0 mt-1">{{ stats.average }} / 20</p>
              </div>
              <div class="w-px bg-orange-200"></div>
              <div>
                <p class="text-sm font-semibold text-orange-700 uppercase m-0">{{ $t('grade_analytics.stat_records') }}</p>
                <p class="text-2xl font-bold text-orange-900 m-0 mt-1">{{ stats.records }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Grade Distribution Donut Chart with Customizable Ranges (Teacher View) -->
      <div class="col-span-12">
        <div class="card mb-4">
          <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
            <div>
              <h5 class="m-0"><i class="pi pi-chart-pie mr-2 text-violet-500"></i>{{ $t('grade_analytics.grade_distribution') }}</h5>
              <p class="text-sm text-muted-color mt-1 mb-0">{{ $t('grade_analytics.distribution_subtitle') }}</p>
            </div>
            <Button
              :icon="showRangeEditor ? 'pi pi-times' : 'pi pi-sliders-h'"
              :label="showRangeEditor ? $t('grade_analytics.close_ranges') : $t('grade_analytics.customize_ranges')"
              severity="secondary"
              outlined
              size="small"
              @click="showRangeEditor = !showRangeEditor"
            />
          </div>

          <!-- Range editor panel -->
          <div v-if="showRangeEditor" class="range-editor mb-4 p-4 rounded-lg border">
            <p class="text-sm font-semibold mb-3"><i class="pi pi-sliders-h mr-2"></i>{{ $t('grade_analytics.ranges_editor_title') }}</p>
            <div class="flex flex-col gap-2">
              <div v-for="(range, idx) in gradeRanges" :key="idx" class="flex items-center gap-2">
                <InputText v-model="range.label" :placeholder="$t('grade_analytics.range_label')" class="w-36" size="small" />
                <span class="text-sm text-muted-color">{{ $t('grade_analytics.range_from') }}</span>
                <InputNumber v-model="range.from" :min="0" :max="20" :step="0.5" inputClass="w-16" size="small" />
                <span class="text-sm text-muted-color">{{ $t('grade_analytics.range_to') }}</span>
                <InputNumber v-model="range.to" :min="0" :max="20" :step="0.5" inputClass="w-16" size="small" />
                <Button icon="pi pi-trash" severity="danger" text size="small" @click="removeGradeRange(idx)" :disabled="gradeRanges.length <= 1" />
                <div class="w-4 h-4 rounded-full flex-shrink-0" :style="{ background: DONUT_COLORS[idx % DONUT_COLORS.length] }"></div>
              </div>
            </div>
            <Button
              icon="pi pi-plus"
              :label="$t('grade_analytics.add_range')"
              severity="secondary"
              text
              size="small"
              class="mt-3"
              @click="addGradeRange"
              :disabled="gradeRanges.length >= 8"
            />
          </div>

          <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 items-center">
            <div class="xl:col-span-2 flex justify-center" style="height:260px">
              <Chart type="doughnut" :data="classGradeDistributionData" :options="classGradeDistributionOptions" class="w-full h-full" />
            </div>
            <div class="flex flex-col gap-2">
              <div
                v-for="(range, idx) in gradeRanges" :key="idx"
                class="flex items-center justify-between p-2 rounded-lg text-sm"
                :style="{ background: DONUT_COLORS[idx % DONUT_COLORS.length].replace('0.8', '0.12'), borderLeft: `4px solid ${DONUT_COLORS[idx % DONUT_COLORS.length]}` }"
              >
                <span class="font-medium">{{ range.label || `${range.from}–${range.to}` }}</span>
                <span class="font-bold ml-2">{{ classGradeDistributionData.datasets[0].data[idx] }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Class Performance Matrix -->
      <div class="col-span-12">
        <div class="card">
          <h5 class="mb-3">{{ $t('grade_analytics.class_performance_matrix') }}</h5>
          <DataTable :value="classAggregates" size="small" stripedRows paginator :rows="10">
            <Column field="label" :header="$t('grade_analytics.class')" sortable></Column>
            <Column field="average" :header="$t('grade_analytics.col_avg_20')" sortable></Column>
            <Column field="passRate" :header="$t('grade_analytics.col_pass_pct')" sortable></Column>
            <Column field="stdDev" :header="$t('grade_analytics.col_std_dev')" sortable></Column>
            <Column :header="$t('grade_analytics.col_status')">
              <template #body="{ data }">
                <Tag v-if="data.average < 10" severity="danger" :value="$t('grade_analytics.poor_status')" />
                <Tag v-else-if="data.average >= 14" severity="success" :value="$t('grade_analytics.excellent')" />
                <Tag v-else severity="info" :value="$t('grade_analytics.average_status')" />
              </template>
            </Column>
          </DataTable>
        </div>
      </div>

      <!-- Subject Exercise Analytics Drilldown (Teacher View) -->
      <div class="col-span-12">
        <div class="card">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h5 class="m-0"><i class="pi pi-compass mr-2 text-violet-500"></i>{{ $t('grade_analytics.exercise_analytics_title') }}</h5>
              <p class="text-sm text-muted-color mt-1 mb-0">{{ $t('grade_analytics.exercise_analytics_subtitle') }}</p>
            </div>
            <span v-if="subjectExerciseAverages.length" class="text-xs text-muted-color">
              {{ subjectExerciseAverages.length }} {{ t('grade_analytics.exercises_count') }}
            </span>
          </div>

          <div v-if="subjectExerciseLoading" class="text-center py-8">
            <i class="pi pi-spin pi-spinner text-2xl"></i>
          </div>

          <template v-else-if="subjectExerciseAverages.length">
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-4">
              <div class="chart-wrap" style="height:250px">
                <Chart type="radar" :data="subjectExerciseRadarData" :options="{ responsive: true, maintainAspectRatio: false, scales: { r: { min: 0 } } }" />
              </div>
              <div class="chart-wrap" style="height:250px">
                <Chart type="bar" :data="subjectExerciseBarData" :options="{ responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { min: 0 } } }" />
              </div>
            </div>
            <DataTable :value="subjectExerciseAverages" size="small" stripedRows>
              <Column field="level_name" :header="t('grade_analytics.col_exercise_name')" />
              <Column field="records_count" :header="t('grade_analytics.col_records')" />
              <Column :header="t('grade_analytics.avg_note')">
                <template #body="{ data }">
                  <div class="flex items-center gap-2">
                    <div class="flex-1 bg-surface-200 rounded-full h-1.5" style="min-width:60px">
                      <div class="h-1.5 rounded-full" :style="`width:${data.max_note > 0 ? (data.avg_note / data.max_note) * 100 : 0}%;background:${(data.max_note > 0 ? (data.avg_note / data.max_note) * 100 : 0) >= 70 ? '#10b981' : (data.max_note > 0 ? (data.avg_note / data.max_note) * 100 : 0) >= 40 ? '#f59e0b' : '#ef4444'}`"></div>
                    </div>
                    <span class="text-xs font-semibold" :class="(data.max_note > 0 ? (data.avg_note / data.max_note) * 100 : 0) >= 70 ? 'text-emerald-600' : (data.max_note > 0 ? (data.avg_note / data.max_note) * 100 : 0) >= 40 ? 'text-amber-500' : 'text-rose-600'">
                      {{ data.avg_note }} / {{ data.max_note }}
                    </span>
                  </div>
                </template>
              </Column>
              <Column :header="t('grade_analytics.col_difficulty')">
                <template #body="{ data }">
                  <Tag
                    :severity="(data.max_note > 0 ? (data.avg_note / data.max_note) * 100 : 0) >= 70 ? 'success' : (data.max_note > 0 ? (data.avg_note / data.max_note) * 100 : 0) >= 40 ? 'warn' : 'danger'"
                    :value="(data.max_note > 0 ? (data.avg_note / data.max_note) * 100 : 0) >= 70 ? t('grade_analytics.easy') : (data.max_note > 0 ? (data.avg_note / data.max_note) * 100 : 0) >= 40 ? t('grade_analytics.medium') : t('grade_analytics.hard')"
                    rounded
                  />
                </template>
              </Column>
            </DataTable>
          </template>

          <div v-else class="text-center py-6 text-muted-color">
            <i class="pi pi-inbox text-2xl mb-2 block"></i>
            {{ $t('grade_analytics.no_exercises') }}
          </div>
        </div>
      </div>

      <!-- Student Rankings Table with Exercise Columns (Teacher View) -->
      <div v-if="showExerciseAnalytics" class="col-span-12">
        <div class="card">
          <div class="flex flex-wrap justify-between items-center gap-2 mb-4">
            <h5 class="m-0">
              <i class="pi pi-users mr-2 text-primary"></i>
              {{ $t('grade_analytics.class_student_rankings') }}
            </h5>
            <div class="flex gap-4 items-center">
              <IconField>
                <InputIcon class="pi pi-search" />
                <InputText v-model="studentRankingsFilters['global'].value" :placeholder="t('grade_analytics.search_students')" />
              </IconField>
              <span class="text-sm text-muted-color">{{ classRankingRows.length }} {{ t('common.students') }}</span>
            </div>
          </div>
          <DataTable
            :value="classRankingRows"
            size="small"
            stripedRows
            paginator
            :rows="20"
            :loading="loading || classGradesLoading"
            v-model:filters="studentRankingsFilters"
            :globalFilterFields="['student_name', 'class_name', 'best_subject']"
            scrollable
          >
            <Column :header="t('grade_analytics.col_rank')" style="width: 3rem" frozen>
              <template #body="{ index }">
                <span class="font-bold" :class="index === 0 ? 'text-amber-500' : index === 1 ? 'text-slate-400' : index === 2 ? 'text-orange-600' : 'text-muted-color'">
                  {{ index + 1 }}
                </span>
              </template>
            </Column>
            <Column field="student_name" :header="t('grade_analytics.student')" sortable frozen>
              <template #body="{ data, index }">
                <div class="flex items-center gap-2">
                  <i v-if="index === 0" class="pi pi-trophy text-amber-500"></i>
                  <span class="font-medium">{{ data.student_name }}</span>
                </div>
              </template>
            </Column>
            <Column field="class_name" :header="t('grade_analytics.class')" sortable />
            <Column field="average" :header="t('grade_analytics.overall_average')" sortable>
              <template #body="{ data }">
                <div
                  class="font-bold px-2 py-1 rounded text-center inline-block min-w-12"
                  :class="data.average >= 16 ? 'bg-emerald-100 text-emerald-700'
                    : data.average >= getClassFailThreshold(data.class_id || null) ? 'bg-blue-50 text-blue-700'
                    : 'bg-rose-100 text-rose-700'"
                >
                  {{ data.average }}
                </div>
              </template>
            </Column>
            <!-- Dynamic exercise columns -->
            <Column
              v-for="ex in classRankingExercises"
              :key="ex.id"
              :header="ex.level_name + ' (/' + ex.max_note + ')'"
              :sortField="`ex_${ex.id}`"
              sortable
            >
              <template #body="{ data }">
                <span
                  v-if="data[`ex_${ex.id}`] !== null && data[`ex_${ex.id}`] !== undefined"
                  class="font-semibold px-2 py-0.5 rounded text-xs"
                  :class="data[`ex_${ex.id}`] < ex.max_note / 2 ? 'bg-rose-100 text-rose-700' : 'bg-emerald-50 text-emerald-700'"
                >
                  {{ data[`ex_${ex.id}`] }}
                </span>
                <span v-else class="text-muted-color text-xs">—</span>
              </template>
            </Column>
            <Column field="best_subject" :header="t('grade_analytics.best_subject')" sortable />
            <Column field="passRate" :header="t('grade_analytics.pass_rate')" sortable>
              <template #body="{ data }">
                <div class="flex items-center gap-2">
                  <div class="flex-1 bg-surface-200 rounded-full h-1.5" style="min-width:50px">
                    <div
                      class="h-1.5 rounded-full"
                      :class="data.passRate >= 70 ? 'bg-emerald-500' : data.passRate >= 40 ? 'bg-amber-400' : 'bg-rose-500'"
                      :style="{ width: data.passRate + '%' }"
                    ></div>
                  </div>
                  <span class="text-xs font-semibold w-10 text-right">{{ data.passRate }}%</span>
                </div>
              </template>
            </Column>
            <template #empty>
              <div class="text-center py-4 text-muted-color">{{ t('common.no_data_available') }}</div>
            </template>
          </DataTable>
        </div>
      </div>
    </template>

    <template v-else-if="!loading && selectedSubjectId && selectedSubjectInfo && !selectedStudentId && !selectedClassId">
      <!-- SUBJECT BREAKDOWN VIEW -->
      <div class="col-span-12">
        <div class="card bg-purple-50 border-purple-200">
          <div class="flex items-center gap-4">
            <div class="w-16 h-16 rounded-full bg-purple-500 text-white flex items-center justify-center text-2xl font-bold">
              <i class="pi pi-book"></i>
            </div>
            <div>
              <h2 class="text-2xl font-bold m-0 text-purple-900">{{ selectedSubjectInfo.name }}</h2>
              <p class="text-purple-700 m-0 mt-1">{{ $t('grade_analytics.subject_analytics_profile') }}</p>
            </div>
            <div class="ml-auto flex gap-4 text-center">
              <div>
                <p class="text-sm font-semibold text-purple-700 uppercase m-0">{{ $t('grade_analytics.overall_average') }}</p>
                <p class="text-2xl font-bold text-purple-900 m-0 mt-1">{{ stats.average }} / 20</p>
              </div>
              <div class="w-px bg-purple-200"></div>
              <div>
                <p class="text-sm font-semibold text-purple-700 uppercase m-0">{{ $t('grade_analytics.pass_rate') }}</p>
                <p class="text-2xl font-bold text-purple-900 m-0 mt-1">{{ stats.passRate }}%</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-span-12 xl:col-span-6">
        <div class="card chart-card">
          <div class="flex justify-between items-center mb-3">
             <h5 class="m-0">{{ $t('grade_analytics.chart_avg_by_class') }}</h5>
          </div>
          <div class="chart-wrap">
            <Chart type="bar" :data="classAverageChartData" :options="baseChartOptions" />
          </div>
        </div>
      </div>

      <div class="col-span-12 xl:col-span-6">
         <div class="card chart-card">
          <div class="flex justify-between items-center mb-3">
            <h5 class="m-0">{{ $t('grade_analytics.chart_teacher_perf') }}</h5>
          </div>
          <DataTable :value="teacherAggregates" size="small" stripedRows paginator :rows="10">
            <Column field="label" :header="$t('grade_analytics.teacher')" sortable />
            <Column field="average" :header="$t('grade_analytics.col_avg_20')" sortable />
            <Column field="passRate" :header="$t('grade_analytics.col_pass_pct')" sortable />
          </DataTable>
        </div>
      </div>

      <!-- Subject Exercise Analytics Drilldown -->
      <div v-if="showExerciseAnalytics" class="col-span-12">
        <div class="card">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h5 class="m-0"><i class="pi pi-compass mr-2 text-violet-500"></i>{{ $t('grade_analytics.exercise_analytics_title') }}</h5>
              <p class="text-sm text-muted-color mt-1 mb-0">{{ $t('grade_analytics.exercise_analytics_subtitle') }}</p>
            </div>
            <span v-if="subjectExerciseAverages.length" class="text-xs text-muted-color">
              {{ subjectExerciseAverages.length }} {{ t('grade_analytics.exercises_count') }}
            </span>
          </div>

          <div v-if="subjectExerciseLoading" class="text-center py-8">
            <i class="pi pi-spin pi-spinner text-2xl"></i>
          </div>

          <template v-else-if="subjectExerciseAverages.length">
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-4">
              <!-- Radar Chart -->
              <div class="chart-wrap" style="height:250px">
                <Chart type="radar" :data="subjectExerciseRadarData" :options="{
                  responsive: true, maintainAspectRatio: false,
                  scales: { r: { min: 0 } }
                }" />
              </div>
              <!-- Bar Chart -->
              <div class="chart-wrap" style="height:250px">
                <Chart type="bar" :data="subjectExerciseBarData" :options="{
                  responsive: true, maintainAspectRatio: false,
                  plugins: { legend: { display: false } },
                  scales: { y: { min: 0 } }
                }" />
              </div>
            </div>

            <!-- Exercise table -->
            <DataTable :value="subjectExerciseAverages" size="small" stripedRows>
              <Column field="level_name" :header="t('grade_analytics.col_exercise_name')" />
              <Column field="records_count" :header="t('grade_analytics.col_records')" />
              <Column :header="t('grade_analytics.avg_note')">
                <template #body="{ data }">
                  <div class="flex items-center gap-2">
                    <div class="flex-1 bg-surface-200 rounded-full h-1.5" style="min-width:60px">
                      <div class="h-1.5 rounded-full" :style="`width:${data.max_note > 0 ? (data.avg_note / data.max_note) * 100 : 0}%;background:${(data.max_note > 0 ? (data.avg_note / data.max_note) * 100 : 0) >= 70 ? '#10b981' : (data.max_note > 0 ? (data.avg_note / data.max_note) * 100 : 0) >= 40 ? '#f59e0b' : '#ef4444'}`"></div>
                    </div>
                    <span class="text-xs font-semibold" :class="(data.max_note > 0 ? (data.avg_note / data.max_note) * 100 : 0) >= 70 ? 'text-emerald-600' : (data.max_note > 0 ? (data.avg_note / data.max_note) * 100 : 0) >= 40 ? 'text-amber-500' : 'text-rose-600'">
                      {{ data.avg_note }} / {{ data.max_note }}
                    </span>
                  </div>
                </template>
              </Column>
              <Column :header="t('grade_analytics.col_difficulty')">
                <template #body="{ data }">
                  <Tag
                    :severity="(data.max_note > 0 ? (data.avg_note / data.max_note) * 100 : 0) >= 70 ? 'success' : (data.max_note > 0 ? (data.avg_note / data.max_note) * 100 : 0) >= 40 ? 'warn' : 'danger'"
                    :value="(data.max_note > 0 ? (data.avg_note / data.max_note) * 100 : 0) >= 70 ? t('grade_analytics.easy') : (data.max_note > 0 ? (data.avg_note / data.max_note) * 100 : 0) >= 40 ? t('grade_analytics.medium') : t('grade_analytics.hard')"
                    rounded
                  />
                </template>
              </Column>
            </DataTable>
          </template>

          <div v-else class="text-center py-6 text-muted-color">
            <i class="pi pi-inbox text-2xl mb-2 block"></i>
            {{ t('grade_analytics.no_exercises') }}
          </div>
        </div>
      </div>

      <!-- Students Needing Work for this Subject (all-trimesters view) -->
      <div class="col-span-12" v-if="selectedSemester === 'all'">
        <div class="card">
          <div class="flex justify-between items-center mb-4">
            <h5 class="m-0">
              <i class="pi pi-exclamation-triangle mr-2 text-rose-500"></i>
              {{ $t('grade_analytics.students_ranked') }}
            </h5>
            <span class="text-sm text-muted-color">{{ $t('grade_analytics.below_passing_threshold') }}</span>
          </div>
          <DataTable
            :value="subjectStudentBreakdown"
            :loading="subjectGradesLoading"
            size="small"
            stripedRows
            paginator
            :rows="15"
            :rowClass="(row: any) => row.average !== null && row.average < row.threshold ? 'subject-student-fail' : ''"
          >
            <Column field="student_name" :header="$t('grade_analytics.student')" sortable>
              <template #body="{ data }">
                <div class="flex items-center gap-2">
                  <i v-if="data.average !== null && data.average < data.threshold" class="pi pi-exclamation-triangle text-rose-500 text-xs"></i>
                  <span :class="{ 'text-rose-600 font-semibold': data.average !== null && data.average < data.threshold }">
                    {{ data.student_name }}
                  </span>
                </div>
              </template>
            </Column>
            <Column field="class_name" :header="$t('grade_analytics.class')" sortable />
            <!-- Dynamic Exam Type Columns -->
            <Column 
              v-for="type in examTypes" 
              :key="type" 
              :field="type" 
              :header="formatExamTypeHeader(type)" 
              sortable
            >
              <template #body="{ data }">
                <span :class="{ 'text-rose-500': data[type] !== null && data[type] < data.threshold }">
                  {{ data[type] !== null ? data[type] : '—' }}
                </span>
              </template>
            </Column>
            <Column field="average" :header="$t('grade_analytics.col_avg_20')" sortable>
              <template #body="{ data }">
                <div
                  class="font-bold px-2 py-1 rounded text-center"
                  :class="data.average !== null && data.average < data.threshold
                    ? 'bg-rose-100 text-rose-700'
                    : 'bg-emerald-50 text-emerald-700'"
                >
                  {{ data.average !== null ? data.average : '—' }}
                </div>
              </template>
            </Column>
            <Column :header="t('grade_analytics.col_status')">
              <template #body="{ data }">
                <Tag v-if="data.average !== null && data.average < data.threshold" severity="danger" :value="t('grade_analytics.needs_work')" rounded />
                <Tag v-else-if="data.average !== null && data.average >= 16" severity="success" :value="t('grade_analytics.excellent')" rounded />
                <Tag v-else-if="data.average !== null" severity="info" :value="t('grade_analytics.passing')" rounded />
                <span v-else class="text-muted-color text-sm">—</span>
              </template>
            </Column>
            <template #empty>
              <div class="text-center py-4 text-muted-color">{{ $t('grade_analytics.no_grade_data') }}</div>
            </template>
          </DataTable>
        </div>
      </div>

      <!-- Grade Distribution Donut + Student Rankings with Exercise Columns (specific trimester+exam) -->
      <template v-if="showExerciseAnalytics">
        <!-- Customizable Donut Chart -->
        <div class="col-span-12">
          <div class="card mb-4">
            <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
              <div>
                <h5 class="m-0"><i class="pi pi-chart-pie mr-2 text-violet-500"></i>{{ $t('grade_analytics.grade_distribution') }}</h5>
                <p class="text-sm text-muted-color mt-1 mb-0">{{ $t('grade_analytics.distribution_subtitle') }}</p>
              </div>
              <Button
                :icon="showRangeEditor ? 'pi pi-times' : 'pi pi-sliders-h'"
                :label="showRangeEditor ? $t('grade_analytics.close_ranges') : $t('grade_analytics.customize_ranges')"
                severity="secondary" outlined size="small"
                @click="showRangeEditor = !showRangeEditor"
              />
            </div>
            <div v-if="showRangeEditor" class="range-editor mb-4 p-4 rounded-lg border">
              <p class="text-sm font-semibold mb-3"><i class="pi pi-sliders-h mr-2"></i>{{ $t('grade_analytics.ranges_editor_title') }}</p>
              <div class="flex flex-col gap-2">
                <div v-for="(range, idx) in gradeRanges" :key="idx" class="flex items-center gap-2">
                  <InputText v-model="range.label" :placeholder="$t('grade_analytics.range_label')" class="w-36" size="small" />
                  <span class="text-sm text-muted-color">{{ $t('grade_analytics.range_from') }}</span>
                  <InputNumber v-model="range.from" :min="0" :max="20" :step="0.5" inputClass="w-16" size="small" />
                  <span class="text-sm text-muted-color">{{ $t('grade_analytics.range_to') }}</span>
                  <InputNumber v-model="range.to" :min="0" :max="20" :step="0.5" inputClass="w-16" size="small" />
                  <Button icon="pi pi-trash" severity="danger" text size="small" @click="removeGradeRange(idx)" :disabled="gradeRanges.length <= 1" />
                  <div class="w-4 h-4 rounded-full flex-shrink-0" :style="{ background: DONUT_COLORS[idx % DONUT_COLORS.length] }"></div>
                </div>
              </div>
              <Button icon="pi pi-plus" :label="$t('grade_analytics.add_range')" severity="secondary" text size="small" class="mt-3" @click="addGradeRange" :disabled="gradeRanges.length >= 8" />
            </div>
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 items-center">
              <div class="xl:col-span-2 flex justify-center" style="height:260px">
                <Chart type="doughnut" :data="classGradeDistributionData" :options="classGradeDistributionOptions" class="w-full h-full" />
              </div>
              <div class="flex flex-col gap-2">
                <div
                  v-for="(range, idx) in gradeRanges" :key="idx"
                  class="flex items-center justify-between p-2 rounded-lg text-sm"
                  :style="{ background: DONUT_COLORS[idx % DONUT_COLORS.length].replace('0.8', '0.12'), borderLeft: `4px solid ${DONUT_COLORS[idx % DONUT_COLORS.length]}` }"
                >
                  <span class="font-medium">{{ range.label || `${range.from}–${range.to}` }}</span>
                  <span class="font-bold ml-2">{{ classGradeDistributionData.datasets[0].data[idx] }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Student Rankings with Exercise Columns -->
        <div class="col-span-12">
          <div class="card">
            <div class="flex flex-wrap justify-between items-center gap-2 mb-4">
              <h5 class="m-0">
                <i class="pi pi-users mr-2 text-primary"></i>
                {{ $t('grade_analytics.class_student_rankings') }}
              </h5>
              <div class="flex gap-4 items-center">
                <IconField>
                  <InputIcon class="pi pi-search" />
                  <InputText v-model="studentRankingsFilters['global'].value" :placeholder="t('grade_analytics.search_students')" />
                </IconField>
                <span class="text-sm text-muted-color">{{ classRankingRows.length }} {{ t('common.students') }}</span>
              </div>
            </div>
            <DataTable
              :value="classRankingRows"
              size="small" stripedRows paginator :rows="20"
              :loading="loading || classGradesLoading"
              v-model:filters="studentRankingsFilters"
              :globalFilterFields="['student_name', 'class_name', 'best_subject']"
              scrollable
            >
              <Column :header="t('grade_analytics.col_rank')" style="width: 3rem" frozen>
                <template #body="{ index }">
                  <span class="font-bold" :class="index === 0 ? 'text-amber-500' : index === 1 ? 'text-slate-400' : index === 2 ? 'text-orange-600' : 'text-muted-color'">
                    {{ index + 1 }}
                  </span>
                </template>
              </Column>
              <Column field="student_name" :header="t('grade_analytics.student')" sortable frozen>
                <template #body="{ data, index }">
                  <div class="flex items-center gap-2">
                    <i v-if="index === 0" class="pi pi-trophy text-amber-500"></i>
                    <span class="font-medium">{{ data.student_name }}</span>
                  </div>
                </template>
              </Column>
              <Column field="class_name" :header="t('grade_analytics.class')" sortable />
              <Column field="average" :header="t('grade_analytics.overall_average')" sortable>
                <template #body="{ data }">
                  <div
                    class="font-bold px-2 py-1 rounded text-center inline-block min-w-12"
                    :class="data.average >= 16 ? 'bg-emerald-100 text-emerald-700'
                      : data.average >= getClassFailThreshold(data.class_id || null) ? 'bg-blue-50 text-blue-700'
                      : 'bg-rose-100 text-rose-700'"
                  >
                    {{ data.average }}
                  </div>
                </template>
              </Column>
              <!-- Dynamic exercise columns -->
              <Column
                v-for="ex in classRankingExercises"
                :key="ex.id"
                :header="ex.level_name + ' (/' + ex.max_note + ')'"
                :sortField="`ex_${ex.id}`"
                sortable
              >
                <template #body="{ data }">
                  <span
                    v-if="data[`ex_${ex.id}`] !== null && data[`ex_${ex.id}`] !== undefined"
                    class="font-semibold px-2 py-0.5 rounded text-xs"
                    :class="data[`ex_${ex.id}`] < ex.max_note / 2 ? 'bg-rose-100 text-rose-700' : 'bg-emerald-50 text-emerald-700'"
                  >
                    {{ data[`ex_${ex.id}`] }}
                  </span>
                  <span v-else class="text-muted-color text-xs">—</span>
                </template>
              </Column>
              <Column field="best_subject" :header="t('grade_analytics.best_subject')" sortable />
              <Column field="passRate" :header="t('grade_analytics.pass_rate')" sortable>
                <template #body="{ data }">
                  <div class="flex items-center gap-2">
                    <div class="flex-1 bg-surface-200 rounded-full h-1.5" style="min-width:50px">
                      <div
                        class="h-1.5 rounded-full"
                        :class="data.passRate >= 70 ? 'bg-emerald-500' : data.passRate >= 40 ? 'bg-amber-400' : 'bg-rose-500'"
                        :style="{ width: data.passRate + '%' }"
                      ></div>
                    </div>
                    <span class="text-xs font-semibold w-10 text-right">{{ data.passRate }}%</span>
                  </div>
                </template>
              </Column>
              <template #empty>
                <div class="text-center py-4 text-muted-color">{{ t('common.no_data_available') }}</div>
              </template>
            </DataTable>
          </div>
        </div>
      </template>
    </template>

    <template v-else-if="!loading">
      <div class="col-span-12 md:col-span-6 xl:col-span-3">
        <div class="card stat-card">
          <p class="stat-title">{{ $t('grade_analytics.stat_avg_grade') }}</p>
          <h3 class="stat-value">{{ stats.average }} / 20</h3>
          <p class="stat-sub">{{ $t('grade_analytics.stat_median_stddev', { median: stats.median, stddev: stats.stdDev }) }}</p>
        </div>
      </div>

      <div class="col-span-12 md:col-span-6 xl:col-span-3">
        <div class="card stat-card">
          <p class="stat-title">{{ $t('grade_analytics.stat_pass_rate') }}</p>
          <h3 class="stat-value">{{ stats.passRate }}%</h3>
          <p class="stat-sub">{{ $t('grade_analytics.stat_excellence', { rate: stats.excellenceRate }) }}</p>
        </div>
      </div>

      <div class="col-span-12 md:col-span-6 xl:col-span-3">
        <div class="card stat-card">
          <p class="stat-title">{{ $t('grade_analytics.stat_records') }}</p>
          <h3 class="stat-value">{{ stats.records }}</h3>
          <p class="stat-sub">{{ $t('grade_analytics.stat_students_teachers', { students: stats.students, teachers: stats.teachers }) }}</p>
        </div>
      </div>

      <div class="col-span-12 md:col-span-6 xl:col-span-3">
        <div class="card stat-card">
          <p class="stat-title">{{ $t('grade_analytics.stat_coverage') }}</p>
          <h3 class="stat-value">{{ $t('grade_analytics.stat_subjects_count', { count: stats.subjects }) }}</h3>
          <p class="stat-sub">{{ $t('grade_analytics.stat_across_classes', { count: stats.classes }) }}</p>
        </div>
      </div>

      <div class="col-span-12 xl:col-span-6">
        <div class="card chart-card">
          <div class="flex justify-between items-center mb-3">
            <h5 class="m-0">{{ $t('grade_analytics.chart_avg_by_subject') }}</h5>
            <Tag :value="`${subjectAggregates.length} ${$t('grade_analytics.subjects_count')}`" severity="info" />
          </div>
          <div class="chart-wrap">
            <Chart type="bar" :data="subjectAverageChartData" :options="baseChartOptions" />
          </div>
        </div>
      </div>

      <div class="col-span-12 xl:col-span-6">
        <div class="card chart-card">
          <div class="flex justify-between items-center mb-3">
            <h5 class="m-0">{{ $t('grade_analytics.chart_avg_by_class') }}</h5>
            <Tag :value="`${classAggregates.length} ${$t('grade_analytics.classes_count')}`" severity="success" />
          </div>
          <div class="chart-wrap">
            <Chart type="bar" :data="classAverageChartData" :options="baseChartOptions" />
          </div>
        </div>
      </div>

      <div class="col-span-12 xl:col-span-6">
        <div class="card chart-card">
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-3">
            <h5 class="m-0">{{ $t('grade_analytics.chart_teacher_perf') }}</h5>
            <Select
              v-model="selectedTeacherForChart"
              :options="teacherOptions"
              optionLabel="label"
              optionValue="value"
              :placeholder="$t('grade_analytics.filter_by_teacher')"
              class="w-full md:w-80"
              filter
            />
          </div>
          <div class="chart-wrap">
            <Chart type="bar" :data="teacherChartData" :options="baseChartOptions" />
          </div>
        </div>
      </div>

      <div class="col-span-12 xl:col-span-6">
        <div class="card chart-card">
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-3">
            <h5 class="m-0">{{ $t('grade_analytics.chart_subject_drilldown') }}</h5>
            <Select
              v-model="selectedSubjectForChart"
              :options="subjectOptions"
              optionLabel="label"
              optionValue="value"
              :placeholder="$t('grade_analytics.filter_by_subject')"
              class="w-full md:w-80"
              filter
            />
          </div>
          <div class="chart-wrap">
            <Chart type="bar" :data="subjectDrilldownChartData" :options="subjectDistributionChartOptions" />
          </div>
        </div>
      </div>

      <div class="col-span-12 lg:col-span-6">
        <div class="card">
          <h5 class="mb-3">{{ $t('grade_analytics.table_top_subjects') }}</h5>
          <DataTable :value="topSubjects" size="small" stripedRows>
            <Column field="label" :header="$t('grade_analytics.col_subject_lbl')" />
            <Column field="average" :header="$t('grade_analytics.col_avg_20')" />
            <Column field="passRate" :header="$t('grade_analytics.col_pass_pct')" />
            <Column field="count" :header="$t('grade_analytics.col_records')" />
          </DataTable>
        </div>
      </div>

      <div class="col-span-12 lg:col-span-6">
        <div class="card">
          <h5 class="mb-3">{{ $t('grade_analytics.table_weakest_subjects') }}</h5>
          <DataTable :value="bottomSubjects" size="small" stripedRows>
            <Column field="label" :header="$t('grade_analytics.col_subject_lbl')" />
            <Column field="average" :header="$t('grade_analytics.col_avg_20')" />
            <Column field="passRate" :header="$t('grade_analytics.col_pass_pct')" />
            <Column field="count" :header="$t('grade_analytics.col_records')" />
          </DataTable>
        </div>
      </div>

      <div class="col-span-12 lg:col-span-6">
        <div class="card">
          <h5 class="mb-3">{{ $t('grade_analytics.table_challenging_classes') }}</h5>
          <DataTable :value="toughestClasses" size="small" stripedRows>
            <Column field="label" :header="$t('grade_analytics.col_class_lbl')" />
            <Column field="average" :header="$t('grade_analytics.col_avg_20')" />
            <Column field="passRate" :header="$t('grade_analytics.col_pass_pct')" />
            <Column field="count" :header="$t('grade_analytics.col_records')" />
          </DataTable>
        </div>
      </div>

      <div class="col-span-12 lg:col-span-6">
        <div class="card">
          <h5 class="mb-3">{{ $t('grade_analytics.table_variable_subjects') }}</h5>
          <DataTable :value="highVarianceSubjects" size="small" stripedRows>
            <Column field="label" :header="$t('grade_analytics.col_subject_lbl')" />
            <Column field="stdDev" :header="$t('grade_analytics.col_std_dev')" />
            <Column field="average" :header="$t('grade_analytics.col_avg_20')" />
            <Column field="count" :header="$t('grade_analytics.col_records')" />
          </DataTable>
        </div>
      </div>

      <!-- Overall Student Rankings table -->
      <div class="col-span-12">
        <div class="card">
          <div class="flex flex-wrap justify-between items-center gap-2 mb-4">
            <h5 class="m-0">
              <i class="pi pi-users mr-2 text-primary"></i>
              {{$t('grade_analytics.class_student_rankings') }}
            </h5>
            <div class="flex gap-4 items-center">
              <IconField>
                <InputIcon class="pi pi-search" />
                <InputText v-model="studentRankingsFilters['global'].value" :placeholder="t('grade_analytics.search_students')" />
              </IconField>
              <span class="text-sm text-muted-color">{{ studentAggregatesData.length }} {{ t('common.students') }}</span>
            </div>
          </div>
          <DataTable
            :value="studentAggregatesData"
            size="small"
            stripedRows
            paginator
            :rows="20"
            :loading="loading"
            v-model:filters="studentRankingsFilters"
            :globalFilterFields="['student_name', 'class_name', 'best_subject']"
          > 
            <Column :header="t('grade_analytics.col_rank')" style="width: 3rem">
              <template #body="{ index }">
                <span
                  class="font-bold"
                  :class="index === 0 ? 'text-amber-500' : index === 1 ? 'text-slate-400' : index === 2 ? 'text-orange-600' : 'text-muted-color'"
                >
                  {{ index + 1 }}
                </span>
              </template>
            </Column>
            <Column field="student_name" :header=" $t('grade_analytics.student') " sortable>
              <template #body="{ data, index }">
                <div class="flex items-center gap-2">
                  <i v-if="index === 0" class="pi pi-trophy text-amber-500"></i>
                  <span class="font-medium">{{ data.student_name }}</span>
                </div>
              </template>
            </Column>
            <Column field="class_name" :header=" $t('common.class') " sortable />
            <Column field="average" :header=" $t('grade_analytics.overall_average') " sortable>
              <template #body="{ data }">
                <div
                  class="font-bold px-2 py-1 rounded text-center inline-block min-w-12"
                  :class="data.average >= 16 ? 'bg-emerald-100 text-emerald-700'
                    : data.average >= 10 ? 'bg-blue-50 text-blue-700'
                    : 'bg-rose-100 text-rose-700'"
                >
                  {{ data.average }}
                </div>
              </template>
            </Column>
            <Column field="best_subject" :header=" $t('grade_analytics.best_subject') " sortable />
            <Column field="best_subject_avg" :header=" $t('grade_analytics.best_average') " sortable>
              <template #body="{ data }">
                <span v-if="data.best_subject_avg !== null" class="font-semibold text-emerald-600">
                  {{ data.best_subject_avg }}
                </span>
                <span v-else class="text-muted-color">—</span>
              </template>
            </Column>
            <Column field="passRate" :header=" $t('grade_analytics.pass_rate') " sortable>
              <template #body="{ data }">
                <div class="flex items-center gap-2">
                  <div class="flex-1 bg-surface-200 rounded-full h-1.5" style="min-width:60px">
                    <div
                      class="h-1.5 rounded-full"
                      :class="data.passRate >= 70 ? 'bg-emerald-500' : data.passRate >= 40 ? 'bg-amber-400' : 'bg-rose-500'"
                      :style="{ width: data.passRate + '%' }"
                    ></div>
                  </div>
                  <span class="text-xs font-semibold w-10 text-right">{{ data.passRate }}%</span>
                </div>
              </template>
            </Column>
            <template #empty>
              <div class="text-center py-4 text-muted-color">{{ $t('grade_analytics.no_students') }}</div>
            </template>
          </DataTable>
        </div>
      </div>

    </template>

    <div v-else class="col-span-12">
      <div class="card text-center py-8">
        <i class="pi pi-spin pi-spinner text-4xl text-primary mb-3"></i>
        <p class="text-muted-color">{{ $t('grade_analytics.loading') }}</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.stat-card {
  min-height: 140px;
}

.stat-title {
  font-size: 0.9rem;
  color: var(--text-color-secondary);
  margin: 0;
}

.stat-value {
  margin: 0.75rem 0 0.35rem;
  font-size: 1.6rem;
}

.stat-sub {
  margin: 0;
  color: var(--text-color-secondary);
  font-size: 0.9rem;
}

.chart-card {
  min-height: 500px;
}

.chart-wrap {
  height: 400px;
}

.chart-wrap :deep(.p-chart) {
  height: 100%;
}

.chart-wrap :deep(canvas) {
  width: 100% !important;
  height: 100% !important;
}

/* Bulletin Scolaire — fully dark-mode-safe */
.bulletin-card {
  border-top: 4px solid var(--p-primary-color);
}

.bulletin-title {
  letter-spacing: 0.08em;
  color: var(--p-text-color);
}

.bulletin-header-divider {
  border-bottom: 1px solid var(--p-content-border-color);
}

.bulletin-info-bar {
  background: var(--p-surface-100);
  border: 1px solid var(--p-content-border-color);
  color: var(--p-text-color);
}

.bulletin-table-wrap {
  border: 1px solid var(--p-content-border-color);
  border-radius: 0.5rem;
}

.bulletin-table {
  width: 100%;
  border-collapse: collapse;
}

.bulletin-thead {
  background: var(--p-surface-section);
  font-size: 0.72rem;
  text-transform: uppercase;
  border-bottom: 2px solid var(--p-content-border-color);
  color: var(--p-text-muted-color);
}

.bulletin-th {
  padding: 0.75rem;
  font-weight: 700;
  border-right: 1px solid var(--p-content-border-color);
  color: var(--p-text-color);
}

.bulletin-th:last-child {
  border-right: none;
}

.bulletin-avg-th {
  background: var(--p-surface-section);
  filter: brightness(0.95);
}

.bulletin-row {
  font-size: 0.875rem;
  transition: background 0.15s;
  border-bottom: 1px solid var(--p-content-border-color);
  color: var(--p-text-color);
  background: var(--p-content-background);
}

.bulletin-row:nth-child(even) {
  background: color-mix(in srgb, var(--p-content-background) 85%, var(--p-primary-color) 5%);
  filter: brightness(0.97);
}

.bulletin-row:hover {
  filter: brightness(0.93);
}

.bulletin-td {
  padding: 0.75rem;
  border-right: 1px solid var(--p-content-border-color);
}

.bulletin-td:last-child {
  border-right: none;
}

.bulletin-avg-td {
  background: var(--p-surface-section);
  color: var(--p-text-color);
  filter: brightness(0.95);
}

.bulletin-total-header {
  background: color-mix(in srgb, var(--p-primary-color) 20%, var(--p-content-background));
  color: var(--p-primary-color);
}

.bulletin-total-cell {
  background: color-mix(in srgb, var(--p-primary-color) 12%, var(--p-content-background));
  color: var(--p-primary-color);
  font-weight: 700;
}

.bulletin-tfoot {
  border-top: 3px solid var(--p-primary-color);
  background: var(--p-surface-section);
  color: var(--p-text-color);
}

.bulletin-appreciation {
  border: 1px solid var(--p-content-border-color);
  background: var(--p-surface-section);
  color: var(--p-text-color);
}

.bulletin-info-bar {
  background: var(--p-surface-section);
  border: 1px solid var(--p-content-border-color);
  color: var(--p-text-color);
}

/* Failing grade cell — shows red text with a subtle red tint background */
.bulletin-grade-fail {
  color: var(--p-red-500) !important;
  background: color-mix(in srgb, var(--p-red-500) 10%, var(--p-content-background)) !important;
  font-weight: 700;
}

/* Failing total pondéré cell — red tint on the primary-tinted column */
.bulletin-grade-fail-total {
  color: var(--p-red-500) !important;
  background: color-mix(in srgb, var(--p-red-500) 15%, var(--p-content-background)) !important;
}

/* Annual / Bilan Annuel badge */
.bulletin-annual-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.3rem 1rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  background: color-mix(in srgb, #f59e0b 20%, var(--p-content-background));
  color: #f59e0b;
  border: 1px solid #f59e0b;
}

.bulletin-annual-note {
  color: #f59e0b;
  font-style: italic;
  opacity: 0.85;
}

/* Subject view — row background for students below the fail threshold */
:deep(.subject-student-fail) {
  background: color-mix(in srgb, var(--p-red-500) 8%, var(--p-content-background)) !important;
}

/* Range editor panel */
.range-editor {
  background: var(--p-surface-section);
  border: 1px solid var(--p-content-border-color);
}
</style>