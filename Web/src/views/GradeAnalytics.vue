<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import GradeService, { type GradeRecord, type GradeAnalyticsOverview } from '@/service/GradeService';
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

const loading = ref(false);
const error = ref<string | null>(null);

const allGrades = ref<EnrichedGrade[]>([]);

const studentGrades = ref<EnrichedGrade[]>([]);
const studentGradesLoading = ref(false);

const selectedStudentInfo = computed(() => {
  if (!selectedStudentId.value) return null;
  return allStudents.value.find(s => s.id === selectedStudentId.value) || null;
});

const studentAverage = computed(() => {
  if (studentGrades.value.length === 0) return 0;
  const sum = studentGrades.value.reduce((acc, g) => acc + normalizedGrade(g), 0);
  return round2(sum / studentGrades.value.length);
});

const studentPassRate = computed(() => {
  if (studentGrades.value.length === 0) return 0;
  const passed = studentGrades.value.filter(g => normalizedGrade(g) >= 10).length;
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
        label: 'Student Subject Average / 20',
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
    const key = g.teacher_id != null ? String(g.teacher_id) : (g.teacher_name || 'unknown');
    if (!map.has(key)) {
      map.set(key, {
        teacher_name: g.teacher_name || 'Unknown',
        subjects: new Set(),
        sum: 0,
        count: 0,
        passed: 0
      });
    }
    const entry = map.get(key)!;
    if (g.subject_name) entry.subjects.add(g.subject_name);
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
    cc: number[];
    devoir: number[];
    composition: number[];
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
        cc: [],
        devoir: [],
        composition: [],
        allNorms: []
      });
    }
    const entry = map.get(sid)!;
    const norm = normalizedGrade(g);
    entry.allNorms.push(norm);
    if (g.exam_type === 'evaluation_continue') entry.cc.push(norm);
    else if (g.exam_type === 'devoir') entry.devoir.push(norm);
    else if (g.exam_type === 'composition') entry.composition.push(norm);
  });

  const avg = (arr: number[]) => arr.length ? round2(arr.reduce((a, b) => a + b, 0) / arr.length) : null;

  return Array.from(map.values()).map(e => ({
    student_name: e.student_name,
    class_name: e.class_name,
    class_id: e.class_id,
    cc: avg(e.cc),
    devoir: avg(e.devoir),
    composition: avg(e.composition),
    average: avg(e.allNorms),
    threshold: getClassFailThreshold(e.class_id)
  })).sort((a, b) => (a.average ?? 99) - (b.average ?? 99));
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

const selectedAcademicYear = ref<string>('');
const selectedSemester = ref<string>('all');
const selectedExamType = ref<string>('all');
const selectedClassId = ref<number | null>(null);
const selectedSubjectId = ref<number | null>(null);
const selectedTeacherId = ref<number | null>(null);
const selectedStudentId = ref<number | null>(null);

const selectedTeacherForChart = ref<number | null>(null);
const selectedSubjectForChart = ref<number | null>(null);

const semesterOptions = [
  { label: 'All Trimesters', value: 'all' },
  { label: 'Trimester 1', value: 'Trimester 1' },
  { label: 'Trimester 2', value: 'Trimester 2' },
  { label: 'Trimester 3', value: 'Trimester 3' }
];

const examTypeOptions = [
  { label: 'All Exam Types', value: 'all' },
  { label: 'Évaluation Continue', value: 'evaluation_continue' },
  { label: 'Devoir', value: 'devoir' },
  { label: 'Composition', value: 'composition' }
];

const getCurrentAcademicYear = (): string => {
  const now = new Date();
  const year = now.getFullYear();
  return now.getMonth() >= 8 ? `${year}-${year + 1}` : `${year - 1}-${year}`;
};

const academicYearOptions = computed(() => {
  const years = new Set<string>();
  allGrades.value.forEach((grade) => {
    if (grade.academic_year) years.add(grade.academic_year);
  });

  const sorted = Array.from(years).sort().reverse();
  if (selectedAcademicYear.value && !sorted.includes(selectedAcademicYear.value)) {
    sorted.unshift(selectedAcademicYear.value);
  }

  return sorted.map((year) => ({ label: year, value: year }));
});

