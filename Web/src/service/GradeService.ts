import ApiService from './ApiService';

// ── New nested shapes ─────────────────────────────────────────────────────────

export interface ExamExercise {
  id: number;
  exam_id: number;
  level_name: string;
  max_note: number;
}

export interface ExerciseGrade {
  id: number;
  grade_id: number;
  exam_exercise_id: number;
  note: number;
  exercise?: ExamExercise;
}

export interface Exam {
  id: number;
  subject_id: number;
  teacher_id: number;
  exam_type: string;
  semester: string;
  academic_year: string;
  max_grade: number;
  subject?: { id: number; name: string };
  teacher?: { id: number; first_name: string; last_name: string };
  exercises?: ExamExercise[];
}

// ── Grade record (new arch) ───────────────────────────────────────────────────

export interface GradeRecord {
  id: number;
  student_id: number;
  exam_id: number;
  grade: number;
  comment: string | null;
  created_at: string;
  updated_at: string;

  // Nested relations returned by the API
  exam?: Exam;
  student?: {
    id: number;
    first_name: string;
    last_name: string;
    code: string;
    class_id?: number;
  };
  exercise_grades?: ExerciseGrade[];

  // Legacy flat fields — kept for backward compat with code that still reads them directly.
  // These are now read from `exam.*` but we expose them as computed helpers in the service.
  /** @deprecated read from exam.subject_id */
  subject_id?: number;
  /** @deprecated read from exam.teacher_id */
  teacher_id?: number;
  /** @deprecated read from exam.exam_type */
  exam_type?: string;
  /** @deprecated read from exam.max_grade */
  max_grade?: number;
  /** @deprecated read from exam.semester */
  semester?: string;
  /** @deprecated read from exam.academic_year */
  academic_year?: string;
  subject?: { id: number; name: string };
  teacher?: { id: number; first_name: string; last_name: string };
}

// ── Flat helpers — resolve values through the exam relation ───────────────────

export const examType   = (g: GradeRecord): string  => g.exam?.exam_type   ?? g.exam_type   ?? '';
export const maxGrade   = (g: GradeRecord): number  => g.exam?.max_grade   ?? g.max_grade   ?? 20;
export const semester   = (g: GradeRecord): string  => g.exam?.semester    ?? g.semester    ?? '';
export const academicYr = (g: GradeRecord): string  => g.exam?.academic_year ?? g.academic_year ?? '';
export const subjectId  = (g: GradeRecord): number  => g.exam?.subject_id  ?? g.subject_id  ?? 0;
export const teacherId  = (g: GradeRecord): number  => g.exam?.teacher_id  ?? g.teacher_id  ?? 0;
export const subjectName = (g: GradeRecord): string =>
  g.exam?.subject?.name ?? g.subject?.name ?? '';
export const teacherName = (g: GradeRecord): string => {
  const t = g.exam?.teacher ?? g.teacher;
  return t ? `${t.first_name} ${t.last_name}`.trim() : '';
};
export const normalizeGrade = (g: GradeRecord): number => {
  const val = Number(g.grade ?? 0);
  const max = Number(g.exam?.max_grade ?? g.max_grade ?? 0);
  if (max <= 0) return Math.max(0, Math.min(20, val));
  return (val / max) * 20;
};

// ── DTOs ─────────────────────────────────────────────────────────────────────

export interface CreateGradeDTO {
  student_id: number;
  exam_id: number;
  grade: number;
  comment?: string | null;
  exercise_grades?: Array<{ exam_exercise_id: number; note: number }>;
}

export interface CreateExamDTO {
  subject_id: number;
  teacher_id: number;
  exam_type: string;
  semester: string;
  academic_year: string;
  max_grade?: number;
  class_ids?: number[];
  exercises?: Array<{ level_name: string; max_note: number }>;
}

// ── Analytics types ───────────────────────────────────────────────────────────

export interface GradeAggregateRow {
  id: number | string;
  label: string;
  count: number;
  average: number;
  pass_rate: number;
  min: number;
  max: number;
  std_dev: number;
}

export interface ExerciseAverageRow {
  exercise_id: number;
  level_name: string;
  max_note: number;
  avg_note: number;
  pass_rate: number;
}

export interface SubjectExerciseAverageRow {
  level_name: string;
  max_note: number;
  records_count: number;
  avg_note: number;
}

export interface GradeAnalyticsStats {
  records: number;
  students: number;
  teachers: number;
  subjects: number;
  classes: number;
  average: number;
  median: number;
  highest: number;
  lowest: number;
  passRate: number;
  excellenceRate: number;
  stdDev: number;
}

export interface GradeAnalyticsOverview {
  stats: GradeAnalyticsStats;
  subject_aggregates: GradeAggregateRow[];
  class_aggregates: GradeAggregateRow[];
  teacher_aggregates: GradeAggregateRow[];
  student_aggregates: Array<GradeAggregateRow & {
    class_name: string;
    class_id: number;
    best_subject: string | null;
    best_subject_avg: number | null;
  }>;
  distribution: Array<{ label: string; count: number }>;
}

