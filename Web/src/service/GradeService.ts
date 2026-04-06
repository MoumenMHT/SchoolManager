import ApiService from './ApiService';

export interface GradeRecord {
  id: number;
  student_id: number;
  subject_id: number;
  teacher_id: number;
  exam_type: string;
  grade: number;
  max_grade: number;
  semester: string;
  academic_year: string;
  comment: string | null;
  created_at: string;
  updated_at: string;
  student?: {
    id: number;
    first_name: string;
    last_name: string;
    code: string;
    class_id?: number;
  };
  subject?: {
    id: number;
    name: string;
  };
  teacher?: {
    id: number;
    first_name: string;
    last_name: string;
  };
}

export interface CreateGradeDTO {
  student_id: number;
  subject_id: number;
  teacher_id: number;
  exam_type: string;
  grade: number;
  max_grade: number;
  semester: string;
  academic_year: string;
  comment?: string | null;
}

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
  distribution: Array<{ label: string; count: number }>;
}

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

  async getClassGrades(classId: number, params?: { subject_id?: number; semester?: string; academic_year?: string }): Promise<GradeRecord[]> {
    const response = await ApiService.get<GradeRecord[]>(`/classes/${classId}/grades`, params);
    return (response.data as any) || [];
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
}

export default new GradeService();