const classOptions = computed(() => {
  return [
    { label: 'All Classes', value: null as number | null },
    ...allClasses.value.map((classItem) => ({
      label: classItem.name,
      value: classItem.id
    }))
  ];
});

const teacherOptions = computed(() => {
  return [
    { label: 'All Teachers', value: null as number | null },
    ...allTeachers.value.map((teacher) => ({
      label: `${teacher.first_name} ${teacher.last_name}`,
      value: teacher.id
    }))
  ];
});

const subjectOptions = computed(() => {
  return [
    { label: 'All Subjects', value: null as number | null },
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
    { label: 'All Students', value: null as number | null },
    ...filtered
      .map((student) => ({
        label: `${student.first_name} ${student.last_name}`.trim(),
        value: student.id
      }))
      .sort((a, b) => a.label.localeCompare(b.label))
  ];
});

const normalizedGrade = (grade: GradeRecord): number => {
  const value = Number(grade.grade ?? 0);
  const max = Number(grade.max_grade ?? 0);
  if (max <= 0) return Math.max(0, Math.min(20, value));
  return (value / max) * 20;
};

const round2 = (value: number): number => Number(value.toFixed(2));

const getTeacherName = (grade: GradeRecord): string => {
  if (grade.teacher?.first_name || grade.teacher?.last_name) {
    return `${grade.teacher?.first_name ?? ''} ${grade.teacher?.last_name ?? ''}`.trim();
  }

  const teacher = allTeachers.value.find((item) => item.id === grade.teacher_id);
  if (teacher) return `${teacher.first_name} ${teacher.last_name}`;
  return `Teacher #${grade.teacher_id}`;
};

const getSubjectName = (grade: GradeRecord): string => {
  if (grade.subject?.name) return grade.subject.name;
  const subject = allSubjects.value.find((item) => item.id === grade.subject_id);
  return subject?.name || `Subject #${grade.subject_id}`;
};

