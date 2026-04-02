<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import GradeService, { type GradeRecord } from '@/service/GradeService';
import TeacherService, { type Teacher } from '@/service/TeacherService';
import SubjectService, { type Subject } from '@/service/SubjectService';
import ClassesService, { type SchoolClass } from '@/service/ClassesService';

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
const allTeachers = ref<Teacher[]>([]);
const allSubjects = ref<Subject[]>([]);
const allClasses = ref<SchoolClass[]>([]);

const selectedAcademicYear = ref<string>('');
const selectedSemester = ref<string>('all');
const selectedExamType = ref<string>('all');
const selectedClassId = ref<number | null>(null);

const selectedTeacherForChart = ref<number | null>(null);
const selectedSubjectForChart = ref<number | null>(null);

const semesterOptions = [
  { label: 'All Semesters', value: 'all' },
  { label: 'Semester 1', value: '1' },
  { label: 'Semester 2', value: '2' }
];

const examTypeOptions = [
  { label: 'All Exam Types', value: 'all' },
  { label: 'Quiz', value: 'quiz' },
  { label: 'Homework', value: 'homework' },
  { label: 'Exam', value: 'exam' },
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

const aggregateBy = (items: EnrichedGrade[], keyFn: (item: EnrichedGrade) => string, labelFn: (item: EnrichedGrade) => string): AggregatedRow[] => {
  const grouped = new Map<string, EnrichedGrade[]>();

  items.forEach((item) => {
    const key = keyFn(item);
    if (!grouped.has(key)) {
      grouped.set(key, []);
    }
    grouped.get(key)?.push(item);
  });

  return Array.from(grouped.entries()).map(([key, group]) => {
    const values = group.map((item) => item.normalized_grade);
    const average = values.reduce((sum, value) => sum + value, 0) / values.length;
    const variance = values.reduce((sum, value) => sum + (value - average) ** 2, 0) / values.length;

    return {
      id: key,
      label: labelFn(group[0]),
      count: group.length,
      average: round2(average),
      passRate: round2((values.filter((value) => value >= 10).length / values.length) * 100),
      min: round2(Math.min(...values)),
      max: round2(Math.max(...values)),
      stdDev: round2(Math.sqrt(variance))
    };
  });
};

const globalFilteredGrades = computed(() => {
  return allGrades.value.filter((grade) => {
    if (selectedAcademicYear.value && grade.academic_year !== selectedAcademicYear.value) return false;
    if (selectedSemester.value !== 'all' && grade.semester !== selectedSemester.value) return false;
    if (selectedExamType.value !== 'all' && grade.exam_type !== selectedExamType.value) return false;
    if (selectedClassId.value && grade.class_id !== selectedClassId.value) return false;
    return true;
  });
});

const subjectAggregates = computed(() => {
  return aggregateBy(
    globalFilteredGrades.value,
    (item) => String(item.subject_id),
    (item) => item.subject_name
  ).sort((a, b) => b.average - a.average);
});

const classAggregates = computed(() => {
  return aggregateBy(
    globalFilteredGrades.value,
    (item) => String(item.class_id ?? 'unknown'),
    (item) => item.class_name
  ).sort((a, b) => b.average - a.average);
});

const teacherAggregates = computed(() => {
  return aggregateBy(
    globalFilteredGrades.value,
    (item) => String(item.teacher_id),
    (item) => item.teacher_name
  ).sort((a, b) => b.average - a.average);
});

const teacherChartRows = computed(() => {
  if (!selectedTeacherForChart.value) {
    return teacherAggregates.value.slice(0, 12);
  }

  const filtered = globalFilteredGrades.value.filter((item) => item.teacher_id === selectedTeacherForChart.value);
  return aggregateBy(
    filtered,
    (item) => String(item.subject_id),
    (item) => item.subject_name
  ).sort((a, b) => b.average - a.average);
});

const subjectChartRows = computed(() => {
  if (!selectedSubjectForChart.value) {
    const values = globalFilteredGrades.value.map((item) => item.normalized_grade);
    const bands = [
      { label: '0-5', min: 0, max: 5 },
      { label: '5-10', min: 5, max: 10 },
      { label: '10-12', min: 10, max: 12 },
      { label: '12-14', min: 12, max: 14 },
      { label: '14-16', min: 14, max: 16 },
      { label: '16-20', min: 16, max: 20.01 }
    ];

    return bands.map((band) => {
      const count = values.filter((value) => value >= band.min && value < band.max).length;
      return {
        id: band.label,
        label: band.label,
        count,
        average: 0,
        passRate: 0,
        min: 0,
        max: 0,
        stdDev: 0
      };
    });
  }

  const filtered = globalFilteredGrades.value.filter((item) => item.subject_id === selectedSubjectForChart.value);
  return aggregateBy(
    filtered,
    (item) => String(item.class_id ?? 'unknown'),
    (item) => item.class_name
  ).sort((a, b) => b.average - a.average);
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

const baseChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom' as const
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      max: 20
    }
  }
};

