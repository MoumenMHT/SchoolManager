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
const distributionRows = ref<AggregatedRow[]>([]);
const teacherDrilldownRows = ref<AggregatedRow[]>([]);
const subjectDrilldownRows = ref<AggregatedRow[]>([]);

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
  { label: 'Quiz', value: 'quiz' },
  { label: 'Homework', value: 'homework' },
  { label: 'Final', value: 'final' },
  { label: 'Project', value: 'project' }
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
      student_name: `${g.student?.first_name || ''} ${g.student?.last_name || ''}`.trim()
    }));
  } catch (err: any) {
    console.error(err);
  } finally {
    classGradesLoading.value = false;
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
            <h3 class="text-2xl font-semibold m-0">Grade Analytics</h3>
            <p class="text-muted-color mt-2 mb-0">School-wide grade intelligence with drill-down by subject, class, and teacher.</p>
          </div>
          <div class="flex items-center gap-2">
            <Button label="Refresh" icon="pi pi-refresh" severity="secondary" outlined @click="reload" :loading="loading" />
            <Button label="Reset Filters" icon="pi pi-filter-slash" text @click="resetFilters" :disabled="loading" />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3 mb-3">
          <Select
            v-model="selectedAcademicYear"
            :options="academicYearOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Academic year"
            class="w-full"
          />
          <Select
            v-model="selectedSemester"
            :options="semesterOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Trimester"
            class="w-full"
          />
          <Select
            v-model="selectedExamType"
            :options="examTypeOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Exam type"
            class="w-full"
          />
          <Select
            v-model="selectedClassId"
            :options="classOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Class"
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
            placeholder="Subject"
            class="w-full"
            filter
          />
          <Select
            v-model="selectedTeacherId"
            :options="teacherOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Teacher"
            class="w-full"
            filter
          />
          <Select
            v-model="selectedStudentId"
            :options="studentOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Student"
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
                <span v-if="selectedStudentInfo.class_id" class="ml-3"><i class="pi pi-users mr-1"></i> Class Info Available</span>
              </p>
            </div>
            <div class="ml-auto flex gap-4 text-center">
              <div>
                <p class="text-sm font-semibold text-blue-700 uppercase m-0">Overall Average</p>
                <p class="text-2xl font-bold text-blue-900 m-0 mt-1" :class="{'text-red-600': studentAverage < 10}">
                  {{ studentAverage }} / 20
                </p>
              </div>
              <div class="w-px bg-blue-200"></div>
              <div>
                <p class="text-sm font-semibold text-blue-700 uppercase m-0">Pass Rate</p>
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
          <h5 class="mb-3">Subject Performance Radar</h5>
          <div class="chart-wrap flex justify-center items-center">
            <Chart v-if="studentRadarChartData.labels.length > 0" type="radar" :data="studentRadarChartData" :options="radarChartOptions" class="w-full h-full" />
            <div v-else class="text-muted-color text-center py-6">No subject data available</div>
          </div>
        </div>
      </div>

      <div class="col-span-12 xl:col-span-8">
        <div class="card">
          <div class="flex justify-between items-center mb-4">
            <h5 class="m-0">Detailed Grades Log</h5>
            <span class="text-sm text-muted-color">Highlighting grades &lt; 10/20</span>
          </div>
          <DataTable :value="studentGrades" :loading="studentGradesLoading" size="small" stripedRows paginator :rows="10">
            <Column field="subject_name" header="Subject" sortable></Column>
            <Column field="exam_type" header="Exam Type" sortable>
              <template #body="{ data }">
                <span class="capitalize">{{ data.exam_type }}</span>
              </template>
            </Column>
            <Column field="teacher_name" header="Teacher" sortable></Column>
            <Column header="Grade / Max" sortable sortField="normalized_grade">
              <template #body="{ data }">
                <div class="font-semibold">{{ Number(data.grade).toFixed(2) }} / {{ data.max_grade }}</div>
              </template>
            </Column>
            <Column header="Status" sortable sortField="normalized_grade">
              <template #body="{ data }">
                <Tag v-if="data.normalized_grade < 10" severity="danger" value="Needs Work" rounded />
                <Tag v-else-if="data.normalized_grade >= 16" severity="success" value="Excellent" rounded />
                <Tag v-else severity="info" value="Passing" rounded />
              </template>
            </Column>
            <template #empty>
              <div class="text-center py-4 text-muted-color">No grades found for this student.</div>
            </template>
          </DataTable>
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

      <!-- Teacher Performace for Class -->
      <div class="col-span-12 xl:col-span-6">
         <div class="card chart-card">
          <div class="flex justify-between items-center mb-3">
            <h5 class="m-0">Teacher Impact</h5>
          </div>
          <DataTable :value="teacherAggregates" size="small" stripedRows>
            <Column field="label" header="Teacher" />
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
    </template>

    <template v-else-if="!loading">
      <div class="col-span-12 md:col-span-6 xl:col-span-3">
        <div class="card stat-card">
          <p class="stat-title">Average Grade</p>
          <h3 class="stat-value">{{ stats.average }} / 20</h3>
          <p class="stat-sub">Median {{ stats.median }} | Std Dev {{ stats.stdDev }}</p>
        </div>
      </div>

      <div class="col-span-12 md:col-span-6 xl:col-span-3">
        <div class="card stat-card">
          <p class="stat-title">Pass Rate</p>
          <h3 class="stat-value">{{ stats.passRate }}%</h3>
          <p class="stat-sub">Excellence {{ stats.excellenceRate }}%</p>
        </div>
      </div>

      <div class="col-span-12 md:col-span-6 xl:col-span-3">
        <div class="card stat-card">
          <p class="stat-title">Grade Records</p>
          <h3 class="stat-value">{{ stats.records }}</h3>
          <p class="stat-sub">Students {{ stats.students }} | Teachers {{ stats.teachers }}</p>
        </div>
      </div>

      <div class="col-span-12 md:col-span-6 xl:col-span-3">
        <div class="card stat-card">
          <p class="stat-title">Coverage</p>
          <h3 class="stat-value">{{ stats.subjects }} Subjects</h3>
          <p class="stat-sub">Across {{ stats.classes }} classes</p>
        </div>
      </div>

      <div class="col-span-12 xl:col-span-6">
        <div class="card chart-card">
          <div class="flex justify-between items-center mb-3">
            <h5 class="m-0">Average Grade by Subject</h5>
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
            <h5 class="m-0">Average Grade by Class</h5>
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
            <h5 class="m-0">Teacher Performance</h5>
            <Select
              v-model="selectedTeacherForChart"
              :options="teacherOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Filter by teacher"
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
            <h5 class="m-0">Subject Drill-down</h5>
            <Select
              v-model="selectedSubjectForChart"
              :options="subjectOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Filter by subject"
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
          <h5 class="mb-3">Top Subjects</h5>
          <DataTable :value="topSubjects" size="small" stripedRows>
            <Column field="label" header="Subject" />
            <Column field="average" header="Avg /20" />
            <Column field="passRate" header="Pass %" />
            <Column field="count" header="Records" />
          </DataTable>
        </div>
      </div>

      <div class="col-span-12 lg:col-span-6">
        <div class="card">
          <h5 class="mb-3">Weakest Subjects</h5>
          <DataTable :value="bottomSubjects" size="small" stripedRows>
            <Column field="label" header="Subject" />
            <Column field="average" header="Avg /20" />
            <Column field="passRate" header="Pass %" />
            <Column field="count" header="Records" />
          </DataTable>
        </div>
      </div>

      <div class="col-span-12 lg:col-span-6">
        <div class="card">
          <h5 class="mb-3">Most Challenging Classes</h5>
          <DataTable :value="toughestClasses" size="small" stripedRows>
            <Column field="label" header="Class" />
            <Column field="average" header="Avg /20" />
            <Column field="passRate" header="Pass %" />
            <Column field="count" header="Records" />
          </DataTable>
        </div>
      </div>

      <div class="col-span-12 lg:col-span-6">
        <div class="card">
          <h5 class="mb-3">Most Variable Subjects</h5>
          <DataTable :value="highVarianceSubjects" size="small" stripedRows>
            <Column field="label" header="Subject" />
            <Column field="stdDev" header="Std Dev" />
            <Column field="average" header="Avg /20" />
            <Column field="count" header="Records" />
          </DataTable>
        </div>
      </div>
    </template>

    <div v-else class="col-span-12">
      <div class="card text-center py-8">
        <i class="pi pi-spin pi-spinner text-4xl text-primary mb-3"></i>
        <p class="text-muted-color">Loading grade analytics...</p>
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
</style>