const getClassInfo = (grade: GradeRecord): { class_id: number | null; class_name: string } => {
  const classId = grade.student?.class_id ?? null;
  if (!classId) return { class_id: null, class_name: 'Unknown Class' };
  const classItem = allClasses.value.find((item) => item.id === classId);
  return {
    class_id: classId,
    class_name: classItem?.name || `Class #${classId}`
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
      label: 'Average Grade /20',
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
      label: 'Average Grade /20',
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
      label: selectedTeacherForChart.value ? 'Subject Average /20' : 'Teacher Average /20',
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
      label: selectedSubjectForChart.value ? 'Class Average /20' : 'Grade Count',
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
  if (!selectedClassId.value || selectedStudentId.value) {
    classGrades.value = [];
    return;
  }
  classGradesLoading.value = true;
  try {
    const rawGrades = await GradeService.getGrades({ ...buildAnalyticsParams(), class_id: selectedClassId.value });
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
  if (!selectedStudentId.value) {
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
          GradeService.getStudentReportCard(selectedStudentId.value, {
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
        cc: number[];
        devoir: number[];
        composition: number[];
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
              cc: [],
              devoir: [],
              composition: [],
            });
          }

          const entry = subjectMap.get(id)!;
          // Always add to the sum; missing trimesters count as 0 (handled by dividing by 3)
          entry.sumAvg += Number(sub.average || 0);
          if (sub.evaluation_continue !== '-' && sub.evaluation_continue !== null)
            entry.cc.push(Number(sub.evaluation_continue));
          if (sub.devoir !== '-' && sub.devoir !== null)
            entry.devoir.push(Number(sub.devoir));
          if (sub.composition !== '-' && sub.composition !== null)
            entry.composition.push(Number(sub.composition));
        });
      });

      // Compute annual averages — always divide by 3 as per school rules
      const annualSubjects = Array.from(subjectMap.values()).map(entry => {
        const annualAvg = Math.round((entry.sumAvg / 3) * 100) / 100;
        const avg = (arr: number[]) =>
          arr.length ? +(arr.reduce((a, b) => a + b, 0) / 3).toFixed(2) : '-';
        return {
          subject: entry.subject,
          teacher: entry.teacher,
          coefficient: entry.coefficient,
          evaluation_continue: avg(entry.cc),
          devoir: avg(entry.devoir),
          composition: avg(entry.composition),
          average: annualAvg,
          weighted_average: Math.round(annualAvg * entry.coefficient * 100) / 100,
        };
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
      const data = await GradeService.getStudentReportCard(selectedStudentId.value, {
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
    await loadStudentReportCard();
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to load grade analytics data.';
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
  await loadAnalytics();
});

watch([selectedExamType, selectedClassId, selectedSubjectId, selectedTeacherId, selectedStudentId], async () => {
  await loadAnalytics();
});

watch([selectedTeacherForChart, selectedSubjectForChart], async () => {
  await loadChartDrilldowns();
});

onMounted(async () => {
  selectedAcademicYear.value = getCurrentAcademicYear();
  await loadMetadata();
  await loadAnalytics();
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
          />
          <Select
            v-model="selectedSemester"
            :options="semesterOptions"
            optionLabel="label"
            optionValue="value"
            :placeholder="$t('grade_analytics.trimester')"
            class="w-full"
          />
          <Select
            v-model="selectedExamType"
            :options="examTypeOptions"
            optionLabel="label"
            optionValue="value"
            :placeholder="$t('grade_analytics.exam_type')"
            class="w-full"
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
                <p class="text-2xl font-bold text-blue-900 m-0 mt-1" :class="{'text-red-600': studentAverage < 10}">
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
          <DataTable :value="studentGrades" :loading="studentGradesLoading" size="small" stripedRows paginator :rows="10">
            <Column field="subject_name" :header="$t('grade_analytics.col_subject')" sortable></Column>
            <Column field="exam_type" :header="$t('grade_analytics.col_exam_type')" sortable>
              <template #body="{ data }">
                <span class="capitalize">{{ data.exam_type }}</span>
              </template>
            </Column>
            <Column field="teacher_name" :header="$t('grade_analytics.col_teacher')" sortable></Column>
            <Column :header="$t('grade_analytics.col_grade_max')" sortable sortField="normalized_grade">
              <template #body="{ data }">
                <div class="font-semibold">{{ Number(data.grade).toFixed(2) }} / {{ data.max_grade }}</div>
              </template>
            </Column>
            <Column :header="$t('grade_analytics.col_status')" sortable sortField="normalized_grade">
              <template #body="{ data }">
                <Tag v-if="data.normalized_grade < 10" severity="danger" :value="$t('grade_analytics.needs_work')" rounded />
                <Tag v-else-if="data.normalized_grade >= 16" severity="success" :value="$t('grade_analytics.excellent')" rounded />
                <Tag v-else severity="info" :value="$t('grade_analytics.passing')" rounded />
              </template>
            </Column>
            <template #empty>
              <div class="text-center py-4 text-muted-color">{{ $t('grade_analytics.no_grades') }}</div>
            </template>
          </DataTable>
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
              <p><span class="text-muted-color mr-2">{{ $t('grade_analytics.bulletin_class') }} :</span> {{ studentReportCardData.data?.student?.class?.name || 'N/A' }}</p>
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
                  <th class="bulletin-th">{{ $t('grade_analytics.col_cc') }}</th>
                  <th class="bulletin-th">{{ $t('grade_analytics.col_devoir') }}</th>
                  <th class="bulletin-th">{{ $t('grade_analytics.col_composition') }}</th>
                  <th class="bulletin-th bulletin-avg-th">{{ $t('grade_analytics.col_avg') }}</th>
                  <th class="bulletin-th bulletin-total-header">{{ $t('grade_analytics.col_weighted') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(sub, index) in studentReportCardData.data?.subjects || []" :key="index" class="bulletin-row">
                  <td class="bulletin-td text-left">
                    <div class="font-bold">{{ sub.subject?.name || 'Unknown' }}</div>
                    <div class="text-xs text-muted-color mt-1" v-if="sub.teacher">Prof. {{ sub.teacher?.last_name }}</div>
                  </td>
                  <td class="bulletin-td font-bold">{{ sub.coefficient }}</td>
                  <!-- CC /20 -->
                  <td class="bulletin-td" :class="{ 'bulletin-grade-fail': isFailing(sub.evaluation_continue) }">
                    {{ sub.evaluation_continue }}
                  </td>
                  <!-- Devoir /20 -->
                  <td class="bulletin-td" :class="{ 'bulletin-grade-fail': isFailing(sub.devoir) }">
                    {{ sub.devoir }}
                  </td>
                  <!-- Composition /40 displayed as /40 raw -->
                  <td class="bulletin-td" :class="{ 'bulletin-grade-fail': sub.composition !== '-' && Number(sub.composition) * 2 < bulletinFailThreshold * 2 }">
                    {{ sub.composition !== '-' ? (Number(sub.composition) * 2).toFixed(2) : '-' }}
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

    <template v-else-if="!loading && selectedClassId && selectedClassInfo && !selectedStudentId && !selectedTeacherId && !selectedSubjectId">
      <!-- CLASS BREAKDOWN VIEW -->
      <div class="col-span-12">
        <div class="card bg-green-50 border-green-200">
          <div class="flex items-center gap-4">
            <div class="w-16 h-16 rounded-full bg-green-500 text-white flex items-center justify-center text-2xl font-bold">
              <i class="pi pi-users"></i>
            </div>
            <div>
              <h2 class="text-2xl font-bold m-0 text-green-900">{{ selectedClassInfo.name }}</h2>
              <p class="text-green-700 m-0 mt-1">Class Profile Breakdown</p>
            </div>
            <div class="ml-auto flex gap-4 text-center">
              <div>
                <p class="text-sm font-semibold text-green-700 uppercase m-0">Class Average</p>
                <p class="text-2xl font-bold text-green-900 m-0 mt-1">{{ stats.average }} / 20</p>
              </div>
              <div class="w-px bg-green-200"></div>
              <div>
                <p class="text-sm font-semibold text-green-700 uppercase m-0">Pass Rate</p>
                <p class="text-2xl font-bold text-green-900 m-0 mt-1">{{ stats.passRate }}%</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Top and Bottom Students -->
       <div class="col-span-12 lg:col-span-6">
        <div class="card">
          <h5 class="mb-3 text-emerald-600 border-b pb-2"><i class="pi pi-star mr-2"></i> Top 5 Students</h5>
          <DataTable :value="classTopStudents" :loading="classGradesLoading" size="small" stripedRows>
            <Column field="student_name" header="Student" />
            <Column header="Average" sortable sortField="average">
               <template #body="{ data }">
                 <span class="font-bold text-emerald-600">{{ data.average }}</span>
               </template>
            </Column>
          </DataTable>
        </div>
      </div>

      <div class="col-span-12 lg:col-span-6">
        <div class="card">
          <h5 class="mb-3 text-rose-600 border-b pb-2"><i class="pi pi-exclamation-triangle mr-2"></i> Needs Attention</h5>
          <DataTable :value="classBottomStudents" :loading="classGradesLoading" size="small" stripedRows>
            <Column field="student_name" header="Student" />
            <Column header="Average" sortable sortField="average">
               <template #body="{ data }">
                 <span class="font-bold" :class="{'text-rose-600': data.average < 10}">{{ data.average }}</span>
               </template>
            </Column>
            <Column header="Status">
               <template #body="{ data }">
                  <Tag v-if="data.average < 10" severity="danger" value="Failing" />
                  <Tag v-else severity="warning" value="At Risk" />
               </template>
            </Column>
          </DataTable>
        </div>
      </div>

      <!-- Subject Breakdown Chart for Class -->
      <div class="col-span-12 xl:col-span-6">
        <div class="card chart-card">
          <div class="flex justify-between items-center mb-3">
             <h5 class="m-0">Subject Averages</h5>
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
            <h5 class="m-0">Teacher Impact</h5>
          </div>
          <DataTable :value="classTeacherBreakdown" size="small" stripedRows>
            <Column field="teacher_name" header="Teacher" />
            <Column field="subjects" header="Subject(s)" />
            <Column field="average" header="Avg /20" />
            <Column field="passRate" header="Pass Rate %" />
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
              <h2 class="text-2xl font-bold m-0 text-orange-900">Prof. {{ selectedTeacherInfo.first_name }} {{ selectedTeacherInfo.last_name }}</h2>
              <p class="text-orange-700 m-0 mt-1">Teacher Analytics Profile</p>
            </div>
            <div class="ml-auto flex gap-4 text-center">
              <div>
                <p class="text-sm font-semibold text-orange-700 uppercase m-0">Given Average</p>
                <p class="text-2xl font-bold text-orange-900 m-0 mt-1">{{ stats.average }} / 20</p>
              </div>
              <div class="w-px bg-orange-200"></div>
              <div>
                <p class="text-sm font-semibold text-orange-700 uppercase m-0">Given Grades</p>
                <p class="text-2xl font-bold text-orange-900 m-0 mt-1">{{ stats.records }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-span-12 xl:col-span-4">
        <div class="card chart-card">
          <h5 class="mb-3">Grade Distribution</h5>
          <div class="chart-wrap">
            <Chart type="doughnut" :data="teacherDistributionChartData" :options="teacherPieOptions" />
          </div>
        </div>
      </div>

      <div class="col-span-12 xl:col-span-8">
        <div class="card chart-card">
          <h5 class="mb-3">Class Performance Matrix</h5>
          <DataTable :value="classAggregates" size="small" stripedRows paginator :rows="10">
            <Column field="label" header="Class" sortable></Column>
            <Column field="average" header="Avg Grade /20" sortable></Column>
            <Column field="passRate" header="Pass Rate %" sortable></Column>
            <Column field="stdDev" header="Variance (Dev)" sortable></Column>
            <Column header="Status">
              <template #body="{ data }">
                <Tag v-if="data.average < 10" severity="danger" value="Poor" />
                <Tag v-else-if="data.average >= 14" severity="success" value="Excellent" />
                <Tag v-else severity="info" value="Average" />
              </template>
            </Column>
          </DataTable>
        </div>
      </div>
    </template>

    <template v-else-if="!loading && selectedSubjectId && selectedSubjectInfo && !selectedStudentId && !selectedClassId && !selectedTeacherId">
      <!-- SUBJECT BREAKDOWN VIEW -->
      <div class="col-span-12">
        <div class="card bg-purple-50 border-purple-200">
          <div class="flex items-center gap-4">
            <div class="w-16 h-16 rounded-full bg-purple-500 text-white flex items-center justify-center text-2xl font-bold">
              <i class="pi pi-book"></i>
            </div>
            <div>
              <h2 class="text-2xl font-bold m-0 text-purple-900">{{ selectedSubjectInfo.name }}</h2>
              <p class="text-purple-700 m-0 mt-1">Subject Analytics Profile</p>
            </div>
            <div class="ml-auto flex gap-4 text-center">
              <div>
                <p class="text-sm font-semibold text-purple-700 uppercase m-0">Global Average</p>
                <p class="text-2xl font-bold text-purple-900 m-0 mt-1">{{ stats.average }} / 20</p>
              </div>
              <div class="w-px bg-purple-200"></div>
              <div>
                <p class="text-sm font-semibold text-purple-700 uppercase m-0">Overall Pass Rate</p>
                <p class="text-2xl font-bold text-purple-900 m-0 mt-1">{{ stats.passRate }}%</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-span-12 xl:col-span-6">
        <div class="card chart-card">
          <div class="flex justify-between items-center mb-3">
             <h5 class="m-0">Class Comparison</h5>
          </div>
          <div class="chart-wrap">
            <Chart type="bar" :data="classAverageChartData" :options="baseChartOptions" />
          </div>
        </div>
      </div>

      <div class="col-span-12 xl:col-span-6">
         <div class="card chart-card">
          <div class="flex justify-between items-center mb-3">
            <h5 class="m-0">Teacher Comparison</h5>
          </div>
          <DataTable :value="teacherAggregates" size="small" stripedRows paginator :rows="10">
            <Column field="label" header="Teacher" sortable />
            <Column field="average" header="Avg /20" sortable />
            <Column field="passRate" header="Pass Rate %" sortable />
          </DataTable>
        </div>
      </div>

      <!-- Students Needing Work for this Subject -->
      <div class="col-span-12">
        <div class="card">
          <div class="flex justify-between items-center mb-4">
            <h5 class="m-0">
              <i class="pi pi-exclamation-triangle mr-2 text-rose-500"></i>
              Students — Ranked Worst to Best
            </h5>
            <span class="text-sm text-muted-color">Red = below passing threshold for their cycle</span>
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
            <Column field="student_name" header="Student" sortable>
              <template #body="{ data }">
                <div class="flex items-center gap-2">
                  <i v-if="data.average !== null && data.average < data.threshold" class="pi pi-exclamation-triangle text-rose-500 text-xs"></i>
                  <span :class="{ 'text-rose-600 font-semibold': data.average !== null && data.average < data.threshold }">
                    {{ data.student_name }}
                  </span>
                </div>
              </template>
            </Column>
            <Column field="class_name" header="Class" sortable />
            <Column field="cc" header="CC /20" sortable>
              <template #body="{ data }">
                <span :class="{ 'text-rose-500': data.cc !== null && data.cc < data.threshold }">
                  {{ data.cc !== null ? data.cc : '—' }}
                </span>
              </template>
            </Column>
            <Column field="devoir" header="Devoir /20" sortable>
              <template #body="{ data }">
                <span :class="{ 'text-rose-500': data.devoir !== null && data.devoir < data.threshold }">
                  {{ data.devoir !== null ? data.devoir : '—' }}
                </span>
              </template>
            </Column>
            <Column field="composition" header="Composition /20" sortable>
              <template #body="{ data }">
                <span :class="{ 'text-rose-500': data.composition !== null && data.composition < data.threshold }">
                  {{ data.composition !== null ? data.composition : '—' }}
                </span>
              </template>
            </Column>
            <Column field="average" header="Average /20" sortable>
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
            <Column header="Status">
              <template #body="{ data }">
                <Tag
                  v-if="data.average !== null && data.average < data.threshold"
                  severity="danger"
                  value="Needs Work"
                  rounded
                />
                <Tag
                  v-else-if="data.average !== null && data.average >= 16"
                  severity="success"
                  value="Excellent"
                  rounded
                />
                <Tag
                  v-else-if="data.average !== null"
                  severity="info"
                  value="Passing"
                  rounded
                />
                <span v-else class="text-muted-color text-sm">—</span>
              </template>
            </Column>
            <template #empty>
              <div class="text-center py-4 text-muted-color">No grade data for this subject.</div>
            </template>
          </DataTable>
        </div>
      </div>
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
            <Tag :value="`${subjectAggregates.length} subjects`" severity="info" />
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
            <Tag :value="`${classAggregates.length} classes`" severity="success" />
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
          <div class="flex justify-between items-center mb-4">
            <h5 class="m-0">
              <i class="pi pi-users mr-2 text-primary"></i>
              Student Rankings — Best to Worst
            </h5>
            <span class="text-sm text-muted-color">{{ studentAggregatesData.length }} students</span>
          </div>
          <DataTable
            :value="studentAggregatesData"
            size="small"
            stripedRows
            paginator
            :rows="20"
            :loading="loading"
          >
            <Column header="#" style="width: 3rem">
              <template #body="{ index }">
                <span
                  class="font-bold"
                  :class="index === 0 ? 'text-amber-500' : index === 1 ? 'text-slate-400' : index === 2 ? 'text-orange-600' : 'text-muted-color'"
                >
                  {{ index + 1 }}
                </span>
              </template>
            </Column>
            <Column field="student_name" header="Student" sortable>
              <template #body="{ data, index }">
                <div class="flex items-center gap-2">
                  <i v-if="index === 0" class="pi pi-trophy text-amber-500"></i>
                  <span class="font-medium">{{ data.student_name }}</span>
                </div>
              </template>
            </Column>
            <Column field="class_name" header="Class" sortable />
            <Column field="average" header="Overall Avg /20" sortable>
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
            <Column field="best_subject" header="Best Subject" sortable />
            <Column field="best_subject_avg" header="Best Avg /20" sortable>
              <template #body="{ data }">
                <span v-if="data.best_subject_avg !== null" class="font-semibold text-emerald-600">
                  {{ data.best_subject_avg }}
                </span>
                <span v-else class="text-muted-color">—</span>
              </template>
            </Column>
            <Column field="passRate" header="Pass Rate %" sortable>
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
              <div class="text-center py-4 text-muted-color">No student data available.</div>
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
</style>