const subjectDistributionChartOptions = computed(() => {
  if (selectedSubjectForChart.value) {
    return baseChartOptions;
  }

  return {
    ...baseChartOptions,
    scales: {
      y: {
        beginAtZero: true
      }
    }
  };
});

const stats = computed(() => {
  const values = globalFilteredGrades.value.map((item) => item.normalized_grade);
  if (!values.length) {
    return {
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
    };
  }

  const sorted = [...values].sort((a, b) => a - b);
  const mid = Math.floor(sorted.length / 2);
  const average = values.reduce((sum, value) => sum + value, 0) / values.length;
  const variance = values.reduce((sum, value) => sum + (value - average) ** 2, 0) / values.length;

  return {
    records: values.length,
    students: new Set(globalFilteredGrades.value.map((item) => item.student_id)).size,
    teachers: new Set(globalFilteredGrades.value.map((item) => item.teacher_id)).size,
    subjects: new Set(globalFilteredGrades.value.map((item) => item.subject_id)).size,
    classes: new Set(globalFilteredGrades.value.map((item) => item.class_id ?? 'unknown')).size,
    average: round2(average),
    median: round2(sorted.length % 2 === 0 ? (sorted[mid - 1] + sorted[mid]) / 2 : sorted[mid]),
    highest: round2(sorted[sorted.length - 1]),
    lowest: round2(sorted[0]),
    passRate: round2((values.filter((value) => value >= 10).length / values.length) * 100),
    excellenceRate: round2((values.filter((value) => value >= 16).length / values.length) * 100),
    stdDev: round2(Math.sqrt(variance))
  };
});

const topSubjects = computed(() => subjectAggregates.value.slice(0, 5));
const bottomSubjects = computed(() => [...subjectAggregates.value].sort((a, b) => a.average - b.average).slice(0, 5));
const toughestClasses = computed(() => [...classAggregates.value].sort((a, b) => a.average - b.average).slice(0, 5));
const highVarianceSubjects = computed(() => [...subjectAggregates.value].sort((a, b) => b.stdDev - a.stdDev).slice(0, 5));

const loadMetadata = async () => {
  const [teachers, subjects, classes] = await Promise.all([
    TeacherService.getTeachers(),
    SubjectService.getSubjects(),
    ClassesService.getClasses()
  ]);

  allTeachers.value = teachers;
  allSubjects.value = subjects;
  allClasses.value = classes;
};

const loadGrades = async () => {
  loading.value = true;
  error.value = null;

  try {
    const params: { academic_year?: string; semester?: string } = {};
    if (selectedAcademicYear.value) {
      params.academic_year = selectedAcademicYear.value;
    }
    if (selectedSemester.value !== 'all') {
      params.semester = selectedSemester.value;
    }

    const grades = await GradeService.getGrades(params);

    allGrades.value = grades.map((grade) => {
      const classInfo = getClassInfo(grade);
      return {
        ...grade,
        normalized_grade: round2(normalizedGrade(grade)),
        student_name: `${grade.student?.first_name ?? ''} ${grade.student?.last_name ?? ''}`.trim(),
        subject_name: getSubjectName(grade),
        teacher_name: getTeacherName(grade),
        class_id: classInfo.class_id,
        class_name: classInfo.class_name
      };
    });
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to load grade analytics data.';
  } finally {
    loading.value = false;
  }
};

const reload = async () => {
  await loadGrades();
};

const resetFilters = async () => {
  selectedSemester.value = 'all';
  selectedExamType.value = 'all';
  selectedClassId.value = null;
  selectedTeacherForChart.value = null;
  selectedSubjectForChart.value = null;
  await loadGrades();
};

watch(selectedAcademicYear, async () => {
  await loadGrades();
});

watch(selectedSemester, async () => {
  await loadGrades();
});

onMounted(async () => {
  selectedAcademicYear.value = getCurrentAcademicYear();
  await loadMetadata();
  await loadGrades();
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

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
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
            placeholder="Semester"
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

    <template v-if="!loading">
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
  min-height: 420px;
}

.chart-wrap {
  height: 320px;
}
</style>