// ── Service ───────────────────────────────────────────────────────────────────

class GradeService {
  async getGrades(params?: {
    student_id?: number;
    subject_id?: number;
    teacher_id?: number;
    class_id?: number;
    semester?: string;
    academic_year?: string;
    exam_type?: string;
    page?: number;
    per_page?: number;
  }): Promise<GradeRecord[]> {
    const response = await ApiService.get<GradeRecord[]>('/grades', params);
    const payload = response.data as any;
    if (Array.isArray(payload)) return payload;
    if (payload?.data && Array.isArray(payload.data)) return payload.data;
    return [];
  }

  async getClassGrades(classId: number, params?: {
    subject_id?: number;
    semester?: string;
    academic_year?: string;
  }): Promise<GradeRecord[]> {
    const response = await ApiService.get<any>(`/classes/${classId}/grades`, params);
    const payload = response as any;
    if (Array.isArray(payload)) return payload;
    if (payload?.data && Array.isArray(payload.data)) return payload.data;
    if (payload?.grades && Array.isArray(payload.grades)) return payload.grades;
    return [];
  }

  async createGrade(data: CreateGradeDTO): Promise<GradeRecord> {
    const response = await ApiService.post<GradeRecord>('/grades', data);
    return (response.data as any)?.data ?? response.data;
  }

  async bulkCreateGrades(grades: CreateGradeDTO[]): Promise<any> {
    const response = await ApiService.post('/grades/bulk', { grades });
    return response;
  }

  async updateGrade(id: number, data: Partial<CreateGradeDTO>): Promise<GradeRecord> {
    const response = await ApiService.put<GradeRecord>(`/grades/${id}`, data);
    return (response.data as any);
  }

  async getAnalyticsOverview(params?: {
    student_id?: number;
    subject_id?: number;
    teacher_id?: number;
    class_id?: number;
    semester?: string;
    academic_year?: string;
    exam_type?: string;
  }): Promise<GradeAnalyticsOverview> {
    const response = await ApiService.get<GradeAnalyticsOverview>('/grades/analytics/overview', params);
    return (response.data as GradeAnalyticsOverview);
  }

  async getStudentReportCard(studentId: number, params: {
    semester: string;
    academic_year: string;
  }): Promise<any> {
    const response = await ApiService.get<any>(`/students/${studentId}/report-card`, params);
    return response;
  }

  // ── Exam management ──────────────────────────────────────────────────────
  async getExams(params?: {
    subject_id?: number;
    teacher_id?: number;
    class_id?: number;
    semester?: string;
    academic_year?: string;
    exam_type?: string;
  }): Promise<Exam[]> {
    const response = await ApiService.get<Exam[]>('/exams', params);
    const payload = response.data as any;
    if (Array.isArray(payload)) return payload;
    if (payload?.data && Array.isArray(payload.data)) return payload.data;
    return [];
  }

  async createExam(data: CreateExamDTO): Promise<Exam> {
    const response = await ApiService.post<Exam>('/exams', data);
    return (response.data as any)?.data ?? response.data;
  }

  async updateExam(id: number, data: Partial<CreateExamDTO>): Promise<Exam> {
    const response = await ApiService.put<Exam>(`/exams/${id}`, data);
    return (response.data as any)?.data ?? response.data;
  }

  async deleteExam(id: number): Promise<any> {
    const response = await ApiService.delete(`/exams/${id}`);
    return response.data;
  }

  async getExamExerciseAverages(examId: number): Promise<ExerciseAverageRow[]> {
    const response = await ApiService.get<any>(`/exams/${examId}/exercise-averages`);
    const payload = response.data;
    if (Array.isArray(payload)) return payload;
    if (payload?.exercises && Array.isArray(payload.exercises)) return payload.exercises;
    if (payload?.data && Array.isArray(payload.data)) return payload.data;
    if (payload?.data?.exercises && Array.isArray(payload.data.exercises)) return payload.data.exercises;
    return [];
  }

  async getExamTypes(params?: {
    semester?: string;
    academic_year?: string;
    class_id?: number;
    student_id?: number;
  }): Promise<string[]> {
    const response = await ApiService.get<string[]>('/exams/types', params);
    return response.data ?? [];
  }

  async getSubjectExerciseAverages(params: {
    academic_year: string;
    subject_id?: number;
    semester?: string;
    exam_type?: string;
    teacher_id?: number;
    class_id?: number;
  }): Promise<SubjectExerciseAverageRow[]> {
    const response = await ApiService.get<SubjectExerciseAverageRow[]>('/analytics/subject-exercise-averages', params);
    const payload = response.data as any;
    if (Array.isArray(payload)) return payload;
    if (payload?.data && Array.isArray(payload.data)) return payload.data;
    return [];
  }
}

export default new GradeService();
